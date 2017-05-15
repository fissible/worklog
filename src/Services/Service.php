<?php
namespace Worklog\Services;

/**
 * Service
 */
class Service
{
    protected static $for = [];

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

    public function make($class)
    {
        if ($callable = static::get_for($class)) {
            return $callable();
        }
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

}
