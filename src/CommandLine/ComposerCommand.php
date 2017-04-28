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

class ComposerCommand extends BinaryCommand
{
    public static $description = 'Run composer';
    public static $options = [
        'nodev' => ['req' => null, 'description' => 'Do not install development dependencies']
    ];
    public static $arguments = [];


    protected function init() {
        $this->binary = '/usr/bin/env composer';
        if ($config_file = $this->option('configuration')) {
            $this->config_file = $config_file;
        }
    }

    public function run() {
        parent::run();

        $raw_command = [
            $this->binary,// '--help'
        ];

        if ($this->Options()->nodev && ! DEVELOPMENT_MODE) {
            $raw_command[] = '--no-dev';
        }

        $this->raw($raw_command);
    }
}