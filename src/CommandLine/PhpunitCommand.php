<?php

namespace Worklog\CommandLine;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class PhpunitCommand extends BinaryCommand {

    public $command_name = 'test';

    public static $description = 'Run PhpUnit tests';

    public static $options = [];

    public static $arguments = [];

    public static $menu = false;

    protected $config = [
        'file' => 'phpunit.xml'
    ];


    protected function init() {
        $this->setBinary(env('BINARY_PHPUNIT'));
        if ($config_file = $this->option('configuration')) {
            $this->config['file'] = $config_file;
        }
    }

    public function run() {
        $this->pushCommand('--configuration="../'.$this->config['file'].'"');
        $this->raw();
    }
}