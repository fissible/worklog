<?php
namespace Worklog\CommandLine;

class Options implements \ArrayAccess
{
    private $App;

    private $command_registry = [];

    /**
     * The command name
     * @var string
     */
    private $command;

    /**
     * The command instance
     * @var Command
     */
    private $Command;

    private $Options = [];

    private $parsed = false;

    private $arguments = [];

    private $options = [];

    private $valid_options = [];

    private static $error_messages = [
        'COMMAND_NAME_UNSET' => 'No command has been configured',
        'COMMAND_NAME_INVALID' => 'Unknown command "%s"',
        'COMMAND_CONFIG_ITEM_INVALID' => 'Unknown command configuration key "%s"',
        'FLAG_NAME_ILLEGAL' => 'Illegal option "%s"',
        'FLAG_NAME_INVALID' => 'Option name must be a non-empty string'
    ];

    public function __construct($registry = [], $Command = null)
    {
        $this->configure($registry, $Command);
    }

    /**
     * Get the CLI arguments
     * @return [type] [description]
     */
    public static function argv()
    {
        global $argv;
        $args = [];
        if (isset($argv) && is_array($argv)) {
            $args = $argv;
            if (is_file($args[0])) {
                // remove script name
                $args = array_slice($args, 1);
            }
        } elseif (isset($_REQUEST) && is_array($_REQUEST)) {
            $args = $_REQUEST;
        }

        return $args;
    }

    public function command_name()
    {
        return $this->command;
    }

    public function App()
    {
        if (! isset($this->App)) {
            $this->App = \Worklog\Application::instance();
        }

        return $this->App;
    }

    public function Command()
    {
        return $this->Command;
    }

    public function configure($registry = [], $Command = null)
    {
        $this->set_command_registry($registry);
        $this->set_Command($Command);
    }

    /**
     * Set the Command registry to local property "$commands"
     * @param [type] $registry [description]
     */
    public function set_command_registry(array $registry = [])
    {
        if (! empty($registry)) {
            $this->command_registry = $registry;
        }
    }

    /**
     * Set which command this instance is concerned with
     * @param  Command $Command The command instance
     * @return $this
     */
    public function set_Command($Command = null)
    {
        $this->Command = $Command;
        $this->command = $this->Command->name();

        if (array_key_exists($this->command, $this->command_registry)) {
            if (array_key_exists('options', $this->command_registry[$this->command])) {
                foreach ($this->command_registry[$this->command]['options'] as $option => $config) {
                    $this->add($option, $config);
                }
            }
        }

        return $this;
    }

    /**
     * Add an allowed option, eg. $this->add('flush', 'f'): -f, $this->add('run_program', 'op', true): --op=1
     * @param string $option The option name; single character for short
     * @param array  $config
     * @internal param mixed $is_required Leave null for flag, false for optional value
     */
    public function add($option, $config = [])
    {
        $option = ltrim(trim($option), '-');
        if (is_numeric($option) || strlen($option) < 1) {
            throw new \InvalidArgumentException($this->error_message('FLAG_NAME_INVALID', __METHOD__, $option));
        }

        $Option = new Option($this, $option, $config);
        $this->Command()->register_option($Option);
        $this->Options[] = $Option;

        if (! in_array($option, $this->options)) {
            $this->options[$option] = null;
        }
    }

    /**
     * Check if an option exists
     * @param $name mixed The name of the option (string)
     * @return bool
     */
    public function exist($name = null)
    {
        $exists = false;

        if (isset($this->options)) {
            $set_options = $this->options;
        } else {
            $set_options = getopt($this->Command()->shortopts(), $this->Command()->longopts());
        }

        // $this->scan();
        if (is_null($name)) {
            $exists = count($set_options) > 0;
        } elseif (is_string($name) && strlen($name) > 0) {
            if (count($set_options) > 0) {
                $exists = isset($set_options[$name]);
            }
        } else {
            throw new \InvalidArgumentException($this->error_messages['FLAG_NAME_INVALID']);
        }

        return $exists;
    }

    /**
     * @todo: Determine which command was run (from argv OR $_REQUEST)
     * @return [type] [description]
     */
    public function infer_command($args = [])
    {
        return $this->Command()->infer($args ?: static::argv());
    }

    /**
     * Get the config for the configured command
     * @param  string     $property The name of the configuration property
     * @return array
     * @throws \Exception
     */
    protected function config($property = null)
    {
        $config = (isset($this->config) ? $this->config : []);

        if (! ($this->Command instanceof BinaryCommand) || isset($this->command)) {

            if (isset($this->command) && ! empty($this->command)) {
                if (array_key_exists($this->command, $this->command_registry)) {
                    $config = $this->command_registry[$this->command];
                } else {
                    throw new \InvalidArgumentException($this->error_message('COMMAND_NAME_INVALID', __METHOD__, $this->command));
                }
            } else {
                throw new \Exception(static::$error_messages['COMMAND_NAME_UNSET']);
            }
        }

        if (! empty($config)) {
            if (! is_null($property)) {
                if (array_key_exists($property, $config)) {
                    $config = $config[$property];
                } else {
                    $config = null;
                }
            }
        }

        return $config;
    }

    /**
     * Get an option by name and optionally set the value
     * @param  string $name
     * @return mixed
     */
    public function Option($name, $value = null)
    {
        if ($this->validate_option($name)) {
            foreach ($this->Options as $Option) {
                if ($Option->name() == $name) {
                    if (! is_null($value)) {
                        $Option->set_value($value);
                        $this->options[$name] = $value;
                    }

                    return $Option;
                }
            }
        }
    }

    /**
     * Return all the set options
     */
    public function all()
    {
        return $this->options;
    }

    /**
     * Return non-flag arguments
     */
    public function args()
    {
        return $this->arguments;
    }

    public function setArgument($offset, $value)
    {
        $this->arguments[$offset] = $value;
    }

    public function getArgument($offset)
    {
        if (isset($this->arguments[$offset])) {
            return $this->arguments[$offset];
        }
    }

    public function unsetArgument($offset)
    {
    	if (isset($this->arguments[$offset])) {
        	unset($this->arguments[$offset]);
            unset($this->arguments[$offset]);
        }
    }

    public function setOption($offset, $value)
    {
        $this->options[$offset] = $value;
    }

    public function getOption($offset)
    {
        if (isset($this->options[$offset])) {
            return $this->options[$offset];
        }
    }

    public function unsetOption($offset)
    {
        if (isset($this->options[$offset])) {
            unset($this->options[$offset]);
        }
    }

    public function parse_args($args)
    {
        $config = $this->config('options');

        if (is_string($args)) {

            // Cleanup characters to place them back in args.
            $args = str_replace(array('=', "\'", '\"'), array('= ', '&#39;', '&#34;'), $args);
            $args = str_getcsv($args, ' ', '"');
            $tmp = array();
            foreach ($args as $arg) {
                if (! empty($arg) && $arg != "&#39;" && $arg != "=" && $arg != " ") {
                    $tmp[] = str_replace(array('= ', '&#39;', '&#34;'), array('=', "'", '"'), trim($arg));
                }
            }
            $args = $tmp;
        }

        $out = array();
        $args_size = count($args);
        for ($i = 0; $i < $args_size; $i++) {
            $value = false;

            // command --abc
            if (substr($args[$i], 0, 2) == '--') {
                $key = rtrim(substr($args[$i], 2), '=');

                if ((isset($config[$key]) && is_bool($config[$key]['req']))) {
                    $value = $args[$i];
                } else {
                    $out[$key] = true;
                }
            // command -a
            } elseif (substr($args[$i], 0, 1) == '-') {
                $key = rtrim(substr($args[$i], 1), '=');

                $opt = str_split($key);
                $opt_size = count($opt);
                if ($opt_size > 1) {
                    // "command -c d e" would be "c=d e")
                    for ($n = 0; $n < $opt_size; $n++) {
                        $key = $opt[$n];
                        $out[$key] = true;
                    }
                } else { // flag
                    if ((isset($config[$key]) && is_bool($config[$key]['req']))) {
                        $out[$key] = true;
                    } else {
                        $value = $args[$i];
                    }
                }
            } else { //  argument
                $value = $args[$i];
            }

            // Assign key to output array
            if (isset($key)) {
                if (isset($out[$key])) {
                    if (is_bool($out[$key])) {
                        $out[$key] = $value;
                    } else {
                        // You could add type checking here but ftw
                        //$out[$key] = trim($out[$key].' '.$value); // this was too greedy
                        $out[$value] = $value;
                    }
                } else {
                    $out[$key] = $value;
                }
            } elseif ($value) {
                $out[$value] = true;
            }
        }

        return $out;
    }

    /**
     * Get options from the command line or web request
     * @param $Command
     * @param  bool       $force
     * @return $this
     * @throws \Exception
     * @internal param string $options
     * @internal param array $longopts
     */
    public function scan($Command = null, $force = false)
    {
        $argv = static::argv();
        $flags_with_values = [];

        if (! is_null($Command)) {
            $this->set_Command($Command);
        }

        $Command = $this->Command;

        if (! isset($this->command)) {
            $this->command = $Command->name();
        }

        if (! $this->parsed || $force) {
            $shortopts = $Command->shortopts();
            $longopts = $Command->longopts();

            if (PHP_SAPI === 'cli' || empty($_SERVER['REMOTE_ADDR'])) {
                $this->options = getopt($shortopts, $longopts);

                // drop the command string
                $command_string_dropped = false;
                foreach ($argv as $akey => $arg) {
                    if (!$command_string_dropped && $arg == $this->command) {
                        unset($argv[$akey]);
                        $command_string_dropped = true;
                        break;
                    }
                }
                $argv = array_values($argv);
                $argv_count = count($argv);
                $args = $this->parse_args($argv);

                if (count($args) > 0) {
                    // Initialize Option instances and set values
                    if ($options = $this->config('options')) {
                        foreach ($options as $flag => $config) {
                            if (array_key_exists($flag, $args)) {
                                $this->options[$flag] = $args[$flag];

                                if ($Option = $this->Option($flag)) {
                                    // $this->Option($flag, $args[$flag]);
                                    if (! $Option->is_flag($flag)) {
                                        $flags_with_values[] = $flag;
                                        $Option->set_value($args[$flag]);
                                    }
                                } else {
                                    throw new \Exception('Missing expected Option '.$flag);
                                }
                            }
                        }
                    }

                    // Parse arguments into Command data
                    $data_keys = $this->config('arguments') ?: [];
                    $data_key = 0;
                    $last_arg = null;
                    $last_flag_gets_value = false;
                    $last_arg_was_option = false;

                    if (false !== ($_data_key_key = array_search('subcommand', $data_keys)) && $Command->validateSubcommand($argv[0])) {
                        unset($data_keys[$_data_key_key]);
                    }

                    foreach ($argv as $key => $argument) {

                        if (! empty($data_keys) && ! array_key_exists($data_key, $data_keys)) {
                            break;
                        }

                        $is_option = substr($argument, 0, 1) == '-';
                        $option = ltrim($argument, '-');

                        if ($is_option && array_key_exists($option, $args)) {
                            if (! empty($this->options) && ! array_key_exists($option, $this->options)) {
                                throw new \Exception(sprintf("Invalid flag \"%s\"", $argument));
                            } elseif (array_key_exists($option, $this->options)) {
                                $last_flag_gets_value = ($options[$option]['req'] === true);
                            } else {
                                $last_flag_gets_value = substr($argument, 0, 2) == '--' && $key != ($argv_count -1);
                                $this->add($option, ['req' => ($last_flag_gets_value ? false : null), 'description' => 'Dynamic option']);
                            }
                            $last_arg_was_option = true;
                            $last_arg = $option;
                            continue;

                        } elseif ($command_string_dropped || $argument !== $this->Command->name()) {
                            if (! $last_arg_was_option || (! in_array($last_arg, $flags_with_values) && ! $last_flag_gets_value)) {
                                $this->arguments[] = $argument;

                                if (array_key_exists($data_key, $data_keys)) {
                                    $Command->addData($data_keys[$data_key], $argument);
                                } else {
                                    $Command->addData($data_key, $argument);
                                }
                                $data_key++;

                            } elseif ($last_arg_was_option && $last_flag_gets_value) {
                                $this->options[$last_arg] = $argument;
                                $this->Option($last_arg, $argument);
                            }
                            $last_arg_was_option = false;
                            $last_flag_gets_value = false;
                            $last_arg = $option;
                        }
                    }

                } // EOF if (count($args) > 0)

            } elseif (isset($_REQUEST)) {
                if (isset($_REQUEST['command'])) {
                    $command = $_REQUEST['command'];
                    unset($_REQUEST['command']);
                } else {
                    foreach ($_REQUEST as $rkey => $rvalue) {
                        if (in_array($rvalue, array_keys($this->command_registry))) {
                            $command = $rvalue;
                            unset($_REQUEST[$rkey]);
                        }
                    }
                }

                $shortopts = preg_split('@([a-z0-9][:]{0,2})@i', $shortopts, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $opts = array_merge($shortopts, $longopts);

                foreach ($opts as $opt) {
                    if (substr($opt, -2) === '::') {
                        $key = substr($opt, 0, -2);
                        if (isset($_REQUEST[$key]) && ! empty($_REQUEST[$key]))
                            $this->Option($key, $_REQUEST[$key]);
                        elseif (isset($_REQUEST[$key]))
                            $this->Option($key, false);
                    } elseif (substr($opt, -1) === ':') {
                        $key = substr($opt, 0, -1);
                        if (isset($_REQUEST[$key]) && ! empty($_REQUEST[$key]))
                            $this->Option($key, $_REQUEST[$key]);
                    } elseif (ctype_alnum($opt)) {
                        if (isset($_REQUEST[$opt]))
                            $this->Option($opt, false);
                    }
                }
            }
            $this->parsed = true;
        }

        return $this;
    }

    /**
     * Throws exception if an invalid (wrong param) or illegal (non-existent) option
     * @param $option
     * @param  bool $configuring
     * @return bool
     * @internal param string $name
     */
    private function validate_option($option, $configuring = false)
    {
        $valid = false;
        if ($options = $this->config('options')) {
            if (is_string($option)) {
                if (strlen($option) > 0) {
                    $valid = in_array($option, array_keys($options));
                } else {
                    throw new \InvalidArgumentException(static::$error_messages['FLAG_NAME_INVALID']);
                }
            }
        } else {
            $valid = true;
        }

        return $valid;
    }

    protected function error_message($error, $method = null)
    {
        $signature = __CLASS__;
        $args = func_get_args();
        $error = array_shift($args);
        $found_format = false;
        $signature = '';

        if (array_key_exists($error, static::$error_messages)) {
            $error = static::$error_messages[$error];
            $found_format = true;
        }
        if (count($args) > 0) {
            $signature = array_shift($args);
            $signature .= '()';
        }
        if ($found_format && count($args)) {
            $error = vsprintf($error, $args);
        }
        if ($signature) {
            $error = $signature.': '.$error;
        }

        return $error;
    }

    /** ArrayAccess abstract method implementations **/

    /**
     * Assigns a value to the specified offset
     *
     * @param mixed $offset
     * @param mixed $value
     * @internal param The $string offset to assign the value to
     * @internal param The $mixed value to set
     */
    public function offsetSet($offset, $value)
    {
        foreach ($this->Options as $Option) {
            if ($Option->name() == $offset) {
                $this->Option($offset, $value);
            }
        }
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Whether or not an offset exists
     *
     * @param  mixed $offset
     * @return bool
     * @internal param An $string offset to check for
     */
    public function offsetExists($offset)
    {
        $exists = false;
        foreach ($this->Options as $Option) {
            if ($Option->name() == $offset) {
                $exists = true;
                break;
            }
        }

        return $exists;
    }

    /**
     * Unsets an offset
     *
     * @param mixed $offset
     * @internal param The $string offset to unset
     */
    public function offsetUnset($offset)
    {
        foreach ($this->Options as $key => $Option) {
            if ($Option->name() == $offset) {
                unset($this->Options[$key]);
                break;
            }
        }
         // if ($this->offsetExists($offset)) {
         //     unset($this->options[$offset]);
        // }
    }

    /**
     * Returns the value at specified offset
     *
     * @param  mixed $offset
     * @return mixed
     * @internal param The $string offset to retrieve
     */
    public function offsetGet($offset)
    {
        return $this->Option($offset);
    }

    public function __get($name)
    {
        return $this->Option($name);
    }

    public function __set($name, $value)
    {
        if ($Option = $this->Option($name)) {
            $Option->set_value($name);
        }
    }

    public function __isset($name)
    {
        if ($this->Option($name)) {
            return true;
        }

        return false;
    }

    /**
     * Unsets an data by key
     *
     * @param $name string The key to unset
     */
    public function __unset($name)
    {
        foreach ($this->Options as $key => $Option) {
            if ($Option->name() == $name) {
                unset($this->Options[$key]);
            }
        }
    }

}
