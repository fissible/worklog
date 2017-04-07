<?php
namespace Worklog\Database;

abstract class Driver {
	
	abstract public function connect($config);

	abstract public function begin_transaction();

    abstract public function commit_transaction();

    abstract public function rollback();

    abstract public function last_error();

    public function first() {
        if (isset($this->result)) {
            return array_shift($this->result);
        }
    }

    public function result() {
        $result = false;
        if (isset($this->result)) {
            $result = $this->result;
        }
        return $result;
    }

    public function count() {
        $count = false;
        if (isset($this->count)) {
            $count = $this->count;
        }
        return $count;
    }

    public static function prepare_value($value, $quote = "'") {
        if ($value === true) {
            $value = 'true';
        } elseif ($value === false) {
            $value = 'false';
        } elseif (strlen($value) == 0) {
            $value = 'NULL';
        } else {
            if ($quote == '"') {
                $value = '"'.preg_replace('/"/', "'", $value).'"';
            } else {
                $value = "'".preg_replace('/\'/', '"', $value)."'";
            }
        }

        return $value;
    }

    public static function where_string(array $where = [], $join = 'AND') {
        $_operators = ['LIKE', 'IN', '<=', '>=', '<>', '!=', '==', '=', '<', '>'];
        $values = $_where = [];

        foreach ($where as $field => $value) {
            $operator = '=';
            foreach ($_operators as $_op) {
                if (false !== stristr($field, $_op)) {
                    $field = str_replace($_op, '', $field);
                    $operator = $_op;
                    break;
                } elseif (false !== stristr($value, $_op)) {
                    $value = str_replace($_op, '', $value);
                    $operator = $_op;
                    break;
                }
            }
            $values[] = $value;
            $_where[] = $field.' '.$operator.' '.static::prepare_value($value);
        }

        return implode(' '.$join.' ', $_where);
    }

    public static function prepare_where_string(array $where = [], $join = 'AND') {
        $_operators = ['LIKE', 'IN', '<=', '>=', '<>', '!=', '==', '=', '<', '>'];
        $values = $_where = [];

        //  WHERE calories < :calories AND colour = :colour';
        //  $sth->execute(array(':calories' => 150, ':colour' => 'red'));

        foreach ($where as $field => $value) {
            $operator = '=';
            foreach ($_operators as $_op) {
                if (false !== stristr($field, $_op)) {
                    $field = str_replace($_op, '', $field);
                    $operator = $_op;
                    break;
                } elseif (false !== stristr($value, $_op)) {
                    $value = str_replace($_op, '', $value);
                    $operator = $_op;
                    break;
                }
            }
            if (method_exists(get_called_class(), 'driver_modify')) {
                list($field, $value) = static::driver_modify($field, $value);
            }
            $values[] = $value;
            $_where[] = $field.' '.$operator.' ?';
        }

        return [ implode(' '.$join.' ', $_where), $values ];
    }
}