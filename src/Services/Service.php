<?php
namespace Worklog\Services;

/**
 * Service
 */
class Service
{
    protected static $for = [];

    protected static $instances = [];


    /**
     * @param $class
     * @param null $callable
     */
    public static function register($class, $callable = null)
    {
        if (is_null($callable)) {
            if (method_exists($class, 'instance')) {
                $callable = function() use ($class) {
                    return $class::instance();
                };
            } elseif (method_exists($class, 'getInstance')) {
                $callable = function() use ($class) {
                    return $class::getInstance();
                };
            }
        }

        static::set_for($class, $callable);
    }

    /**
     * @param $class
     * @param array $constructor_args
     * @return mixed
     */
    public function make($class, $constructor_args = [])
    {
        if ($callable = static::get_for($class)) {
            if (is_callable($callable)) {
                $callable = $callable($constructor_args);
            }
            return $callable;
        }

        return new $class($constructor_args);
    }

    /**
     * @param $class
     * @param array $constructor_args
     * @return mixed
     */
    public static function instance($class, $constructor_args = [])
    {
        if (! isset(static::$instances[$class])) {
            if ($instance = static::make($class, $constructor_args)) {
                static::$instances[$class] = $instance;
            }
        }

        return static::$instances[$class];
    }


    private static function get_for($class)
    {
        return static::$for[$class];
    }

    private static function set_for($class, $callable)
    {
        if (! is_callable($callable)) {
            throw new \InvalidArgumentException('Setting a "for" member requires a callable.');
        }

        static::$for[$class] = $callable;
    }

    public static function __callStatic($name, $args)
    {
        $class = $args[0];
        if ($callable = static::get_for($class)) {
            return $callable();
        }
    }

}
