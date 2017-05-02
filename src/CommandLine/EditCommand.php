<?php

namespace Worklog\CommandLine;

use Worklog\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/3/17
 * Time: 10:16 AM
 */
class EditCommand extends Command
{
    public $command_name;

    public static $description = 'Update a work log entry';
    public static $options = [];
    public static $arguments = [];
    public static $menu = true;

    protected static $exception_strings = [
        'invalid_argument' => 'Command requires a valid ID as the argument',
        'record_not_found' => 'Record %d not found'
    ];

    public function run()
    {
        parent::run();

        $arguments = $this->arguments();

        switch (count($arguments)) {
            case 0:
                $this->expectData('id', static::$exception_strings['invalid_argument']);
                break;
            case 1:
                $Command = new WriteCommand();
                $Command->set_invocation_flag();
                $Command->setData('id', $arguments[0]);
                break;
            default:
                $last_result = false;
                foreach ($arguments as $id) {
                    Output::line(sprintf('Editing entry %d...', $id));

                    $Command = new WriteCommand();
                    $Command->set_invocation_flag();
                    $Command->getData('RETURN_RESULT', true);
                    $Command->setData('id', $id);

                    $last_result = $Command->run();
                }

                return $last_result;

                break;
        }

        return $Command->run();
    }
}
