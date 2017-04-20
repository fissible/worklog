<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Models\Task;
use Worklog\Str;

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
        'i' => ['req' => true, 'description' => 'The Jira Issue key'],
        'd' => ['req' => true, 'description' => 'The task description']
    ];
    public static $arguments = [ 'issue', 'description' ];

    public static $usage = '%s [-id] [issue] [description]';

    public static $menu = true;

    private $Task;

    const CACHE_TAG = 'start';

    const CACHE_NAME_DELIMITER = '_';


    public function run() {
        parent::run();

        $Now = Carbon::now();
        $Expiry = $Now->copy()->addDays(2);
        $this->Task = new Task();
        $TaskData = new \stdClass;
        $last_index = 0;

        $RecoverCommand = new RecoverCommand();
        $RecoverCommand->set_invocation_flag();
        $RecoverCommand->setData('warn', 'start');

        if (false === $RecoverCommand->run()) {
            printl('Recovery aborted, resuming current task...');
            return false;
        }

        // Get the latest cache index (should always be 1 with the RecoverCommand running first)
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
            $TaskData->issue = $issue;
        }

        // description
        if (($description = $this->option('d')) || ($description = $this->getData('description'))) {
            $TaskData->description = $description;
        }

        $TaskData->date = $this->Task->defaultValue('date');
        $TaskData->start = $this->Task->defaultValue('start');

        debug($TaskData);

        $start_time = $this->App()->Cache()->data(
            self::CACHE_TAG.self::CACHE_NAME_DELIMITER.($last_index + 1),
            $TaskData,
            [ self::CACHE_TAG ],
            $Now->diffInSeconds($Expiry)
        )->start;

        return 'New task started at '.Str::time($start_time);
    }
}