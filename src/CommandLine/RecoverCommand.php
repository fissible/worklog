<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Models\Task;
use Worklog\Services\TaskService;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/7/17
 * Time: 10:49 AM
 */
class RecoverCommand extends Command {

    public $command_name;

    public static $description = 'Recover a started work log entry';
    public static $options = [];
    public static $arguments = [];
    public static $menu = false;


    public function run() {
        parent::run();

        list($filename, $Task) = TaskService::cached(true);

        if ($Task instanceof Task) {
            if ($Task->hasAttribute('start')) {

                $stop_task = true;
                $cancel_task = false;
                $result = false;

                if (IS_CLI) {
                    $Today = Carbon::today();

                    if ($Task->date->lt($Today)) {
                        $prompt = sprintf(
                            'Complete work item%s started on %s at %s',
                            $Task->description_summary,
                            $Task->date->toFormattedDateString(),
                            $Task->start_time
                        );
                    } else {
                        $prompt = sprintf(
                            'Complete work item%s started at %s',
                            $Task->description_summary,
                            $Task->start_time
                        );
                    }

                    if (! Input::confirm($prompt, $stop_task)) {
                        $stop_task = false;
                        if (Input::confirm('Do you want to cancel it?', $cancel_task)) {
                            $cancel_task = true;
                        }
                    }
                }

                // Stop or cancel the started task
                if ($stop_task) {
                    if ($this->getData('warn')) {
                        if ($Task->date->ne($Today)) {
                            printl('Recovering task...');
                        } else {
                            printl('Stopping task..');
                        }
                    }

                    if (! $Task->valid() && $this->internally_invoked()) {
                        printl('Please complete the missing details...');
                    }

                    $result = (new StopCommand())->run();
                } elseif ($cancel_task) {
                    $result = (new CancelCommand())->run();
                }


                if (IS_CLI && false !== $result) {
                    if ($this->getData('warn') == 'start') {
                        printl('Starting new task...');
                    } elseif ($this->internally_invoked()) {
                        printl('Resuming prior command...');
                    }
                }

                return $result;

            } elseif (! is_null($filename)) {
                debug($filename.' -- DELETES HERE ');
//                $this->App()->Cache()->clear($filename);
            }
        }

        return null;
    }
}