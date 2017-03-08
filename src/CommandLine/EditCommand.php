<?php

namespace Worklog\CommandLine;

use CSATF\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/3/17
 * Time: 10:16 AM
 */
class EditCommand extends Command
{
    public $command_name;

    public static $description = 'Update a work log entries';
    public static $options = [];
    public static $arguments = [ 'id' ];
    public static $menu = true;

    private static $exception_strings = [
        'invalid_argument' => 'Command requires a valid ID as the argument',
        'record_not_found' => 'Record %d not found'
    ];

    public function run() {
        parent::run();
        $this->expectData('id', static::$exception_strings['invalid_argument']);
        $Command = new WriteCommand($this->App());
        $Command->set_invocation_flag();
        $Command->setData('id', $this->getData('id'));

        return $Command->run();
    }
}