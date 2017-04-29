<?php

namespace Worklog\CommandLine;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class ComposerCommand extends BinaryCommand {

    public static $description = 'Run composer';
    public static $options = [
        // 'nodev' => ['req' => null, 'description' => 'Do not install development dependencies'],
        // 'test' => ['req' => null, 'description' => 'test']
    ];
    public static $arguments = [];


    protected function init() {
        $this->setBinary(env('BINARY_COMPOSER'));
        if ($config_file = $this->option('configuration')) {
            $this->config_file = $config_file;
        }
    }

    public function run() {
        parent::run();

        $command = $this->command();

        if ($this->Options()->nodev && ! DEVELOPMENT_MODE) {
            $command[] = '--no-dev';
        }

        $this->raw($command);
    }
}