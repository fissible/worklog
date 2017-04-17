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
class WriteCommand extends Command {

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

    private $Task;

    const TYPE_UPDATE = 0;
    const TYPE_INSERT = 1;


    public function run() {
        parent::run();

        $TaskService = new TaskService();
        $LastTask = $description = null;

        // Get a Task instance
        if (($id = $this->option('e')) || ($id = $this->getData('id'))) {
            $type = self::TYPE_UPDATE;
            $this->Task = Task::findOrFail($id);
        } else {
            $type = self::TYPE_INSERT;
            $this->Task = $TaskService->make();
            $this->Task->date = $this->Task->defaultValue('date');
            $LastTask = $TaskService->lastTask();
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
                    if ($field ===  $this->Task->getKeyName()) continue;

                    if (! $this->Task->hasAttribute($field) || $type == self::TYPE_UPDATE) {

                        $default = $this->Task->defaultValue($field);
                        $prompt_default = ''.$default;

                        // UPDATE default values
                        if ($type == self::TYPE_UPDATE) {
                            switch($field) {
                                case 'start':
                                case 'stop':
                                    $prompt_default = static::get_twelve_hour_time($default);
                                    break;
                                case 'date':
                                    $prompt_default = Carbon::parse($default)->toDateString();
                                    break;
                                case 'description':
                                    $description = trim($this->Task->description);
                                    if ($description) {
                                        $description = preg_replace('/\s+/', ' ', $description);
                                        if (strlen($description) > 27) {
                                            $description = substr($description, 0, 24) . '...';
                                        }
                                    }
                                    $prompt_default = $description;
                                    break;
                            }

                        // INSERT default values
                        } else {
                            switch($field) {
                                case 'start':
                                    if ($LastTask) {
                                        $default = $LastTask->stop;
                                    }
                                    // ...fallthrough
                                case 'stop':
                                    $prompt_default = static::get_twelve_hour_time($default);
                                    break;
                            }
                        }

                        // Prompt user for values...
                        $response = Input::ask($this->Task->promptForAttribute($field, $default));

                        if (strlen($response) > 0) {
                            $response = trim($response);

                            switch($field) {
                                case 'issue':
                                    $issue = $response;
                                    break;
                                case 'description':
                                    $prefix = substr($response, 0, 1);
                                    if ($prefix == '.' || $prefix == ',') {
                                        $response = trim(substr($response, 1));

                                        if ($this->Task->description) {
                                            $join = ($prefix == '.' ? "\n" : ($prefix == ',' ? ' ' : ''));
                                            $response = $this->Task->description .= $join . $response;
                                        }
                                    }

                                    if ($type !== self::TYPE_UPDATE && (strlen($response) < 1 || is_null($response))) {
                                        printl("\t".'is_UPDATE & $response is null or strlen < 1: set $response to false');
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

                                if (is_null($response)) {
                                    printl("\t".'$response is null');
                                } else {
                                    printl("\t".'set '.$field.' to string with length of '.strlen($response));
                                }


                                $this->Task->{$field} = $response;
                            }
                        } elseif ($type == self::TYPE_UPDATE) {
                            printl("\t".'set '.$field.' to "'.var_export($default, true).'"');

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

        $Command = new DetailCommand($this->App());
        $Command->set_invocation_flag();
        $Command->setData('id', $this->Task->id);

        return $Command->run();
    }
}