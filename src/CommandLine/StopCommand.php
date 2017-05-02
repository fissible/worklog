<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Services\TaskService;
use Worklog\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/21/17
 * Time: 4:20 PM
 */
class StopCommand extends Command
{
    public $command_name;

    public static $description = 'Stop and record a work log entry';
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

    public function run()
    {
        parent::run();

        $cache_name = null;
        $filename = null;

        list($filename, $Task) = TaskService::cached();

        if ($Task) {
            if ($Task->hasAttribute('start')) {
                $fields = [ 'issue', 'description', 'date', 'start', 'stop' ];
                $Today = Carbon::today();
                $Command = new WriteCommand();
                $Command->setData('RETURN_RESULT', true);
                $Command->set_invocation_flag();

                // issue key
                if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
                    $Task->issue = $issue;
                }

                // description
                if (($description = $this->option('d')) || ($description = $this->getData('description'))) {
                    $Task->description = $description;
                }

                // stop
                if (! $Task->date->lt($Today)) {
                    $Task->stop = $Task->defaultValue('stop');
                }

                foreach ($fields as $field) {
                    if ($Task->hasAttribute($field)) {
                        $Command->setData($field, $Task->{$field}.'');
                    }
                }

                if ($Command->run()) {
                    $this->App()->Cache()->disable_purge(false);
                    $this->App()->Cache()->clear($filename);
                }
            } else {
                if (! is_null($filename)) {
                    $this->App()->Cache()->disable_purge(false);
                    $this->App()->Cache()->clear($filename);
                }
                throw new \Exception(static::$exception_strings['not_found']);
            }
        } else {
            throw new \Exception(static::$exception_strings['not_found']);
        }
    }
}
