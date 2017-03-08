<?php

namespace Worklog\CommandLine;

use Carbon\Carbon;
use CSATF\CommandLine\Output;
use Worklog\Services\TaskService;
use CSATF\CommandLine\Command as Command;

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

        $Tasks = new TaskService(App()->db());
        list($filename, $Task) = $Tasks->cached(true);

        // Current (started) task
        if ($Task) {
            $Tasks->formatFieldsForDisplay($Task);
            $Tasks->setCalculatedFields($Task);
            $description = '';
            if (property_exists($Task, 'description') && strlen($Task->description)) {
                $description = ' - '.$Task->description;
            }
            Output::line('Started task:');
            Output::line($Task->start_time.$description, ' ');
            Output::line();
        }

        $Command = new ReportCommand($this->App());
        $Command->set_invocation_flag();
        $Command->setData('today', true);
        return $Command->run();
    }
}