<?php
namespace Worklog\Services;

use Carbon\Carbon;
use Worklog\Services\Service;
use Worklog\CommandLine\Output;

/**
 * Model Service
 */

class ModelService extends Service
{
    protected $db;

    protected static $table;

    protected static $primary_key_field;

    protected static $fields;

    protected static $entity_class = '\stdClass';

    const INSERT = 10;

    const UPDATE = 11;

    public function __construct(\Worklog\Database\Driver $db)
    {
        $this->db = $db;
    }

    public function make(array $data = []) {
        $obj = new static::$entity_class($this->db);
        foreach ($data as $key => $value) {
            $obj->$key = $value;
        }
        return $obj;
    }

    public static function primary_key() {
        return static::$primary_key_field;
    }

    public static function fields() {
        return static::$fields;
    }

    public static function field($field) {
        $fields = static::fields();
        if (! is_string($field)) {
            throw new \InvalidArgumentException('ModelService::field() requires a string as the first parameter');
        }
        if (array_key_exists($field, $fields)) {
            return $fields[$field];
        }
    }

    public function field_prompt($field, $default = null) {
        if ($config = static::field($field)) {
            if (array_key_exists('prompt', $config)) {
                if (is_null($default)) {
                    $default = $this->field_default_value($field);
                }
                $prompt = $config['prompt'];
                if ($config['required']) {
                    $prompt .= ' (required)';
                }
                if ($default) {
                    $prompt .= ' [' . $default . ']';
                }
                $prompt .= ': ';

                return $prompt;
            }
        }
    }

    public function calculated_field($record, $field) {
        return null;
    }

    public function default_val($field) {
        return $this->field_default_value($field);
    }

    public function field_default_value($field) {
        $default = null;
        if (is_null($default)) {
            if ($config = static::field($field)) {
                if (array_key_exists('default', $config)) {
                    $default = $config['default'];
                    if (substr($default, 0, 1) == '*') {
                        $method = substr($default, 1);
                        $sub_method = null;
                        $arguments = null;
                        $sub_arguments = null;

                        if (false !== strpos($method, '->')) {
                            $parts = explode('->', $method);
                            $method = $parts[0];
                            $sub_method = $parts[1];
                        }

                        if (false !== strpos($method, '(') && false !== strpos($method, ')')) {
                            list($method, $arguments) = static::strParse($method, 'args');
                        }

                        if (! is_null($arguments)) {
                            $default = call_user_func_array([ $this, $method ], $arguments);
                        } else {
                            $default = call_user_func([ $this, $method ]);
                        }

                        // sub-method
                        if (! is_null($sub_method) && $default) {
                            $obj = (is_object($default) ? $default : $this);

                            if (false !== strpos($sub_method, '(') && false !== strpos($sub_method, ')')) {
                                list($sub_method, $sub_arguments) = static::strParse($sub_method, 'args');
                            }

                            if (! is_null($sub_arguments)) {
                                $default = call_user_func_array([ $obj, $sub_method ], $sub_arguments);
                            } else {
                                $default = call_user_func([ $obj, $sub_method ]);
                            }
                        }
                    }
                }
            }
        }

        return $default;
    }

    protected function DateTime($time = 'now', $timezone = null) {
        return new Carbon($time, $timezone);
    }

    protected function now_string($format = 'Y-m-d H:i', $time = 'now', $timezone = null) {
        $Date = $this->DateTime($time, $timezone);
        return $Date->format($format);
    }

    protected static function strParse($input, $type) {
        $output = $input;
        switch ($type) {
            case 'args':
                if (false !== strpos($input, '(') && false !== strpos($input, ')')) {
                    $open_paren_pos = strpos($input, '(');
                    $method = substr($input, 0, $open_paren_pos);
                    if ($arg_string = substr($input, $open_paren_pos + 1, (strpos($input, ')', $open_paren_pos) - $open_paren_pos) - 1)) {
                        $arguments = explode(',', $arg_string);
                        $arguments = array_map('trim', $arguments);
                        $arguments = array_map(function ($n) {
                            return trim($n, "'");
                        }, $arguments);

                        $output = [ $method, $arguments ];
                    }
                }
                break;
        }

        return $output;
    }

    public function write($Record) {
        $type = self::INSERT;
        $result = null;

        if (is_array($Record)) {
            if (empty($Record)) {
                throw new \Exception('Cannot write an empty record');
            }

            if (isset($Record[static::$primary_key_field])) {
                $type = self::UPDATE;
            }
        } elseif ($Record instanceof \stdClass) {
            if (property_exists($Record, static::$primary_key_field)) {
                $type = self::UPDATE;
            }
        }

        if ($type === self::INSERT) {
            $result = $this->db->insert(static::$table, $Record);
            if (! property_exists($Record, 'id') && is_numeric($result)) {
                $Record->id = $result;
            }
        } else {
            $result = $this->db->update(static::$table, $Record, [ 'id' => $Record->id ]);
        }

        return $result;
    }

    /**
     * @param array $where
     * @param array $order_by
     * @param integer $limit
     * @return \Worklog\Database\Driver
     */
    public function select($where = [], $order_by = null, $limit = 0) {
        return $this->db->select(static::$table, $where, $order_by, $limit);

//        if ($results = $this->db->result()) {
//            foreach ($results as $key => $Record) {
//                $this->setCalculatedFields($Record);
//            }
//        }
//
//        return $this;
    }

    public function result() {
        return $this->db->result();
    }

    /**
     * @param array $where
     * @return array
     */
    public function delete($where = []) {
        return App()->db()->delete(static::$table, $where);
    }

    public function setCalculatedFields($Record) {
        if (method_exists($this, 'calculated_field')) {
            foreach ($this->calculated_field_callbacks() as $field => $callback) {
                $Record->$field = $callback($Record);
            }
        }
    }

    public function formatFieldsForDisplay($Record) {
        if (method_exists($this, 'display_format_callbacks')) {
            foreach ($this->display_format_callbacks() as $field => $callback) {
                $Record->$field = $callback($Record);
            }
        }
    }

    /**
     * Sort records
     * @param $records
     * @param string $mode
     * @return bool
     */
    public function sort(array $records = [], $dir = 'asc', $mode = 'default') {
        return $records;
    }

    public function ascii_table() {
        $args = func_get_args();
        if (func_num_args() < 1) {
            $records = $this->select()->result();
        } elseif (isset($args[0])) {
            $records = $args[0];
        } else {
            throw new \InvalidArgumentException('ModelService::ascii_table() requires an array as the first argument.');
        }

        $row_data = $col_widths = $headers = [];
        $num_headers = count(static::$display_headers);

        $records = $this->sort($records);

        foreach ($records as $task_id => $Record) {
            $data = [];

            // Format fields for display
            $this->formatFieldsForDisplay($Record);

            foreach (static::$display_headers as $name => $header) {
                $data[$name] = '';
                
                if (! isset($headers[$name])) {
                    $headers[$name] = $header;
                    $col_widths[$name] = 0;
                }
                
                foreach ($Record as $field_name => $value) {
                    if ($field_name == $name) {
                        $val_len = strlen($value);
                        // $col_widths[$name] = max(min((160/$num_headers), $val_len), $col_widths[$name]);
                        $col_widths[$name] = max(min((((Output::line_length()/$num_headers) + $val_len) / 2), $val_len), $col_widths[$name]);
                        $data[$name] = preg_replace('/\s+/', ' ', $value);
                    }
                }
            }
            $row_data[] = array_values($data);
        }

        return Output::data_grid(array_values($headers), $row_data, array_values($col_widths));
    }
}