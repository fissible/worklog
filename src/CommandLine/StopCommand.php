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

    protected static $exception_strings = [
        'not_found' => 'No open work items found.'
    ];


    public function run() {
        parent::run();

        $Tasks = new TaskService();
        $cache_name = null;
        $filename = null;

        list($filename, $Task) = TaskService::cached();

        if ($Task) {
            if ($Task->hasAttribute('start')) {
                $Command = new WriteCommand($this->App());
                $Command->setData('RETURN_RESULT', true);
                $Command->set_invocation_flag();

                // if stopping a task started today (from CLI)
                if (IS_CLI && $Task->date->toDateString() !== Carbon::now()->toDateString()) {
                    $description = '';
                    if ($Task->hasAttribute('description') && strlen($Task->description) > 0) {
                        $description = preg_replace('/\s+/', ' ', $Task->description);
                        if (strlen($description) > 27) {
                            $description = substr($description, 0, 24).'...';
                        }
                        $description = ' ('.$description.')';
                    }
//                    $prompt = sprintf('Complete work item%s started at %s [Y/n]: ', $description, static::get_twelve_hour_time($Task->start));
//                    $response = trim(strtolower(Input::ask($prompt))) ?: 'y';
//                    if ($response[0] !== 'y') {
//                        print "Stop aborted.\n";
//                        return false;
//                    }
                    $prompt = sprintf('Complete work item%s started at %s', $description, static::get_twelve_hour_time($Task->start));
                    if (! Input::confirm($prompt, true)) {
                        print "Stop aborted.\n";
                        return false;
                    }
                }

                // cache file
//                $Command->setData('start_cache_file', $filename);

                // issue key
                if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
                    $Task->issue = $issue;
                }

                // description
                if (($description = $this->option('d')) || ($description = $this->getData('description'))) {
                    $Task->description = $description;
                }

                if (IS_CLI && substr($Task->date, 0, 10) !== substr($Task->defaultValue('date'), 0, 10)) {
//                    printl($Task->friendly_date_string);
//                    printl($Task->defaultValue('date'));
                    printf("WARNING: stopping task started on %s\n", Carbon::parse($Task->date)->toFormattedDateString());
                } else {
                    $Task->stop = $Task->defaultValue('stop');
                }


                $fields = [ 'issue', 'description', 'date', 'start', 'stop' ];

                foreach ($fields as $field) {
                    if ($Task->hasAttribute($field)) {
                        $Command->setData($field, $Task->{$field});
                    }
                }

                if ($Command->run()) {
                    $this->App()->Cache()->clear($filename);
                }
            } else {
                if (! is_null($filename)) {
                    $this->App()->Cache()->clear($filename);
                }
                throw new \Exception(static::$exception_strings['not_found']);
            }
        } else {
            throw new \Exception(static::$exception_strings['not_found']);
        }
    }
}