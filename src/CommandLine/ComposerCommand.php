<?php

namespace Worklog\CommandLine;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class ComposerCommand extends BinaryCommand
{
    public static $description = 'Run composer';

    public static $options = [];

    public static $arguments = [];
    

    public function init()
    {
        $this->setBinary(env('BINARY_COMPOSER'));
        if ($config_file = $this->option('configuration')) {
            $this->config_file = $config_file;
        }
    }

    public function run()
    {
        parent::run();
    }
}
