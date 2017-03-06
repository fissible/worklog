<?php

namespace Worklog\CommandLine;

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
        $Command = new ReportCommand($this->App());
        $Command->setData('today', true);
        return $Command->run();
    }
}