<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Models\Task;
use Worklog\Services\TaskService;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/21/17
 * Time: 4:20 PM
 */
class WriteCommand extends Command
{
    public $command_name;

    public static $description = 'Create a work log entry';
    public static $options = [
        'i' => ['req' => true, 'description' => 'The Jira Issue key'],
        'd' => ['req' => true, 'description' => 'The task description'],
        'e' => ['req' => true, 'description' => 'The task ID to edit']
    ];
    public static $arguments = [ 'issue', 'description' ];

    public static $usage = '%s [-ide] [issue[, description]]';

    public static $menu = true;

    private $type;

    private $Task;

    const TYPE_UPDATE = 0;
    const TYPE_INSERT = 1;

    public function run()
    {
        parent::run();

        $TaskService = new TaskService();
        $description = null;

        // Get a Task instance
        if (($id = $this->option('e')) || ($id = $this->getData('id'))) {
            $this->type = self::TYPE_UPDATE;
            $this->Task = Task::findOrFail($id);
        } else {
            $this->type = self::TYPE_INSERT;
            $this->Task = $TaskService->make();
            $this->Task->date = $this->Task->defaultValue('date');
        }

        // Parse flags/params

        // issue key
        if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
            $this->Task->issue = $issue;
        }

        // description
        if (($description = $this->option('d')) || ($description = $this->getData('description'))) {
            $this->Task->description = $description;
        }

        // date
        if ($date = $this->getData('date')) {
            $this->Task->date = static::parse_date_input($date);
        }

        // start
        if ($start = $this->getData('start')) {
            $this->Task->start = $start;
        }

        // stop
        if ($stop = $this->getData('stop')) {
            $this->Task->stop = $stop;
        }

        if (IS_CLI) {
            do {
                // HYDRATE TASK
                foreach (Task::fields() as $field => $config) {
                    if ($field === $this->Task->getKeyName()) continue;

                    if ($this->is_update() || strlen($this->Task->{$field}) < 1) {

                        $default = $this->Task->defaultValue($field);;
                        if ($this->Task->hasAttribute($field)) {
                            $default = $this->Task->{$field};
                        }

                        // Prompt user for values...
                        $response = Input::ask($this->Task->promptForAttribute($field, $default));

                        if (strlen($response) > 0) {
                            $response = trim($response);

                            switch ($field) {
                                case 'description':
                                    $prefix = substr($response, 0, 1);
                                    if ($prefix == '.' || $prefix == ',') {
                                        $response = trim(substr($response, 1));

                                        if ($this->Task->description) {
                                            $join = ($prefix == '.' ? "\n" : ($prefix == ',' ? ' ' : ''));
                                            $response = $this->Task->description .= $join . $response;
                                        }
                                    }

                                    if ($this->is_update() && (strlen($response) < 1 || is_null($response))) {
                                        $response = false;
                                    }
                                    break;
                                case 'start':
                                case 'stop':
                                    $response = static::parse_time_input($response);
                                    break;
                                case 'date':
                                    $response = Carbon::parse($response)->toDateString();
                                    break;
                            }

                            if (false !== $response) {
                                $this->Task->{$field} = $response;
                            }
                        } else {
                            $this->Task->{$field} = $default;
                        }

                    }
                }
            } while (! $this->Task->valid());
        }

        $result = $this->Task->save();

        if ($this->getData('RETURN_RESULT')) {
            return $result;
        }

        $Command = new DetailCommand();
        $Command->set_invocation_flag();
        $Command->setData('id', $this->Task->id);

        return $Command->run();
    }

    /**
     * @return bool
     */
    private function is_update()
    {
        return $this->type == self::TYPE_UPDATE;
    }
}
