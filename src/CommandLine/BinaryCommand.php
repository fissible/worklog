<?php

namespace Worklog\CommandLine;

/**
 * Abstract binary command
 */

abstract class BinaryCommand extends Command
{

    public static $menu = false;

    protected $binary;

    protected $config_file;

    protected $raw_command;

    protected $raw_command_background = false;


    public function __construct($command = [], $in_background = false) {
        parent::__construct();
        $this->setRawCommand($command, $in_background);
    }

    public function setRawCommand($command = [], $in_background = false) {
        if (! empty($command)) {
            $this->raw_command = $command;
            $this->backgroundRawCommand($in_background);
        }
    }

    public function backgroundRawCommand($bool = true) {
        $this->raw_command_background = (bool) $bool;
    }

    public function command($flags = [], $arguments = []) {
        $command = (array) $this->raw_command;

        if ($this->binary && ! in_array($this->binary, $command)) {
            array_unshift($command, $this->binary);
        }

        if (is_array($command)) {
            if (false !== $flags) {
                $flags = $flags ? $flags : $this->flags();
            }
            if (false !== $arguments) {
                $arguments = $arguments ? $falgs : $this->arguments();
            }

            // Pass in "--flags" and "arguments"
            if (static::$flags_before_arguments) {
                if ($flags) $command = array_merge($command, $flags);
                if ($arguments) $command = array_merge($command, $arguments);
            } else {
                if ($arguments) $command = array_merge($command, $arguments);
                if ($flags) $command = array_merge($command, $flags);
            }
        }

        return $command;
    }

    /**
     * Pass arbitrary command to exec(). Obviously, be careful.
     * 
     * @param $command array|string
     * @throws \InvalidArgumentException
     */
    protected function raw($command = [], $in_background = false) {
        if (! empty($command) && ! isset($this->raw_command) || empty($this->raw_command)) {
            $this->setRawCommand($command, $in_background);
        }
        
        // localize raw command, fallsback to the binary (could be null)
        $command = $this->command();

        if ($command) {
            $command = implode(' ', $command);

            if ($this->raw_command_background) {
            // execute the command in background
                if (false === strpos($command, '&')) {
                    $command .= ' > /dev/null &';
                }
            } else {
                // redirect to stdout
                if (false === strpos($command, 'tty')) {
                    $command .= ' > `tty`';
                }
            }
            
            $this->output = [];
            exec($command, $this->output);

            if (isset($this->output[0])) {
                $this->pid = (int)$this->output[0];
            }

        } else {
            throw new \InvalidArgumentException(static::$exception_strings['no_input']);
        }

        return $this->getOutput();
    }

    /**
     * Run this command
     */
    public function run() {
        parent::run();

        chdir(dirname(APPLICATION_PATH));

        // if ($this->command()) {
        //     $this->raw();
        // }

        return $this->getOutput();
    }

}