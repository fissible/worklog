<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 5/15/17
 * Time: 10:14 AM
 */

namespace Worklog\Services;

use Monolog\Logger;

class Log
{

    /**
     * @var Logger
     */
    private static $instance;

    /**
     * Return a configured Logger instance
     * @return Logger
     */
    public static function instance() {
        if (! isset(static::$instance)) {
            if ($Log = Service::make(get_class())) {
                static::$instance = $Log;
            } else {
                static::$instance = static::Monolog();
            }
        }

        return static::$instance;
    }

    /**
     * Return a Monolog\Logger instance
     * @return Logger
     */
    public static function Monolog()
    {
        return new \Monolog\Logger(APP_LOGGER_NAME);
    }

    public function __call($name, $arguments) {
        $instance = static::instance();
        if (isset(static::$instance)) {
            return call_user_func_array([ $instance, $name ], $arguments);
        }
    }

    public static function __callStatic($name, $arguments) {
        $instance = static::instance();
        if (isset(static::$instance)) {
            return call_user_func_array([ $instance, $name ], $arguments);
        }
    }
}