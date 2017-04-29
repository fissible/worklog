<?php
namespace Worklog\CommandLine;

/**
 * DatabaseTableSearchCommand
 */
class DatabaseTableSearchCommand extends Command {

	public $command_name;

	public static $description = 'Run a fuzzy search for database tables';
	public static $options = [
		'c' => ['req' => null, 'description' => 'Search for columns'],
		's' => ['req' => null, 'description' => 'Return as space-delimited list']
	];
	public static $arguments = [ 'input' ];
	public static $usage = '[-cs] %s <search-term>';
	public static $menu = false;

	public function run() {
		$db = $this->App()->db();

		if ($this->option('c')) {
			$col_widths = [];
			$output = '';
			$headers = [ 'Table', 'Column', 'Type' ];
			$num_headers = count($headers);
			$input = $this->getData('input');

			if (empty($input)) {
				throw new \Exception('Must supply a search criteria.');
			}
			$result = $db->query(sprintf("SELECT table_name, column_name, data_type FROM information_schema.columns WHERE column_name ~* '%s';", $input));

			foreach ($db->rows() as $row_index => $row) {
				$data = [];
				$field_index = 0;
				foreach ($row as $field_name => $value) {
					if (! isset($col_widths[$field_index])) {
						$col_widths[$field_index] = 0;
					}
					$col_widths[$field_index] = max(min((160/$num_headers), strlen($value)), $col_widths[$field_index]);
					$data[] = preg_replace('/\s+/', ' ', $value);
					$field_index++;
				}
				$row_data[] = $data;
			}
			$output .= Output::data_grid($headers, $row_data, $col_widths);

			return $output;
		} else {
			$tables = [];
			$table = $this->getData('input');

			if (! is_string($table)) {
				$table = '*';
			}

			foreach ($db->getTables() as $key => $table_name) {
				if ($table == '*' || strlen($table) < 1) {
					$tables[] = [ 'value' => $table_name, 'lev' => 0 ];
				} else {
					if (stristr($table_name, $table)) {
						$tables[] = [ 'value' => $table_name, 'lev' => levenshtein($table, $table_name) ];
					} else {
						if (($lev = levenshtein($table, $table_name)) < 4) {
							$tables[] = [ 'value' => $table_name, 'lev' => $lev ];
						}
					}
				}
			}

			if (count($tables)) {
				usort($tables, function ($a, $b) {
					return $a['lev'] - $b['lev'];
				});
				foreach ($tables as $key => $option) {
					$tables[$key] = $option['value'];
				}

				if (IS_CLI) {
					if ($this->option('s')) {
						$tables = implode(' ', $tables);
					} else {
						$tables = "\t".implode("\n\t", $tables);
					}
				}
			} elseif (IS_CLI) {
				if ($this->option('s')) {
					$tables = '';
				} else {
					$tables = 'No matches found';
				}
			}

			return $tables;
		}
		
	}
}