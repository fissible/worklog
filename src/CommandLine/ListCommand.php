<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Models\Task;
use Worklog\Services\TaskService;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/23/17
 * Time: 8:34 AM
 */
class ListCommand extends Command
{
    public $command_name;

    public static $description = 'Show work log entries';
    public static $options = [
        'i' => ['req' => true, 'description' => 'The JIRA Issue key'],
        'l' => ['req' => null, 'description' => 'Entries from last week']
    ];
    public static $arguments = [ 'issue' ];
    public static $menu = true;

    public function run()
    {
        parent::run();

        $where = [];
        $issue = null;
        $TaskService = new TaskService();
        $DateStart = Carbon::today()->startOfWeek();
        $DateEnd = Carbon::today()->endOfWeek();

        if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
            $where['issue'] = $issue;
        }

        if ($this->option('l')) {
            $DateStart->subWeek();
            $DateEnd->subWeek();
        }

        // Change Sunday to Saturday
        $DateStart->setTime(6, 0);
        $DateEnd->subDay()->endOfDay();

        if ($this->option('l')) {
            $where['date>='] = $DateStart->toDateTimeString();
            $where['date<='] = $DateEnd->toDateTimeString();
        }

        $Tasks = Task::where($where)->get();

        return $TaskService->ascii_table($Tasks);
    }
}
