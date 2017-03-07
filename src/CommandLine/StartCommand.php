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
class StartCommand extends Command {

    public $command_name;

    public static $description = 'Start a work log entry';
    public static $options = [
        'i' => ['req' => true, 'description' => 'The JIRA Issue key'],
        'd' => ['req' => true, 'description' => 'The task description']
    ];
    public static $arguments = [ 'issue', 'description' ];
//    public static $usage = '%s [-ls] [opt1]';
    public static $menu = true;

    private $Task;

    private static $exception_strings = [
        'time_format' => 'Start/stop times must be a time format: HH:MM'
    ];

    const CACHE_TAG = 'start';

    const CACHE_NAME_DELIMITER = '_';


    public function run() {
        parent::run();

        $Now = Carbon::now();
        $EndOfDay = $Now->copy()->hour(18); // 6:00 pm
        $Tasks = new TaskService(App()->db());
        $this->Task = $Tasks->make();
//        $LastTask = $Tasks->lastTask([ 'date' => Carbon::now()->toDateString() ]);
        $last_index = 0;

        // Get the latest index
        if ($cached_start_times = $this->App()->Cache()->load_tags(self::CACHE_TAG)) {
            foreach ($cached_start_times as $name => $file) {
                $parts = explode(self::CACHE_NAME_DELIMITER, $name);
                if ($parts[1] > $last_index) {
                    $last_index = $parts[1];
                }
            }
        }

        // issue key
        if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
            $this->Task->issue = $issue;
        }

        // description
        if ($description = $this->option('d') || $description = $this->getData('description')) {
            $this->Task->description = $description;
        }

        $this->Task->date = $Tasks->default_val('date');
        $this->Task->start = $Tasks->default_val('start');

        return static::get_twelve_hour_time($this->App()->Cache()->data(
            self::CACHE_TAG.self::CACHE_NAME_DELIMITER.($last_index + 1),
            $this->Task,
            [ self::CACHE_TAG ],
            $Now->diffInSeconds($EndOfDay)
        )->start);
    }
}