<?php

namespace Worklog\CommandLine;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class PhpunitCommand extends BinaryCommand {

    public static $description = 'Run PhpUnit tests';
    public static $options = [];
    public static $arguments = [];
    public static $menu = false;

    protected $config_file = 'phpunit.xml';


    protected function init() {
        $this->setBinary(env('BINARY_PHPUNIT'));
        if ($config_file = $this->option('configuration')) {
            $this->config_file = $config_file;
        }
    }

    public function run() {
        parent::run();

        $command = $this->command();
        $command[] = '--configuration="'.dirname(APPLICATION_PATH).'/tests/'.$this->config_file.'"';

        $this->raw($command);
    }
}