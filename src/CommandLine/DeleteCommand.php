<?php
namespace Worklog\CommandLine;

use Worklog\Models\Task;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/23/17
 * Time: 8:34 AM
 */
class DeleteCommand extends Command
{
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

    public function run()
    {
        parent::run();

        $id = $this->getData('id');
        $out = '';

        if (is_numeric($id)) {
            $Task = Task::findOrFail($id);
            $prompt = sprintf('Delete Task %d%s?', $Task->id, ($Task->description ? ' ('.$Task->description.')' : ''));

            if (! IS_CLI || $this->option('f') || Input::confirm($prompt, true)) {
                if ($Task->delete()) {
                    $out = sprintf('Deleted Task %d', $id);
                }
            }
        } else {
            throw new \InvalidArgumentException(static::$exception_strings['invalid_argument']);
        }

        return $out;
    }
}
