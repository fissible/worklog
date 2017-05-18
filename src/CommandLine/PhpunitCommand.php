<?php

namespace Worklog\CommandLine;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class PhpunitCommand extends BinaryCommand
{
    public static $description = 'Run PhpUnit tests';

    public static $options = [];

    public static $arguments = [];

    public static $menu = false;

    protected $config = [
        'file' => 'phpunit.xml'
    ];

    public function init()
    {
        parent::init();
        $this->setBinary(env('BINARY_PHPUNIT'));
        if ($config_file = $this->option('configuration')) {
            $this->config['file'] = $config_file;
        }
    }

    public function compile()
    {
        if (! $this->initialized()) {
            $this->init();
        }
        $this->pushCommand('--configuration="'.ROOT_PATH.'/'.$this->config['file'].'"');
        return $this->command(true);
    }

    public function run()
    {
        $this->compile();

        if ($this->internally_invoked()) {
            static::collect_output();
        }

        return parent::run();
    }
}
