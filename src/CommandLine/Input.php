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
     * @param null $prompt
     * @param null $default
     * @return null|string
     */
    public static function ask($prompt = null, $default = null) {
        if ($prompt) echo $prompt;
        $fp = fopen("php://stdin","r");
        $line = rtrim(fgets($fp, 1024), "\r\n");
        return $line ?: $default;
    }

    /**
     * @param null $prompt
     * @param bool $default
     * @return bool
     */
    public static function confirm($prompt = null, $default = false) {
        $options = ($default ? ' [Y/n]' : ' [y/N]');
        if ($prompt)  {
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
     * @param null $prompt
     * @return string
     */
    public static function secret($prompt = null) {
        if ($prompt) echo $prompt;
        exec('stty -echo');
        $line = trim(fgets(STDIN));
        exec('stty echo');
        print "\n";
        return $line;
    }
}