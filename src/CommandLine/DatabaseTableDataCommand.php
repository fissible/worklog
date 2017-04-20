<?php
namespace Worklog\CommandLine;

/**
 * DatabaseTableDataCommand
 * Get records from a database table
 */
class DatabaseTableDataCommand extends Command {

	public $command_name;
	
	public static $description = 'Show records from a database table';
	public static $arguments = [ 'table' ];
	public static $options = [
		'f' => ['req' => true, 'description' => 'SELECT fields string'],
		'w' => ['req' => true, 'description' => 'WHERE string'],
		'o' => ['req' => true, 'description' => 'ORDER BY string'],
		'l' => ['req' => true, 'description' => 'LIMIT string']
	];
	public static $usage = '%s [-wol] <table-name>';
	public static $menu = true;
	private $required_data = [ 'table' ];

	public function run() {
		$col_widths = $tables = $template = $select = $headers = $row_data = $data = [];
		$db = $this->App()->db();
		$table = $this->getData('table');
		$fields = $this->option('f') ?: '*';
		$where = $this->option('w') ?: null;
		$order = $this->option('o') ?: null;
		$limit = $this->option('l') ?: null;

		if ($fields != '*') {
			$fields = preg_replace('/\s+/', '', $fields);
			$select = explode(',', $fields);
		}

		if (is_null($limit)) {
			$limit = 25;
		} elseif (empty($limit) || $limit == '0') {
			$limit = null;
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

		if ($db->tableExists($table)) {
			$output = 'Table "'.$table."\"\t";
			$pkeys  = $db->getPrimaryKeys($table);
			$result = $db->get_fields($table);
			$num_headers = $db->numRows();

			$field_index = 0;
			foreach ($db->rows() as $field_info) {
				$field_name = $field_info['column_name'];

				if (empty($select) || in_array($field_name, $select)) {
					$data_type = $field_info['data_type'];
					$key_info = '';
					
					if (! isset($col_widths[$field_index])) {
						$col_widths[$field_index] = 0;
					}
					if (! is_null($pkeys) && in_array($field_info['column_name'], $pkeys)) {
						$data_type = '[PK] '.$data_type;
					}
					

					$col_widths[$field_index] = max(min((160/$num_headers), strlen($field_name)), $col_widths[$field_index]);
					$col_widths[$field_index] = max(min((160/$num_headers), strlen($data_type)), $col_widths[$field_index]);
					$headers[$field_index] = $field_name./*"\n"*/' '.$data_type;
					$field_index++;
				}
			}

			$result = $db->get($fields, $table, $where, $order, $limit);
			$output .= $db->numRows().' rows'."\n";

			foreach ($db->rows() as $row_index => $row) {
				$data = [];
				$field_index = 0;
				foreach ($row as $field_name => $value) {
					$col_widths[$field_index] = max(min((160/$num_headers), strlen($value)), $col_widths[$field_index]);
					$data[] = preg_replace('/\s+/', ' ', $value);
					$field_index++;
				}
				$row_data[] = $data;
			}
			$output .= Output::data_grid($headers, $row_data, $col_widths);
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