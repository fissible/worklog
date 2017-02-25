<?php
namespace Worklog\CommandLine;

use Worklog\Services\TaskService;
use CSATF\CommandLine\Command as Command;

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
        'i' => ['req' => true, 'description' => 'The JIRA Issue key'],
        'd' => ['req' => true, 'description' => 'The task description'],
//        't' => ['req' => true, 'description' => 'Test flag: requires value']
    ];
    public static $arguments = [ 'issue', 'description' ];
//    public static $usage = '%s [-ls] [opt1]';
    public static $menu = true;

    private $Task;

    private static $exception_strings = [
        'time_format' => 'Start/stop times must be a time format: HH:MM'
    ];


    public function run() {
        parent::run();

        $LastTask = $description = null;
        $Tasks = new TaskService(App()->db());
        $LastTask = $Tasks->lastTask();
        $this->Task = $Tasks->make();//new \stdClass();
        $this->Task->date = $Tasks->field_default_value('date');

        if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
            $this->Task->issue = $issue;
        }

        if ($description = $this->option('d') || $description = $this->getData('description')) {
            $this->Task->description = $description;
        }


        if (IS_CLI) {
            do {
                foreach (TaskService::fields() as $field => $config) {
                    // Provide option to update existing
//                    Probably should not allow this
//                    if (isset($this->Task)) {
//                        if ($field == 'start') {
//                            continue;
//                        }
//                    }
//                    if (property_exists($Task, 'issue') && ! isset($this->Task)) {
//                        if ($_Tasks = App()->db()->get('task')) {
//                            foreach ($_Tasks as $_id => $_Task) {
//                                if ($_Task->issue == $Task->issue) {
//                                    if ('y' == strtolower(readline(sprintf('Modify existing Task %d? [Y/n]: ', $_Task->id)))) {
//                                        $this->Task = $Task = $_Task;
//                                        break;
//                                    }
//                                }
//                            }
//                        }
//                    }

                    if (! property_exists($this->Task, $field)) {
                        if ($field == 'start' && $LastTask) {
                            $stop = $LastTask->stop;
                            $stop_parts = explode(':', $stop);
                            $default = $stop_parts[0].':'.str_pad((intval($stop_parts[1]) + 1), 2, '0', STR_PAD_LEFT);
                        } else {
                            $default = $Tasks->field_default_value($field);
                        }

                        if ($prompt = $Tasks->field_prompt($field, $default)) {
                            if ($response = readline($prompt)) {
                                $response = trim($response);
                                if ($field == 'description' && property_exists($this->Task, 'description')) {
                                    $this->Task->description .= "\n" . $response;
                                } else {
                                    if ($field == 'issue' && is_null($issue)) {
                                        $issue = $response;
                                    }
                                    if (in_array($field, ['start', 'stop'])) {
                                        if (false === strpos($response, ':')) {
                                            if (!is_numeric($response)) {
                                                throw new \Exception(static::$exception_strings['time_format']);
                                            }
                                            $response .= ':00';
                                        }
                                        $response_parts = explode(':', $response);
                                        $response_parts[0] = intval($response_parts[0]);
                                        if (!is_numeric($response_parts[0])) {
                                            throw new \Exception(static::$exception_strings['time_format']);
                                        }
                                        if ($response_parts[0] < 12) {
                                            $ampm_response = readline(sprintf('%02d:%02d AM or PM? [a/p]: ', $response_parts[0], $response_parts[1]));
                                            if (strtolower($ampm_response[0]) == 'p') {
                                                $response_parts[0] += 12;
                                            }
                                        }
                                        $response_parts[0] = str_pad($response_parts[0], 2, '0', STR_PAD_LEFT);
                                        $response = $response_parts[0] . ':' . $response_parts[1];
                                    }
                                    $this->Task->{$field} = $response;
                                }

                            } else {
                                $this->Task->{$field} = $default;
                            }
                        }
                    }
                }
            } while (! $this->TaskValid($this->Task));
        }

//        print '$this->Task: ';
//        var_dump($this->Task);

        $Tasks->write($this->Task);

        $Command = new ListCommand($this->App());

        if ($issue) {
            $Command->setData('issue', $issue);
        }

        return $Command->run();
    }

    protected static function field($field) {
        $fields = TaskService::fields();
        if (array_key_exists($field, $fields)) {
            return $fields[$field];
        }
    }

    protected function TaskValid($Task) {
        $valid = true;
        foreach (TaskService::fields() as $field => $config) {
            if (isset($config['required']) && $config['required']) {
                if ($Task instanceof \stdClass) {
                    if (! property_exists($Task, $field)) {
                        $valid = false;
                    }
                } elseif (is_array($Task)) {
                    if (! array_key_exists($field, $Task)) {
                        $valid = false;
                    }
                } else {
                    throw new \Exception('TaskValid() expects an array or \stdClass instance');
                }
            }
        }

        return $valid;
    }
}