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
        if (! in_array('-m', $arguments))
        return static::call('commit', $arguments);
    }

    public static function diff($arguments = [])
    {
        return static::call('diff', $arguments);
    }

    public static function hash($revision = 'HEAD')
    {
        return static::call('rev-parse', $revision);
    }

    public static function push($arguments = [])
    {
        return static::call('diff', $arguments);
    }

    public static function tags($arguments = [])
    {
        return static::call('tag', $arguments);
    }

    public static function call($subcommand = '', $arguments = [])
    {
        return GitCommand::call(static::normalize_args($arguments, $subcommand));
    }

    public static function normalize_args($args = [], $subcommand = '')
    {
        $args = (array) $args;
        if (isset($args[0]) && $args[0] == 'git') {
            array_shift($args);
        }
        if ($subcommand && (! isset($args[0]) || $args[0] !== $subcommand)) {
            array_unshift($args, $subcommand);
        }

        return $args;
    }
}