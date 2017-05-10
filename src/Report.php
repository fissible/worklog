<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/21/17
 * Time: 8:50 AM
 */

namespace Worklog;

use Carbon\Carbon;
use Worklog\Models\Task;
use Worklog\CommandLine\Output;
use Illuminate\Database\Eloquent\Collection;

class Report
{
    private $issue;

    private $DateRange = [];

    private $data = [];

    private $order_by;

    private $group_by;

    private $last_group;

    private $last_date;

    private $last_issue;

    private $max_metric = 'h';

    private $TotalDuration;

    const CACHE_TAG = 'Report';

    const NO_GROUP_KEY_FLAG = 'NA';

    protected static $exception_strings = [
        'dates_flipped' => 'End date cannot be before start date',
        'issue_not_found' => 'Issue %s not found',
        'no_entries' => 'No entries'
    ];

    /**
     * Report constructor.
     * @param null $StartDate
     * @param null $EndDate
     * @param null $issue
     */
    public function __construct($StartDate = null, $EndDate = null, $issue = null)
    {
        $this->setStartDate($StartDate);
        $this->setEndDate($EndDate);
        $this->setIssue($issue);
    }

    /**
     * @param  null  $issue
     * @return $this
     */
    public function setIssue($issue = null)
    {
        $this->issue = $issue;

        return $this;
    }

    /**
     * @return mixed
     */
    public function rangeInDays()
    {
        return $this->DateRange[0]->diffInDays($this->DateRange[1]);
    }

    /**
     * Transform an entity into a standard array format.
     * @param $Record
     * @return array
     */
    protected function transformEntity($Record)
    {
        if ($Record instanceof \stdClass) {
            $Record = json_decode(json_encode($Record), true);
            $Record['date'] = $Record['date']['date']; 
        }
        if (is_array($Record)) {
            $Record = new Task($Record);
        }

        $record = [
            'issue' => ($Record->hasAttribute('issue') && $Record->issue ? $Record->issue : self::NO_GROUP_KEY_FLAG),
            'description' => $Record->description,
            'duration' => $Record->duration,
            'date' => $Record->date,
            'time' => ''
        ];

        if ($this->isForToday()) {
            if ($Record->start || $Record->stop) {
                if ($Record->start) {
                    $record['time'] = str_pad(Str::time($Record->start), 8, ' ', STR_PAD_LEFT);
                }
                if ($Record->stop) {
                    if (strlen($record['time']) > 0) {
                        $record['time'] .= ' - ';
                    }
                    $record['time'] .= Str::time($Record->stop);
                }
            }
        }

        // format the date
        if ($this->rangeInDays() < 7) {
            $record['date'] = $record['date']->format('l');
        } else {
            $record['date'] = $record['date']->toFormattedDateString();
        }

        return $record;
    }

    /**
     * @param $data
     * @param  null   $group
     * @return string
     * @internal param bool $borderless
     */
    protected function formatEntity($data, $group = null)
    {
        $group_by = isset($this->group_by) ? $this->group_by : null;
        $prefix = '';

        if ($data['date'] instanceof Carbon) {
            $data['date'] = $this->formatEntityField($data, 'date', $group);
        }

        if ($this->isForToday()) {
            if (isset($data['time']) && isset($data['time'])) {
                $prefix = str_pad($data['time'], 21, ' ', STR_PAD_RIGHT);
            }
        } else {
            if (array_key_exists($group_by, $data) && strlen($data[$group_by]) > 0) {
                $label = '';
                $minimum_string_length = 0;

                //  GROUP BY Issue, [ Date ]
                if ($group_by == 'issue' && array_key_exists('date', $data)) {
                    $label = '['.$data['date'].']';
                    $minimum_string_length = $this->rangeInDays() < 7 ? 11 : 14;

                    //  GROUP BY Date, [ Issue ]
                } elseif ($group_by == 'date' && array_key_exists('issue', $data) && strtoupper($data['issue']) !== self::NO_GROUP_KEY_FLAG) {
                    $label = '['.$data['issue'].']';
                    $minimum_string_length = 9;
                }

                $label_string_length = strlen($label);

                if ($label_string_length) {
                    $minimum_string_length = max($minimum_string_length, $label_string_length);

                    if ($minimum_string_length > 0) {
                        switch ($this->same_iteration_values($data, $group)) {
                            case true:
                                $prefix = str_repeat(' ', $minimum_string_length);
                                break;
                            case false:
                                $prefix = str_repeat(' ', $minimum_string_length - $label_string_length).$label;
                                break;
                            default:
                                break;
                        }
                        $prefix .= ' ';
                    }
                }
            }
        }

        return $prefix . $data['description'];
    }

    /**
     * @param $data
     * @param $key
     * @param  null $group
     * @return null
     */
    protected function formatEntityField($data, $key, $group = null)
    {
        $array_key_exists = array_key_exists($key, $data);
        $new_value = ($array_key_exists ? $data[$key] : null);

        switch ($key) {
            case 'date':
                if ($array_key_exists && $data['date'] instanceof Carbon) {
                    if ($this->rangeInDays() < 7) {
                        $new_value = $data['date']->format('l'); // Wednesday -> 9
                    } else {
                        $new_value = $data['date']->toFormattedDateString(); // Feb 24, 2017 -> 12
                    }
                }
                break;
        }

        return $new_value;
    }

    /**
     * @return bool
     */
    public function hasData()
    {
        return $this->dataCount() > 0;
    }

    /**
     * @return int
     */
    public function dataCount()
    {
        if (is_object($this->data) && method_exists($this->data, 'count')) {
           return $this->data->count();
        }

        return count($this->data);
    }

    /**
     * @param  Carbon $Date
     * @param  null   $DateEnd
     * @return $this
     */
    public function forDate(Carbon $Date, $DateEnd = null)
    {
        $this->setStartDate($Date);

        if ($DateEnd instanceof Carbon) {
            if ($DateEnd->lt($Date)) {
                throw new \InvalidArgumentException(static::$exception_strings['dates_flipped']);
            }

            $this->setEndDate($DateEnd);
        } else {
            $this->setEndDate($Date);
        }

        return $this;
    }

    /**
     * @return Report
     */
    public function forToday()
    {
        return $this->forDate(Carbon::today());
    }

    /**
     * @return Report
     */
    public function forLastWeek()
    {
        $this->DateRange[0] = Carbon::today()->startOfWeek()->subWeek();
        $this->DateRange[1] = Carbon::today()->endOfWeek()->subDay()->subWeek();

        return $this->setRangeTimes();
    }

    /**
     * @param  null  $StartDate
     * @return $this
     */
    public function setStartDate($StartDate = null)
    {
        if (is_null($StartDate)) {
            $StartDate = Carbon::today()->startOfWeek();
        }
        $this->DateRange[0] = $StartDate;

        return $this->setRangeTimes();
    }

    /**
     * @param  null  $EndDate
     * @return $this
     */
    public function setEndDate($EndDate = null)
    {
        if (is_null($EndDate)) {
            $EndDate = Carbon::today()->endOfWeek()->subDay();
        }
        $this->DateRange[1] = $EndDate;

        return $this->setRangeTimes();
    }

    /**
     * @return $this
     */
    private function setRangeTimes()
    {
        if (array_key_exists(0, $this->DateRange)) {
            $this->DateRange[0]->setTime(5, 0);
        }
        if (array_key_exists(1, $this->DateRange)) {
            $this->DateRange[1]->endOfDay();
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isForToday()
    {
        return ($this->DateRange[0]->isToday() && $this->DateRange[1]->isToday());
    }

    public function orderBy($order_by = [])
    {
        $this->order_by = $order_by;

        return $this;
    }

    /**
     * @param $key
     * @return $this
     * @internal param $input
     */
    public function groupBy($key)
    {
        $this->group_by = $key;

        return $this;
    }

    private function groupData()
    {
        if ($this->hasData() && isset($this->group_by)) {
            $data = $this->data;
            $this->data = [];

            foreach ($data as $key => $item) {
                if (! array_key_exists($this->group_by, $item)) {
                    $item[$this->group_by] = self::NO_GROUP_KEY_FLAG;
                }

                $group_key = $item[$this->group_by];

                if (! array_key_exists($group_key, $this->data)) {
                    $this->data[$group_key] = [];
                }

                $this->data[$group_key][] = $item;
            }

            if ($this->group_by == 'issue') {
                uksort($this->data, function ($a, $b) {
                    if ($a == $b) {
                        return 0;
                    }
                    if ($a == self::NO_GROUP_KEY_FLAG) {
                        return 1;
                    }
                    if ($b == self::NO_GROUP_KEY_FLAG) {
                        return -1;
                    }

                    return strcmp($a, $b);
                });
            }
        }

        return $this;
    }

    /**
     * @return mixed
     */
    protected function query()
    {
        $Query = Task::query();

        if (isset($this->issue)) {
            $Query->where('issue', $this->issue);
        }

        if ($this->DateRange[1]->lt($this->DateRange[0])) {
            throw new \InvalidArgumentException(static::$exception_strings['dates_flipped']);
        }

        if ($this->DateRange[0]->toDateString() === $this->DateRange[1]->toDateString()) {
            $Query->whereDate('date', $this->DateRange[0]->toDateString());
        } else {
            $Query->whereRaw('(DATE(date) <= \''.$this->DateRange[1]->toDateString().'\' AND DATE(date) >= \''.$this->DateRange[0]->toDateString().'\')');
        }

        if (isset($this->order_by) && ! empty($this->order_by)) {
            foreach ((array) $this->order_by as $field => $direction) {
                $Query->orderBy($field, $direction);
            }
        } else {
            $Query->defaultSort();
        }

        return $Query;
    }

    public static function bust_cache(Carbon $DateStart, Carbon $DateEnd = null)
    {
        $count = 0;

        if (is_null($DateEnd)) {
            $DateEnd = $DateStart->copy();
        }
        $Cache = App()->Cache();

        foreach ($Cache->Items() as $Item) {
            $pare = false;

            $CacheDateStart = $Item->meta('date_start') ? Carbon::parse($Item->meta('date_start')) : null;
            $CacheDateStop = $Item->meta('date_stop') ? Carbon::parse($Item->meta('date_stop')) : null;

            if (null !== $CacheDateStart && null !== $CacheDateStop) {
                if ($DateStart->eq($DateEnd) && ($CacheDateStart->eq($DateStart) || $CacheDateStop->eq($DateStart))) {
                    $pare = true;
                } elseif ($CacheDateStart->between($DateStart, $DateEnd) || $CacheDateStop->between($DateStart, $DateEnd)) {
                    $pare = true;
                }
            } elseif (null !== $CacheDateStart) {
                if ($DateStart->eq($DateEnd) && $CacheDateStart->eq($DateStart)) {
                    $pare = true;
                } elseif ($CacheDateStart->between($DateStart, $DateEnd)) {
                    $pare = true;
                }
            } elseif (null !== $CacheDateStop) {
                if ($DateStart->eq($DateEnd) && $CacheDateStop->eq($DateStart)) {
                    $pare = true;
                } elseif ($CacheDateStop->between($DateStart, $DateEnd)) {
                    $pare = true;
                }
            }

            if ($pare) {
                if ($Cache->clear($Item)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Query the results of a given query
     */
    protected function queryCacheData($clear = false)
    {
        $Cache = App()->Cache();
        $Query = $this->query();
        $QueryBuilder = $Query->getQuery();
        $cache_name = base64_encode($QueryBuilder->toSql());

        // -c to bust/clear cache
        if ($clear) {
            $deleted = $Cache->clear($cache_name);
        }

        // Do retrieve or exe/store/return
        $data = $Cache->data(
            $cache_name, function() use ($Query) {
                $Collection = $Query->get();

                $decorator = function($CacheItem) use ($Collection) {
                    // Add meta data for cach busting
                    $CacheItem->meta('date_start', $this->DateRange[0]->toDateString());
                    $CacheItem->meta('date_stop', $this->DateRange[1]->toDateString());

                    // always return the data
                    return $Collection;
                };

                return $decorator;
            }, [ self::CACHE_TAG ], Carbon::now()->diffInSeconds(Carbon::now()->addDays(2))
        );
        
        $this->setData($data);

        return $this->data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function run($clear_cache = false)
    {
        $this->queryCacheData($clear_cache);

        if ($this->dataCount() > 0) {
            if (isset($this->group_by)) {
                $this->groupData();
            }
        } else {
            if ($this->issue) {
                throw new \InvalidArgumentException(sprintf(static::$exception_strings['issue_not_found'], $this->issue));
            } else {
                throw new \Exception(static::$exception_strings['no_entries']);
            }
        }

        return $this->data;
    }

    /**
     * @param  array $data
     * @return $this
     */
    private function setData($data)
    {
        if ($data instanceof Collection) {
            $data = $data->toArray();
        }
        if (is_array($data)) {
            foreach ($data as $key => $record) {
                $this->data[] = $this->transformEntity($record);
            }
        }

        return $this;
    }

    /**
     * @param bool $borderless
     * @param null $max_metric
     * @internal param string $max_metric h=hour, m=minute
     */
    public function table($borderless = false, $max_metric = null, $clear_cache = false)
    {
        $variant = 'heavy';
        $DateStart = $this->DateRange[0];
        $DateEnd = $this->DateRange[1];
        $border = ($borderless ? '' : Output::uchar('ver', $variant));
        $this->last_group = $this->last_date = $this->last_issue = null;
        $pad_length = $this->line_length(4);
        $rows_output = 0;

        if (! is_null($max_metric)) {
            $this->max_metric = $max_metric;
        }

        if (empty($this->data)) {
            $this->run($clear_cache);
        }

        $this->TotalDuration = new Duration($this->max_metric);

        $char['top_right'] = '';
        $hline = $this->horizontal_line('mid', $variant);

        // Print date (or date range)
        if (! $borderless) {
            Output::line($this->horizontal_line('top', $variant));
        }
        if ($DateStart->toDateString() === $DateEnd->toDateString()) {
            $header = $DateStart->format('l').', '.$DateStart->toFormattedDateString();
        } else {
            $header = $DateStart->toFormattedDateString().' - '.$DateEnd->toFormattedDateString();
            $range_in_days = $this->rangeInDays();

            if ($range_in_days > 7) {
                $header .= ' ('.$range_in_days.' days)';
            }
        }

        Output::line(Output::bold($header), $border);
        if (! $borderless) {
            Output::line($hline);
        } else {
            Output::line();
        }

        foreach ($this->data as $group => $data) {
            if (is_int($group)) {
                // ungrouped

                $this->TotalDuration->add($data['duration']);

                Output::line($this->formatEntity($data), $border);
                $rows_output++;

                $this->update_iteration_values($data);

            } else {
                // grouped

                $Duration = new Duration($this->max_metric);

                // Group header
                if (! empty($group) && !is_int($group) && $group !== self::NO_GROUP_KEY_FLAG) {
                    if (! $borderless) {
                        $group_str_length = strlen($group);
                        Output::line(Output::bold($group).' '.$border, $border);
                        Output::line(Output::uchar('mid_l', $variant).str_repeat(Output::uchar('hor', $variant), $group_str_length + 2).Output::uchar('bot_r', $variant).str_repeat(' ', ($pad_length - ($group_str_length + 1))).$border);
                    } else {
                        Output::line(Output::bold($group), '');
                    }
                }

                /////////////////////////////
                foreach ($data as $key => $_data) {
                    $Duration->add($_data['duration']);
                    $this->TotalDuration->add($_data['duration']);

                    if ($group !== $this->last_group) {
                        $this->last_group = $group;
                        $this->last_issue = null;
                        $this->last_date = null;
                    }

                    // empty line between entries
                    if (false === $this->same_iteration_values($_data, $group)) {
                        Output::line('', $border);
                    }

                    Output::line($this->formatEntity($_data, $group), $border);

                    $this->update_iteration_values($_data, $group);
                }
                /////////////////////////////

                // Print Duration and bottom line
                if (count($data) > 1 || count($this->data) > 1) {
                    Output::line(str_pad($Duration->asString(), $this->line_length() - 4, ' ', STR_PAD_LEFT), $border);
                    if (! $borderless) {
                        Output::line($this->horizontal_line('mid', $variant));
                    }
                } else {
                    Output::line('', $border);
                }

                $rows_output++;
            }
        } // EOF foreach (grouped/ungrouped data)

        // Print Total Duration
        if ($rows_output > 0) {
            Output::line(($borderless ? '' : $hline));
            $prefix = 'Total Duration:';
            Output::line($prefix.str_pad($this->TotalDuration->asString(), ($pad_length - strlen($prefix)), ' ', STR_PAD_LEFT), $border);

            if (! $borderless) {
                Output::line($this->horizontal_line('bot', $variant));
            }
        }

        Output::line();
    }

    /**
     * @param $data
     * @param  null      $group
     * @return bool|null
     */
    private function same_iteration_values($data, $group = null)
    {
        $same = true;

        if (! is_null($group) && ! is_null($this->last_group) && $group !== $this->last_group) {
            $same = false;
        }

        if ($same) {
            switch ($this->group_by) {
                case 'issue':
                    if (! is_null($this->last_date)) {
                        $date = $data['date'];
                        if ($data['date'] instanceof Carbon) {
                            $date = $this->formatEntityField($data, 'date', $group);
                        }

                        if ($date !== $this->last_date) {
                            $same = false;
                        }
                    } else {
                        $same = null;
                    }
                    break;
                case 'date':
                    if (! is_null($this->last_issue)) {
                        $issue = $data['issue'];

                        if ($issue !== $this->last_issue) {
                            $same = false;
                        }
                    } else {
                        $same = null;
                    }
                    break;
            }
        }

        return $same;
    }

    /**
     * @param $data
     * @param  null  $group
     * @return $this
     */
    private function update_iteration_values($data, $group = null)
    {
        if ($data['date'] instanceof Carbon) {
            $data['date'] = $this->formatEntityField($data, 'date', $group);
        }
        $this->last_date = $data['date'];
        $this->last_issue = $data['issue'];

        return $this;
    }

    /**
     * @param  string $pos
     * @param  string $variant
     * @return string
     */
    protected function horizontal_line($pos = 'mid', $variant = 'light')
    {
        $line_length = $this->line_length();

        if (Output::allow_unicode()) {
            $horizontal_line = Output::horizontal_line($pos, $line_length, $variant);
        } else {
            $horizontal_line = '+'.str_repeat('-', $line_length - 2).'+';
        }

        return $horizontal_line;
    }

    /**
     * @return mixed
     */
    protected function line_length($shorten_by = 0)
    {
        return Output::line_length() - $shorten_by;
    }
}
