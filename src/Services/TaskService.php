<?php

namespace Worklog\Services;

use Carbon\Carbon;
use Worklog\Models\Task;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/22/17
 * Time: 8:56 AM
 */
class TaskService extends ModelService {

    protected static $entity_class = '\Worklog\Models\Task';

    protected static $display_headers = [
        'id' => 'ID',
        'issue' => 'Issue',
        'description' => 'Description',
        'date' => 'Date',
        'start' => 'Start',
        'stop' => 'Stop',
        'duration' => 'Time Spent'
    ];

    const CACHE_TAG = 'start';


    public function __construct($entity_class = null) {
        if (is_null($entity_class)) {
            $entity_class = static::$entity_class;
        }
        parent::__construct($entity_class);
    }

    public function lastTask($where = []) {
        $Latest = null;
        $LastTask = null;

        if (empty($where)) {
            $where = [ 'date' => Carbon::now()->toDateString() ];
        }

        if ($Tasks = Task::where($where)->defaultSort()->get()) {
            foreach ($Tasks as $Task) {
                if ($Task->hasAttribute('stop')) {
                    $stop_parts = explode(':', $Task->stop);
                    if (! is_numeric($stop_parts[1])) {
                        if (false !== stripos($stop_parts[1], 'p') && intval($stop_parts[0]) < 12) {
                            $stop_parts[0] = intval($stop_parts[0]) + 12;
                        }
                        $stop_parts[1] = preg_replace('/[^0-9]/', '', $stop_parts[1]);
                    }
                    $Stop = Carbon::parse($Task->date)->hour($stop_parts[0])->minute($stop_parts[1]);

                    if (is_null($Latest) || $Latest->lt($Stop)) {
                        $Latest = $Stop->copy();
                        $LastTask = $Task;
                    }
                }
            }
        }

        return $LastTask;
    }

    public static function cached($disable_purge = false) {
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
            $data = json_decode(json_encode($Cache->load($cache_name)), true);
            if (array_key_exists('date', $data) && is_array($data['date'])) {
                if (array_key_exists('date', $data['date'])) {
                    $data['date'] = $data['date']['date'];
                } else {
                    $data['date'] = '';
                }
            }
            $Task = new Task($data);
        }

        return [ $filename, $Task ];
    }

    /**
     * Format fields for display
     * @return array
     */
//    protected function display_format_callbacks() {
//        $time_string = function ($time) {
//            if (false === stripos($time, 'AM') && false === stripos($time, 'PM')) {
//                $Date = Carbon::parse(date("Y-m-d").' '.$time);
//                $time = $Date->format('g:i a');
//            }
//            return $time;
//        };
//
//        return [
//            // description
//            'description' => function ($Record) {
//                $str = '';
//                if (property_exists($Record, 'description')) {
//                    $str = str_replace('\n', "\n", $Record->description);
//                }
//                return $str;
//            },
//
//            // date: '2017-02-23 09:30:47' -> 'Feb 23, 2017'
//            'date' => function ($Record) {
//                if (property_exists($Record, 'date')) {
//                    $date = new Carbon($Record->date);
//                    return $date->toFormattedDateString();
//                }
//            },
//
//            // duration: '02:00'/'04:00'
//            'duration' => function ($Record) {
//                if (property_exists($Record, 'start') && property_exists($Record, 'stop')) {
//                    if (property_exists($Record, 'duration') && $Record->duration instanceof \DateInterval) {
//                        $DateInterval = $Record->duration;
//                    } else {
//                        $DateInterval = $this->calculated_field($Record, 'duration');
//                    }
//
//                    $output = '';
//                    if ($DateInterval->h) {
//                        $output .= $DateInterval->h.($DateInterval->h > 1 ? ' hrs' : ' hr');
//                    }
//                    if ($DateInterval->i) {
//                        if (strlen($output)) {
//                            $output .= ', ';
//                        }
//                        $output .= $DateInterval->i.($DateInterval->i > 1 ? ' mins' : ' min');
//                    }
//
//                    return $output;
//                }
//            },
//
//            // start/stop: '14:00' -> '02:00 PM'
//            'start' => function ($Record) use ($time_string) {
//                if (property_exists($Record, 'start')) {
//                    return $time_string($Record->start);
//                }
//            },
//            'stop' => function ($Record) use ($time_string) {
//                if (property_exists($Record, 'stop')) {
//                    return $time_string($Record->stop);
//                }
//            }
//        ];
//    }

    /**
     * Sort records
     * @param $records
     * @param $dir
     * @param $mode
     * @return array
     */
//    public function sort(array $records = [], $dir = 'asc', $mode = 'default') {
//        switch ($mode) {
//            case 'default':
//            default:
//                uasort($records, function($a, $b) {
//                    if (property_exists($a, 'date') && property_exists($b, 'date') &&
//                        property_exists($a, 'start') && property_exists($b, 'start')) {
//                        $aDate = Carbon::parse(substr($a->date, 0, 10).' '.$a->start);
//                        $bDate = Carbon::parse(substr($b->date, 0, 10).' '.$b->start);
//                        return $aDate->timestamp - $bDate->timestamp;
//                    } else {
//                        return 0;
//                    }
//                });
//                if (strtolower($dir) == 'desc') {
//                    $records = array_reverse($records, true);
//                }
//                break;
//        }
//
//        return $records;
//    }
}