<?php
namespace Worklog\CommandLine;

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
        'i' => ['req' => true, 'description' => 'The JIRA Issue key']
    ];
    public static $arguments = [ 'issue' ];
    public static $menu = true;


    public function run() {
        parent::run();

        $where = [];
        $issue = null;
        $TaskService = new TaskService(App()->db());
//        TaskService::set_cli_max_line_length(250);
        Output::set_line_length(250);

        if (($issue = $this->option('i', false)) || ($issue = $this->getData('issue'))) {
            $where['issue'] = $issue;
        }

        $Tasks = $TaskService->select($where)->result();

        return $TaskService->ascii_table($Tasks);
    }
}