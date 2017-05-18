<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 5/11/17
 * Time: 9:00 AM
 */

namespace Worklog\Services;

use Worklog\CommandLine\Input;
use Worklog\CommandLine\GitCommand;

class Git
{
    protected static $fetched = false;

    protected static $current_branch;

    protected static $branches;

    const BRANCH_TYPE_FEATURE = 'feature';

    const BRANCH_TYPE_RELEASE = 'release';

    const BRANCH_TYPE_HOTFIX = 'hotfix';


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

    public static function branch(/* str $name, str $type */)
    {
        if (! isset(static::$current_branch) || ! isset(static::$branches)) {
            $current = '';
            foreach (static::call('branch') as $key => $branch) {
                if (false !== ($pos = strpos($branch, '*'))) {
                    $current = substr_replace($branch, '', $pos, 1);
                } else {
                    if (! isset(static::$branches)) {
                        static::$branches = [];
                    }
                    static::$branches[] = trim($branch);
                }
            }
            if ($current) {
                static::$current_branch = $current;
                array_unshift(static::$branches, $current);
            }
        }
        
        // git branch "<name>"
        if ($name = func_get_arg(0)) {
            $type = self::BRANCH_TYPE_FEATURE;

            // git branch "<type>/<name>"
            if (false !== strpos($name, '/')) {
                $parts = explode('/', $name, 2);
                $name = $parts[1];
                $type = $parts[0];
            }

            if (! in_array($name, static::$branches)) {
                // branch does not exist

                debug($name.' does not exist', 'purple');


                $types = [ self::BRANCH_TYPE_FEATURE, self::BRANCH_TYPE_RELEASE, self::BRANCH_TYPE_HOTFIX ];

                // git branch "<name>" "<type>"
                $type = func_get_arg(1) ?: $type;

                if (false === $type || ! in_array($type, $types)) {
                    if (IS_CLI) {
                        $type = Input::ask('What type of branch ([f]eature, [r]elease, [h]otfix)?', $type);
                    }
                }

                switch ($type) {
                    case self::BRANCH_TYPE_FEATURE:
                        $base_branch = 'development';
                        $checkout_branch = 'master-development';
                        break;
                    case self::BRANCH_TYPE_RELEASE:
                        $base_branch = 'development';
                        $checkout_branch = 'master-development';
                        break;
                    case self::BRANCH_TYPE_HOTFIX:
                        $base_branch = 'master';
                        $checkout_branch = 'master';
                        break;
                }

                if (in_array($type, $types)) {
                    $new_branch_name = sprintf('%s-%s-%s', $base_branch, $type, $name);
                    $create = true;

                    debug(compact('new_branch_name'), 'green');

                    static::call('checkout -b '.$checkout_branch);
                    static::call('pull');

                    if (IS_CLI) {
                        $create = Input::confirm(sprintf('Create new branch "%s"', $new_branch_name), true);
                    }
                    if ($create) {
                        static::call(sprintf('checkout -b %s', $new_branch_name));
                        static::call(sprintf('push --set-upstream origin %s', $new_branch_name));

                        return $new_branch_name;
                    } else {
                        return false;
                    }
                } else {
                    Log::warn(sprintf("Invalid branch type '%s'", $type));
                    throw new \InvalidArgumentException("Invalid branch type");
                }
                
            } else {
                // it does exist, now what?
                throw new \InvalidArgumentException("Branch already exists");
            }
        }

        return static::$branches;
    }

    /**
     * Get branches, filter by regex or exact name
     */
    public static function branches($regex = null)
    {
        $branches = static::branch();
        
        if (! is_null($regex) && ! empty($branches)) {
            foreach ($branches as $key => $branch) {
                if ($branch === $regex) return [ $branch ]; // early return
                if (! preg_match($regex, $branch)) {
                    unset($branches[$key]);
                }
            }
        }

        return $branches;
    }

    /**
     * @param string $revision
     * @return string
     */
    public static function commit_message($revision = 'HEAD')
    {
        $message = '';

        if ($revision) {
            $matches_tag = preg_match('/^(\d+\.)?(\d+\.)?(\d+)$/', $revision);
            $original = GitCommand::collect_output();

            if ($matches_tag && ($output = unwrap(static::call(sprintf('tag -n -l %s', $revision))))) {
                $message = $output;
                if (false !== ($tag_pos = strpos($output, $revision))) {
                    $message = trim(substr_replace($output, '', strpos($output, $revision), strlen($revision)));
                }
            } else {
                if ($output = unwrap(static::call('rev-parse --verify '.$revision.' 2> /dev/null'), false)) {
                    if ($output = unwrap(static::call([ 'show -s --format=%s 2> /dev/null', escapeshellarg($output) ]), false)) {
                        $message = $output;
                    }
                }
            }

            GitCommand::collect_output($original);
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
        $result = null;
        $arguments = [];
        if (! DEVELOPMENT_MODE) $arguments = static::add_quiet_flag($arguments);

        // debounce... kinda
        if (! static::$fetched || (strtotime('now') - static::$fetched) > 30) {
            $result = static::call('fetch', $arguments);
            static::$fetched = strtotime('now');
        }
        
        return $result;
    }

    public static function hash($revision = 'HEAD')
    {
        return static::call('rev-parse', $revision);
    }

    public static function push($arguments = [])
    {
        return static::call('push', $arguments);
    }

    public static function show_origin($short = false)
    {
        $output = static::call('remote show origin');
        if ($short) {
            $output = end($output);
            $status = (is_string($output) ? trim($output) : '');
        }
        return $output;
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