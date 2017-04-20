<?php
namespace Worklog\CommandLine;

use Worklog\Services\TaskService;
use Worklog\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/7/17
 * Time: 11:32 AM
 */
class CancelCommand extends Command {

    public $command_name;

    public static $description = 'Cancel a started work log entry';
    public static $options = [
        'i' => ['req' => true, 'description' => 'The JIRA Issue key'],
        'd' => ['req' => true, 'description' => 'The task description']
    ];
    public static $arguments = [ 'issue', 'description' ];
//    public static $usage = '%s [-ls] [opt1]';
    public static $menu = true;

    protected static $exception_strings = [
        'not_found' => 'No open work items found.'
    ];


    public function run() {
        parent::run();

        $deleted = false;
        $filename = null;

        list($filename, $Task) = TaskService::cached(true);

        if ($Task) {
            if (! is_null($filename)) {
                $deleted = $this->App()->Cache()->clear($filename);
            }
            if (IS_CLI && $deleted) {
                printl('Log entry canceled.');
            }
        } else {
            throw new \Exception(static::$exception_strings['not_found']);
        }

        return $deleted;
    }
}