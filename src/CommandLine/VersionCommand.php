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

    const MINIMUM_VERSION = '3.0.0';


    /**
     * Register sub-commands
     */
    public function init()
    {
        if (! $this->initialized()) {
            $this->registerSubcommand('check');
            $this->registerSubcommand('latest');
            $this->registerSubcommand('increment');
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
                $response = sprintf('Later version %s available', $latest);

                if ($revision_message = Git::commit_message($latest)) {
                    $response .= "\n".$revision_message;
                }
                return $response;
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

    protected function _test()
    {
        // test harness method
    }

    /**
     * @throws Exception
     */
    protected function _increment()
    {
        if (DEVELOPMENT_MODE) {

            if (! $this->gitTagFor('HEAD')) {
                // on a commit with no TAG
                //
                $output = Git::show_origin();
                $output = end($output);
                $status = (is_string($output) ? trim($output) : '');
                //
                $use_text_editor = true;

                if (false !== stripos($status, 'up to date')) {
                    $new = null;

                    $prompt_version = function($guess) {
                        $new = false;
                        if ($input = Input::ask('What is the new version? ('.$guess.'): ', $guess)) {
                            $input = trim($input);
                            if (strlen($input)) {
                                $new = $input;
                            }
                        }
                        return $new;
                    };

                    $prompt_message_text = function($message = null, $prompt = 'Commit message: ') use ($use_text_editor) {
                        if ($use_text_editor) {
                            $input = Input::text($prompt, $message);
                            if ($input !== $message) {
                                Output::text_response($input, $prompt);
                            }
                        } else {
                            if ($message) {
                                $prompt = str_replace(': ', ' ('.$message.'): ', $prompt);
                            }

                            $input = Input::ask($prompt, $message);
                        }

                        if ($input/* = Input::ask($prompt, $message)*/) {
                            $input = trim($input);
                            if (strlen($input)) {
                                $message = $input;
                            }
                        }
                        return $message;
                    };

                    banner(
                        "Given a version number MAJOR.MINOR.PATCH, increment the:\n".
                        "    MAJOR version when you make incompatible API changes,\n".
                        "    MINOR version when you add functionality in a backwards-compatible manner, and\n".
                        "    PATCH version when you make backwards-compatible bug fixes.",
                        'Semantic Versioning', 'blue');

                    // get latest, increment
                    $latest = $this->_latest();
                    $parts = explode('.', $latest);
                    $parts[2]++;
                    $guess = implode('.', $parts);

                    $new = $prompt_version($guess);
                    while (! preg_match('/^(\d+\.)?(\d+\.)?(\*|\d+)$/', $new)) {
                        printl('You must enter a valid version string, eg. MAJOR.MINOR.PATCH');
                        $new = $prompt_version($guess);
                    }

                    $annotation = '';
                    if ($input = $prompt_message_text(Git::commit_message('HEAD'), 'Annotated tag description: ')) {
                        $annotation = trim($input);
                    }

                    $commit_default = $annotation;

                    if (false !== strpos($commit_default, "\n")) {
                        $parts = explode("\n", $commit_default);
                        $commit_default = current($parts);
                    }
                    $message = $prompt_message_text('['.$new.'] '.$commit_default);
                    while (empty($message)) {
                        printl('You must enter a commit message');
                        $message = $prompt_message_text('['.$new.'] '.$commit_default);
                    }

                    // get commit -a -m "<message>"
                    Git::call('commit -a '.($message ? '-m '.escapeshellarg($message).' ' : ''));

                    // get tag -a <tag> -m "<message>"
                    Git::call('tag '.($annotation ? '-a -m '.escapeshellarg($annotation).' ' : '').$new);

                    // get push origin master --tags
                    Git::call('push origin master --tags');

                    return $this->_current();

                } else {
                    $exception_message = 'Cannot increment, local branch is in an invalid state';

                    if (false !== stripos($status, 'fast-forwardable')) {
                        $exception_message .= ': push your local changes to the remote branch';
                    } else {
                        $paren_start = strpos($status, '(') + 1;
                        $paren_end = strpos($status, '(', $paren_start);
                        if (false !== $paren_start && false !== $paren_end) {
                            $status = substr($status, $paren_start, ($paren_end - $paren_start));
                            $exception_message .= ': '.$status;
                        }
                    }
                    

                    throw new \Exception($exception_message);
                }
            } else {
                $result = $this->_check($this->_current(), true);
                switch ($result) {
                    case -1:
                        // current version lower than the latest
                        throw new \Exception('You are on an older version and cannot increment');
                        break;
                    case 0:
                        // current version same as the latest
                        throw new \Exception('You are on the latest version');
                        break;
                    case 1:
                        // current version higher than the latest
                        throw new \Exception('You are on a newer version... Are you a wizard?');
                        break;
                }
            }
        } else {
            throw new \Exception('You cannot increment the version number in production mode');
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
                if ($this->flag('f') || $this->_check($this->_current(), true) < 0) { // version_compare($current, $latest)
                    if ($hash = $this->gitHashForTag($tag)) {
                        $switched_to = $tag;
                        Command::call(GitCommand::class, 'fetch -q');
                        Command::call(GitCommand::class, sprintf('checkout %s -q', $hash));
                        Command::call(ComposerCommand::class, 'install');
                    }
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
        Git::fetch(true);
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
        Git::fetch(true);
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
        Git::fetch(true);

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
