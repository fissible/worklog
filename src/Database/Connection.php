<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/11/17
 * Time: 2:22 PM
 */

namespace Worklog\Database;

use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;

class Connection
{
    protected $capsule;

    protected $connection;

    protected static $_connection;

    protected static $instance;


    public function __construct($config = [])
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config, 'default');
        $this->capsule->setEventDispatcher(new Dispatcher(new Container));
        $this->capsule->bootEloquent();
        $this->capsule->setAsGlobal();
        $this->connection = $this->capsule->getConnection('default');

        if (! isset(static::$_connection)) {
            static::set($this->connection);
        }
    }

    public static function getInstance($config = [])
    {
        if (! isset(static::$instance)) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    public static function get()
    {
        return static::$_connection;
    }

    public static function set(\Illuminate\Database\Connection $Connection)
    {
        static::$_connection = $Connection;
    }

    /**
     * Get the primary keys for a table.
     * @param  [type] $table [description]
     * @return string The primary key field names
     */
    public function getPrimaryKey($table)
    {
        if (null === ($primary = $this->getModel($table)->getKeyName())) {
            $primary = 'id';
        }

        return $primary;
    }

    public function getSchema($table)
    {
        $schema = [];
        if ($Model = $this->getModel($table)) {
            $schema = $Model::fields();
        }

        return $schema;
    }

    /**
     * @param $table
     * @return \Worklog\Models\Model
     */
    public function getModel($table)
    {
        try {
            $model_name = '\\Worklog\\Models\\'.ucfirst($table);
            return new $model_name;
        } catch (\Exception $e) {
            $Model = new \Worklog\Models\Model;
            $Model->setTable($table);

            return $Model;
        }
    }

    /**
     * Get a fluent query builder instance.
     *
     * @param  string  $table
     * @return \Illuminate\Database\Query\Builder
     */
    public static function table($table)
    {
        return static::$instance->connection->table($table);
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->connection, $name)) {
            return call_user_func_array([ $this->connection, $name ], $arguments);
        }
        if (method_exists($this->capsule, $name)) {
            return call_user_func_array([ $this->capsule, $name ], $arguments);
        }
    }
}
