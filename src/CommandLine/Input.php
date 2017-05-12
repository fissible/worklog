<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/17/17
 * Time: 10:00 AM
 */

namespace Worklog\CommandLine;

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

    public static function text()
    {
        $output = null;
        if (env('BINARY_TEXT_EDITOR')) {
            $temp_dir = '/tmp';
            $temp_file = md5(rand(111, 999)).'.tmp';
            $file = $temp_dir.'/'.$temp_file;
            $temp_dir_exists = is_dir($temp_dir);
            $temp_file_exists = file_exists($file);

            if (! $temp_dir_exists) {
                mkdir($temp_dir);
            }

            file_put_contents($file, [ '', '# Lines starting with \'#\' will be ignored.' ]);

            $Command = new LaunchEditorCommand();
            $Command->set_invocation_flag();
            $Command->setData('file', $file);
            $Command->run();

            if (file_exists($file)) {
                $output = file($file);
                while ($line = current($output)) {
                    if (substr($line, 0, 1) == '#') {
                        $key = key($output);
                        unset($output[$key]);
                    }
                    next($output);
                }
                $output = implode("\n", $output);
            }

            if (! $temp_file_exists) {
                unlink($temp_file_exists);
            }
            if (! $temp_dir_exists) {
                rmdir($temp_dir);
            }
        }
        return $output;
    }
}
