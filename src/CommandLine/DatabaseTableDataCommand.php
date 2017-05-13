<?php
namespace Worklog\CommandLine;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DatabaseTableDataCommand
 * Get records from a database table
 */
class DatabaseTableDataCommand extends Command
{
    public $command_name;

    public static $description = 'Show records from a database table';
    public static $arguments = [ 'table' ];
    public static $options = [
        'f' => ['req' => true, 'description' => 'SELECT fields string'],
        'w' => ['req' => true, 'description' => 'WHERE string'],
        'o' => ['req' => true, 'description' => 'ORDER BY string'],
        'l' => ['req' => true, 'description' => 'LIMIT string']
    ];
    public static $usage = '%s [-fwol] <table-name>';
    public static $menu = true;
    private $required_data = [ 'table' ];

    public function run()
    {
        $Eloquent = eloquent();
        $Builder = $Eloquent->getSchemaBuilder();
        $col_widths = $tables = $template = $select = $headers = $row_data = $data = [];
        $db = $this->App()->db();
        $table = $this->getData('table');
        $fields = $this->option('f') ?: '*';
        $where = $this->option('w') ?: null;
        $order = $this->option('o') ?: null;
        $limit = $this->option('l') ?: null;
        $offset = null;

        if ($fields != '*') {
            $fields = preg_replace('/\s+/', '', $fields);
            $select = explode(',', $fields);
        }

        if (is_null($limit)) {
            $limit = 25;
        } elseif (empty($limit) || $limit == '0') {
            $limit = null;
        } elseif (false !== strpos($limit, ',')) {
            $limit_parts = explode(',', $limit);
            $offset = trim($limit_parts[0]);
            $limit = trim($limit_parts[1]);
        } elseif (false !== stripos($limit, 'offset')) {
            $limit_parts = explode('offset', strtolower($limit));
            $limit = trim($limit_parts[0]);
            $offset = trim($limit_parts[1]);
        }

        if (! $db->tableExists($table)) {
            $output = sprintf('Unknown table "%s"', $table);
            $Command = new DatabaseTableSearchCommand();
            $Command->set_invocation_flag();
            $Command->setData('input', $table);
            $tables = $Command->run();

            if (count($tables) === 1) {
                $table = $tables[0];
            }
        }

        if ($Builder->hasTable($table)) {
            $Model = $Eloquent->getModel($table);
            $output = 'Table "'.$table."\"\t";
            $pkeys  = (array) $Eloquent->getPrimaryKey($table);
            $schema = $Eloquent->getSchema($table);
            $fields = $Builder->getColumnListing($table);
            $num_headers = count($fields);

//            debug(compact('headers', 'fields'), 'green');

            $field_index = 0;
            foreach ($fields as $field) {
                $data_type = '';

                if (array_key_exists($field, $schema)) {
                    $data_type = $schema[$field]['type'];
                }

                if (empty($select) || in_array($field, $select)) {
//                    if (! isset($col_widths[$field_index])) {
//                        $col_widths[$field_index] = 0;
//                    }
                    if (! is_null($pkeys) && in_array($field, $pkeys)) {
                        $data_type = '[PK] '.$data_type;
                    }

//                    $col_widths[$field_index] = max(min((160/$num_headers), strlen($field)), $col_widths[$field_index]);
//                    $col_widths[$field_index] = max(min((160/$num_headers), strlen($data_type)), $col_widths[$field_index]);

                    if (! in_array($field, array_keys($headers))) {
                        $headers[$field] = $field;
                    }
//                    if ($data_type) {
//                        $headers[$field] .= ' '.$data_type;
//                    }

                    $field_index++;
                }
            }

//            $db->get($fields, $table, $where, $order, $limit);
//            $rows = $db->rows();
//            $output .= $db->numRows().' rows'."\n";

            $query = $Eloquent::table($table);

            if ($where) {
                $query->whereRaw($where);
                $output .= ' Where: '.$where;
            }

            if ($order) {
                $query->orderByRaw($order);
                $output .= ' Ordered: '.$order;
            }

            if (! is_null($limit)) {
                $query->limit($limit);
                $output .= ' Limit: '.$limit;
            }

            if (! is_null($offset)) {
                $query->offset($offset);
                $output .= ', Offset: '.$offset;
            }

            $rows = $query->get();

            $row_count_string = count($rows).' row'.(count($rows) == 1 ? '' : 's');
            $output .= str_repeat(' ', 20).$row_count_string."\n";

            foreach ($rows as $row_index => $row) {
                $data = [];
                $field_index = 0;
                foreach ($row as $field_name => $value) {
//                    $col_widths[$field_index] = max(min((160/$num_headers), strlen($value)), $col_widths[$field_index]);
                    $data[] = preg_replace('/\s+/', ' ', $value);
                    $field_index++;
                }
                $row_data[] = $data;
            }
//            debug($row_data, 'blue');
//            debug($headers);
            $output .= Output::data_grid(array_values($headers), $row_data/*, $col_widths*/, null, Output::cols());
        } else {
            if (is_string($tables)) {
                $tables = explode(' ', trim($tables));
            }
            if (count($tables)) {
                $output .= "\nDid you mean one of these?\n";
                foreach ($tables as $table) {
                    $output .= "\t".$table."\n";
                }
            }
        }

        return $output;
    }
}
