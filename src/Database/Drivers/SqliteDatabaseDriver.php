<?php
namespace Worklog\Database\Drivers;

use Worklog\Database\Driver;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/13/17
 * Time: 9:23 AM
 */
class SqliteDatabaseDriver extends Driver
{
    /**
     * @var int
     */
    private $lastInsertPKeys;

    /**
     * @var resource
     */
    private $last_query;

    /**
     * @var array
     */
    private $last_error;

    private $escape_char = '"';

    private $protect_identifiers = true;

    private $reserved_identifiers = ['*'];

    /**
     * @var resource Database resource
     */
    private $db;

    private $stmt;

    private $result;

    public function __construct(/**/)
    {
        $args = func_get_args();
        $num_args = func_num_args();
        if ($num_args == 1 && is_array($args[0])) {
            $this->connect($args[0]);
        } else {
            $this->connect($args);
        }
    }

    public function connect($config)
    {
        $connString = 'sqlite:';

        if (array_key_exists('path', $config)) {
            $connString .= trim($config['path']);
        } else {
            $connString .= ':memory:';
        }

        if ($this->db = new \PDO($connString)) {
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->connected = true;

            return true;
        }

        throw new \Exception(sprintf('Unable to connect to Sqlite database using connection string: %s', $connString));
    }

    public function is_connected()
    {
        return $this->connected;
    }

    public function begin_transaction()
    {
        $this->db->beginTransaction();
    }

    public function commit_transaction()
    {
        $this->db->commit();
    }

    public function rollback()
    {
        $this->db->rollBack();
    }

    public function select($table, $where = [], $order_by = null, $limit = 0)
    {
        list($sql, $values) = $this->prepare_sql('*', $table, $where, $order_by, $limit);

        $this->prepare($sql);

        if ($values) {
            $this->execute($values);
        } else {
            $this->execute();
        }

        return $this->rows();
    }

    public function prepare_sql($fields, $table, $where = null, $order_by = null, $limit = null)
    {
        $values = [];
        if (is_array($fields)) {
            $fields = implode(',', $fields);
        }
        $sql = sprintf('SELECT %s FROM %s', $fields, $table);
        if (! is_null($where) && ! empty($where)) {
            list($where, $values) = static::prepare_where_string($where);
            $sql .= sprintf(' WHERE %s', $where);
        }
        if (! is_null($order_by)) {
            $sql .= sprintf(' ORDER BY %s', $order_by);
        }
        if (! is_null($limit) && $limit != 0) {
            $sql .= sprintf(' LIMIT %s', $limit);
        }

        return [ $sql, $values ];
    }

    public static function driver_modify($field, $value)
    {
        switch (true) {
            case (stristr($field, 'datetime')):
                $field = 'datetime('.$field.')';
                break;
            case (stristr($field, 'date')):
                $field = 'date('.$field.')';
                break;
            case (stristr($field, 'time')):
                $field = 'time('.$field.')';
                break;
        }

        return [ $field, $value ];
    }

    public function prepare($sql, $options = [])
    {
        //$options = array_merge([ PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY ], $options);
        $this->stmt = $this->db->prepare($sql, $options);
    }

    /**
     * Binds a PARAMETER to the specified variable name
     * @param $key
     * @param $variable
     * @param $type
     * @return mixed
     */
    public function param($key, $variable, $type = null)
    {
        if (is_null($type)) $type = \PDO::PARAM_STR;
        if (! is_numeric($key) && substr($key, 0, 1) !== ':') {
            $key = ':'.$key;
        }

        return $this->stmt->bindParam($key, $variable, $type);
    }

    /**
     * Binds a VALUE to a parameter
     * @param $key
     * @param $value
     * @param $type
     * @return mixed
     */
    public function bind($key, $value, $type = null)
    {
        if (is_null($type)) $type = \PDO::PARAM_STR;
        if (! is_numeric($key) && substr($key, 0, 1) !== ':') {
            $key = ':'.$key;
        }

        return $this->stmt->bindValue($key, $value, $type);
    }

    public function execute($input = [])
    {
        return $this->stmt->execute($input);
    }

    public function fetch()
    {
        return $this->stmt->fetch();
    }

    public function fetchAll()
    {
        return $this->stmt->fetchAll();
    }

    /**
     * Get the rows in a table.
     * @param str fields The names of the fields to return
     * @param str table
     * @return resource A resultset resource
     */
    public function get($fields, $table, $where = null, $order_by = null, $limit = null)
    {
        if (is_array($fields)) {
            $fields = implode(',', $fields);
        }
        $sql = sprintf('SELECT %s FROM %s', $fields, $table);
        if (! is_null($where) && ! empty($where)) {
            $sql .= sprintf(' WHERE %s', static::where_string($where));
        }
        if (! is_null($order_by)) {
            $sql .= sprintf(' ORDER BY %s', $order_by);
        }
        if (! is_null($limit) && $limit != 0) {
            $sql .= sprintf(' LIMIT %s', $limit);
        }

        return $this->query($sql);
    }

    /**
     * Get a row from a table.
     * @param str table
     * @param str where
     * @return resource A resultset resource
     */
    public function get_row($table, $where = null)
    {
        return $this->get('*', $table, $where, null, 1);
    }

    /**
     * Get the rows in a table.
     * @param str fields The names of the fields to return
     * @param str table
     * @return resource A resultset resource
     */
    public function get_rows($fields, $table, $where = null)
    {
        return $this->get($fields, $table, $where);
    }

    /**
     * Get the tables info in a database.
     * @return
     */
    public function getDatabase()
    {
        $this->prepare("SELECT name FROM sqlite_master WHERE type='table';");
        $this->execute();

        return $this->rows();
    }

    /**
     * Get the tables in a database.
     * @return array
     */
    public function getTables()
    {
        $tables = [];
//        $this->getDatabase();
//        if ($rows = $this->rows()) {
        if ($rows = $this->getDatabase()) {
            foreach ($rows as $table_info) {
                if (is_object($table_info)) {
                    $tables[] = $table_info->name;
                } elseif (array_key_exists('name', $table_info)) {
                    $tables[] = $table_info['name'];
                }
            }
        }

        return $tables;
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

    /**
     * Update a row.
     * @param str table
     * @param str values
     * @param str where
     * @return bool
     */
    public function updateRow($table, $values, $where)
    {
        if (is_array($where)) {
            $where = static::where_string($where);
        }
        if ($values instanceof \stdClass) {
            $_values = [];
            foreach ($values as $field => $value) {
                if ($field !== 'id') {
                    $_values[$field] = $value;
                }
            }
            $values = $_values;
        }
        if (is_array($values)) {
            list($fields, $values) = $this->separate_fields_values($values);
            foreach ($values as $key => $value) {
                $field = $fields[$key];
                if ($value === true) {
                    $values[$key] = $field.' = true';
                } elseif ($value === false) {
                    $values[$key] = $field.' = false';
                } elseif (strlen($value) == 0) {
                    $values[$key] = $field.' = NULL';
                } else {
                    $values[$key] = $field." = '".preg_replace('/\'/', '"', $value)."'";
                }
            }
            $values = implode(', ', $values);
        }
        $values = preg_replace('/"/','\'',$values);
        $values = preg_replace('/`/','"',$values);
        $qs = sprintf('UPDATE %s SET %s WHERE %s', $table, $values, $where);

        return $this->query($qs);
    }

    /**
     * Insert a new row.
     * @param str table
     * @param  array $data
     * @return bool
     */
    public function insertRow($table, $data = [])
    {
        $result = false;
        list($names, $values) = $this->separate_fields_values($data);
        if ($values = $this->prepare_values($values, true)) {
            $names = implode(',', $names);
            $qs = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $table,
                $names,
                $values
            );
            $this->query($qs);

            $result = $this->db->lastInsertId();
        }

        return $result;
    }

    /**
     * Insert new rows.
     * @param str table
     * @param  array $data
     * @return bool
     */
    public function insertRows($table, $data = [])
    {
        $result = false;
        $rows = [];
        $names = $this->separate_fields_values($data[0], 'fields');
        foreach ($data as $row_key => $row_data) {
            $rows[] = $this->separate_fields_values($row_data, 'values');
        }
        if (count($rows) > 0) {
            $names = implode(',', $names);
            $qs = sprintf('INSERT INTO %s (%s) VALUES ', $table, $names);
            foreach ($rows as $key => $values) {
                if ($values = $this->prepare_values($values, false)) {
                    $qs .= sprintf('(%s),', $values);
                }
            }
            $qs = rtrim($qs, ',');

            $this->query($qs);

            $result = $this->db->lastInsertId();
        }

        return $result;
    }

    private function prepare_values($values, $wrap_in_paren = false)
    {
        if (is_array($values)) {
            foreach ($values as $key => $row_values) {
                if (is_array($row_values)) {
                    $values[$key] = $this->prepare_values($row_values, true);
                } else {
                    if (strlen($row_values) == 0) {
                        $values[$key] = 'NULL';
                    } else {
                        $values[$key] = "'".preg_replace('/\'/', '"', $row_values)."'";
                    }
                }
            }
            $values = implode(', ', $values);
        }

        if ($wrap_in_paren) {
            $values = '('.$values.')';
        }

        return $values;
    }

    /**
     * Given an associative array of data, parse into separate arrays
     * @param array
     * @param string Return "both" field names and values, or just "fields" or just "values"
     * @return array
     */
    private function separate_fields_values($data = [], $mode = 'both')
    {
        $values = $names = $return = [];
        switch ($mode) {
            case 'key':
            case 'keys':
            case 'name':
            case 'field':
            case 'names':
            case 'fields':
                $mode = 'fields';
                break;
            case 'data':
            case 'value':
            case 'values':
                $mode = 'values';
                break;
            default:
                $mode = 'both';
                break;
        }
        if ($mode == 'names') $mode = 'fields';
        foreach ($data as $key => $value) {
            if ($mode == 'both' || $mode == 'fields') {
                $names[] = $key;
            }
            if ($mode == 'both' || $mode == 'values') {
                $values[] = $value;
            }
        }
        switch ($mode) {
            case 'fields':
                $return = $names;
                break;
            case 'values':
                $return = $values;
                break;
            case 'both':
                $return = [$names, $values];
                break;
        }

        return $return;
    }

    /**
     * Get the columns in a table.
     * @param str table
     * @return resource A resultset resource
     */
    public function deleteRow($table, $where)
    {
        if (is_array($where)) {
            $where = static::where_string($where);
        }

        return $this->query(sprintf('DELETE FROM %s WHERE %s', $table, $where));
    }

    /**
     * Escape a string to be part of the database query.
     * @param str string The string to escape
     * @return str The escaped string
     */
    public function escape($string)
    {
        return $this->db->escapeString($string);
    }

    /**
     * Fetch a row from a query resultset.
     * @return str[] An array of the fields and values from the next row in the resultset
     */
    public function row()
    {
        return $this->result->fetch(\PDO::FETCH_CLASS);
    }

    /**
     * Fetch rows from a query resultset.
     * @return str[] An array of the fields and values from the next row in the resultset
     */
    public function rows()
    {
        if (isset($this->stmt) && ! is_null($this->stmt)) {
            return $this->stmt->fetchAll(\PDO::FETCH_CLASS);
        } elseif (isset($this->result) && ! is_null($this->result)) {
            return $this->result->fetchAll(\PDO::FETCH_CLASS);
        }

    }

    /**
     * The number of rows in a resultset.
     * @param resource resource A resultset resource
     * @return int The number of rows
     */
    public function numRows()
    {
        $nrows = 0;
//        $this->result->reset();
        while ($this->result->fetchAll())
            $nrows++;
//        $this->result->reset();

        return $nrows;
    }

    /**
     * Insert a row into the datastore
     * @param  [type] $table [description]
     * @param  [type] $data  [description]
     * @return [type] [description]
     */
    public function insert($table, $data)
    {
        $id = false;

        if (is_array($data) || $data instanceof \stdClass) {
            if ((is_array($data) && isset($data[0])) && (is_array($data[0]) || $data[0] instanceof \stdClass)) {
                $results = [];
                foreach ($data as $key => $record) {
                    $results[] = $this->insert($table, $record);
                }

                return $results;
            } else {
                $this->lastInsertPKeys[] = $id = $this->insertRow($table, $data);
            }
        } else {
            throw new \InvalidArgumentException('Must supply an array of data as the second argument');
        }

        return $id;
    }

    /**
     * Update a record in the datastore
     * @param  [type] $table [description]
     * @param  [type] $data  [description]
     * @return [type] [description]
     */
    public function update($table, $data, $where)
    {
        $count = 0;
        if (is_array($data) || $data instanceof \stdClass) {
            if (is_array($data) && isset($data[0]) && (is_array($data[0]) || $data[0] instanceof \stdClass)) {
                foreach ($data as $key => $record) {
                    $_where = [];
                    foreach ($where as $w_key => $w_vale) {
                        if (array_key_exists($w_key, $data)) {
                            $_where[$w_key] = $data[$w_key];
                        }
                    }
                    $count += $this->update($table, $record, $_where);
                }
            } else {
                if ($this->updateRow($table, $data, $where)) {
                    $count++;
                }
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

    public function delete($table, $where)
    {
        if (is_array($where)) {
            $where = static::where_string($where);
        }
        if (strlen($where)) {
            return $this->query(sprintf('DELETE FROM %s WHERE %s', $table, $where));
        } else {
            throw new \InvalidArgumentException('DELETE requires a WHERE condition');
        }
    }

    /**
     * Execute SQL statement
     */
    public function query($sql)
    {
        $this->last_query = $sql;
        $this->result = $this->db->query($sql);
        if (false === $this->result) {
            throw new \Exception($this->last_error());
        }

        return $this->result;
    }

    /**
     * Close the database connection.
     */
    public function close()
    {
        $this->db->closeCursor();
    }

    /**
     * Get the last query
     */
    public function last_query()
    {
        return $this->last_query;
    }

    public function last_error()
    {
        $last_error = $this->get_last_error();

        return $last_error[2];
    }

    public function get_last_error()
    {
        $this->last_error = $this->db->errorInfo();

        return $this->last_error;
    }

    public function __call($func, $args)
    {
        return call_user_func_array(array(&$this->db, $func), $args);
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
    public function create_table($table, $fields, $primary_keys = [], $keys = [], $if_not_exists = true)
    {
        $this->query($this->create_table_sql($table, $fields, $primary_keys, $keys, $if_not_exists));

        return $this->tableExists($table);
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
    public function create_table_sql($table, $fields, $primary_keys = [], $keys = [], $if_not_exists = true)
    {
        $sql = 'CREATE TABLE ';
        if ($if_not_exists === TRUE) {
            if ($this->tableExists($table)) {
                return "SELECT * FROM $table"; // Needs to return innocuous but valid SQL statement
            }
        }
        $sql .= $this->escape_identifiers($table)." (";
        $current_field_count = 0;

        foreach ($fields as $field => $attributes) {
            // Numeric field names aren't allowed in databases, so if the key is
            // numeric, we know it was assigned by PHP and the developer manually
            // entered the field information, so we'll simply add it to the list
            if (is_numeric($field)) {
                $sql .= "\n\t$attributes";
            } else {
                $attributes = array_change_key_case($attributes, CASE_UPPER);
                $sql .= "\n\t".$this->protect_identifiers($field);
                $is_unsigned = (array_key_exists('UNSIGNED', $attributes) && $attributes['UNSIGNED'] === TRUE);

                // Convert datatypes to be PostgreSQL-compatible
                switch (strtoupper($attributes['TYPE'])) {
                    case 'TINYINT':
                    case 'SMALLINT':
                    case 'MEDIUMINT':
                    case 'INT':
                    case 'BIGINT':
                        $attributes['TYPE'] = 'INTEGER';
                        break;
                    case 'FLOAT':
                    case 'DOUBLE':
                        $attributes['TYPE'] = 'REAL';
                        break;
                    case 'DATETIME':
                    case 'TIMESTAMP':
                        $attributes['TYPE'] = 'NUMERIC';
                        break;
                    case 'STRING':
                    case 'CHARACTER':
                    case 'VARCHAR':
                    case 'TINYTEXT':
                    case 'MEDIUMTEXT':
                    case 'LONGTEXT':
                        $attributes['TYPE'] = 'TEXT';
                        break;
                    case 'BYTEA':
                    case 'LONGBLOB':
                        $attributes['TYPE'] = 'BLOB';
                        break;
                }

                // If this is an auto-incrementing primary key, use the serial data type instead
                if (in_array($field, $primary_keys) && array_key_exists('AUTO_INCREMENT', $attributes) && $attributes['AUTO_INCREMENT'] === TRUE) {
                    $sql .= ' INTEGER PRIMARY KEY';
                } else {
                    $sql .=  ' '.$attributes['TYPE'];
                }

                if (array_key_exists('CONSTRAINT', $attributes)) {
                    $sql .= ' '.$attributes['CONSTRAINT'].' ';
                }

                if (array_key_exists('DEFAULT', $attributes)) {
                    if (!in_array($attributes['DEFAULT'], [ 'NOW()', 'NULL' ])) {
                        if (false === strpos($attributes['DEFAULT'], 'nextval(')) {
                            $attributes['DEFAULT'] = '\''.$attributes['DEFAULT'].'\'';
                        }
                    }
                    if ($attributes['DEFAULT'] !== '') {
                        $sql .= ' DEFAULT '.$attributes['DEFAULT'];
                    }
                }

                if (array_key_exists('NULL', $attributes) && $attributes['NULL'] !== TRUE) {
                    $sql .= ' NOT NULL';
                } elseif (! array_key_exists('REQUIRED', $attributes) || $attributes['REQUIRED'] === TRUE) {
                    $sql .= ' NOT NULL';
                }

                // Added new attribute to create unqite fields. Also works with MySQL
                if (array_key_exists('UNIQUE', $attributes) && $attributes['UNIQUE'] === TRUE) {
                    $sql .= ' UNIQUE';
                }
            }

            // don't add a comma on the end of the last field
            if (++$current_field_count < count($fields)) {
                $sql .= ',';
            }
        }

//        if (count($primary_keys) > 0) {
//            // Something seems to break when passing an array to _protect_identifiers()
//            foreach ($primary_keys as $index => $key) {
//                $primary_keys[$index] = $this->protect_identifiers($key);
//            }
//            $sql .= ",\n\tPRIMARY KEY (" . implode(', ', $primary_keys) . ")";
//        }

        $sql .= "\n);";

        if (is_array($keys) && count($keys) > 0) {
            foreach ($keys as $key) {
                if (is_array($key)) {
                    $key = $this->protect_identifiers($key);
                } else {
                    $key = array($this->protect_identifiers($key));
                }
                foreach ($key as $field) {
                    $sql .= "CREATE INDEX " . $table . "_" . str_replace(array('"', "'"), '', $field) . "_index ON $table ($field); ";
                }
            }
        }

        return $sql;
    }

    /**
     * Escape the SQL Identifiers
     *
     * This function escapes column and table names
     *
     * @access	private
     * @param	string
     * @return string
     */
    public function escape_identifiers($item)
    {
        if ($this->escape_char == '') {
            return $item;
        }

        foreach ($this->reserved_identifiers as $id) {
            if (strpos($item, '.'.$id) !== FALSE) {
                $str = $this->escape_char. str_replace('.', $this->escape_char.'.', $item);

                // remove duplicates if the user already included the escape
                return preg_replace('/['.$this->escape_char.']+/', $this->escape_char, $str);
            }
        }

        if (strpos($item, '.') !== FALSE) {
            $str = $this->escape_char.str_replace('.', $this->escape_char.'.'.$this->escape_char, $item).$this->escape_char;
        } else {
            $str = $this->escape_char.$item.$this->escape_char;
        }

        // remove duplicates if the user already included the escape
        return preg_replace('/['.$this->escape_char.']+/', $this->escape_char, $str);
    }

    /**
     * Protect Identifiers
     *
     * This function takes a column or table name (optionally with an alias) and inserts
     * the table prefix onto it.  Some logic is necessary in order to deal with
     * column names that include the path.  Consider a query like this:
     *
     * SELECT * FROM hostname.database.table.column AS c FROM hostname.database.table
     *
     * Or a query with aliasing:
     *
     * SELECT m.member_id, m.member_name FROM members AS m
     *
     * Since the column name can include up to four segments (host, DB, table, column)
     * or also have an alias prefix, we need to do a bit of work to figure this out and
     * insert the table prefix (if it exists) in the proper position, and escape only
     * the correct identifiers.
     *
     * @access	private
     * @param	string
     * @param	bool
     * @param	mixed
     * @param	bool
     * @return string
     */
    public function protect_identifiers($item, $prefix_single = FALSE, $protect_identifiers = NULL)
    {
        if ( ! is_bool($protect_identifiers)) {
            $protect_identifiers = $this->protect_identifiers;
        }

        if (is_array($item)) {
            $escaped_array = array();

            foreach ($item as $k => $v) {
                $escaped_array[$this->protect_identifiers($k)] = $this->protect_identifiers($v);
            }

            return $escaped_array;
        }

        // Convert tabs or multiple spaces into single spaces
        $item = preg_replace('/[\t ]+/', ' ', $item);

        // If the item has an alias declaration we remove it and set it aside.
        // Basically we remove everything to the right of the first space
        $alias = '';
        if (strpos($item, ' ') !== FALSE) {
            $alias = strstr($item, " ");
            $item = substr($item, 0, - strlen($alias));
        }

        // This is basically a bug fix for queries that use MAX, MIN, etc.
        // If a parenthesis is found we know that we do not need to
        // escape the data or add a prefix.  There's probably a more graceful
        // way to deal with this, but I'm not thinking of it -- Rick
        if (strpos($item, '(') !== FALSE) {
            return $item.$alias;
        }

        // Break the string apart if it contains periods, then insert the table prefix
        // in the correct location, assuming the period doesn't indicate that we're dealing
        // with an alias. While we're at it, we will escape the components
        if (strpos($item, '.') !== FALSE) {
            if ($protect_identifiers === TRUE) {
                $item = $this->escape_identifiers($item);
            }

            return $item.$alias;
        }

        if ($protect_identifiers === TRUE AND ! in_array($item, $this->reserved_identifiers)) {
            $item = $this->escape_identifiers($item);
        }

        return $item.$alias;
    }
}
