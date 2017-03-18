<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use CSATF\CommandLine\Output;
use Worklog\Services\TaskService;
use CSATF\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/23/17
 * Time: 8:34 AM
 */
class ListCommand extends Command {

    public $command_name;

    public static $description = 'Show work log entries';
    public static $options = [
        'i' => ['req' => true, 'description' => 'The JIRA Issue key'],
        'l' => ['req' => null, 'description' => 'Entries from last week']
    ];
    public static $arguments = [ 'issue' ];
    public static $menu = true;


    public function run() {
        parent::run();

        $where = [];
        $issue = null;
        $TaskService = new TaskService(App()->db());
        $DateStart = Carbon::today()->startOfWeek();
        $DateEnd = Carbon::today()->endOfWeek();
        Output::set_line_length(250);

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

        $Tasks = $TaskService->select($where)/*->result()*/;

        return $TaskService->ascii_table($Tasks);
    }
}