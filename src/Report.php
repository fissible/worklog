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

    const NO_GROUP_KEY_FLAG = 'NA';

    protected static $exception_strings = [
        'dates_flipped' => 'End date cannot be before start date',
        'issue_not_found' => 'Issue %s not found',
        'no_entries' => 'No entries found'
    ];


    /**
     * Report constructor.
     * @param null $StartDate
     * @param null $EndDate
     * @param null $issue
     */
    public function __construct($StartDate = null, $EndDate = null, $issue = null) {
        $this->setStartDate($StartDate);
        $this->setEndDate($EndDate);
        $this->setIssue($issue);
    }

    /**
     * @param null $issue
     * @return $this
     */
    public function setIssue($issue = null) {
        $this->issue = $issue;

        return $this;
    }

    /**
     * @return mixed
     */
    public function rangeInDays() {
        return $this->DateRange[0]->diffInDays($this->DateRange[1]);
    }

    /**
     * Transform an entity into a standard array format.
     * @param $Record
     * @return array
     */
    protected function transformEntity($Record) {
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
     * @param null $group
     * @return string
     * @internal param bool $borderless
     */
    protected function formatEntity($data, $group = null) {
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

            //  GROUP BY Issue, [ Date ]
            if ($group_by == 'issue') {
                if (strlen($data['date']) > 1) {
                    $date = '['.$data['date'].']';
                    $date_string_length = strlen($date);
                    $minimum_string_length = ($this->rangeInDays() < 7 ? 12 : 15);
                    $prefix = str_repeat(' ', $minimum_string_length);

                    if (! $this->same_iteration_values($data, $group)) {
                        $prefix = $date.str_repeat(' ', $minimum_string_length - $date_string_length);
                    }
                }

            //  GROUP BY Date, [ Issue ]
            } elseif ($group_by == 'date') {
                if ($this->same_iteration_values($data, $group)) {
                    if (strtoupper($data['issue']) !== self::NO_GROUP_KEY_FLAG) {
                        $prefix = str_repeat(' ', strlen($data['issue']) + 2);
                    }
                } else {
                    if (strtoupper($data['issue']) !== self::NO_GROUP_KEY_FLAG) {
                        $group_strlen = isset($data['issue']) ? strlen($data['issue']) + 2 : 12;
                        $prefix = '['.$data['issue'].']'.str_repeat(' ', $group_strlen - strlen($data['issue']));
                    }
                }
            }
        }

        return $prefix . $data['description'];
    }

    /**
     * @param $data
     * @param $key
     * @param null $group
     * @return null
     */
    protected function formatEntityField($data, $key, $group = null) {
        $array_key_exists = array_key_exists($key, $data);
        $new_value = ($array_key_exists ? $data[$key] : null);

        switch ($key) {
            case 'date':
                if ($array_key_exists) {
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
    public function hasData() {
        return $this->dataCount() > 0;
    }

    /**
     * @return int
     */
    public function dataCount() {
        if (is_object($this->data) && method_exists($this->data, 'count')) {
           return $this->data->count();
        }

        return count($this->data);
    }

    /**
     * @param Carbon $Date
     * @param null $DateEnd
     * @return $this
     */
    public function forDate(Carbon $Date, $DateEnd = null) {
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
    public function forToday() {
        return $this->forDate(Carbon::today());
    }

    /**
     * @return Report
     */
    public function forLastWeek() {
        $this->setStartDate();
        $this->setEndDate();

        $this->DateRange[0]->subWeek();
        $this->DateRange[1]->subWeek();

        return $this->setRangeTimes();
    }

    /**
     * @param null $StartDate
     * @return $this
     */
    public function setStartDate($StartDate = null) {
        if (is_null($StartDate)) {
            $StartDate = Carbon::today()->startOfWeek();
        }
        $this->DateRange[0] = $StartDate;

        return $this->setRangeTimes();
    }

    /**
     * @param null $EndDate
     * @return $this
     */
    public function setEndDate($EndDate = null) {
        if (is_null($EndDate)) {
            $EndDate = Carbon::today()->endOfWeek()->subDay();
        }
        $this->DateRange[1] = $EndDate;

        return $this->setRangeTimes();
    }

    /**
     * @return $this
     */
    private function setRangeTimes() {
        if (array_key_exists(0, $this->DateRange)) {
            $this->DateRange[0]->setTime(6, 0);
        }
        if (array_key_exists(1, $this->DateRange)) {
            $this->DateRange[1]->endOfDay();
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isForToday() {
        return ($this->DateRange[0]->isToday() && $this->DateRange[1]->isToday());
    }

    public function orderBy($order_by = []) {
        $this->order_by = $order_by;

        return $this;
    }

    /**
     * @param $key
     * @return $this
     * @internal param $input
     */
    public function groupBy($key) {
        $this->group_by = $key;

        return $this;
    }

    private function groupData() {
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
        }

        return $this;
    }

    /**
     * @return mixed
     */
    protected function query() {
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
            $Query->whereBetween('date', [ $this->DateRange[0]->toDatetimeString(), $this->DateRange[1]->toDatetimeString() ]);
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

    /**
     * @return array
     * @throws \Exception
     */
    public function run() {
        $this->setData($this->query()->get());

        if ($this->dataCount() > 0) {
            if (isset($this->group_by)) {
                $this->groupData();
            }
        } else {
            if ($this->issue) {
                throw new \Exception(sprintf(static::$exception_strings['issue_not_found'], $this->issue));
            } else {
                throw new \Exception(static::$exception_strings['no_entries']);
            }
        }

        return $this->data;
    }

    /**
     * @param array $data
     * @return $this
     */
    private function setData($data) {
        if ($data instanceof Collection) {
            foreach ($data as $key => $Record) {
                $this->data[] = $this->transformEntity($Record);
            }
        }

        return $this;
    }

    /**
     * @param bool $borderless
     * @param null $max_metric
     * @internal param string $max_metric h=hour, m=minute
     */
    public function table($borderless = false, $max_metric = null) {
        $DateStart = $this->DateRange[0];
        $DateEnd = $this->DateRange[1];
        $border = ($borderless ? '' : Output::uchar('ver'));
        $this->last_group = $this->last_date = $this->last_issue = null;
        $pad_length = $this->line_length(4);

        if (! is_null($max_metric)) {
            $this->max_metric = $max_metric;
        }

        if (empty($this->data)) {
            $this->run();
        }

        $this->TotalDuration = new Duration($this->max_metric);

        $char['top_right'] = '';
        $hline = $this->horizontal_line('mid', 'light');

        // Print date (or date range)
        if (! $borderless) {
            Output::line($this->horizontal_line('top', 'light'));
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

                $this->update_iteration_values($data);

            } else {
                // grouped

                $Duration = new Duration($this->max_metric);

                // Group header
                if (! empty($group) && !is_int($group) && $group !== self::NO_GROUP_KEY_FLAG) {
                    if (! $borderless) {
                        $group_str_length = strlen($group);
                        Output::line(Output::bold($group).' '.$border, $border);
                        Output::line(Output::uchar('mid_l').str_repeat(Output::uchar('hor'), $group_str_length + 2).Output::uchar('bot_r').str_repeat(' ', ($pad_length - ($group_str_length + 1))).$border);
                    } else {
                        Output::line(Output::bold($group), '');
                    }
                }

                /////////////////////////////
                foreach ($data as $key => $_data) {
                    $Duration->add($_data['duration']);
                    $this->TotalDuration->add($_data['duration']);

                    // empty line between entries
                    if ($key > 0) {
                        if (! $this->same_iteration_values($_data, $group)) {
                            Output::line('', $border);
                        }
                    }

                    Output::line($this->formatEntity($_data, $group), $border);

                    $this->update_iteration_values($_data, $group);
                }
                /////////////////////////////

                // Print Duration and bottom line
                Output::line(str_pad($Duration->asString(), $this->line_length() - 4, ' ', STR_PAD_LEFT), $border);
                if (! $borderless) {
                    Output::line($this->horizontal_line('mid', 'light'));
                }

                if ($group !== $this->last_group) {
                    $this->last_group = $group;
                    $this->last_issue = null;
                    $this->last_date = null;
                }
            }
        } // EOF foreach (grouped/ungrouped data)

        // Print Total Duration
        if (count($this->data) > 0) {
            Output::line(($borderless ? '' : $hline));
            $prefix = 'Total Duration:';
            Output::line($prefix.str_pad($this->TotalDuration->asString(), ($pad_length - strlen($prefix)), ' ', STR_PAD_LEFT), $border);

            if (! $borderless) {
                Output::line($this->horizontal_line('bot', 'light'));
            }
        }

        Output::line();
    }

    /**
     * @param $data
     * @param null $group
     * @return bool
     */
    private function same_iteration_values($data, $group = null) {
        $same = true;

        if (! is_null($this->group_by) && ! is_null($this->last_group) && $group !== $this->last_group) {
            $same = false;
        }

        if ($same) {
            switch ($this->group_by) {
                case 'issue':
                    if ($data['date'] instanceof Carbon) {
                        $data['date'] = $this->formatEntityField($data, 'date', $group);
                    }
                    if ($data['date'] !== $this->last_date) {
                        $same = false;
                    }
                    break;
                case 'date': // @todo: remove
                    if ($data['issue'] !== $this->last_issue) {
                        $same = false;
                    }
                    break;
            }
        }

        return $same;
    }

    /**
     * @param $data
     * @param null $group
     */
    private function update_iteration_values($data, $group = null) {
        if (isset($this->group_by) && $group !== $this->last_group && ! is_null($this->last_group)) {
            $this->last_issue = null;
            $this->last_date = null;
        } else {
            if ($data['date'] instanceof Carbon) {
                $data['date'] = $this->formatEntityField($data, 'date', $group);
            }

            if ($data['date'] !== $this->last_date) {
                $this->last_date = $data['date'];
            }

            if ($data['issue'] !== $this->last_issue) {
                $this->last_issue = $data['issue'];
            }
        }
    }

    /**
     * @param string $pos
     * @param string $variant
     * @return string
     */
    protected function horizontal_line($pos = 'mid', $variant = 'light') {
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
    protected function line_length($shorten_by = 0) {
        return Output::line_length() - $shorten_by;
    }
}