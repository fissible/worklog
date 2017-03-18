<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Duration;
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
    public static $arguments = [ 'date', 'date_end' ];
    public static $usage = '%s [-ilt] [date [end_date]]';
    public static $menu = true;

    protected static $exception_strings = [
        'invalid_argument' => 'Command requires a valid date as the argument',
        'issue_not_found' => 'Issue %s not found',
        'no_records_found' => 'No entries found'
    ];

    private static $output_line_length = 120;


    public function run() {
        parent::run();

        $TaskService = new TaskService(App()->db());
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
            if ($date_end = $this->getData('date_end')) {
                $DateEnd = Carbon::parse($date_end);
            } else {
                $DateEnd = $DateStart->copy();
            }
        } elseif ($this->option('l')) {
            $DateStart->subWeek();
            $DateEnd->subWeek();
        }

        $DateStart->setTime(6, 0);
        $DateEnd->endOfDay();

        if ($DateEnd->lt($DateStart)) {
            throw new \InvalidArgumentException('End date cannot be before start date.');
        }

        if ($DateStart->toDateString() === $DateEnd->toDateString()) {
            $where['date'] = $DateStart->toDateString();
        } else {
            $where['date>='] = $DateStart->toDateString();
            $where['date<='] = $DateEnd->toDateString();
        }

        $Tasks = $TaskService->select($where)/*->result()*/;
        $task_count = App()->db()->count();

        if ($issue && $task_count < 1) {
            throw new \Exception(sprintf(static::$exception_strings['issue_not_found'], $issue));
        }

        if ($Tasks) {
            $Tasks = $TaskService->sort($Tasks);
            $last_date = $last_issue = null;

            foreach ($Tasks as $key => $Task) {
                $_issue = (property_exists($Task, 'issue') && $Task->issue ? $Task->issue : 'NA');

                if (! array_key_exists($_issue, $issues)) {
                    $issues[$_issue] = [
                        'descriptions' => [],
                        'durations' => [],
                        'dates' => [],
                        'times' => []
                    ];
                }
                if (property_exists($Task, 'duration') && ! empty($Task->duration)) {
                    $issues[$_issue]['durations'][$key] = $Task->duration;
                }

                $TaskService->formatFieldsForDisplay($Task);

                if (property_exists($Task, 'description') && ! empty($Task->description)) {
                    $issues[$_issue]['descriptions'][$key] = $Task->description;
                }
                if (property_exists($Task, 'date') && ! empty($Task->date)) {
                    $issues[$_issue]['dates'][$key] = Carbon::parse($Task->date);
                }

                if ($today) {
                    if (property_exists($Task, 'start') || property_exists($Task, 'stop')) {
                        $time = '';
                        if (! empty($Task->start)) {
                            $time = str_pad(static::get_twelve_hour_time($Task->start), 8, ' ', STR_PAD_LEFT);
                        }
                        if (! empty($Task->stop)) {
                            if (strlen($time)) {
                                $time .= ' - ';
                            }
                            $time .= static::get_twelve_hour_time($Task->stop);
                        }

                        $issues[$_issue]['times'][$key] = $time;
                    }
                }
            }

            if (IS_CLI) {
                $max_metric = 'h';

                if (! empty($issues)) {
                    Output::set_line_length(static::$output_line_length);
                    $TotalDuration = new Duration($max_metric);
                    $hline = '+' . str_repeat('-', static::$output_line_length - 2) . '+';

                    // Print date (or date range)
                    Output::line($hline);
                    if ($DateStart->toDateString() === $DateEnd->toDateString()) {
                        $header = $DateStart->format('l').', '.$DateStart->toFormattedDateString();
                    } else {
                        $header = $DateStart->toFormattedDateString().' - '.$DateEnd->toFormattedDateString();
                    }

                    Output::line("\033[1m".$header."\033[0m", $border);
                    Output::line('+' . str_repeat('=', static::$output_line_length - 2) . '+');

                    foreach ($issues as $_issue => $data) {
                        $Duration = new Duration($max_metric);
                        $pad_length = static::$output_line_length - 4;

                        // Print Issue
                        if ($_issue !== 'NA') {
                            Output::line("\033[1m".$_issue."\033[0m".' |', $border);
                            Output::line('+'.str_repeat('-', strlen($_issue) + 2).'+'.str_repeat(' ', ($pad_length - (strlen($_issue) + 1))).'|');
                        }

                        // Print Descriptions
                        foreach ($data['descriptions'] as $issue_task_index => $description) {
                            $_datetime_display = '';

                            if (isset($data['dates'][$issue_task_index])) {
                                $date = $data['dates'][$issue_task_index]->format('D');
                                // empty line between days/dates (visual grouping of tasks on a given day)
                                if ($date !== $last_date && ! is_null($last_date)) {
                                    Output::line('', '|');
                                }
                                $_datetime_display = ($date === $last_date ? str_repeat(' ', mb_strlen($date) + 3) : '['.$date.'] ');
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

                        // Print Duration
                        foreach ($data['durations'] as $_duration) {
                            $Duration->add($_duration);
                            $TotalDuration->add($_duration);
                        }

                        Output::line(str_pad($Duration->asString(), $pad_length, ' ', STR_PAD_LEFT), $border);
                        Output::line($hline);

                        if ($_issue !== $last_issue) {
                            $last_issue = $_issue;
                            $last_date = null;
                        }
                    }

                    if (count($issues) > 1) {
                        Output::line($hline);
                        $prefix = 'Total Duration:';
                        Output::line($prefix.str_pad($TotalDuration->asString(), ($pad_length - strlen($prefix)), ' ', STR_PAD_LEFT), $border);
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