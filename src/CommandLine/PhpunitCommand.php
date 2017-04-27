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
    public static $options = [];
    public static $arguments = [];
    public static $menu = true;

    private $phpunit_binary;

    private $config_file = 'phpunit.xml';


    protected function init() {
        $this->phpunit_binary = VENDOR_PATH.'/bin/phpunit';
        if ($config_file = $this->option('configuration')) {
            $this->config_file = $config_file;
        }
    }

    public function run() {
        parent::run();

        // debug($this->Options()->args()); // arguments
        // debug($this->Options()->all());  // flags
        $args = $this->Options()->args();
        $flags = $this->Options()->all();

        $command = [];
        $command[] = $this->phpunit_binary;
        $command[] = '--configuration="'.dirname(APPLICATION_PATH).'/tests/'.$this->config_file.'"';
//        $command[] = '--verbose';
//        $command[] = '--debug';
        $command = array_merge($command, $flags);
        $command = array_merge($command, $args);

        $this->raw($command);
    }
}