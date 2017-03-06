<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Services\TaskService;
use CSATF\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/21/17
 * Time: 4:20 PM
 */
class StopCommand extends Command {

    public $command_name;

    public static $description = 'Stop and record a work log entry';
    public static $options = [
        'i' => ['req' => true, 'description' => 'The JIRA Issue key'],
        'd' => ['req' => true, 'description' => 'The task description']
    ];
    public static $arguments = [ 'issue', 'description' ];
//    public static $usage = '%s [-ls] [opt1]';
    public static $menu = true;

    private $Task;

    private static $exception_strings = [
        'not_found' => 'No open work items found.'
    ];

    const CACHE_TAG = 'start';

    const CACHE_NAME_DELIMITER = '_';


    public function run() {
        parent::run();

        $Now = Carbon::now();
        $EndOfDay = $Now->copy()->hour(18); // 6:00 pm
        $Tasks = new TaskService(App()->db());
        $last_index = 0;
        $cache_name = null;
        $filename = null;

        // Get the latest index
        if ($cached_start_times = $this->App()->Cache()->load_tags(self::CACHE_TAG)) {
            foreach ($cached_start_times as $name => $file) {
                $parts = explode(self::CACHE_NAME_DELIMITER, $name);
                if ($parts[1] > $last_index) {
                    $last_index = $parts[1];
                    $cache_name = $name;
                    $filename = $file;
                }
            }
        }

        if (! is_null($cache_name)) {
            $Command = new WriteCommand($this->App());
            $this->Task = json_decode(json_encode($this->App()->Cache()->load($cache_name)));

            if (is_object($this->Task) && property_exists($this->Task, 'start')) {
                if (IS_CLI) {
                    $prompt = sprintf('Complete work item started at %s [Y/n]: ', static::get_twelve_hour_time($this->Task->start));
                    $response = trim(strtolower(readline($prompt))) ?: 'n';
                    if ($response[0] !== 'y') {
                        print "Stop aborted.\n";
                        return false;
                    }
                }

                // cache file
                $Command->setData('start_cache_file', $filename);

                // issue key
                if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
                    $this->Task->issue = $issue;
                }

                // description
                if (($description = $this->option('d')) || ($description = $this->getData('description'))) {
                    $this->Task->description = $description;
                }

                $this->Task->stop = $Tasks->default('stop');

                $fields = [ 'issue', 'description', 'date', 'start', 'stop' ];

                foreach ($fields as $field) {
                    if (property_exists($this->Task, $field)) {
                        $Command->setData($field, $this->Task->{$field});
                    }
                }

                return $Command->run();
            } else {
                // not found
            }
        } else {
            throw new \Exception(static::$exception_strings['not_found']);
        }
    }
}