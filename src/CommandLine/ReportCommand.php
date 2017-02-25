<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use CSATF\CommandLine\Output;
use Worklog\Services\TaskService;
use CSATF\CommandLine\Command as Command;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/24/17
 * Time: 2:28 PM
 */
class ReportCommand extends Command {

    public $command_name;

    public static $description = 'Report work log entries';
    public static $options = [
        'i' => ['req' => true, 'description' => 'The JIRA Issue key'],
        'l' => ['req' => null, 'description' => 'Entries from last week']
    ];
    public static $arguments = [ 'date' ];
    public static $menu = true;

    private static $exception_strings = [
        'invalid_argument' => 'Command requires a valid date as the argument',
        'issue_not_found' => 'Issue %s not found',
        'no_records_found' => 'No entries found'
    ];

    private static $output_line_length = 120;


    public function run() {
        parent::run();

        $TaskService = new TaskService(App()->db());
        $date = $this->getData('date');
        $DateStart = Carbon::today()->startOfWeek();
        $DateEnd = Carbon::today()->endOfWeek();
        $DateNow = Carbon::now();
        $DateInterval = Carbon::now();
        $border = '|';
        $issues = [];
        $where = [];

        if ($date) {
            $DateStart = Carbon::parse($date);
            $DateEnd = $DateStart->copy();
        } elseif ($this->option('l')) {
            $DateStart->subWeek();
            $DateEnd->subWeek();
        }

        // Change Sunday to Saturday
        $DateEnd->subDay();

        if ($issue = $this->option('i')) {
            $where['issue'] = $issue;
        }

        $Tasks = $TaskService->select($where)->result();
        $task_count = App()->db()->count();

        if ($issue && $task_count < 1) {
            throw new \Exception(sprintf(static::$exception_strings['issue_not_found'], $issue));
        }

        if ($Tasks) {
            $Tasks = $TaskService->sort($Tasks);
            $last_date = null;

            foreach ($Tasks as $key => $Task) {

                // filter by issue key
                if (is_null($issue) || (property_exists($Task, 'issue') && $Task->issue == $issue)) {

                    // filter by date
                    $TaskDate = Carbon::parse($Task->date);
                    if ($DateStart->toDateString() === $DateEnd->toDateString()) {
                        if ($TaskDate->toDateString() !== $DateStart->toDateString()) {
                            continue;
                        }
                    } else {
                        if ($TaskDate->lt($DateStart) || $TaskDate->gt($DateEnd)) {
                            continue;
                        }
                    }

                    $_issue = (property_exists($Task, 'issue') && $Task->issue ? $Task->issue : 'NA');
                    $date = Carbon::parse($Task->date)->format('D');
                    $_date_display = ($date === $last_date ? str_repeat(' ', strlen($last_date) + 2) : '['.$date.']');

                    if (! array_key_exists($_issue, $issues)) {
                        $issues[$_issue] = [
                            'descriptions' => [],
                            'durations' => [],
                            'dates' => []
                        ];
                    }
                    if (property_exists($Task, 'duration') && ! empty($Task->duration)) {
                        $issues[$_issue]['durations'][] = $Task->duration;
                    }
                    $TaskService->formatFieldsForDisplay($Task);
                    if (property_exists($Task, 'description') && ! empty($Task->description)) {

                        $issues[$_issue]['descriptions'][] = $_date_display.' '.$Task->description;
                    }

                    if ($date !== $last_date) {
                        $last_date = $date;
                    }
                }
            }

            if (IS_CLI) {
                if (! empty($issues)) {
                    Output::set_line_length(static::$output_line_length);
                    $hline = '+' . str_repeat('-', static::$output_line_length - 2) . '+';

                    // Print date (or date range)
                    Output::line($hline);
                    if ($DateStart->toDateString() === $DateEnd->toDateString()) {
                        Output::line($DateStart->toFormattedDateString(), $border);
                    } else {
                        Output::line($DateStart->toFormattedDateString().' - '.$DateEnd->toFormattedDateString(), $border);
                    }
                    Output::line($hline);


                    foreach ($issues as $_issue => $data) {
                        $duration = '';
                        $DateInterval = Carbon::now();
                        foreach ($data['durations'] as $_duration) {
                            $DateInterval->add($_duration);
                        }
                        $DiffInterval = $DateNow->diff($DateInterval);
                        if ($DiffInterval->d) {
                            $duration .= $DiffInterval->d.' days';
                        }
                        if ($DiffInterval->h) {
                            if (strlen($duration)) $duration .= ', ';
                            $duration .= $DiffInterval->h.' hours';
                        }
                        if ($DiffInterval->i) {
                            if (strlen($duration)) $duration .= ', ';
                            $duration .= $DiffInterval->i.' mins';
                        }
                        $pad_length = static::$output_line_length - 4;//(static::$output_line_length - strlen($duration));
                        $duration = str_pad($duration, $pad_length, ' ', STR_PAD_LEFT);


                        Output::line($hline);
                        if ($_issue !== 'NA') {
                            Output::line($_issue, $border);
                            Output::line($hline);
                        }
                        foreach ($data['descriptions'] as $description) {
                            Output::line('- '.$description, $border);
                        }
                        Output::line($hline);
                        Output::line($duration, $border);
                        Output::line($hline);
                    }
                } else {
                    throw new \Exception(static::$exception_strings['no_records_found']);
                }
            } else {
                return $issues;
            }
        } else {
            throw new \Exception(static::$exception_strings['no_records_found']);
        }
    }
}