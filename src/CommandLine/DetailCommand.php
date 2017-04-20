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
class DetailCommand extends Command {

    public $command_name;

    public static $description = 'Show work log entry details';
    public static $options = [];
    public static $arguments = [ 'id' ];
    public static $menu = true;

    protected static $exception_strings = [
        'invalid_argument' => 'Command requires a valid ID as the argument',
        'record_not_found' => 'Record %d not found'
    ];

    private static $output_line_length = 120;


    public function run() {
        parent::run();

        $id = $this->expectData('id', static::$exception_strings['invalid_argument'])->getData('id');

        if (is_numeric($id)) {
            $Task = Task::findOrFail($id);

            Output::set_line_length(static::$output_line_length);
            $border = '|';
            $Date = Carbon::parse($Task->date);
            $Today = Carbon::today();
            $id = str_pad($Task->id, min((strlen($Task->id) + 1), 7));
            $hline = '+'.str_repeat('-', static::$output_line_length - 2).'+';
            $period_duration = [];
            $duration_length = 0;


            if ($Task->start && $Task->stop) {
                if ($Today->toDateString() === $Date->toDateString()) {
                    $period_duration[] = $Task->start.' - '.$Task->stop;
                } else {
                    $period_duration[] = $Task->friendly_date_string;
                }
                $duration_length += strlen($period_duration[0]);
            }

            if ($Task->duration) {
                $pad_length = (static::$output_line_length - $duration_length) - 4;
                $period_duration[] = str_pad($Task->duration_string, $pad_length, ' ', STR_PAD_LEFT);
            }

            Output::line($hline);
            Output::line($id.' | '.($Task->issue ?: ''), $border);
            Output::line($hline);
            Output::line($Task->description, $border);
            Output::line($hline);
            Output::line(implode('', $period_duration), $border);
            Output::line($hline);

            if (DEVELOPMENT_MODE) {
                $attributes = $Task->attributesToArray();
                print Output::data_grid(array_keys($attributes), [ array_values($attributes) ]);
            }


        } else {
            throw new \InvalidArgumentException(static::$exception_strings['invalid_argument']);
        }
    }
}