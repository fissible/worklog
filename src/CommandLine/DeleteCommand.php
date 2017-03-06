<?php
namespace Worklog\CommandLine;

use Worklog\Services\TaskService;
use CSATF\CommandLine\Output;
use CSATF\CommandLine\Command as Command;

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

    private static $exception_strings = [
        'invalid_argument' => 'Command requires a valid ID as the argument'
    ];


    public function run() {
        parent::run();

        $TaskService = new TaskService(App()->db());
        $id = $this->getData('id');

        if (is_numeric($id)) {
            $where = [ 'id' => $id ];

            if ($Task = $TaskService->select($where, 1)->first()) {
                $prompt = sprintf('Delete Task %d%s? [Y/n]: ', $Task->id, ($Task->description ? ' ('.$Task->description.')' : ''));

                if (! IS_CLI || $this->option('f') || 'y' == strtolower(readline($prompt))) {
                    $TaskService->delete($where, 1);
                }

            } else {
                throw new \InvalidArgumentException(static::$exception_strings['invalid_argument']);
            }
        } else {
            throw new \InvalidArgumentException(static::$exception_strings['invalid_argument']);
        }

        return (new ListCommand($this->App()))->run();
    }
}