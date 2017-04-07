<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Services\TaskService;
use CSATF\CommandLine\Command as Command;

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

        $Tasks = new TaskService(App()->db());
        list($filename, $Task) = $Tasks->cached(true);

        if ($Task) {
            if (property_exists($Task, 'start')) {

                if (IS_CLI) {
                    if ($this->getData('warn')) {
                        if (substr($Task->date, 0, 10) !== substr($Tasks->default_val('date'), 0, 10)) {
                            printl('Recovering a previously started task...');
                        } else {
                            printl('Stopping previous task..');
                        }
                    }

                    if (! $Tasks->valid($Task) && $this->internally_invoked()) {
                        printl('Please complete the missing details...');
                    }
                }

                // Stop the started task
                $result = (new StopCommand($this->App()))->run();

                if (IS_CLI) {
                    if ($this->getData('warn') == 'start') {
                        printl('Starting new task...');
                    } elseif ($this->internally_invoked()) {
                        printl('Resuming prior command...');
                    }
                }

                return $result;

            } elseif (! is_null($filename)) {
                $this->App()->Cache()->clear($filename);
            }
        }

        return true;
    }
}