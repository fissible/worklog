<?php

namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Models\Task;
use Worklog\Services\TaskService;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class PhpunitCommand extends Command
{
//    public $command_name;

    public static $description = 'Run unit tests';
    public static $options = [
//        'bootstrap' => ['req' => true, 'description' => 'Include a file before test execution'],
//        'i' => ['req' => true, 'description' => 'The JIRA Issue key'],
    ];
    public static $arguments = [];
    public static $menu = true;

    private $phpunit_binary;

    public function run() {
        parent::run();

        debug($this->Options()->args());
        debug($this->Options()->all());

        exec($this->phpunit_binary.' -v'." > `tty`");
    }

    protected function init() {
        $this->phpunit_binary = VENDOR_PATH.'/bin/phpunit';
    }
}