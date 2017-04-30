<?php

namespace Tests;

use Worklog\Testing\DatabaseTransactions;
use Worklog\Database\Drivers\SqliteDatabaseDriver;

class DatabaseTest extends TestCase {

	use DatabaseTransactions;


	public function testCanConnect()
	{
		$this->assertTrue($this->db->is_connected());
	}

	public function testTransaction()
	{
		$table = 'test_table';

		$this->assertFalse($this->db->tableExists($table));

		$this->create_table();

		$this->assertTrue($this->db->tableExists($table));

		$this->db->rollback();

		$this->assertFalse($this->db->tableExists($table));

		$this->db->begin_transaction();
	}

	public function testInsertSelect()
	{
		$table = 'test_table';
		$rows = [
			['value' => 'alpha'],
			['value' => 'beta'],
			['value' => 'gamma']
		];
		$this->create_table($table);

		foreach ($rows as $key => $row) {
			$this->notSeeInDatabase($table, $row);

			$rows[$key]['id'] = $this->db->insert($table, $row);

			$this->seeInDatabase($table, $row);
		}
		
		$selection = $this->db->select($table, ['value' => '!=NULL']);
		
		$this->assertEquals(count($rows), count($selection));
	}

	public function testInsertDelete()
	{
		$table = 'test_table';
		$row = ['value' => 'initial'];
		$this->create_table($table);

		$this->notSeeInDatabase($table, $row);

		$row['id'] = $this->db->insert($table, $row);

		$this->seeInDatabase($table, $row);

		$this->db->delete($table, $row);

		$this->notSeeInDatabase($table, $row);
	}

	public function testInsertUpdate()
	{
		$table = 'test_table';
		$row = ['value' => 'initial'];
		$new_row = ['value' => 'updated'];
		$this->create_table($table);

		$this->notSeeInDatabase($table, $row);

		$row['id'] = $this->db->insert($table, $row);

		$this->seeInDatabase($table, $row);

		$this->db->update($table, $new_row, $row);

		$new_row = array_merge($row, $new_row);

		$this->seeInDatabase($table, $new_row);
	}


	private function create_table($table = 'test_table') {
		$this->db->create_table($table, $this->table_field_config(), ['id']);
	}

	private function table_field_config($props = [])
	{
		return array_merge([
			'id'    => ['type' => 'int', 'auto_increment' => true],
			'value' => ['type' => 'string', 'default' => 'NULL']
		], $props);
	}
}