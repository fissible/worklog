<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/17/17
 * Time: 10:00 AM
 */

namespace Worklog\CommandLine;

use Worklog\Filesystem\File;

class Input
{
    /**
     * @param  null        $prompt
     * @param  null        $default
     * @return null|string
     */
    public static function ask($prompt = null, $default = null)
    {
        if ($prompt) echo $prompt;
        $fp = fopen("php://stdin","r");
        $line = rtrim(fgets($fp, 1024), "\r\n");

        return $line ?: $default;
    }

    /**
     * @param  null $prompt
     * @param  bool $default
     * @return bool
     */
    public static function confirm($prompt = null, $default = false)
    {
        $options = ($default ? ' [Y/n]' : ' [y/N]');
        if ($prompt) {
            $prompt = trim(trim(trim($prompt), ':'));
            if (! preg_match('/\[[yn]\/[yn]\]/i', $prompt)) {
                $prompt .= $options;
            }
            $prompt .= ': ';
        }
        $response = static::ask($prompt, $default);

        if (! is_bool($response)) {
            $response = trim($response);
            $char = strtolower($response[0]);
            if ($char === 'y') {
                $response = true;
            } elseif ($char == 'n') {
                $response = false;
            } else {
                $response = $default;
            }
        }

        return (bool) $response;
    }

    /**
     * @param  null   $prompt
     * @return string
     */
    public static function secret($prompt = null)
    {
        if ($prompt) echo $prompt;
        exec('stty -echo');
        $line = trim(fgets(STDIN));
        exec('stty echo');
        print "\n";

        return $line;
    }

    public static function text($prefix_lines = [], $lines = [], $suffix_lines = [])
    {
        $output = null;
        if (env('BINARY_TEXT_EDITOR')) {
            $temp_dir = '/tmp';
            $temp_file = md5(rand(111, 999)).'.tmp';
            $Dir = new File($temp_dir, true);
            $File = new File($temp_dir.'/'.$temp_file);
            $created_directory = false;
            $created_file = false;
            array_unshift($suffix_lines, '', '# Lines starting with \'#\' will be ignored.');

            if (! $Dir->exists()) {
                $Dir->touch();
                $created_directory = $Dir->exists();
            }
            if (! $File->exists()) {
                $File->touch();
                $created_file = $File->exists();
            }

            foreach ((array)$lines as $lkey => $_line) {
                if (false !== strpos($_line, "\n")) {
                    $tokens = explode("\n", $_line);
                    foreach ($tokens as $tkey => $token) {
                        if ($tkey === 0) {
                            $lines[$lkey] = $token;
                        } else {
                            array_splice($lines, ($tkey + $lkey), 0, $token);
                        }
                    }
                }
            }

            $lines = (array)$lines;
            if (empty($lines)) {
                $lines[] = ''; // user input goes here
            }
            $line_count = count($lines);
            $cursor = [ ($line_count + 1), strlen($lines[($line_count - 1)]) ];

            if ($prefix_lines) {
                foreach ((array)$prefix_lines as $key => $line) {
                    array_unshift($lines, '# '.trim(trim($line, '#')));
                }
            }

            if ($suffix_lines) {
                foreach ((array)$suffix_lines as $key => $line) {
                    $line = trim(trim($line, '#'));
                    $lines[] = (strlen($line) > 0 ? '# ' : '').$line;
                }
            }

            $file_written = $File->write($lines, LOCK_EX, "\n");


            // vim +startinsert :cal cursor(row:30, col:5) <path>
            BinaryCommand::call([
                env('BINARY_TEXT_EDITOR'),                      // vim
                vsprintf('"+cal cursor(%d, %d)"', $cursor),     // set cursor to end of last input line
                '+startinsert',                                 // start vim in insert mode
                '"+highlight Comment ctermfg=green"',           // define a color group
                '"+match Comment /^#.*/"',                      // color regex matches
                $File->path()                                   // temporary file path
            ]);

            if ($created_file && $file_written) {
                $output = $File->lines();
                foreach ($output as $key => $line) {
                    if (substr($line, 0, 1) == '#' || strlen($line) < 1) {
                        unset($output[$key]);
                    }
                }
                $output = implode("\n", $output);
            }

            if ($created_file) {
                $File->delete();
            }
            if ($created_directory) {
                $Dir->delete();
            }
        }

        return $output;
    }
}
