<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 5/11/17
 * Time: 9:00 AM
 */

namespace Worklog\Services;

use Worklog\CommandLine\GitCommand;

class Git
{
    public function __construct()
    {
        if (! env('BINARY_GIT')) {
            throw new \Exception('Please configure a git binary in the .env file using BINARY_GIT=');
        }
    }

    public static function getInstance()
    {
        return new static;
    }

    public static function add($arguments = [])
    {
        if (empty($arguments)) $arguments[] = '.';
        return static::call('add', $arguments);
    }

    public static function commit_message($revision = 'HEAD')
    {
        $message = '';

        if ($revision) {
            if ($output = unwrap(static::call('rev-parse --verify '.$revision.' 2> /dev/null'), false)) {
                if ($output = unwrap(static::call([ 'show -s --format=%s 2> /dev/null', escapeshellarg($output) ]), false)) {
                    $message = $output;
                }
            }
        }

        return $message;
    }

    public static function commit($commit_message = '', $all = false)
    {
        $arguments = [];
        if ($all) {
            $arguments[] = '--all';
        }
        if (trim($commit_message)) {
            $arguments[] = sprintf('--message=%s', escapeshellarg(trim($commit_message)));
        }
        return static::call('commit', $arguments);
    }

    public static function diff($arguments = [])
    {
        return static::call('diff', $arguments);
    }

    public static function fetch($quiet = false)
    {
        $arguments = [];
        if (! DEVELOPMENT_MODE) $arguments = static::add_quiet_flag($arguments);
        return static::call('fetch', $arguments);
    }

    public static function hash($revision = 'HEAD')
    {
        return static::call('rev-parse', $revision);
    }

    public static function push($arguments = [])
    {
        return static::call('push', $arguments);
    }

    public static function status($short = false)
    {
        $arguments = [];
        if ($short) $arguments = static::add_short_flag($arguments);
        return static::call('status', $arguments);
    }

    public static function tag($arguments = [])
    {
        return static::call('tag', $arguments);
    }

    public static function tags($regex = null)
    {
        $tags = static::call('tag', '2>&1');

        if ($regex = unwrap($regex)) {
            foreach ($tags as $key => $tag) {
                if (! preg_match($regex, $tag)) {
                    unset($tags[$key]);
                }
            }
            $tags = array_values($tags);
        }

        return $tags;
    }

    public static function call($subcommand = '', $arguments = [])
    {
        return GitCommand::call(static::normalize_args($arguments, $subcommand));
    }

    public static function add_quiet_flag($arguments = [])
    {
        $arguments = (array)$arguments;
        if (! in_array('-q', $arguments) && ! in_array('-v', $arguments)) {
            $arguments[] = '-q';
        }

        return $arguments;
    }

    public static function add_short_flag($arguments = [])
    {
        $arguments = (array)$arguments;
        if (! in_array('-s', $arguments) && ! in_array('-l', $arguments)) {
            $arguments[] = '-s';
        }

        return $arguments;
    }

    public static function normalize_args($args = [], $subcommand = '')
    {
        $args = (array) $args;
        if (current($args) == 'git') {
            array_shift($args);
        }
        if (! empty($subcommand) && current($args) !== $subcommand) {
            array_unshift($args, $subcommand);
        }

        return $args;
    }
}