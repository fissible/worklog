<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Report;
use Worklog\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/24/17
 * Time: 2:28 PM
 */
class ReportCommand extends Command
{
    public $command_name;

    public static $description = 'Report work log entries';
    public static $options = [
        'c' => ['req' => null, 'description' => 'Clear the cached report data.'],
        'i' => ['req' => true, 'description' => 'The Jira Issue key'],
        'j' => ['req' => null, 'description' => 'Output as JSON'],
        'l' => ['req' => null, 'description' => 'Entries from last week'],
        't' => ['req' => null, 'description' => 'Entries from today'],
        'g' => ['req' => true, 'description' => 'Group by "issue", "date" or "none'],
        'n' => ['req' => null, 'description' => 'Output without borders']
    ];
    public static $arguments = [ 'date', 'date_end' ];
    public static $usage = '%s [-ilt] [date [end_date]]';
    public static $menu = true;

    protected static $valid_group_keys = [
        'date', 'issue'
    ];

    protected static $default_group_key = 'date';

    protected static $exception_strings = [
        'issue_not_found' => 'Issue %s not found',
        'invalid_group_key' => 'Invalid group key'
    ];

    public function run()
    {
        parent::run();

        $clear_cache = (bool) $this->option('c');
        $group_by_overidden = false;
        $group_by = $this->option('g');
        $json = $this->option('j');
        $Report = new Report();

        debug(compact('clear_cache', 'group_by', 'json'), 'green');

        // -i [jira_issue_key]
        if ($issue = $this->option('i')) {
            $Report->setIssue($issue);
        }

        // -g [group_by_field]
        if ($group_by !== 'none' && false !== filter_var($group_by, FILTER_VALIDATE_BOOLEAN)) {
            if (in_array($group_by, static::$valid_group_keys)) {
                $Report->groupBy($group_by);
                $group_by_overidden = true;
            } else {
                throw new \InvalidArgumentException(static::$exception_strings['invalid_group_key']);
            }
        } elseif (strtolower($group_by) === 'none') {
            $group_by_overidden = true;
        } else {
            $Report->groupBy(static::$default_group_key);
        }

        // -t Report for Today
        if ($this->option('t') || $this->getData('today')) {
            $Report->forToday();

            if (! $group_by_overidden) {
                $Report->groupBy('issue');
            }

        // Report for specific date or date range
        } elseif ($date = $this->getData('date')) {
            $DateStart = Carbon::parse($date);
            $DateEnd = null;

            if ($date_end = $this->getData('date_end')) {
                $DateEnd = Carbon::parse($date_end);
            }

            $Report->forDate($DateStart, $DateEnd);

            if (! $group_by_overidden) {
                $Report->groupBy('date');
            }

        // Report for last week
        } elseif ($this->option('l')) {
            $Report->forLastWeek();

            if (! $group_by_overidden) {
                $Report->groupBy('date');
            }
        }

        $Report->orderBy([ 'date' => 'asc', 'start' => 'asc' ]);

        try {
            if ($json) {
                return json_encode($Report->run($clear_cache));
            } elseif (IS_CLI) {
                $Report->table($this->option('n'), null, $clear_cache);
            } else {
                return $Report->run($clear_cache);
            }
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($e->getMessage() == 'No items') {
                return sprintf('%s here%s...', $e->getMessage(), (rand(0, 99) > 96 ? (rand(0, 1) ? ', pal' : ', mate') : ''));
            } else {
                throw $e;
            }
        }
    }
}
