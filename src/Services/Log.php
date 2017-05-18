<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 5/15/17
 * Time: 10:14 AM
 */

namespace Worklog\Services;

class Log
{

    /**
     * App Logger
     * @var
     */
    private static $instance;

    /**
     * Channel Logger
     * @var
     */
    private static $instances = [];


    /**
     * Return a configured Logger instance
     * @param null $name
     * @return mixed
     */
    public static function instance($name = null) {
        if (is_null($name)) {
            if (! isset(static::$instance)) {
                static::$instance = Service::instance(get_class());
            }

            return static::$instance;
        } else {
            if (! array_key_exists($name, static::$instances)) {
                if ($instance = Service::instance(get_class(), $name)) {
                    static::$instances[$name] = $instance;
                } elseif ($AppLogger = static::instance()) {
                    static::$instances[$name] = $AppLogger->withName($name);
                }
            }

            return static::$instances[$name];
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments) {
        $instance = static::instance();
        if (isset(static::$instance)) {
            return call_user_func_array([ $instance, $name ], $arguments);
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments) {
        $instance = static::instance();
        if (isset(static::$instance)) {
            return call_user_func_array([ $instance, $name ], $arguments);
        }
    }
}