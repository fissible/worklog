<?php
namespace Worklog\CommandLine;

/**
 * DatabaseTableInfoCommand
 * Get info about a database table
 */
class DatabaseTableInfoCommand extends Command
{
    public $command_name;

    public static $description = 'Show info for a database table';
    public static $arguments = [ 'table' ];
    public static $usage = '%s <table-name>';
    public static $menu = true;
    private $required_data = [ 'table' ];

    public function run()
    {
        $fields = $tables = $template = [];
        $db = $this->App()->db();
        $table = $this->getData('table');
        $headers = [ ' ', 'Column', 'Type', 'Nullable', 'Default' ];
        $max_key_info_width = 0;
        $max_field_name_width = 0;
        $max_data_type_width = 0;

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

        if ($db->tableExists($table)) {
            $output = 'Table "'.$table."\"\n";
            $pkeys  = $db->getPrimaryKeys($table);
            $fkeys  = $db->getForeignKeys($table);
            $result = $db->get_fields($table);

            foreach ($db->rows() as $field_info) {
                $key_info = '';
                if (! is_null($pkeys) && in_array($field_info['column_name'], $pkeys)) {
                    $key_info = 'PK';
                }
                if (! is_null($fkeys) && false !== ($fkey_index = array_search($field_info['column_name'], $fkeys))) {
                    if (! empty($key_info)) $key_info .= ',';
                    $key_info .= 'FK'.($fkey_index + 1);
                }
                if (strlen($key_info) > $max_key_info_width) {
                    $max_key_info_width = strlen($key_info);
                }
                if (strlen($field_info['column_name']) > $max_field_name_width) {
                    $max_field_name_width = strlen($field_info['column_name']);
                }
                $length = ($field_info['character_maximum_length'] ?: ($field_info['numeric_precision'] ?: false));
                $data_type = $field_info['data_type'].($length ? ' ('.$length.')' : '');
                if (strlen($data_type) > $max_data_type_width) {
                    $max_data_type_width = strlen($data_type);
                }
                $default = $field_info['column_default'];
                if ($default === 'NULL::character varying') {
                    $default = 'NULL';
                }
                $fields[] = [
                    $key_info,
                    $field_info['column_name'],
                    $data_type,
                    $field_info['is_nullable'],
                    $default
                ];
            }
            $template = [ $max_key_info_width, $max_field_name_width + 1, $max_data_type_width + 1, 8, null ];
            $output .= Output::data_grid($headers, $fields, $template);
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
