<?php
namespace Worklog\Database;

use Worklog\Services\TaskService;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/14/17
 * Time: 10:35 AM
 */
class CreateTaskTable extends Migration
{
    private $name = 'create_task_table';
    
    private $class_name = 'CreateTaskTable';

    protected function up() {
        $fields = TaskService::fields();
        foreach ($fields as $fname => $fconfig) {
            $fields[$fname]['default'] = '';
        }

        $this->db->create_table(
            'task',
            $fields,
            (array) TaskService::primary_key()
        );

        return $this->db->tableExists('task');
    }

    protected function down() {
        $this->db->exec('DROP TABLE IF EXISTS task');
        return ! $this->db->tableExists('task');
    }
}
