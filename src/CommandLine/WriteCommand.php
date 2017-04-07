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

        $Tasks = new TaskService(App()->db());
        $LastTask = $description = null;

        // Get a Task instance
        if (($id = $this->option('e')) || ($id = $this->getData('id'))) {
            $type = self::TYPE_UPDATE;
            $_Tasks = $Tasks->select([ 'id' => $id ]);
            $this->Task = $_Tasks[0];
        } else {
            $type = self::TYPE_INSERT;
            $this->Task = $Tasks->make();
            $this->Task->date = $Tasks->field_default_value('date');
            $LastTask = $Tasks->lastTask([ 'date' => Carbon::now()->toDateString() ]);
        }

        // Parse flags/params

        // cache file
//        $start_cache_file = $this->getData('start_cache_file');

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

                        // UPDATE default values
                        if ($type == self::TYPE_UPDATE) {
                            $default = (property_exists($this->Task, $field) ? $this->Task->{$field} : '');
                            $prompt_default = $default;

                            switch($field) {
                                case 'start':
                                case 'stop':
                                    $prompt_default = static::get_twelve_hour_time($default);
                                    break;
                                case 'date':
                                    $prompt_default = Carbon::parse($default)->toDateString();
                                    break;
                                case 'description':
                                    $description = '';
                                    if (property_exists($this->Task, 'description') && strlen($this->Task->description) > 0) {
                                        $description = preg_replace('/\s+/', ' ', $this->Task->description);
                                        if (strlen($description) > 27) {
                                            $description = substr($description, 0, 24) . '...';
                                        }
                                    }
                                    $prompt_default = $description;
                                    break;
                            }

                        // INSERT default values
                        } else {
                            $default = $Tasks->field_default_value($field);
                            $prompt_default = $default;

                            switch($field) {
                                case 'start':
                                    if ($LastTask) {
                                        $default = $LastTask->stop;
                                    } else {
                                        $default = $Tasks->field_default_value($field);
                                    }
                                    $prompt_default = static::get_twelve_hour_time($default);
                                    break;
                                case 'stop':
                                    $default = $Tasks->field_default_value($field);
                                    $prompt_default = static::get_twelve_hour_time($default);
                                    break;
                            }
                        }

                        // Prompt user for values...
                        if ($prompt = $Tasks->field_prompt($field, $prompt_default)) {
                            $response = readline($prompt);

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

                                            if (property_exists($this->Task, 'description')) {
                                                $join = ($prefix == '.' ? "\n" : ($prefix == ',' ? ' ' : ''));
                                                $response = $this->Task->description .= $join . $response;
                                            }
                                        }
                                        break;
                                    case 'start':
                                    case 'stop':
                                        $response = static::parse_time_input($response);
                                        break;
                                }

                                $this->Task->{$field} = $response;

                            } else {
                                $this->Task->{$field} = $default;
                            }
                        }
                    }
                }
            } while (! $Tasks->valid($this->Task));
        }

        unset($this->Task->duration);


        $result = $Tasks->write($this->Task);


        // Return


        if ($this->getData('RETURN_RESULT')) {
            return $result;
        }

        $Command = new DetailCommand($this->App());
        $Command->set_invocation_flag();
        $Command->setData('id', $this->Task->id);
        return $Command->run();

//        if (! is_null($start_cache_file)) {
//            $this->App()->Cache()->clear($start_cache_file);
//        }

        $Command = new TodayCommand($this->App());
        $Command->set_invocation_flag();

        if ($type == self::TYPE_UPDATE) {
            $Command = new DetailCommand($this->App());
            $Command->set_invocation_flag();
            $Command->setData('id', $id);
        } elseif ($issue) {
            $Command = new ListCommand($this->App());
            $Command->set_invocation_flag();
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
}