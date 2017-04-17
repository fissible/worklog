<?php

namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\CommandLine\Output;
use Worklog\Services\TaskService;
use Worklog\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/3/17
 * Time: 9:29 AM
 */
class TodayCommand extends Command
{
    public $command_name;

    public static $description = 'Report today\'s work log entries';
    public static $options = [];
    public static $arguments = [];
    public static $menu = true;

    public function run() {
        parent::run();

        list(, $Task) = TaskService::cached(true);

        // Current (started) task
        if ($Task) {
            Output::line('Started task:');
            printl(Output::data_grid(
                [ 'Started', 'Issue', 'Description' ],
                [[ $Task->start_string, ($Task->issue ?: ''), $Task->description ]],
                null,
                160
            ));
        }

        $Command = new ReportCommand($this->App());
        $Command->set_invocation_flag();
        $Command->setData('today', true);
        return $Command->run();
    }
}