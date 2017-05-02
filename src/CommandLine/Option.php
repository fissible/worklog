<?php
namespace Worklog\CommandLine;

class Option
{
    private $name;

    private $command;

    private $Options;

    private $value;

    private $arguments = [];

    private $shortopts = [];

    private $longopts = [];

    private $valid_options = [];

    private $options = [];

    private $config = [];

    private $parsed = false;

    private $reqs = [];

    private static $error_messages = [
        'ILLEGAL_OPTION' => 'Illegal option -%s',
        'ILLEGAL_NAME' => 'Option name "%s" already exists on command %s',
        'INVALID_NAME' => 'Option name must be a non-empty string'
    ];

    const CONFIG_KEY_REQUIRED = 'req';

    const CONFIG_KEY_DESCRIPTION = 'description';

    public function __construct(Options $Options, $name, $config = [])
    {
        $this->Options = $Options;
        $this->set_name($name);
        $this->configure($config);
    }

    public function name()
    {
        return $this->name;
    }

    public function value()
    {
        return $this->value;
    }

    /**
     * Return true if this is a flag option
     * @param  string  $option The option name
     * @return boolean
     */
    public function is_flag()
    {
        return is_null($this->config(self::CONFIG_KEY_REQUIRED));
    }

    public function is_long()
    {
        return ! $this->is_short();
    }

    public function is_short()
    {
        return strlen($this->name) == 1;
    }

    protected function set_name($name)
    {
        if (! is_string($name) || strlen($name) < 1) {
            throw new \InvalidArgumentException(static::$error_messages['INVALID_NAME']);
        }
        // @todo: move to Options
        foreach ($this->Options as $Option) {
            if ($Option->name() == $name) {
                throw new \Exception(sprintf(static::$error_messages['ILLEGAL_NAME'], $name, $this->Options->command_name()));
            }
        }
        $this->name = $name;

        return $this;
    }

    public function set_value($value)
    {
        $this->value = $value;
    }

    protected function configure($config = [])
    {
        foreach ($config as $key => $value) {
            $this->config($key, $value);
        }
    }

    public function get_option_string()
    {
        return $this->name . $this->get_requirement_string();
    }

    public function get_requirement_string()
    {
        $is_required = $this->config(self::CONFIG_KEY_REQUIRED);

        return (!is_null($is_required) ? ($is_required ? ':' : '::') : '');
    }

    /**
     * Get/set a config value to the configuration array.
     * @param  string                        $key     [description]
     * @param  string                        $value   [description]
     * @param  string                        $default [description]
     * @param mixed
     * @return $this|array|mixed|null|string
     */
    protected function config($key = null, $value = null, $default = null)
    {
        $config = $this->config;
        if (! is_null($key)) {
            if (is_null($value)) {
                // GET
                $config = $default;
                if (array_key_exists($key, $this->config)) {
                    $config = $this->config[$key];
                }
            } else {
                // SET
                if (array_key_exists($key, $this->config)) {
                    if (is_array($value)) {
                        $this->config[$key] = array_merge($this->config[$key], $value);
                    } elseif (false === $value) {
                        unset($this->config[$key]);
                    } else {
                        $this->config[$key] .= $value;
                    }
                } else {
                    $this->config[$key] = $value;
                }

                return $this;
            }
        }

        return $config;
    }

    public function as_cli_string()
    {
        return ($this->is_long() ? '--' : '-').$this->name.(! $this->is_flag() && $this->value ? ' '.(is_numeric($this->value) ? $this->value : '"'.$this->value.'"') : '');
    }

    public function __toString()
    {
        return $this->value;
    }

}
