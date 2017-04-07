<?php
namespace Worklog\CommandLine;

/**
 * SetMemberActionCommand
 * Update a member action record
 */
class SetMemberActionCommand extends Command {

	public $command_name;

	public static $description = 'Set a member_action record to complete/incomplete';
	public static $options = [];
	public static $arguments = [ 'member_id', 'action_type', 'completed' ];
	public static $usage = '%s <id|username> <action> <complete>';
	public static $menu = true;

	public function run() {
		$output = '';
		$table = 'member_actions';

		$member_id = $this->getData('member_id');
		$action_type = $this->getData('action_type');
		$completed = $this->getData('completed') !== null ? (bool) $this->getData('completed') : false;

		// cached: check if the supplied action type exists
		$cache_name = 'member_action_type:'.$action_type;
		$cache_tags = [ 'exists' ];
		list($action_type_id, $action_type_code) = $this->App()->Cache()->data($cache_name, function() use ($action_type) {
			switch ($action_type) {
				case 'c':
				case 'contact':
					$action_type = 'REGISTRATION_CONTACT';
					break;
				case 'p':
				case 'profile':
					$action_type = 'REGISTRATION_PROFILE';
					break;
			}
			$action_type_search_field = (is_numeric($action_type) ? 'id' : 'code');
			$where = $this->db->escape_identifiers($action_type_search_field).' = ';
			$where .= ($action_type_search_field == 'id' ? $action_type : '\''.$action_type.'\'');
			$this->db->get_row('member_action_types', $where);
			
			if ($this->db->numRows() === 1) {
				$row = $this->db->row();
				return [ $row['id'], $row['code'] ];
			} else {
				return false;
			}
		}, $cache_tags, (ONE_HOUR_IN_SECONDS * 3));

		if (! $action_type_id) {
			$output = sprintf("Action type %s invalid, use ID or Code\n", var_export($action_type, true));
			$output .= (new MemberActionsCommand($this->App()))->run();
			return $output;
		}

		// convert member username to member id
		if (! is_numeric($member_id)) {
			// cached: get a member id from a username
			$cache_name = 'member_id_from_username:'.$member_id;
			$cache_tags = [ 'exists', 'member' ];
			$member_id = $this->App()->Cache()->data($cache_name, function() use ($member_id) {
				$this->db->get_row('members', $this->db->escape_identifiers('username').' = \''.$member_id.'\'');
				if ($this->db->numRows() === 1) {
					$row = $this->db->row();
					return (int) $row['id'];
				} else {
					return false;
				}
			}, $cache_tags, (ONE_HOUR_IN_SECONDS * 3));
		} else {
			$member_id = (int) $member_id;
		}

		if (! $member_id) {
			throw new \InvalidArgumentException('Member invalid: use member ID or username');
		}

		// check if an insert or update
		$where = [];
		$where[] = $this->db->escape_identifiers('member_id').' = '.$member_id;
		$where[] = $this->db->escape_identifiers('member_action_type_id').' = '.$action_type_id;
		$where = implode(' AND ', $where);
		$this->db->get_row($table, $where);

		$data = [ 'completed' => $completed ];
		$data_info = $action_type_code.' to '.($completed ? 'complete' : 'incomplete');

		if ($this->db->numRows() > 0) {
			// update
			$this->db->updateRow($table, $data, $where);
			$output = 'Existing member_action record updated: '.$data_info;
		} else {
			// insert
			$data['member_id'] = $member_id;
			$data['member_action_type_id'] = $action_type_id;
			$this->db->insertRow($table, $data);
			$output = 'New member_action record created: '.$data_info;
		}

		return $output;
	}
}