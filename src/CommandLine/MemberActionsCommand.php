<?php
namespace Worklog\CommandLine;

/**
 * MemberActionsCommand
 * Lists options this script takes (CLI)
 */
class MemberActionsCommand extends Command {

	public $command_name;

	public static $description = 'Display member_action_types';
	public static $options = [
		's' => [
			Option::CONFIG_KEY_REQUIRED    => null,
			Option::CONFIG_KEY_DESCRIPTION => 'Space delim. string of action codes'
		]
	];
	public static $usage = '[-s] %s';
	public static $menu = true;

	public function run() {
		$output = '';
		$table = 'member_action_types';
		$where = null;
		$order_by = 'id ASC';
		$limit = null;
		$as_string = $this->option('s');
		$grid_data = $grid_row = $max_lengths = [];
		$headers = [ 'ID', 'Code', 'Description', 'Redirects to' ];
		$select_fields = [ 'id', 'code', 'description', 'redirect' ];
		$select_fields_protected = array_map(array($this->db, 'protect_identifiers'), $select_fields);
		
		$this->db->get($select_fields_protected, $table, $where, $order_by, $limit);
		$row_count = $this->db->numRows();
		$rows = $this->db->rows();

		// Reduce associative array to indexed and calc max-lengths for Output::data_grid()
		foreach ($rows as $key => $row) {
			if ($as_string) {
				$grid_data[] = $row['code'];
			} else {
				$grid_row = [];
				foreach ($select_fields as $fkey => $field_name) {
					if (! isset($max_lengths[$fkey]) || strlen($row[$field_name]) > $max_lengths[$fkey]) {
						$max_lengths[$fkey] = strlen($row[$field_name]);
						if (strlen($headers[$fkey]) > $max_lengths[$fkey]) {
							$max_lengths[$fkey] = strlen($headers[$fkey]);
						}
					}
					$grid_row[] = $row[$field_name];
				}
				$grid_data[] = $grid_row;
			}
		}
		if ($as_string) {
			$output = implode(' ', $grid_data);
		} else {
			$output .= Output::data_grid($headers, $grid_data, $max_lengths);
		}

		return $output;
	}
}