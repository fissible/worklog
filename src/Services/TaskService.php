<?php

namespace Worklog\Services;

use Carbon\Carbon;
use CSATF\Services\ModelService;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/22/17
 * Time: 8:56 AM
 */
class TaskService extends ModelService {

    protected static $table = 'task';

    protected static $primary_key_field = 'id';

    protected static $fields = [
        'id' => [
            'type' => 'integer',
            'auto_increment' => true
        ],
        'issue' => [
            'type' => 'string',
            'default' => null,
            'prompt' => 'What is the JIRA Issue key?',
            'required' => false
        ],
        'description' => [
            'type' => 'string',
            'default' => null,
            'prompt' => 'What did you do?',
            'required' => true
        ],
        'date' => [
            'type' => 'string',
            'default' => '*now_string',
            'prompt' => 'What was the date (YYYY-MM-DD)?',
            'required' => true
        ],
        'start' => [
            'type' => 'string',
            'default' => '*DateTime->format(\'H:i\')',
            'prompt' => 'What time did you start?',
            'required' => false
        ],
        'stop' => [
            'type' => 'string',
            'default' => '*DateTime->format(\'H:i\')',
            'prompt' => 'What time did you stop?',
            'required' => false
        ]
    ];

    protected static $primary_keys = [ 'id' ];

    protected static $display_headers = [
        'id' => 'ID',
        'issue' => 'Issue',
        'description' => 'Description',
        'date' => 'Date',
        'start' => 'Start',
        'stop' => 'Stop',
        'duration' => 'Time Spent'
    ];

    private static $exception_strings = [
        'time_format' => 'Start/stop times must be a time format: HH:MM'
    ];

    const CACHE_TAG = 'start';


    public function lastTask($where = []) {
        $Latest = null;
        $_record = null;
        if ($records = $this->select($where)/*->result()*/) {
            $records = $this->sort($records, 'desc');

            foreach ($records as $record) {
                if (property_exists($record, 'stop')) {
                    $stop_parts = explode(':', $record->stop);
                    if (! is_numeric($stop_parts[1])) {
                        if (false !== stripos($stop_parts[1], 'p') && intval($stop_parts[0]) < 12) {
                            $stop_parts[0] = intval($stop_parts[0]) + 12;
                        }
                        $stop_parts[1] = preg_replace('/[^0-9]/', '', $stop_parts[1]);
                    }
                    $Stop = Carbon::parse($record->date)->hour($stop_parts[0])->minute($stop_parts[1]);

                    if (is_null($Latest) || $Latest->lt($Stop)) {
                        $Latest = $Stop->copy();
                        $_record = $record;
                    }
                }
            }
        }

        return $_record;
    }

    public function cached($disable_purge = false) {
        $last_index = 0;
        $cache_name = null;
        $filename = null;
        $Task = false;
        $Cache = App()->Cache();

        if ($disable_purge) {
            $Cache->disable_purge();
        }

        // Get the latest index
        if ($cached_start_times = $Cache->load_tags(self::CACHE_TAG)) {
            foreach ($cached_start_times as $name => $file) {
                $parts = explode('_', $name);
                if ($parts[1] > $last_index) {
                    $last_index = $parts[1];
                    $cache_name = $name;
                    $filename = $file;
                }
            }
        }

        if (! is_null($cache_name)) {
            $Task = json_decode(json_encode($Cache->load($cache_name)));
        }

        return [ $filename, $Task ];
    }

    /**
     * Return true if all required fields have values
     * @param $Task
     * @return bool
     * @throws \Exception
     */
    public function valid($Task) {
        $valid = true;
        foreach (static::fields() as $field => $config) {
            if (isset($config['required']) && $config['required']) {
                if ($Task instanceof \stdClass) {
                    if (! property_exists($Task, $field)) {
                        $valid = false;
                    }
                } elseif (is_array($Task)) {
                    if (! array_key_exists($field, $Task)) {
                        $valid = false;
                    }
                } else {
                    throw new \Exception('TaskValid() expects an array or \stdClass instance');
                }
            }
        }

        return $valid;
    }

    /**
     * Set calculated field values
     * @param $Record
     * @param $field
     * @param null $value
     * @return bool|\DateInterval|null
     */
    public function calculated_field($Record, $field, $value = null) {
        $callbacks = $this->calculated_field_callbacks();
        if (array_key_exists($field, $callbacks)) {
            if ($_value = $callbacks[$field]($Record)) {
                $value = $_value;
            }
        }

        return $value;
    }

    /**
     * Set calculated field values
     * @return array
     */
    protected function calculated_field_callbacks() {
        return [
            'duration' => function ($Record) {
                if (property_exists($Record, 'start') && property_exists($Record, 'stop')) {
                    if ($Record->start && $Record->stop) {
                        $start_parts = explode(':', $Record->start); // "08:00"
                        $stop_parts = explode(':', $Record->stop);   // "16:38"
                        $Start = Carbon::today()->hour(intval($start_parts[0]))->minute(intval(preg_replace('/[^0-9]/', '', $start_parts[1])));
                        $Stop = Carbon::today()->hour(intval($stop_parts[0]))->minute(intval(preg_replace('/[^0-9]/', '', $stop_parts[1])));
                        return $Start->diff($Stop);
                    }
                }
            },
            'start_datetime' => function($Record) {
                if (property_exists($Record, 'start') && property_exists($Record, 'date')) {
                    return Carbon::parse(substr($Record->date, 0, 10).' '.$Record->start)->toTimeString();
                }
            },
            'stop_datetime' => function($Record) {
                if (property_exists($Record, 'stop') && property_exists($Record, 'date')) {
                    return Carbon::parse(substr($Record->date, 0, 10).' '.$Record->stop)->toTimeString();
                }
            },
            'start_time' => function($Record) {
                if (property_exists($Record, 'start') && property_exists($Record, 'date')) {
                    return Carbon::parse(substr($Record->date, 0, 10).' '.$Record->start)->format('g:i a');
                }
            },
            'stop_time' => function($Record) {
                if (property_exists($Record, 'stop') && property_exists($Record, 'date')) {
                    return Carbon::parse(substr($Record->date, 0, 10).' '.$Record->stop)->format('g:i a');
                }
            }
        ];
    }

    /**
     * Format fields for display
     * @return array
     */
    protected function display_format_callbacks() {
        $time_string = function ($time) {
            if (false === stripos($time, 'AM') && false === stripos($time, 'PM')) {
                $Date = Carbon::parse(date("Y-m-d").' '.$time);
                $time = $Date->format('g:i a');
            }
            return $time;
        };

        return [
            // description
            'description' => function ($Record) {
                $str = '';
                if (property_exists($Record, 'description')) {
                    $str = str_replace('\n', "\n", $Record->description);
                }
                return $str;
            },

            // date: '2017-02-23 09:30:47' -> 'Feb 23, 2017'
            'date' => function ($Record) {
                if (property_exists($Record, 'date')) {
                    $date = new Carbon($Record->date);
                    return $date->toFormattedDateString();
                }
            },

            // duration: '02:00'/'04:00'
            'duration' => function ($Record) {
                if (property_exists($Record, 'start') && property_exists($Record, 'stop')) {
                    if (property_exists($Record, 'duration') && $Record->duration instanceof \DateInterval) {
                        $DateInterval = $Record->duration;
                    } else {
                        $DateInterval = $this->calculated_field($Record, 'duration');
                    }

                    $output = '';
                    if ($DateInterval->h) {
                        $output .= $DateInterval->h.($DateInterval->h > 1 ? ' hrs' : ' hr');
                    }
                    if ($DateInterval->i) {
                        if (strlen($output)) {
                            $output .= ', ';
                        }
                        $output .= $DateInterval->i.($DateInterval->i > 1 ? ' mins' : ' min');
                    }

                    return $output;
                }
            },

            // start/stop: '14:00' -> '02:00 PM'
            'start' => function ($Record) use ($time_string) {
                if (property_exists($Record, 'start')) {
                    return $time_string($Record->start);
                }
            },
            'stop' => function ($Record) use ($time_string) {
                if (property_exists($Record, 'stop')) {
                    return $time_string($Record->stop);
                }
            }
        ];
    }

    /**
     * Sort records
     * @param $records
     * @param $dir
     * @param $mode
     * @return array
     */
    public function sort(array $records = [], $dir = 'asc', $mode = 'default') {
        switch ($mode) {
            case 'default':
            default:
                uasort($records, function($a, $b) {
                    if (property_exists($a, 'date') && property_exists($b, 'date') &&
                        property_exists($a, 'start') && property_exists($b, 'start')) {
                        $aDate = Carbon::parse(substr($a->date, 0, 10).' '.$a->start);
                        $bDate = Carbon::parse(substr($b->date, 0, 10).' '.$b->start);
                        return $aDate->timestamp - $bDate->timestamp;
                    } else {
                        return 0;
                    }
                });
                if (strtolower($dir) == 'desc') {
                    $records = array_reverse($records, true);
                }
                break;
        }

        return $records;
    }
}