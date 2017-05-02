<?php
namespace Worklog\CommandLine;

use Carbon\Carbon;
use Worklog\Models\Task;
use Worklog\Str;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 2/23/17
 * Time: 8:34 AM
 */
class DetailCommand extends Command
{
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

    public function run()
    {
        parent::run();

        $id = $this->expectData('id', static::$exception_strings['invalid_argument'])->getData('id');

        if (is_numeric($id)) {
            $variant = 'heavy';
            $border = output::uchar('ver', $variant);
            $period_duration = [];
            $duration_length = 0;
            $Today = Carbon::today();
            $Task = Task::findOrFail($id);
            $Date = Carbon::parse($Task->date);
            $id = str_pad($Task->id, min((strlen($Task->id) + 1), 7));

            if (isset(static::$output_line_length)) {
                Output::set_line_length(static::$output_line_length);
            }
            Output::set_variant($variant);

            if ($Task->start && $Task->stop) {
                if ($Today->toDateString() === $Date->toDateString()) {
                    $period_duration[] = $Task->start.' - '.$Task->stop;
                } else {
                    $period_duration[] = $Task->friendly_date_string;
                }
                $duration_length += strlen($period_duration[0]);
            }

            if ($Task->duration) {
                $pad_length = (Output::line_length() - $duration_length) - 4;
                $period_duration[] = str_pad($Task->duration_string, $pad_length, ' ', STR_PAD_LEFT);
            }

            Output::line(Output::horizontal_line('top'));
            Output::line($id.' | '.($Task->issue ?: ''), $border);
            Output::line(Output::horizontal_line('mid'));
            Output::line($Task->description, $border);
            Output::line(Output::horizontal_line('mid'));
            Output::line(implode('', $period_duration), $border);
            Output::line(Output::horizontal_line('bot'));

            // Show raw data table
            if (DEVELOPMENT_MODE) {
                Output::set_variant('light');

                $attributes = $Task->attributesToArray();
                $display_headers = $Task->display_headers();
                $headers = [];

                foreach ($attributes as $key => $val) {
                    if (array_key_exists($key, $display_headers)) {
                        $headers[] = $display_headers[$key];
                    } else {
                        $headers[] = Str::title($key);
                    }
                }

                printl(Output::data_grid($headers, [ array_values($attributes) ]));
            }

        } else {
            throw new \InvalidArgumentException(static::$exception_strings['invalid_argument']);
        }
    }
}
