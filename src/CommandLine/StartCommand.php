<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Services\TaskService;
use CSATF\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/21/17
 * Time: 4:20 PM
 */
class StartCommand extends Command {

    public $command_name;

    public static $description = 'Start a work log entry';
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

        $id = $description = null;
        $Tasks = new TaskService(App()->db());
        $this->Task = new \stdClass();
        $this->Task->date = Carbon::now()->toDateTimeString();

        if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
            $this->Task->issue = $issue;
        }

        if ($description = $this->option('d') || $description = $this->getData('description')) {
            $this->Task->description = $description;
        }


        if (IS_CLI) {
            do {
                foreach (static::$fields as $field => $config) {
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
                        if ($response = readline($this->getPrompt($field))) {
                            $response = trim($response);
                            if ($field == 'description' && property_exists($this->Task, 'description')) {
                                $this->Task->description .= "\n".$response;
                            } else {
                                if ($field == 'issue' && is_null($issue)) {
                                    $issue = $response;
                                }
                                if (in_array($field, [ 'start', 'stop' ])) {
                                    if (false === strpos($response, ':')) {
                                        if (! is_numeric($response)) {
                                            throw new \Exception(static::$exception_strings['time_format']);
                                        }
                                        $response .= ':00';
                                    }
                                    $response_parts = explode(':', $response);
                                    $response_parts[0] = intval($response_parts[0]);
                                    if (! is_numeric($response_parts[0])) {
                                        throw new \Exception(static::$exception_strings['time_format']);
                                    }
                                    if ($response_parts[0] < 12) {
                                        $ampm_response = readline(sprintf('%02d:%02d AM or PM? [a/p]: ', $response_parts[0], $response_parts[1]));
                                        if (strtolower($ampm_response[0]) == 'p') {
                                            $response_parts[0] += 12;
                                        }
                                    }
                                    $response_parts[0] = str_pad($response_parts[0], 2, '0', STR_PAD_LEFT);
                                    $response = $response_parts[0].':'.$response_parts[1];
                                }
                                $this->Task->{$field} = $response;
                            }

                        } else {
                            $this->Task->{$field} = $this->getDefault($field);
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
        if (array_key_exists($field, static::$fields)) {
            return static::$fields[$field];
        }
    }

    protected function getDefault($field) {
        $default = null;
//        if (isset($this->Task)) {
//            if (property_exists($this->Task, $field)) {
//                $default = $this->Task->{$field};
//            }
//        }
        if (is_null($default)) {
            if ($config = static::field($field)) {
                $default = $config['default'];
                if (substr($default, 0, 1) == '*') {
                    $method = substr($default, 1);
                    $sub_method = null;
                    $arguments = null;
                    $sub_arguments = null;

                    if (false !== strpos($method, '->')) {
                        $parts = explode('->', $method);
                        $method = $parts[0];
                        $sub_method = $parts[1];
                    }

                    if (false !== strpos($method, '(') && false !== strpos($method, ')')) {
                        list($method, $arguments) = $this->strParse($method, 'args');
                    }

                    if (! is_null($arguments)) {
                        $default = call_user_func_array([ $this, $method ], $arguments);
                    } else {
                        $default = call_user_func([ $this, $method ]);
                    }

                    // sub-method
                    if (! is_null($sub_method) && $default) {
                        $obj = (is_object($default) ? $default : $this);

                        if (false !== strpos($sub_method, '(') && false !== strpos($sub_method, ')')) {
                            list($sub_method, $sub_arguments) = $this->strParse($sub_method, 'args');
                        }

                        if (! is_null($sub_arguments)) {
                            $default = call_user_func_array([ $obj, $sub_method ], $sub_arguments);
                        } else {
                            $default = call_user_func([ $obj, $sub_method ]);
                        }
                    }
                }
            }
        }

        return $default;
    }

    protected function getPrompt($field) {
        if ($config = static::field($field)) {
            $prompt = $config['prompt'];
            if ($config['required']) {
                $prompt .= ' (required)';
            }
            if ($field !== 'description' && $default = $this->getDefault($field)) {
                $prompt .= ' [' . $default . ']';
            }
            $prompt .= ': ';

            return $prompt;
        }
    }

    protected function TaskValid($Task) {
        $valid = true;
        foreach (static::$fields as $field => $config) {
            if ($config['required']) {
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

    protected function DateTime($time = 'now', $timezone = null) {
        return new Carbon($time, $timezone);
    }

    protected function strParse($input, $type) {
        $output = $input;
        switch ($type) {
            case 'args':
                if (false !== strpos($input, '(') && false !== strpos($input, ')')) {
                    $open_paren_pos = strpos($input, '(');
                    $method = substr($input, 0, $open_paren_pos);
                    if ($arg_string = substr($input, $open_paren_pos + 1, (strpos($input, ')', $open_paren_pos) - $open_paren_pos) - 1)) {
                        $arguments = explode(',', $arg_string);
                        $arguments = array_map('trim', $arguments);
                        $arguments = array_map(function ($n) {
                            return trim($n, "'");
                        }, $arguments);

                        $output = [ $method, $arguments ];
                    }
                }
                break;
        }

        return $output;
    }
}