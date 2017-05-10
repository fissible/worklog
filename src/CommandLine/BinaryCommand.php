<?php

namespace Worklog\CommandLine;

/**
 * Abstract binary command
 */

class BinaryCommand extends Command
{

    public static $menu = false;

    protected $binary;

    protected static $collect_output = false;

    protected $config_file;

    protected $initialized = false;

    protected $executed = false;

    protected $ready = false;

    protected $raw_command;

    protected $final_command;

    protected $raw_command_background = false;

    protected static $flags_before_arguments = false;

    public function __construct($command = [], $in_background = false)
    {
        parent::__construct();
        $this->setRawCommand($command, $in_background);

        if (method_exists($this, 'init')) {
            $this->init();
        }
    }

    public function init()
    {
        if (! $this->initialized()) {
            if ($config_file = $this->option('configuration')) {
                $this->config_file = $config_file;
            }
        }
        
        $this->initialized(true);
    }

    public static function call($decorate = null, $Command = BinaryCommand::class, $borrow_binary = true)
    {
        $Parent = new static();
        $Parent->init();

        if (!($Command instanceof Command)) {
            $Command = new $Command();
        }

        if (! is_callable($decorate)) {
            if (is_string($decorate)) {
                $decorate = (array) $decorate;
            }
            if (is_array($decorate) && is_a($Command, BinaryCommand::class)) {
                if ($borrow_binary && $binary = $Parent->getBinary()) {
                    if (! isset($decorate[0]) || $decorate[0] !== $binary) {
                        array_unshift($decorate, $binary);
                    }
                }
            }

            $Command = new $Command($decorate);
        }

        $Command->resolve();

        if (is_callable($decorate)) {
            $Command = call_user_func($decorate, $Command);
        }

        return $Command->run();
    }

    /**
     * Parse command arguments and return a Command object
     * @param  array   $args The command and command arguments
     * @return Command
     */
    public function resolve($args = [])
    {
        $this->setOptions();
        $this->parse_static_data();
        
        if (! empty($args)) {
            $this->setRawCommand($args);
        }

        return $this;
    }

    // public function name() {
    //     $name = '';
    //     if (isset($this->binary)) {
    //         $name = $this->binary;
    //     } elseif (isset($this->command_name)) {
    //         $name = $this->command_name;
    //     }
    //     return $name;
    // }

    public function setBinary($binary)
    {
        $this->binary = $binary;
    }

    public function getBinary()
    {
        if (isset($this->binary)) {
            return $this->binary;
        }
    }

    public function setRawCommand($command = [], $in_background = false)
    {
        if (! empty($command)) {
            $this->raw_command = [];
            $this->pushCommand($command);
            $this->backgroundRawCommand($in_background);
            $this->ready(false);
        }
    }

    public function backgroundRawCommand($bool = true)
    {
        $this->raw_command_background = (bool) $bool;
    }

    public function command($reprocess = false)
    {
        if (! $this->initialized()) {
            $this->init();
        }
        if (! isset($this->final_command) || $reprocess == true) {
            $this->final_command = [];
            $this->ready(false);

            // Add the binary
            if (isset($this->binary)) {
                $this->final_command[] = escapeshellcmd($this->binary);
            }

            // Pass in "--flags" and "arguments"
            $this->pushOptions();

            // Process maunal pushes
            foreach ((array) $this->raw_command as $key => $token) {
                if (isset($this->binary) && $token == $this->binary) {
                    continue;
                }
                $this->pushFinalCommand($token);
            }

            // Update ready status
            $this->ready(! empty($this->final_command));
        }

        return $this->final_command;
    }

    private function pushOptions()
    {
        $pushFlags = function ($flags) {
            foreach ($flags as $key => $value) {
                $prefix = (strlen($key) == 1 ? '-' : '--');
                $this->pushFinalCommand($prefix.$key);

                if (false !== $value) {
                    $this->pushFinalCommand($value);
                }
            }
        };
        if (static::$flags_before_arguments) {
            $pushFlags($this->flags());
            $this->pushFinalCommand($this->arguments());
        } else {
            $this->pushFinalCommand($this->arguments());
            $pushFlags($this->flags());
        }
    }

    public function pushCommand($token, $check_exists = false)
    {
        return $this->_pushCommand($token, false, $check_exists);
    }

    protected function pushFinalCommand($token, $check_exists = false)
    {
        return $this->_pushCommand($token, true, $check_exists);
    }

    private function _pushCommand($token, $final, $check_exists = false)
    {
        $command = ($final ? 'final_command' : 'raw_command');
        if (is_array($token)) {
            foreach ($token as $key => $_token) {
                $this->_pushCommand($_token, $final, $check_exists);
            }
        } elseif ((is_int($token) || strlen($token) > 0) && (! $check_exists || ! in_array($token, $this->{$command}))) {
            $this->{$command}[] = $token;
        }

        return $this;
    }

    public function initialized($set = null)
    {
        if (! is_null($set)) {
            $this->initialized = (bool) $set;
        }

        return $this->initialized;
    }

    public function ready($set = null)
    {
        if (! is_null($set)) {
            $this->ready = (bool) $set;
        }

        return $this->ready;
    }

    public function executed($set = null)
    {
        if (! is_null($set)) {
            $this->executed = (bool) $set;
        }

        return $this->executed;
    }

    public static function collect_output($collect = true)
    {
        $original = static::$collect_output;
        static::$collect_output = (bool) $collect;

        return $original;
    }

    /**
     * Pass arbitrary command to exec(). Obviously, be careful.
     *
     * @param $command array|string
     * @throws \InvalidArgumentException
     */
    protected function raw($command = [], $in_background = false)
    {
        if (! empty($command)) {
            $this->setRawCommand($command, $in_background);
        }

        // localize raw command, fallsback to the binary (could be null)
        $command = $this->command(! $this->ready());

        if ($command && $this->ready()) {
            $command = implode(' ', $command);

            // output redirection
            if (false === strpos($command, '>')) {
                if ($this->raw_command_background) {
                // execute the command in background
                    if (false === strpos($command, '&')) {
                        $command .= ' > /dev/null &';
                    }
                } else {
                    // redirect to stdout
                    if ((static::$collect_output && false !== strpos($command, 'tty')) || (! static::$collect_output) && false === strpos($command, 'tty')) {
                        $command = str_replace('tty', ' ', $command);
                        $command .= ' > `tty`';
                    }
                }
            }
            

            // debug($command, 'red');
            $this->output = [];
            exec($command, $this->output);

            if (isset($this->output[0])) {
                $this->pid = (int) $this->output[0];
            }
            $this->executed(true);
            $this->ready(false);

        } elseif ($this->ready()) {
            throw new \InvalidArgumentException(static::$exception_strings['no_input']);
        }

        return $this->getOutput();
    }

    /**
     * Run this command
     */
    public function run()
    {
        chdir(dirname(APPLICATION_PATH));

         if (! $this->executed()) {
             $this->raw();
         }

        return parent::run();
    }

    protected static function set_flags_before_arguments($bool = true)
    {
        static::$flags_before_arguments = (bool) $bool;
    }

    protected function authorizeSubcommand($subcommand)
    {
        $can = false;

        if (! is_null($subcommand) && $this->validateSubcommand($subcommand)) {
            $can = true;
        } else {
            throw new \InvalidArgumentException('Valid subcommands: '.implode(', ', $this->getValidSubcommands()), 1);
        }

        return $can;
    }

    public function getSubcommand($subcommand = null)
    {
        if (is_null($subcommand)) {
            if (isset($this->subcommand))
                return $this->subcommand;
        } else {
            if ($this->validateSubcommand($subcommand)) {
                return $this->subcommands[$subcommand];
            }
        }
    }

    protected function getValidSubcommands()
    {
        $commands = [];

        if (isset($this->subcommands)) {
            $commands = array_keys($this->subcommands);
        }

        return $commands;
    }

    protected function runSubcommand($subcommand)
    {
        if (false !== $this->authorizeSubcommand($subcommand)) {
            $this->setSubcommand($subcommand);
            if (is_callable($this->subcommands[$subcommand])) {
                $function = $this->subcommands[$subcommand];
                return $function($this);
            } else {
                return call_user_func_array([ $this, '_'.ltrim($subcommand, '_') ], []);
            }
        }
    }

    protected function setSubcommand($subcommand)
    {
        if ($this->validateSubcommand($subcommand)) {
            $this->subcommand = $subcommand;
        } else {
            throw static::getInvalidSubcommandException($subcommand);
        }

        return $this;
    }

    public function validateSubcommand($subcommand, $callable_or_method = null)
    {
        $valid = false;
        $valid_subcommands = $this->getValidSubcommands();

        if (is_null($callable_or_method) && in_array($subcommand, $valid_subcommands)) {
            $valid = true;
        } elseif (! is_null($callable_or_method)) {
            // callable is valid
            $valid = is_callable($callable_or_method);
            
            // invalidate $subcommand = '_something' to prevent "__something" from being invoked
            if (! $valid && substr($callable_or_method, 0, 1) !== '_') {
                // local _ prefixed method is valid
                $valid = method_exists($this, '_'.$callable_or_method);
            }
        }

        return $valid;
    }

    protected function registerSubcommand($subcommand, $callable_or_method = null)
    {
        if (is_null($callable_or_method))
            $callable_or_method = $subcommand;

        if ($this->validateSubcommand($subcommand, $callable_or_method)) {
            if (! isset($this->subcommands)) {
                $this->subcommands = [];
            }
            if (! array_key_exists($subcommand, $this->subcommands)) {
                $this->subcommands[$subcommand] = $callable_or_method;
            } else {
                throw static::getSubcommandRegisteredException($subcommand);
            }
        } else {
            throw static::getInvalidSubcommandException(
                $subcommand,
                sprintf("%s is not callable nor a local method", $callable_or_method)
            );
        }
    }

    private static function getInvalidSubcommandException($subcommand, ...$append)
    {
        $message = sprintf("%s: invalid sub-command", $subcommand);
        foreach ($append as $key => $value) {
            $message .= ', '.$value;
        }
        return new \InvalidArgumentException($message);
    }

    private static function getSubcommandRegisteredException($subcommand, ...$append)
    {
        $message = sprintf("Error: subcommand %s is already registered", $subcommand);
        foreach ($append as $key => $value) {
            $message .= ', '.$value;
        }
        return new \InvalidArgumentException($message);
    }

}
