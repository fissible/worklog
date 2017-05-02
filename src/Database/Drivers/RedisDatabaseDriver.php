<?php
namespace Worklog\Database\Drivers;

use Predis\Client;
use Carbon\Carbon;
use Worklog\Database\Driver;

/**
 * class
 */
class RedisDatabaseDriver extends Driver
{
    /**
     * @var Predis/Client
     */
    private $db;

    private $lastInsertPKeys = [];

    private $result;

    private $count;

    /**
     * Database index
     * @var integer
     */
    private static $config = [ 'database' => 1 ];

    /**
     * A Predis instance for the schema database
     * @var Predis\Client
     */
    private static $schema_db;

    /**
     * The configured database's schema
     * @var stdClass
     */
    private static $schema;

    private static $actions = [ 'GET', 'SELECT', 'INSERT', 'UPDATE', 'DELETE' ];

    private static $entity_class = '\stdClass';

    const ERROR_INVALID_SOURCE = 'Invalid query source';


    public function __construct($config = [], $schema = '')
    {
        if (empty($config)) {
            $config = $this->config();
        }
        if (count($config) > 0) {
            $this->connect($config);
        } else {
            throw new \InvalidArgumentException('Invalid Redis configuration', 1);
        }

        $this->set_schema($schema);
    }

    public static function set_config(array $config)
    {
        if (! is_array($config)) {
            throw new \InvalidArgumentException('Redis configuration must be an array');
        }
        if (isset($config['database']) && ! is_numeric($config['database'])) {
            throw new \InvalidArgumentException('Database argument must be an integer');
        }
        static::$config = $config;
    }

    protected function set_schema($schema = null)
    {
        if ($schema) {
            if (! $this->schema = json_decode($schema)) {
                throw new \InvalidArgumentException('Invalid schema format: must be json');
            }
        }
    }

    /**
     * Return the schema stdClass object.
     * @return object [description]
     */
    public function schema()
    {
        return $this->schema;
    }

    /**
     * Return a Predis/Client for the schema database (0).
     * @return object [description]
     */
    public function schema_db()
    {
        if (! isset($this->schema_db)) {
            $schema_db_schema = null;
            $config['database'] = 0;
            if (isset($config['schema_path']) && file_exists($config['schema_path'])) {
                $schema_db_schema = json_decode(file_get_contents($config['schema_path']));
            }
            $this->schema_db = new static($config, $schema_db_schema);
        }

        return $this->schema_db;
    }

    public function config()
    {
        return $this->config;
    }

    public function database_index()
    {
        $config = $this->config();

        return $config['database'];
    }

    public function init()
    {
        return false; // not implemented
        $schema = [
            'table' => '[
                {"id":1,"name":"table","seq_next":3,"created":"NOW()","updated":"NOW()"},
                {"id":2,"name":"field","seq_next":8,"created":"NOW()","updated":"NOW()"}
            ]',
            'field' => '[
                {"id": 1,"name": "id","type": "int","table_id": 1,"created":"NOW()","updated":"NOW()"},
                {"id": 2,"name": "name","type": "str","table_id": 1,"created":"NOW()","updated":"NOW()"},
                {"id": 3,"name": "seq_next","type": "int","table_id": 1,"created":"NOW()","updated":"NOW()"},
                {"id": 4,"name": "created","type": "datetime","table_id": 1,"created":"NOW()","updated":"NOW()"},
                {"id": 5,"name": "updated","type": "datetime","table_id": 1,"created":"NOW()","updated":"NOW()"},
                {"id": 6,"name": "id","type": "int","table_id": 2,"created":"NOW()","updated":"NOW()"},
                {"id": 7,"name": "name","type": "str","table_id": 2,"created":"NOW()","updated":"NOW()"},
                {"id": 8,"name": "type","type": "str","table_id": 2,"created":"NOW()","updated":"NOW()"},
                {"id": 9,"name": "table_id","type": "int","table_id": 2,"created":"NOW()","updated":"NOW()"},
                {"id": 10,"name": "created","type": "datetime","table_id": 2,"created":"NOW()","updated":"NOW()"},
                {"id": 11,"name": "updated","type": "datetime","table_id": 2,"created":"NOW()","updated":"NOW()"}
            ]'
        ];

        $enc_data = json_encode($data);
        var_export($enc_data);

        return json_decode('[
            {
                "id": 1,
                "name": "table",
                "seq_next": 3
            },
            {
                "id": 2,
                "name": "field",
                "seq_next": 8
            }
        ]');
    }

    /**
     * Connect to the database.
     * @throws \Exception
     * @param str[] config
     * @return $obj
     */
    public function connect($config)
    {
        // $parameters, $options
        $parameters = [
            'scheme' => null,
            'host' => null,
            'port' => null,
            'path' => null,
            'ssl' => null
        ];
        $options = [
            'profile' => null,
            'prefix' => null,
            'exceptions' => null,
            'connections' => null,
            'cluster' => null,
            'replication' => null,
            'aggregate' => null,
            'parameters' => null
        ];

        foreach ($parameters as $key => $value) {
            if (array_key_exists($key, $config)) {
                $parameters[$key] = $config[$key];
            } elseif (! is_null($value)) {
                $parameters[$key] = $value;
            } else {
                unset($parameters[$key]);
            }
        }
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $config)) {
                $options[$key] = $config[$key];
            } elseif (! is_null($value)) {
                $options[$key] = $value;
            } else {
                unset($options[$key]);
            }
        }

        if (array_key_exists('database', $config)) {
            if (! is_numeric($config['database']) || ! is_int($config['database'])) {
                throw new \Exception('Configuration key \'database\' must be an integer');
            } elseif ($config['database'] < 0) {
                throw new \InvalidArgumentException('Configuration key \'database\' must be an integer >= 0');
            }
            if (! array_key_exists('parameters', $options)) {
                $options['parameters'] = [];
            }
            $options['parameters']['database'] = $config['database'];
        } else {
            throw new \Exception('Configuration must include a \'database\' key: value');
        }

        if ($this->db = new Client($parameters, $options)) {
            $this->db->connect();

            return $this->db->isConnected();
        }

        throw new \Exception(sprintf('Unable to connect to %s using config: %s', var_export($config, true)));
    }

    public function begin_transaction()
    {
        return $this->db->multi();
    }

    public function commit_transaction()
    {
        return $this->db->exec();
    }

    public function rollback()
    {
        return $this->db->discard();
    }

    public function table_schema($table)
    {
        if ($schema = $this->schema()->get($table.'.schema')) { // key: article.schema

            return json_decode($schema);
        }
    }

    public function select($table, $where = [], $limit = 0)
    {
        $this->get($table);

        if (isset($this->result)) {
            if (! empty($where)) {
                foreach ($where as $wfield => $wvalue) {
                    foreach ($this->result as $id => $Record) {
                        if (! $this->Record_matches($Record, $wfield, $wvalue)) {
                            unset($this->result[$id]);
                        }
                    }
                }
            }
            $this->count = count($this->result);
        } else {
            $this->count = 0;
        }

        return $this;
    }

    /**
     * Check if the Record field matches the condition string
     * @param  object  $Record    The Record instance
     * @param  string  $field     The name of the field
     * @param  mixed   $condition The value or condition string to match, eg. ">=5"
     * @return boolean
     */
    protected function Record_matches($Record, $field, $condition)
    {
        $matches = false;
        $date_compare = 'datetime';
        $operators = [ '<=', '>=', '<>', '!=', '==', '=', '<', '>', 'LIKE', 'IN' ];
        $operator = '=';
        $regex = false;

        foreach ($operators as $key => $op) {
            if (is_scalar($condition) && ! is_numeric($condition)) {
                if ($op === strtoupper(substr($condition, 0, strlen($op)))) {
                    $operator = $op;
                    $condition = trim(substr_replace($condition, '', 0, strlen($op)));
                    break;
                } elseif ($op === strtoupper(substr($field, (strlen($op) * -1)))) {
                    $operator = $op;
                    $field = trim(substr_replace($field, '', (strlen($op) * -1)));
                    break;
                }
            }
        }

        if (property_exists($Record, $field)) {
            if (is_scalar($condition)) {
                if (false !== stristr($field, 'date') && false !== strtotime($condition)) {
                    if (strlen($condition) <= 10) {
                        $date_compare = 'date';
                    }
                    $condition = Carbon::parse($condition);
                    $Date = Carbon::parse($Record->{$field});

                } elseif ($operator == 'LIKE' && (false !== strpos($condition, '*') || false !== strpos($condition, '%'))) {
                    // eg. "How to *" -> "/^How to (.+)$/"
                    if (false !== strpos($condition, '%')) {
                        $condition = str_replace('%', '*', $condition);
                    }
                    $condition = preg_quote($condition, '/');
                    $prefix = (substr($condition, 0, 2) === '\*' ? '' : '^');
                    $suffix = (substr($condition, -2) === '\*' ? '' : '$');
                    $regex = str_replace('\*', '(.+)', $condition);
                    $regex = str_replace('%', '\.', $regex);
                    $regex = $prefix.$regex.$suffix;
                }
            }

            switch ($operator) {
                case '<':
                    if ($condition instanceof Carbon) {
                        $matches = $Date->lt($condition);
                    } else {
                        $matches = ($Record->{$field} < $condition);
                    }
                    break;
                case '>':
                    if ($condition instanceof Carbon) {
                        $matches = $Date->gt($condition);
                    } else {
                        $matches = ($Record->{$field} > $condition);
                    }
                    break;
                case '<=':
                    if ($condition instanceof Carbon) {
                        $matches = $Date->lte($condition);
                    } else {
                        $matches = ($Record->{$field} <= $condition);
                    }
                    break;
                case '>=':
                    if ($condition instanceof Carbon) {
                        $matches = $Date->gte($condition);
                    } else {
                        $matches = ($Record->{$field} >= $condition);
                    }
                    break;
                case '<>':
                case '!=':
                    if ($condition instanceof Carbon) {
                        $matches = $Date->ne($condition);
                    } else {
                        $matches = ($Record->{$field} != $condition);
                    }
                    break;
                case 'LIKE':
                    if ($regex) {
                        $matches = preg_match('/'.$regex.'/', $Record->{$field});
                    } else {
                        $matches = (false !== strpos($Record->{$field}, $condition));
                    }
                    break;
                case 'IN':
                    $matches = in_array($Record->{$field}, $condition);
                case '==':
                default: # =
                    if ($condition instanceof Carbon) {
                        if ($date_compare == 'datetime') {
                            $matches = $Date->eq($condition);
                        } else {
                            $matches = $Date->isSameDay($condition);
                        }
                    } else {
                        $matches = ($Record->{$field} == $condition);
                    }
                    break;
            }
        }

        return $matches;
    }

    public function first()
    {
        if (isset($this->result)) {
            return array_shift($this->result);
        }
    }

    public function result()
    {
        $result = false;
        if (isset($this->result)) {
            $result = $this->result;
        }

        return $result;
    }

    public function count()
    {
        $count = false;
        if (isset($this->count)) {
            $count = $this->count;
        }

        return $count;
    }

    /**
     * Execute SQL statement
     */
    public function query()
    {
        $args = func_get_args();
        if (isset($args[0])) {
            if (static::sql_action($sql)) {
                if (is_string($sql)) {
                    $sql = $this->parse_sql($sql);
                }
                if (is_array($sql)) {
                    /*
                    $this->db->set("key:$i", str_pad($i, 4, '0', 0));
                    $this->db->get("key:$i");
                     */
                    switch (true) {
                        case (isset($sql['SELECT'])):
                            //              get_key($table, $pkey = null, $column = null)

                            $this->schema()->get($sql['FROM'].'.schema'); // key: article.schema
                            $keys = static::get_key($sql['FROM'], [17,21], ['id', 'title']);
                            foreach ($keys as $key) {
                                # code...
                            }
                            break;
                        case (isset($sql['INSERT'])):
                            #
                            break;
                        case (isset($sql['UPDATE'])):
                            #
                            break;
                        case (isset($sql['DELETE'])):
                            #
                            break;
                    }
                    /*
                    array (
                      'SELECT' => '*',
                      'FROM' => 'table',
                      'WHERE' => 'car=1',
                      'ORDER' => 'tires DESC',
                    )
                    array (
                      'SELECT' => '*',
                      'FROM' => 'TEST',
                      'WHERE' => 'color=\'green\'',
                      'LIMIT' => '2',
                    )
                    array (
                      'SELECT' => 'id',
                      'FROM' => 'schema:table',
                      'WHERE' => 'database=1 AND name = \'column\'',
                    )
                    array (
                      'SELECT' => '*',
                      'FROM' => 'jump',
                      'WHERE' => '(color = \'red\' OR trim = \'LX\') AND wheels > 3 AND wheels < 5',
                    )
                    array (
                      'INSERT' => true,
                      'INTO' => 'table',
                      'SET' => 'database=0, name=\'column\'',
                    )
                    array (
                      'UPDATE' => 'table',
                      'SET' => 'name=\'key\'',
                      'WHERE' => 'name=\'index\'',
                    )
                    array (
                      'DELETE' => true,
                      'FROM' => 'cars',
                      'WHERE' => 'id=17',
                      'LIMIT' => '1',
                    )
                     */
                } else {
                    throw new \Exception('sql_to_command requires a SQL string or parsed SQL command array');
                }
            } else {
                return $this->db->rawCommand(func_get_args());
            }
        } else {
            throw new \InvalidArgumentException('query() requires at least one argument');
        }
    }

    protected static function sql_action($sql)
    {
        $action = null;
        foreach (static::$actions as $key => $_action) {
            if (strtoupper(substr($sql, 0, strlen($_action))) == $_action) {
                $action = $_action;
            }
        }

        return $action;
    }

    /**
     * Give a table plus an optional primary key value(s) and optional column(s),
     * return matching key(s).
     * @param  string $table  The table
     * @param  mixed  $pkey   The primary key (or keys)
     * @param  mixed  $column The column(s)
     * @return mixed  The key string (or array of matched key strings)
     */
    public static function get_key($table, $pkey = null, $column = null)
    {
        $key = $table;
        $keys = [];
        if (! is_null($pkey)) {
            if (is_string($pkey) && false !== strpos($pkey, ',')) {
                $pkey = explode(',', $pkey);
            }
            if (is_array($pkey)) {
                foreach ($pkey as $_pkey) {
                    $keys[] = $key.'.'.$_pkey;
                }
            } else {
                $key .= '.'.$pkey;
            }
        } else {
            $key .= '.*'; // wildcard, not regex
        }
        if (! is_null($column)) {
            if (is_string($column) && false !== strpos($column, ',')) {
                $column = explode(',', $column);
            }
            if (is_array($column)) {
                if (! empty($keys)) {
                    $_keys = [];
                    foreach ($keys as $key_key => $_key) {
                        foreach ($column as $i => $_column) {
                            $_keys[] = $_key.'.'.$_column;
                        }
                    }
                    $keys = $_keys;
                } else {
                    foreach ($column as $i => $_column) {
                        $keys[] = $key.'.'.$_column;
                    }
                }
            } else {
                if (! empty($keys)) {
                    foreach ($keys as $key_key => $value) {
                        $keys[$key_key] .= '.'.$column;
                    }
                } else {
                    $key .= '.'.$column;
                }
            }
        } else {
            $key .= '.*'; // wildcard, not regex
        }

        if (! empty($keys)) {
            return $keys;
        }

        return $key;
    }

    /**
     * Override Predis::get() to allow for multi-key matching via splat (*) char.
     * @param  string       $key The key or key pattern to search for
     * @return string|array
     */
    public function get(/**/)
    {
        $tables = [];
        $table_count = 0;
        $raw_data = $records = [];
        $args = func_get_args();
        $num_args = func_num_args();

        unset($this->result);
        $this->count = 0;

        // If only one argument, pad with null if not all 3 slugs are present, ie. 'table.2'
        if ($num_args == 1 && ! is_array($args[0])) {
            $count = substr_count($args[0], '.');
            if ($count < 3) {
                $args = explode('.', $args[0]);
                $args[] = null;
                if ($count < 2) {
                    $args[] = null;
                }
            }
        }

        $num_args = count($args);

        if ($num_args == 1) {
            $key = $args[0];
        } else {
            $key = call_user_func_array([$this, 'get_key'], $args);
        }

        if (is_array($key)) {
            $_raw_data = [];
            foreach ($key as $_key) {
                if ($_raw_data = $this->get($_key)) {
                    if (is_array($_raw_data)) {
                        foreach ($_raw_data as $pkey => $record) {
                            foreach ($record as $field => $value) {
                                $raw_data[$pkey][$field] = $value;
                            }
                        }
                    } else {
                        if (is_object($_raw_data) && property_exists($_raw_data, 'id')) {
                            $raw_data[$_raw_data->id] = $_raw_data;
                        } else {
                            $raw_data[] = $_raw_data;
                        }
                    }
                }
            }

            return $raw_data;
        } elseif (false !== strpos($key, '*')) {
            // eg. "article.*.title" -> "/^article\.(.+)\.title$/"
            $key = str_replace('.', '%', $key);
            $key = preg_quote($key, '/');
            $prefix = (substr($key, 0, 2) === '\*' ? '' : '^');
            $suffix = (substr($key, -2) === '\*' ? '' : '$');
            $regex = str_replace('\*', '(.+)', $key);
            $regex = str_replace('%', '\.', $regex);
            $regex = $prefix.$regex.$suffix;
            $raw_data = $data = [];
            foreach ($this->keys('*') as $kkey => $_key) {
                if (preg_match('/'.$regex.'/', $_key)) {
                    // Extract the table, primary key, and field name
                    if ($data = $this->entity_array($_key)) {
                        $raw_data[] = $data;
                    }
                }
            }
        } else {
            if ($data = $this->entity_array($key)) {
                $raw_data[] = $data;
            }
        }

        // translate raw data into array of stdClass objects representing entities/records
        if (is_array($raw_data) && isset($raw_data[0]) && is_array($raw_data[0])) {
            foreach ($raw_data as $key => $_data) {
                if (! in_array($_data['table'], $tables)) {
                    $tables[] = $_data['table'];
                }
            }
            $table_count = count($tables);

            // sort data
            foreach ($raw_data as $key => $_data) {
                $table = $_data['table'];
                $pkey = $_data['pkey'];
                $field = $_data['field'];

                if ($field == 'id') continue;

                if ($table_count > 1) {
                    if (! isset($records[$table])) {
                        $records[$table] = [];
                    }
                    if (! isset($records[$table][$pkey])) {
                        $records[$table][$pkey] = [ 'id' => $pkey ];
                    }
                    $records[$table][$pkey][$field] = $_data['value'];
                } else {
                    if (! isset($records[$pkey])) {
                        $records[$pkey] = [ 'id' => $pkey ];
                    }
                    $records[$pkey][$field] = $_data['value'];
                }
            }

            // convert into object instances
            if ($table_count > 1) {
                foreach ($records as $table => $_records) {
                    foreach ($_records as $pkey => $record) {
                        $records[$table][$pkey] = static::entity($record);
                    }
                }
            } else {
                foreach ($records as $key => $record) {
                    $records[$key] = static::entity($record);
                    if ($key != $record['id']) {
                        print 'ERROR: key/id mismatch: '."\n";
                    }
                }
            }
        } else {
            $records = $raw_data;
        }

        $this->result = $records;
        $this->count = count($this->result);

        return $this->result;
    }

    /**
     * Set a key/value
     */
    public function set(/**/)
    {
        $args = func_get_args();

        if (func_num_args() == 2 && is_string($args[0])) {
            return call_user_func_array([ $this->db, 'set' ], $args);
        } else {
            $value = array_pop($args);
            $key = call_user_func_array([ $this, 'get_key' ], $args);

            return $this->set($key, $value);
        }
    }

    /**
     * Get the next primary key value for the specified table
     * @todo : query schema for pk value
     * @param  [type] $table [description]
     * @return [type] [description]
     */
    public function next_primary_key($table)
    {
        $next = 1;
        if ($keys = $this->get($table, '*'/*, 'id'*/)) {
            foreach ($keys as $key => $record) {
                if ((int) $record->id >= $next) {
                    $next = $record->id + 1;
                }
            }
        }

        return $next;
    }

    /**
     * Insert a row into the datastore
     * @param  [type] $table [description]
     * @param  [type] $data  [description]
     * @return [type] [description]
     */
    public function insert($table, $data)
    {
        $next_primary_key = $this->next_primary_key($table);
        $id = false;

        if (is_array($data) || $data instanceof \stdClass) {
            if ((is_array($data) && isset($data[0])) && (is_array($data[0]) || $data[0] instanceof \stdClass)) {
                $results = [];
                foreach ($data as $key => $record) {
                    $results[] = $this->insert($table, $record);
                }

                return $results;
            } else {
                if ($data instanceof \stdClass) {
                    if (! property_exists($data, 'id')) {
                        $data->id = $next_primary_key;
                    } elseif ((int) $data->id !== $next_primary_key) {
                        throw new \InvalidArgumentException('Invalid primary key supplied');
                    }
                } else {
                    if (! isset($data['id'])) {
                        $data['id'] = $next_primary_key;
                    } elseif ((int) $data['id'] !== $next_primary_key) {
                        throw new \InvalidArgumentException('Invalid primary key supplied');
                    }
                }
                foreach ($data as $field => $value) {
                    if ($field !== 'id') {
                        $this->set($table.'.'.$next_primary_key.'.'.$field, $value);
                    } else {
                        $id = $value;
                    }
                }
                $this->lastInsertPKeys[] = $next_primary_key;
            }
        } else {
            throw new \InvalidArgumentException('Must supply an array of data as the second argument');
        }

        return $id;
    }

    /**
     * Update a record in the datastore
     * @param $table
     * @param $data
     * @return int        [type]        [description]
     * @throws \Exception
     * @internal param $ [type] $table [description]
     * @internal param $ [type] $data  [description]
     */
    public function update($table, $data)
    {
        $count = 0;
        if (is_array($data) || $data instanceof \stdClass) {
            if (is_array($data) && isset($data[0]) && (is_array($data[0]) || $data[0] instanceof \stdClass)) {
                foreach ($data as $key => $record) {
                    $count += $this->update($table, $record);
                }
            } else {
                if ($data instanceof \stdClass) {
                    if (! property_exists($data, 'id')) {
                        throw new \Exception('Cannot update a record without a primary key');
                    }
                    $primary_key = $data->id;
                } else {
                    if (! isset($data['id'])) {
                        throw new \Exception('Cannot update a record without a primary key');
                    }
                    $primary_key = $data['id'];
                }

                foreach ($data as $field => $value) {
                    if ($field !== 'id') {
                        $this->set($table.'.'.$primary_key.'.'.$field, $value);
                    }
                }
                $count++;
            }
        } else {
            throw new \InvalidArgumentException('Must supply an array of data as the second argument');
        }

        return $count;
    }

    /**
     * Get the ID of the last inserted record.
     * @return int The last insert ID ('a/b' in case of multi-field primary key)
     */
    public function insert_id()
    {
        return join('/', $this->lastInsertPKeys);
    }

    public function delete($table, $where, $limit = 0)
    {
        $keys = $_keys = $records = [];
        $id = $deleted = 0;
        $records = $this->select($table, $where, $limit)->result();

        if ($records) {
            foreach ($records as $pkey => $record) {
                $id = $record->id;
                foreach ($record as $field => $value) {
                    $keys[] = $table.'.'.$id.'.'.$field;
                }
                if (! in_array($table.'.'.$id.'.id', $keys)) {
                    $keys[] = $table.'.'.$id.'.id';
                }
                $deleted++;
            }
        }

        if ($this->db->del($keys)) {
            return $deleted;
        }

        return false;
    }

    /**
     * Given a valid key, return the entity data array
     * @param  [type] $key [description]
     * @return [type] [description]
     */
    protected function entity_array($key)
    {
        $raw_data = [];
        if (preg_match("/^(?P<table>[\D]+)\.(?P<pkey>[\d]+)\.(?P<field>[\D]+)$/", $key, $matches)) {
            $table = $matches['table'];
            $pkey = $matches['pkey'];
            $field = $matches['field'];
            $value = $this->db->get($key);
            $raw_data = compact('table', 'pkey', 'field', 'value');
        }

        return $raw_data;
    }

    /**
     * Return an instance of static::$entity_class given supplied data array
     * @param  array  $data
     * @return object
     */
    protected static function entity(array $data)
    {
        $obj = new static::$entity_class;

        if (method_exists($obj, 'make')) {
            $obj = $obj->make($data);
        } else {
            foreach ($data as $field => $value) {
                if (! property_exists($obj, $field)) {
                    $obj->$field = $value;
                }
            }
        }

        return $obj;
    }

    /**
     * Convert SQL-style string into components
     * @return [type] [description]
     */
    public function parse_sql($sql)
    {
        $keywords = array_merge(static::$actions, [ 'FROM', 'INTO', 'SET', 'WHERE', 'GROUP', 'JOIN', 'ORDER', 'LIMIT' ]);
        $order_or_group_flag = false;
        $phrase_match = null;
        $sql = trim($sql);
        $output = [];

        if ($action = static::sql_action($sql)) {
            if ($action == 'GET') {
                $sql = substr_replace($sql, 'SELECT', 0, 3);
            }

            $_parts = preg_split('/\s/', $sql);

            foreach ($_parts as $pkey => $word) {
                if ($order_or_group_flag) {
                    $order_or_group_flag = false;
                    continue;
                }
                foreach ($keywords as $key => $phrase) {
                    if (strtoupper($word) == $phrase) {
                        if (in_array($phrase, [ 'DELETE', 'INSERT' ])) {
                            $output[$phrase] = true;
                            continue(2);
                        }
                        if (in_array($phrase, [ 'ORDER', 'GROUP' ])) {
                            $order_or_group_flag = true;
                        }
                        $phrase_match = $phrase;
                        continue(2);
                    }
                }
                if ($phrase_match) {
                    if (! isset($output[$phrase_match])) {
                        $output[$phrase_match] = '';
                    }
                    if (strlen($output[$phrase_match])) {
                        $output[$phrase_match] .= ' ';
                    }
                    $output[$phrase_match] .= $word;
                }
            }
        } else {
            throw new \InvalidArgumentException('Invalid query action');
        }

        return $output;
    }

    /**
     * Get the columns in a table.
     * @param str table
     * @return resource A resultset resource
     */
    public function get_fields($table)
    {
  //   	$qs = sprintf('SELECT * FROM information_schema.columns WHERE table_name =\'%s\'', $table);
        // return $this->query($qs);
    }

    /**
     * Get the tables info in a database.
     * @return resource A resultset resource
     */
    public function getDatabase()
    {
        // return $this->query('SELECT table_name FROM information_schema.tables WHERE table_schema=\'public\'');
        return $this->query('SELECT name FROM schema.table WHERE database = '.$this->database_index());
    }

    /**
     * Get the tables in a database.
     * @return array
     */
    public function getTables()
    {
        $tables = [];
        $table_schemas = $this->schema_db()->get('*.schema');
        foreach ($table_schemas as $key => $table_info) {
            $tables[] = $table_info['table_name'];
        }

        return $tables;
    }

    /**
     * Get the primary keys for a table.
     * @param  [type] $table [description]
     * @return str[]  The primary key field names
     */
    public function getPrimaryKeys($table)
    {
  //       $i = 0;
  //       $primary = NULL;
  //   	$query = sprintf('SELECT pg_attribute.attname
     //        FROM pg_class, pg_attribute, pg_index
  //           WHERE pg_class.oid = pg_attribute.attrelid AND
  //           pg_class.oid = pg_index.indrelid AND
  //           pg_index.indkey[%d] = pg_attribute.attnum AND
  //           pg_index.indisprimary = \'t\' AND
  //           relname=\'%s\'',
        // 	$i,
        // 	$table
        // );
  //   	$this->query($query);

  //       do {
  //           if ($row = $this->row()) {
  //               $primary[] = $row['attname'];
  //           }
  //           $i++;
  //       } while ($row);

  //       return $primary;
    }

    public function table_exists($table)
    {
        if ($schema = $this->table_schema($table)) {
            return true;
        }

        return false;
    }

    /**
     * Create Table Schema object
     *
     * @param	string	the table name
     * @param	array	the fields
     * @param	mixed	primary key(s)
     * @param	mixed	key(s)
     * @return stdClass
     */
    public function create_table_schema($table, $fields, $keys)
    {
        $schema = new \stdClass;
        $schema->NAME = $table;
        $schema->COLUMNS = [];
        $schema->KEYS = [];
        $schema->SEQUENCE = 1;

        foreach ($fields as $field => $attributes) {
            $attributes = array_change_key_case($attributes, CASE_LOWER);

            if (! isset($attributes['name'])) {
                $attributes['name'] = $field;
            }

            // Convert datatypes to be PostgreSQL-compatible
            switch (strtoupper($attributes['type'])) {
                case 'TINYINT':
                case 'SMALLINT':
                case 'MEDIUMINT':
                case 'INT':
                case 'BIGINT':
                    $attributes['type'] = 'int';
                    break;
                case 'DOUBLE':
                    $attributes['type'] = 'float';
                    break;
                case 'TIMESTAMP':
                    $attributes['type'] = 'datetime';
                    break;
                default:
                    $attributes['type'] = 'str';
                    break;
            }

            if (array_key_exists('default', $attributes)) {
                if (! in_array($attributes['default'], [ 'NOW()', 'NULL' ])) {
                    if (false === strpos($attributes['default'], 'nextval(')) {
                        $attributes['default'] = '\''.$attributes['default'].'\'';
                    }
                }
            }

            $schema->COLUMNS[] = $attributes;
        }

        if (is_array($keys) && count($keys) > 0) {
            foreach ($keys as $attributes) {
                $attributes = array_change_key_case($attributes, CASE_LOWER);

                if (! isset($attributes['name'])) {
                    $attributes['name'] = $field;
                }
                $schema->KEYS[] = $attributes;
            }
        }

        return $schema;
    }
    /**
     * Create Table
     *
     * @param	string	the table name
     * @param	array	the fields
     * @param	mixed	primary key(s)
     * @param	mixed	key(s)
     * @param	boolean	should 'IF NOT EXISTS' be added to the SQL
     * @return bool
     */
    public function create_table($table, $fields, $keys)
    {
        // $this->query($this->create_table_sql($table, $fields, $primary_keys, $keys, $if_not_exists));
        // return $this->tableExists($table);
        /*
        public function table_schema($table)
        {
            if ($schema = $this->schema_db()->get($table.'.schema')) { // key: article.schema

                return json_decode($schema);
            }
        }

        $table_schemas = $this->schema_db()->get('*.schema');
        foreach ($table_schemas as $key => $table_info) {
            $tables[] = $table_info['table_name'];
        }

        $tables = $this->schema_db()->get('*.schema');
         */

        $schema = $this->create_table_schema($table, $fields, $keys);
        $schema = json_encode($schema);
        /*  // TARGET OUTPUT OF var_export($schema);
        {
            "NAME": "table",
            "COLUMNS": [
                {
                    "name": "id",
                    "type": "int",
                    "null": false,
                    "default": "nextval()"
                },
                {
                    "name": "name",
                    "type": "str",
                    "default": null,
                    "null": false,
                    "unique": true
                },
                {
                    "name": "created",
                    "type": "datetime",
                    "default": "NOW()"
                },
                {
                    "name": "updated",
                    "type": "datetime",
                    "default": "NOW()"
                }
            ],
            "KEYS": [
                {
                    "NAME": "id",
                    "TYPE": "primary"
                },
                {
                    "NAME": "name",
                    "TYPE": "unique"
                }
            ],
            "SEQUENCE": 1
        }
         */

        $this->schema_db()->set($table.'.schema', $schema); // AND/OR separate into tables: table, column, key
        /*
        // Getting the schema
        $schema = $this->schema_db()->get($table.'.schema');
        $schema = json_decode($schema);

        // OR
        $schema_db = $this->schema_db();
        $schema_db->where();
        $schema = $this->schema_db()->get();
        $schema = json_decode($schema);

         */

        // $config = $this->config();

        // creating a "table" just means insert data into (database:0): schema:table, schema:column, schema:key
        // if ($this->database_index()/*$config['database']*/ > 0) // update schemadb
        // else // update redis-0.json
    }

    /**
     * Create Table
     *
     * @param	string	the table name
     * @param	array	the fields
     * @param	mixed	primary key(s)
     * @param	mixed	key(s)
     * @param	boolean	should 'IF NOT EXISTS' be added to the SQL
     * @return bool
     */
    public function create_table_sql($table, $fields, $primary_keys, $keys, $if_not_exists = true)
    {
        $sql = 'CREATE TABLE ';
        // if ($if_not_exists === TRUE) {
        // 	if ($this->tableExists($table)) {
        // 		return "SELECT * FROM $table"; // Needs to return innocous but valid SQL statement
        // 	}
        // }
        // $sql .= $this->escape_identifiers($table)." (";
        // $current_field_count = 0;

        // foreach ($fields as $field => $attributes) {
        // 	// Numeric field names aren't allowed in databases, so if the key is
        // 	// numeric, we know it was assigned by PHP and the developer manually
        // 	// entered the field information, so we'll simply add it to the list
        // 	if (is_numeric($field)) {
        // 		$sql .= "\n\t$attributes";
        // 	} else {
        // 		$attributes = array_change_key_case($attributes, CASE_UPPER);
        // 		$sql .= "\n\t".$this->protect_identifiers($field);
        // 		$is_unsigned = (array_key_exists('UNSIGNED', $attributes) && $attributes['UNSIGNED'] === TRUE);

        // 		// Convert datatypes to be PostgreSQL-compatible
        // 		switch (strtoupper($attributes['TYPE'])) {
        // 			case 'TINYINT':
        // 				$attributes['TYPE'] = 'SMALLINT';
        // 				break;
        // 			case 'SMALLINT':
        // 				$attributes['TYPE'] = ($is_unsigned) ? 'INTEGER' : 'SMALLINT';
        // 				break;
        // 			case 'MEDIUMINT':
        // 				$attributes['TYPE'] = 'INTEGER';
        // 				break;
        // 			case 'INT':
        // 				$attributes['TYPE'] = ($is_unsigned) ? 'BIGINT' : 'INTEGER';
        // 				break;
        // 			case 'BIGINT':
        // 				$attributes['TYPE'] = ($is_unsigned) ? 'NUMERIC' : 'BIGINT';
        // 				break;
        // 			case 'DOUBLE':
        // 				$attributes['TYPE'] = 'DOUBLE PRECISION';
        // 				break;
        // 			case 'DATETIME':
        // 				$attributes['TYPE'] = 'TIMESTAMP';
        // 				break;
        // 			case 'LONGTEXT':
        // 				$attributes['TYPE'] = 'TEXT';
        // 				break;
        // 			case 'BLOB':
        // 				$attributes['TYPE'] = 'BYTEA';
        // 				break;
        // 		}

        // 		// If this is an auto-incrementing primary key, use the serial data type instead
        // 		if (in_array($field, $primary_keys) && array_key_exists('AUTO_INCREMENT', $attributes) && $attributes['AUTO_INCREMENT'] === TRUE) {
        // 			$sql .= ' SERIAL';
        // 		} else {
        // 			$sql .=  ' '.$attributes['TYPE'];
        // 		}

        // 		// Modified to prevent constraints with integer data types
        // 		if (array_key_exists('CONSTRAINT', $attributes) && strpos($attributes['TYPE'], 'INT') === false) {
        // 			$sql .= '('.$attributes['CONSTRAINT'].')';
        // 		}

        // 		if (array_key_exists('DEFAULT', $attributes)) {
        // 			if (!in_array($attributes['DEFAULT'], [ 'NOW()', 'NULL' ])) {
        // 				if (false === strpos($attributes['DEFAULT'], 'nextval(')) {
        // 					$attributes['DEFAULT'] = '\''.$attributes['DEFAULT'].'\'';
        // 				}
        // 			}
        // 			$sql .= ' DEFAULT '.$attributes['DEFAULT'];
        // 		}

        // 		if (array_key_exists('NULL', $attributes) && $attributes['NULL'] === TRUE) {
        // 			$sql .= ' NULL';
        // 		} else {
        // 			$sql .= ' NOT NULL';
        // 		}

        // 		// Added new attribute to create unqite fields. Also works with MySQL
        // 		if (array_key_exists('UNIQUE', $attributes) && $attributes['UNIQUE'] === TRUE) {
        // 			$sql .= ' UNIQUE';
        // 		}
        // 	}

        // 	// don't add a comma on the end of the last field
        // 	if (++$current_field_count < count($fields)) {
        // 		$sql .= ',';
        // 	}
        // }

        // if (count($primary_keys) > 0) {
        // 	// Something seems to break when passing an array to _protect_identifiers()
        // 	foreach ($primary_keys as $index => $key) {
        // 		$primary_keys[$index] = $this->protect_identifiers($key);
        // 	}
        // 	$sql .= ",\n\tPRIMARY KEY (" . implode(', ', $primary_keys) . ")";
        // }

        // $sql .= "\n);";

        // if (is_array($keys) && count($keys) > 0) {
        // 	foreach ($keys as $key) {
        // 		if (is_array($key)) {
        // 			$key = $this->protect_identifiers($key);
        // 		} else {
        // 			$key = array($this->protect_identifiers($key));
        // 		}
        // 		foreach ($key as $field) {
        // 			$sql .= "CREATE INDEX " . $table . "_" . str_replace(array('"', "'"), '', $field) . "_index ON $table ($field); ";
        // 		}
        // 	}
        // }
        return $sql;
    }

    /**
     * Check if a table exists
     * @param string The table name
     * @return bool
     */
    public function tableExists($table)
    {
        $tables = $this->getTables();

        return in_array($table, $tables);
    }

    public function keys($pattern)
    {
        $options = $this->getOptions();
        $response = $this->__call('keys', array($pattern));

        if (isset($options->prefix) && !$response instanceof Predis\ResponseErrorInterface) {
            $length = strlen($options->prefix->getPrefix());
            $response = array_map(function ($key) use ($length) {
                return substr($key, $length);
            }, $response);
        }

        return $response;
    }

    /**
     * Get the last error
     */
    public function last_error()
    {
        return $this->db->getLastError();
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([ $this->db, $name ], $arguments);
    }
}
