<?php
namespace Worklog\Database;

use CSATF\Database\Migration;
use Worklog\Services\TaskService;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/17/17
 * Time: 2:20 PM
 */
class RedisToSqlite extends Migration
{
    private $name = '2017_03_17_142052_redis_to_sqlite';
    
    private $class_name = 'RedisToSqlite';

    protected function up() {
        printl('redis to sqlite up');
        $db_config = database_config();

        if (! is_array($db_config) || ! $db_config) {
            printl(Output::color('database_config() DOES NOT WORK', 'red'));
        }


        $count = 0;
        try {
            $redis = database('Redis', $db_config['Redis']);
            $records = $redis->select('task')->result();
            $record_count = count($records);

            if ($record_count > 0) {
                printl('Found '.count($records).' in Redis database...');

                // switch to Sqlite database
                $db = database('Sqlite', $db_config['Sqlite']);

                $TaskService = new TaskService($db);
                $fields = array_keys(TaskService::fields());
                $records = $TaskService->sort($records);

                foreach ($records as $record) {
                    foreach ($record as $___field => $___value) {
                        if (! in_array($___field, $fields)) {
                            unset($record->{$___field});
                        }
                    }

                    if ($db->insert('task', $record)) {
                        $count++;
                    }
                }
                printl($count.' records inserted.');
            } else {
                printl('Found no records to migrate');
            }

        } catch (\Exception $e) {
            print Output::color(get_class($e).' '.$e->getMessage()."\n", 'red');
        }

        return true;
    }

    protected function down() {
        printl('redis to sqlite down');
        $this->db->exec('DELETE FROM task');
        $rows = $this->db->select('task');
        printl($rows);

        return (count($rows) === 0);
    }
}
