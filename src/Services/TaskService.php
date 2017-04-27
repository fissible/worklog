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
        $last_index = 1;
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
                if (false !== strpos($name, '_')) {
                    $parts = explode('_', $name);
                    if ($parts[1] > $last_index) {
                        $last_index = $parts[1];
                        $cache_name = $name;
                        $filename = $file;
                    }
                } else {
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
                    $data['date'] = Carbon::parse(filemtime($filename))->toDateString();
                }
            }

            $Task = new Task($data);
        }

        return [ $filename, $Task ];
    }
}