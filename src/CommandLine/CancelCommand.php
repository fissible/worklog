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
        $return = false;

        list($CacheItem, $Task) = TaskService::cached(true);

        if ($Task && $CacheItem) {
            $deleted = $this->App()->Cache()->delete($CacheItem);
        } else {
            throw new \Exception(static::$exception_strings['not_found']);
        }

        if (IS_CLI) {
            if ($deleted) {
                $return = 'Log entry canceled.';
            } else {
                $return = 'Error deleting log entry.';
            }
        } else {
            $return = $deleted;
        }

        return $return;
    }
}