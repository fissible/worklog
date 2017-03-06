<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use CSATF\CommandLine\Output;
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
        'e' => ['req' => true, 'description' => 'The task ID to edit']
    ];
    public static $arguments = [ 'issue', 'description' ];
//    public static $usage = '%s [-ls] [opt1]';
    public static $menu = true;

    private $Task;

    private static $exception_strings = [
        'date_format' => 'Date must be a valid format, eg. YYYY-MM-DD',
        'time_format' => 'Start/stop times must be a time format: HH:MM'
    ];

    const TYPE_UPDATE = 0;
    const TYPE_INSERT = 1;


    public function run() {
        parent::run();

        $Tasks = new TaskService(App()->db());
        $type = $LastTask = $description = null;

        // Get a Task instance
        if (($id = $this->option('e')) || ($id = $this->getData('id'))) {
            $type = self::TYPE_UPDATE;
            $this->Task = $Tasks->select([ 'id' => $id ])->first();
        } else {
            $type = self::TYPE_INSERT;
            $this->Task = $Tasks->make();
            $this->Task->date = $Tasks->field_default_value('date');
            $LastTask = $Tasks->lastTask([ 'date' => Carbon::now()->toDateString() ]);
        }

        // Parse flags/params

        // cache file
        $start_cache_file = $this->getData('start_cache_file');

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
            $this->Task->start = $start;//static::parse_time_input($start);
        }

        // stop
        if ($stop = $this->getData('stop')) {
            $this->Task->stop = $stop;//static::parse_time_input($stop);
        }

        if (IS_CLI) {
            do {
                foreach (TaskService::fields() as $field => $config) {
                    $prompt_default = '';
                    $default = null;

                    // HYDRATE TASK
                    if (! property_exists($this->Task, $field) || $type == self::TYPE_UPDATE) {
                        if ($type == self::TYPE_UPDATE) {
                            $default = $this->Task->{$field};
                            $prompt_default = $default;

                            if (in_array($field, [ 'start', 'stop' ])) {
                                $prompt_default = static::get_twelve_hour_time($default);
                            } elseif ($field == 'date') {
                                $prompt_default = Carbon::parse($default)->toDateString();
                            }

                        } elseif ($field == 'start') {
                            if ($LastTask) {
                                $default = $LastTask->stop;
                            } else {
                                $default = $Tasks->field_default_value($field);
                            }
                            $prompt_default = static::get_twelve_hour_time($default);
                        } elseif ($field == 'stop') {
                            $default = $Tasks->field_default_value($field);
                            $prompt_default = static::get_twelve_hour_time($default);
                        } else {
                            $default = $Tasks->field_default_value($field);
                            $prompt_default = $default;
                        }

                        // PROMPT USER
                        if ($prompt = $Tasks->field_prompt($field, $prompt_default)) {
                            $response = readline($prompt);

                            if (strlen($response) > 0) {
                                $response = trim($response);

                                if ($type == self::TYPE_INSERT && $field == 'description' && property_exists($this->Task, 'description')) {
                                    $this->Task->description .= "\n" . $response;
                                } else {
                                    if ($field == 'issue' && is_null($issue)) {
                                        $issue = $response;
                                    }
                                    if (in_array($field, ['start', 'stop'])) {
                                        $response = static::parse_time_input($response);
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

        unset($this->Task->duration);

        $Tasks->write($this->Task);

        if (! is_null($start_cache_file)) {
            $this->App()->Cache()->clear($start_cache_file);
        }

        $Command = new TodayCommand($this->App());

        if ($type == self::TYPE_UPDATE) {
            $Command = new DetailCommand($this->App());
            $Command->setData('id', $id);
        } elseif ($issue) {
            $Command = new ListCommand($this->App());
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