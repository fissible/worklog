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
        'l' => ['req' => null, 'description' => 'Entries from last week'],
        't' => ['req' => null, 'description' => 'Entries from today']
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
        ;
        $DateStart = Carbon::today()->startOfWeek();
        $DateEnd = Carbon::today()->endOfWeek()->subDay();
        $DateNow = Carbon::now();
        $today = false;
        $border = '|';
        $issues = [];
        $where = [];

        if ($issue = $this->option('i')) {
            $where['issue'] = $issue;
        }

        if ($this->option('t') || $this->getData('today')) {
            $DateStart = Carbon::today();
            $DateEnd = Carbon::today();
            $today = true;
        } elseif ($date = $this->getData('date')) {
            $DateStart = Carbon::parse($date);
            $DateEnd = $DateStart->copy();
        } elseif ($this->option('l')) {
            $DateStart->subWeek();
            $DateEnd->subWeek();
        }

        $DateStart->setTime(6, 0);
        $DateEnd->endOfDay();

        if ($DateStart->toDateString() === $DateEnd->toDateString()) {
            $where['date'] = $DateStart->toDateString();
        } else {
            $where['date>='] = $DateStart->toDateString();
            $where['date<='] = $DateEnd->toDateString();
        }

        $Tasks = $TaskService->select($where)->result();
        $task_count = App()->db()->count();

        if ($issue && $task_count < 1) {
            throw new \Exception(sprintf(static::$exception_strings['issue_not_found'], $issue));
        }

        if ($Tasks) {
            $Tasks = $TaskService->sort($Tasks);
            $last_date = $last_issue = null;
            $issue_task_index = 0;

            foreach ($Tasks as $key => $Task) {
                $_issue = (property_exists($Task, 'issue') && $Task->issue ? $Task->issue : 'NA');

                if (! array_key_exists($_issue, $issues)) {
                    $issue_task_index = 0;
                    $issues[$_issue] = [
                        'descriptions' => [],
                        'durations' => [],
                        'dates' => [],
                        'times' => []
                    ];
                }
                if (property_exists($Task, 'duration') && ! empty($Task->duration)) {
                    $issues[$_issue]['durations'][$issue_task_index] = $Task->duration;
                }
                $TaskService->formatFieldsForDisplay($Task);
                if (property_exists($Task, 'description') && ! empty($Task->description)) {
                    $issues[$_issue]['descriptions'][$issue_task_index] = $Task->description;
                }
                if (property_exists($Task, 'date') && ! empty($Task->date)) {
                    $issues[$_issue]['dates'][$issue_task_index] = Carbon::parse($Task->date);
                }

                if ($today) {
                    if (property_exists($Task, 'start') || property_exists($Task, 'stop')) {
                        $time = '';
                        if (! empty($Task->start)) {
                            $time = static::get_twelve_hour_time($Task->start);
                        }
                        if (! empty($Task->stop)) {
                            if (strlen($time)) {
                                $time .= ' - ';
                            }
                            $time .= static::get_twelve_hour_time($Task->stop);
                        }

                        $issues[$_issue]['times'][$issue_task_index] = $time;
                    }
                }

                $issue_task_index++;
            }

            if (IS_CLI) {
                if (! empty($issues)) {
                    Output::set_line_length(static::$output_line_length);
                    $hline = '+' . str_repeat('-', static::$output_line_length - 2) . '+';

                    // Print date (or date range)
                    Output::line($hline);
                    if ($DateStart->toDateString() === $DateEnd->toDateString()) {
                        Output::line($DateStart->format('l').', '.$DateStart->toFormattedDateString(), $border);
                    } else {
                        Output::line($DateStart->toFormattedDateString().' - '.$DateEnd->toFormattedDateString(), $border);
                    }
//                    Output::line($hline);
                    Output::line('+' . str_repeat('=', static::$output_line_length - 2) . '+');

                    foreach ($issues as $_issue => $data) {
                        $duration = '';
                        $DateInterval = Carbon::now();

                        if ($_issue !== $last_issue) {
                            $last_issue = $_issue;
                            $last_date = null;
                        }

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
                        $pad_length = static::$output_line_length - 4;
                        $duration = str_pad($duration, $pad_length, ' ', STR_PAD_LEFT);


//                        Output::line($hline);

                        if ($_issue !== 'NA') {
                            Output::line($_issue, $border);
                            Output::line($hline);
                        }

                        foreach ($data['descriptions'] as $issue_task_index => $description) {
                            $_datetime_display = '';

                            if (isset($data['dates'][$issue_task_index])) {
                                $date = $data['dates'][$issue_task_index]->format('D');
                                if ($date !== $last_date && ! is_null($last_date)) {
                                    Output::line('', '|');
                                }
                                $_datetime_display = ($date === $last_date ? str_repeat(' ', strlen($date) + 3) : '['.$date.'] ');
                            }
                            if ($today) {
                                if (isset($data['times']) && isset($data['times'][$issue_task_index])) {
                                    $_datetime_display = str_pad($data['times'][$issue_task_index], 21, ' ', STR_PAD_RIGHT);
                                }
                            }
                            Output::line($_datetime_display.$description, $border);
                            if ($date !== $last_date) {
                                $last_date = $date;
                            }
                        }
//                        Output::line($hline);
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