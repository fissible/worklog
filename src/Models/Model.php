<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/10/17
 * Time: 9:37 AM
 */

namespace Worklog\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Worklog\Str;

class Model extends Eloquent
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected static $fields = [
        /*
        'field_name' => [
            'type' => 'string', // or 'integer'
            'auto_increment' => true
            'default' => null,
            'prompt' => 'What is the Issue key?',
            'required' => true
        ]
        */
    ];

    protected static $display_headers = [/* 'id' => 'ID' */];


    public function classname()
    {
        return get_class($this);
    }

    public function display_headers()
    {
        return static::$display_headers;
    }

    public function hasAttribute($attr)
    {
        return array_key_exists($attr, $this->attributes);
    }

    /**
     * @param $field
     * @return mixed
     */
    public static function field($field)
    {
        if (!is_string($field)) {
            throw new \InvalidArgumentException('Model::field("") requires a string as the first parameter');
        }
        $fields = static::fields();
        if (array_key_exists($field, $fields)) {
            return $fields[$field];
        }
    }

    /**
     * @return array
     */
    public static function fields()
    {
        return static::$fields;
    }

    /**
     * Return true if all required fields have values
     * @param  bool  $get_config
     * @return array
     */
    public static function required_fields($get_config = false)
    {
        $fields = [];
        foreach (static::fields() as $field => $config) {
            if (isset($config['required']) && $config['required']) {
                if ($get_config) {
                    $fields[$field] = $config;
                } else {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * @param $field
     * @param  null   $default
     * @return string
     */
    public function promptForAttribute($field, $default = null)
    {
        $prompt = sprintf('What is the %s?', Str::snake($field, ' '));

        if (is_null($default)) {
            $default = $this->defaultValue($field);
        }

        if ($config = static::field($field)) {
            if (array_key_exists('prompt', $config)) {
                $prompt = $config['prompt'];
                if ($config['required']) {
                    $prompt .= ' (required)';
                }
                if ($default) {
                    $prompt .= ' [' . $default . ']';
                }
                $prompt .= ': ';
            }
        }

        return $prompt;
    }

    public static function required($field)
    {
        return in_array($field, static::required_fields());
    }

    public function satisfied($field = null)
    {
        $satisfied = false;
        if (is_null($field)) {
            $satisfied = $this->valid();
        } elseif (! in_array($field, static::required_fields()) || ($this->hasAttribute($field) && strlen($this->attributes[$field]) > 0)) {
            $satisfied = true;
        }

        return $satisfied;
    }

    /**
     * Return true if all required fields have values
     * @return bool
     */
    public function valid()
    {
        $valid = true;
        foreach (static::required_fields() as $field) {
            if (! $this->hasAttribute($field) || strlen($this->attributes[$field]) < 1) {
                $valid = false;
                break;
            }
        }

        return $valid;
    }

    public function defaultValue($field, $default = null)
    {
        if (is_null($default) && $this->exists) {
            $default = $this->{$field};
        }

        if (is_null($default) && $config = static::field($field)) {
            if (array_key_exists('default', $config)) {
                $default = $config['default'];
                if (substr($default, 0, 1) == '*') {
                    $class = $this;
                    $method = substr($default, 1);
                    $sub_method = null;
                    $arguments = null;
                    $sub_arguments = null;

                    if (false !== strpos($method, '::')) {
                        $parts = explode('::', $method);
                        $class = '\\Worklog\\'.$parts[0];
                        $method = $parts[1];
                    }

                    if (false !== strpos($method, '->')) {
                        $parts = explode('->', $method);
                        $class = '\\'.$parts[0];
                        $class = new $class;
                        $method = $parts[1];
                    }

                    if (false !== strpos($method, '(') && false !== strpos($method, ')')) {
                        list($method, $arguments) = Str::parseFunctionArgs($method);
                    }

                    if (! is_null($arguments)) {
                        $default = call_user_func_array([ $class, $method ], $arguments);
                    } else {
                        $default = call_user_func([ $class, $method ]);
                    }
                }
            }
        }

        return $default;
    }
}
