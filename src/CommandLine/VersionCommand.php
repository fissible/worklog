<?php

namespace Worklog\CommandLine;

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

    public function init()
    {
        if (! $this->initialized()) {
            $this->registerSubcommand('switch');

            parent::init();
        }
    }

    public function run()
    {
        parent::run();

        BinaryCommand::collect_output();
//        $currentCommitHash = $this->gitHashFor();

        if ($subcommand = $this->argument('subcommand')) {
            return $this->runSubcommand($subcommand);
        }

        if ($tag = $this->gitTagFor('HEAD')) {
            return $tag;
        } else {
            return 'Your local repository is not synced with a particular version.';
        }
    }


    /**
     * @return mixed
     */
    protected function _switch()
    {
        if ($hash = $this->gitHashForTag($this->getData('version'))) {
            Command::call(GitCommand::class, 'fetch');
            Command::call(GitCommand::class, sprintf('checkout %s', $hash));
            Command::call(ComposerCommand::class, 'install');
        }

        return $hash;
    }

    /**
     * Get commit hash for the specified revision
     * @param string $revision_specifier
     * @return mixed
     */
    private function gitHashFor($revision_specifier = 'HEAD')
    {
        return unwrap(Command::call(GitCommand::class, sprintf('rev-parse %s', $revision_specifier)));
    }

    /**
     * Get commit hash for the specified tag/version
     * @param $tag
     * @return mixed
     */
    private function gitHashForTag($tag)
    {
        return unwrap(Command::call(GitCommand::class, sprintf('rev-list -n 1 %s', $tag)));;
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

        $result = Command::call(
            GitCommand::class,
            sprintf(
                "show-ref --tags -d | grep ^%s | sed -e 's,.* refs/tags/,,' -e 's/\\^{}//'",
                $commitHash
            )
        );

        if ($result) {
            return unwrap($result);
        }

        return false;
    }
}
