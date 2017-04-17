<?php
namespace Worklog\CommandLine;

use Worklog\Models\Task;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/23/17
 * Time: 8:34 AM
 */
class DeleteCommand extends Command {

    public $command_name;

    public static $description = 'Delete a work log entry by ID';
    public static $options = [
        'f' => ['req' => null, 'description' => 'Skip prompt']
    ];
    public static $arguments = [ 'id' ];
    public static $menu = true;

    protected static $exception_strings = [
        'invalid_argument' => 'Command requires a valid ID as the argument'
    ];


    public function run() {
        parent::run();

        $id = $this->getData('id');

        if (is_numeric($id)) {
            $Task = Task::findOrFail($id);
            $prompt = sprintf('Delete Task %d%s? [Y/n]: ', $Task->id, ($Task->description ? ' ('.$Task->description.')' : ''));

            if (! IS_CLI || $this->option('f') || 'n' !== strtolower(readline($prompt))) {
                $Task->delete();
            }

        } else {
            throw new \InvalidArgumentException(static::$exception_strings['invalid_argument']);
        }

        return printf('Deleted Task %d', $id);
    }
}