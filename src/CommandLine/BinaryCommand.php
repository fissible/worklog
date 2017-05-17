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
                        $command .= ' > `tty` 2>&1';
                    }
                }
            }
            

//            debug($command, 'red');
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

}
