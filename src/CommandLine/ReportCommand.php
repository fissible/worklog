<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Duration;
use Worklog\CommandLine\Output;
use Worklog\Services\TaskService;
use Worklog\CommandLine\Command as Command;

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
        't' => ['req' => null, 'description' => 'Entries from today'],
        'n' => ['req' => null, 'description' => 'Output without borders']
    ];
    public static $arguments = [ 'date', 'date_end' ];
    public static $usage = '%s [-ilt] [date [end_date]]';
    public static $menu = true;

    protected static $exception_strings = [
        'issue_not_found' => 'Issue %s not found'
    ];

    private static $output_line_length = 120;


    public function run() {
        parent::run();

        $allow_unicode = Output::allow_unicode();
        $TaskService = new TaskService(App()->db());
        $DateStart = Carbon::today()->startOfWeek();
        $DateEnd = Carbon::today()->endOfWeek()->subDay();
        $today = false;
        $borderless = (bool) $this->option('n');
        $border = ($borderless ? '' : '|');
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

                $TaskService->setCalculatedFields($Task);

                if (property_exists($Task, 'duration') && ! empty($Task->duration)) {
                    $issues[$_issue]['durations'][$key] = $Task->duration;
                } else {
                    printl($Task->id.' NO DURATION');
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

                $allow_unicode;
                /*
function box($size) {
    $isize = ($size - 2);
    printl(Output::uchar('down_right', 'double').str_repeat(Output::uchar('hor', 'double'), $isize).Output::uchar('down_left', 'double'));
    for ($i = 0; $i <= $isize; $i++) {
        printl(Output::uchar('ver', 'double').str_repeat(' ', $isize).Output::uchar('ver', 'double'));
    }
    printl(Output::uchar('up_right', 'double').str_repeat(Output::uchar('hor', 'double'), $isize).Output::uchar('up_left', 'double'));
}*/

                if (! empty($issues)) {
                    $u = function ($char, $variant = 'light') {
                        return Output::uchar($char, $variant);
                    };
                    $hline = function ($flags, $variant = 'light') {
                        return '+' . str_repeat('-', static::$output_line_length - 2) . '+';
                    };
                    Output::set_line_length(static::$output_line_length);
                    $TotalDuration = new Duration($max_metric);

//                    $char = [
//                        'top_l' => ($allow_unicode ? Output::uchar('down_right') : '+'),
//                        'top_r' => ($allow_unicode ? Output::uchar('down_left') : '+'),
//                        'hor' => ($allow_unicode ? Output::uchar('hor') : '-'),
//                        'ver' => ($allow_unicode ? Output::uchar('ver') : '|'),
//                        'mid_l' => ($allow_unicode ? Output::uchar('ver_right') : '+'),
//                        'mid_r' => ($allow_unicode ? Output::uchar('ver_left') : '+'),
//                        'bot_l' => ($allow_unicode ? Output::uchar('up_right') : '+'),
//                        'bot_r' => ($allow_unicode ? Output::uchar('up_left') : '+'),
//                        'top_ver' => ($allow_unicode ? Output::uchar('down_hor') : '+'),// ┬
//                        'bot_ver' => ($allow_unicode ? Output::uchar('up_hor') : '+'),// ┴
//                        'cross' => ($allow_unicode ? Output::uchar('cross') : '+'),
//                    ];
                    $char['top_right'] = '';
                    $hline = '+' . str_repeat('-', static::$output_line_length - 2) . '+';
                    $hline_heavy = '';



                    // Print date (or date range)
                    if (! $borderless) {
                        Output::line($hline);
                    }
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
                            if (! $borderless) {
                                Output::line("\033[1m".$_issue."\033[0m".' |', $border);
                                Output::line('+'.str_repeat('-', strlen($_issue) + 2).'+'.str_repeat(' ', ($pad_length - (strlen($_issue) + 1))).$border);
                            } else {
                                Output::line("\033[1m".$_issue."\033[0m", '');
                            }


                        }

                        // Print Descriptions
                        foreach ($data['descriptions'] as $issue_task_index => $description) {
                            $_datetime_display = '';

                            if (isset($data['dates'][$issue_task_index])) {
                                $date = $data['dates'][$issue_task_index]->format('D');
                                // empty line between days/dates (visual grouping of tasks on a given day)
                                if ($date !== $last_date && ! is_null($last_date)) {
                                    Output::line('', $border);
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
                        if (! $borderless) {
                            Output::line($hline);
                        }

                        if ($_issue !== $last_issue) {
                            $last_issue = $_issue;
                            $last_date = null;
                        }
                    }

                    if (count($issues) > 1) {
                        Output::line(($borderless ? '' : $hline));
                        $prefix = 'Total Duration:';
                        Output::line($prefix.str_pad($TotalDuration->asString(), ($pad_length - strlen($prefix)), ' ', STR_PAD_LEFT), $border);
                        if (! $borderless) {
                            Output::line($hline);
                        }
                    }

                    Output::line();

                } else {
                    return Output::color('No entries found', 'cyan');
                }
            } else {
                return $issues;
            }
        } else {
            return Output::color('No entries found', 'cyan');
        }
    }
}