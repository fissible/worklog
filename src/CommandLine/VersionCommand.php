<?php

namespace Worklog\CommandLine;
use Exception;
use Worklog\Services\Git;

/**
 * VersionCommand
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 5/10/17
 * Time: 10:50 AM
 */

class VersionCommand extends Command
{
    public $command_name;

    public static $description = 'Display application version';
    public static $options = [
//        'l' => ['req' => null, 'description' => 'Show all available information'],
//        's' => ['req' => null, 'description' => 'Show less information']
    ];
    public static $arguments = [ 'subcommand', 'version' ];
//    public static $usage = '%s [-ls] [opt1]';
    public static $menu = true;

    protected static $exception_strings = [
        'detached_head' => 'Your local repository is not synced with a particular version',
        'invalid_tag' => 'The supplied version does not exist'
    ];

    const MINIMUM_VERSION = '2.1.8';


    /**
     * Register sub-commands
     */
    public function init()
    {
        if (! $this->initialized()) {
            $this->registerSubcommand('check');
            $this->registerSubcommand('latest');
            $this->registerSubcommand('list');
            $this->registerSubcommand('switch');

            parent::init();
        }
    }

    public function run()
    {
        parent::run();

        BinaryCommand::collect_output();

        if ($subcommand = $this->argument('subcommand')) {
            return $this->runSubcommand($subcommand);
        }

        return $this->_current();
    }


    /**
     * Check if a newer version is available
     * @param null $tag
     * @param bool $internally_invoked
     * @return mixed
     * @throws Exception
     */
    protected function _check($tag = null, $internally_invoked = false)
    {
        $tags = $this->_list();

        // check method input, command input, default to current version
        $current = coalesce($tag, $this->getData('version'), $this->_current());
        $latest = $this->_latest();

        if (! in_array($current, $tags) || ! in_array($latest, $tags)) {
            throw new Exception(static::$exception_strings['invalid_tag']);
        }

        if ($internally_invoked) {
            return version_compare($current, $latest);
        } else {
            if (version_compare($current, $latest, '<')) {
                return sprintf('Later version %s available', $latest);
            } else {
                return 'You have the most up to date version';
            }
        }
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function _current()
    {
        if ($tag = $this->gitTagFor('HEAD')) {
            return $tag;
        } else {
            throw new Exception(static::$exception_strings['detached_head']);
        }
    }

    /**
     * @return array|null
     * @internal param bool $return_diff
     */
    protected function _latest()
    {
        $tags = $this->_list();
        return end($tags);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function _list()
    {
        Git::fetch(true);
        $tags = Git::tags();
        foreach ($tags as $key => $tag) {
            if (version_compare($tag, self::MINIMUM_VERSION, '<')) {
                unset($tags[$key]);
            }
        }
        usort($tags, 'version_compare');

        return $tags;
    }

    /**
     * @param null $tag
     * @param bool $internally_invoked
     * @return mixed
     */
    protected function _switch($tag = null, $internally_invoked = false)
    {
        $switched_to = false;

        if ($tag = coalesce($tag, $this->getData('version'), $this->_latest())) {
            if ($this->flag('f') || $this->valid($tag)) {
                if ($hash = $this->gitHashForTag($tag)) {
                    $switched_to = $tag;
                    Command::call(GitCommand::class, 'fetch -q');
                    Command::call(GitCommand::class, sprintf('checkout %s -q', $hash));
                    Command::call(ComposerCommand::class, 'install');
                }
            } else {
                throw new \InvalidArgumentException(static::$exception_strings['invalid_tag']);
            }
        } else {
            throw new \InvalidArgumentException(static::$exception_strings['invalid_tag']);
        }

        if ($internally_invoked) {
            return $switched_to;
        } else {
            if ($switched_to) {
                return sprintf('Switched to version %s', $switched_to);
            } else {
                return 'You have the most up to date version';
            }
        }
    }

    /**
     * Get commit hash for the specified revision
     * @param string $revision_specifier
     * @return mixed
     */
    private function gitHashFor($revision_specifier = 'HEAD')
    {
        return unwrap(Git::call(
            sprintf('rev-parse %s', $revision_specifier))
        );
    }

    /**
     * Get commit hash for the specified tag/version
     * @param $tag
     * @return mixed
     */
    private function gitHashForTag($tag)
    {
        return unwrap(Git::call(
            sprintf('rev-list -n 1 %s', unwrap($tag, false))
        ));
    }

    /**
     * Get a tag associated with the specified revision
     * @param string $revision_specifier
     * @param bool $skip_rev_parse
     * @return mixed
     */
    private function gitTagFor($revision_specifier = 'HEAD', $skip_rev_parse = false)
    {
        if ($skip_rev_parse) {
            $commitHash = $revision_specifier;
        } else {
            $commitHash = $this->gitHashFor($revision_specifier);
        }

        return unwrap(Git::call(
            sprintf(
                "show-ref --tags -d | grep ^%s | sed -e 's,.* refs/tags/,,' -e 's/\\^{}//'",
                $commitHash
            )
        ));
    }

    public function valid($version)
    {
        return in_array($version, $this->_list());
    }
}
