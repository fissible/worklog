<?php

namespace Worklog\CommandLine;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class GitCommand extends BinaryCommand
{
    public static $description = 'Run composer';

    public static $options = [
        'a' => ['req' => null, 'description' => 'Annotated tag'],
        'd' => ['req' => null, 'description' => 'Delete a tag'],
        'l' => ['req' => null, 'description' => 'Search for tags with a particular pattern'],
        'm' => ['req' => true, 'description' => 'Message text']
    ];

    public static $arguments = [ 'subcommand' ];

    protected $subcommands;
    

    public function init()
    {
        if (! $this->initialized()) {
            $this->setBinary(env('BINARY_GIT'));
            $this->registerSubcommand('diff');
            $this->registerSubcommand('record');
            $this->registerSubcommand('status');
            $this->registerSubcommand('tag');
            $this->registerSubcommand('versions');

            parent::init();
        }
    }

    public function run()
    {
        $this->init();

        if ($subcommand = $this->argument('subcommand')) {
            return $this->runSubcommand($subcommand);
        }
        
        return parent::run();
    }

    public function getCommitMessageAtRevision($revision = 'HEAD')
    {
        if ($output = $this->call('rev-parse --verify '.$revision.' 2> /dev/null')) {
            $hash = $output[0];
            $output = $this->call([ 'show -s --format=%s 2> /dev/null', escapeshellarg($hash) ]);
            $message = $output[0];
        }

        return $message;
    }


    // subcommand implementations _{subcommand}()

    protected function _diff()
    {
        $arguments = $this->arguments();
        $flags = $this->flags();

        $command = [ 'diff' ];

        if (isset($arguments[1])) {
            $command[] = $arguments[1];
        }
        
        return $this->call($command);
    }

    /**
     * Stage all files and commit to git repository
     */
    protected function _record()
    {
        // get last commit message
        $commit_message = $this->getCommitMessageAtRevision('HEAD');

        // cUrl silly commit message
        if ($output = $this->call(function($curl) {
            $curl->setRawCommand( 'curl -vs http://whatthecommit.com/index.txt 2> /dev/null', true);

            return $curl;
        }, BinaryCommand::class, false)) {
            $commit_message = $output[0];
        }

        if (IS_CLI) {
            if ($input = Input::ask('Commit message'.($commit_message ? ' ('.$commit_message.')' : '').': ', $commit_message)) {
                $input = trim($input);
                if (strlen($input)) {
                    $commit_message = $input;
                }
            }
        }
        
        $this->call(  'add .');
        $this->call([ 'commit -m', escapeshellarg($commit_message) ]);

    }

    protected function _status()
    {
        return $this->call('status --short');
    }

    /**
     *
     */
    protected function _tag()
    {
        $arguments = $this->arguments();
        $flags = $this->flags();
        $command = [ 'tag' ];

        $annotate = $this->flag('a') || ($this->flag('m') && (false === $this->flag('s') && false === $this->flag('u')));
        $delete = $this->flag('d');
        $lookup = ($this->flag('l') || $this->flag('n') || $this->flag('sort') || $this->flag('format'));

        switch (true) {
            case (false !== $annotate):
                $command[] = '-a';
                if (isset($arguments[1])) { //      [0] assumed to be "tag"
                    $command[] = $arguments[1]; //  [1] assumed to be the tag name
                }

                if ($message = $this->flag('m')) {
                    $command[] = '-m '.escapeshellarg($message);
                }
                break;
            case (false !== $delete):
                $command[] = '-d';
                if (isset($arguments[1])) { //      [0] assumed to be "tag"
                    $command[] = $arguments[1]; //  [1] assumed to be the tag name
                }
                break;
            default:
                foreach ($flags as $key => $flag) {
                    $command[] = '-'.ltrim($flag, '-');
                }
                foreach ($arguments as $key => $argument) {
                    if ($key == 0 && $argument == 'tag') {
                        continue;
                    }
                    $command[] = $argument;
                }
                break;
        }

        // git tag v1.4-lw                          - lightweight tag
        // git tag -a v1.4 -m "my version 1.4"      - annotated tag

        // git tag [-a | -s | -u <keyid>] [-f] [-m <msg> | -F <file>] <tagname> [<commit> | <object>]
        // git tag -d <tagname>...                  - delete tag

        // If -m <msg> or -F <file> is given and -a, -s, and -u <keyid> are absent, -a is implied.


        if (IS_CLI) {
            if ($annotate && ! isset($message)) {
                $prompt = 'Annotated tag description'.(array_key_exists('a', $flags) ? '' :' (optional)').': ';
                if ($input = Input::ask($prompt)) {
                    $message = trim($input);
                    if (strlen($message)) {
                        $command[] = '-m '.escapeshellarg($message);
                    }
                }
            }
        }

        return $this->call($command);
    }

    /**
     * @return mixed
     */
    protected function _versions()
    {
        return $this->call('tag');
    }
}
