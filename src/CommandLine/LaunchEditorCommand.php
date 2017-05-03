<?php
namespace Worklog\CommandLine;

use Worklog\Str;
use Worklog\Filesystem\File;

/**
 * LaunchEditorCommand
 * Launch an editor to create/edit the SQL for given ticket number (value required)
 */
class LaunchEditorCommand extends BinaryCommand
{
    public $command_name;

    public static $description = 'Launch text-editor';

    public static $arguments = [ 'file' ];

    private $required_data = [ 'file' ];


    protected function init()
    {
        $this->setBinary(env('BINARY_TEXT_EDITOR'));
        parent::init();
    }
}
