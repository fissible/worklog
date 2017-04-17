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

    private static $entity_class = '\stdClass';

    const INSERT = 10;

    const UPDATE = 11;


    public function __construct($entity_class = null) {
        if (is_null($entity_class)) {
            $entity_class = static::$entity_class;
        }
        static::set_entity_class($entity_class);
    }

    protected static function set_entity_class($entity_class) {
        static::$entity_class = $entity_class;
    }

    public function make(array $data = []) {
        if (static::$entity_class == '\stdClass') {
            $obj = new static::$entity_class($this->db);
            foreach ($data as $key => $value) {
                $obj->$key = $value;
            }
        } else {
            $obj = static::Model($data);
        }

        return $obj;
    }

    public static function Model(array $data = []) {
        printl('Model() -> '.static::$entity_class);
        return new static::$entity_class($data);
    }

    public static function primary_key() {
        $Model = static::Model();
        return $Model::$primary_key_field;
    }

    public function display_headers() {
        $Model = static::Model();
        return $Model->display_headers();
    }

    public static function fields() {
        $Model = static::Model();
        return $Model::fields();
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

    /**
     * @param array $where
     * @param array $order_by
     * @param integer $limit
     * @return \Worklog\Database\Driver
     */
    public function select($where = [], $order_by = null, $limit = 0) {
        $Model = $this->make();
        $Query = $Model->newQuery();

        if (! empty($where)) {
            $Query->where($where);
        }

        // sort records
        if (! is_null($order_by)) {
            $Query->orderBy($order_by);
        } elseif (method_exists($Model, 'scopeDefaultSort')) {
            $Query->defaultSort();
        }
        if ($limit) {
            $Query->limit($limit);
        }

        return $Query;
    }

    public function result() {
        deprecated();//deprecated(__CLASS__, __METHOD__, __LINE__);
        return $this->db->result();
    }

    /**
     * @param array $where
     * @return array
     */
    public function delete($where = []) {
        $deleted = 0;
        if ($Records = $this->select($where)->get()) {
            foreach ($Records as $Record) {
                if ($Record->delete()) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function ascii_table() {
        $args = func_get_args();

        if (func_num_args() < 1) {
            $records = $this->select()->get();
        } elseif (isset($args[0])) {
            $records = $args[0];
        } else {
            throw new \InvalidArgumentException('ModelService::ascii_table() requires an array as the first argument.');
        }

        $row_data = $col_widths = $headers = [];
        $display_headers = static::display_headers();
        $num_headers = count($display_headers);

        foreach ($records as $task_id => $Record) {
            $data = [];

            foreach ($display_headers as $name => $header) {
                $data[$name] = '';
                
                if (! isset($headers[$name])) {
                    $headers[$name] = $header;
                    $col_widths[$name] = 0;
                }

                if ($value = $Record->{$name}) {
                    if (is_object($value)) {
                        $value = get_class($value);
                    }
                    $val_len = strlen($value);
                    // $col_widths[$name] = max(min((160/$num_headers), $val_len), $col_widths[$name]);
                    $col_widths[$name] = max(min((((Output::line_length()/$num_headers) + $val_len) / 2), $val_len), $col_widths[$name]);
                    $data[$name] = preg_replace('/\s+/', ' ', $value);
                }
            }
            $row_data[] = array_values($data);
        }

        return Output::data_grid(array_values($headers), $row_data, array_values($col_widths));
    }
}