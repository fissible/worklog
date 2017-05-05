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

    public static $options = [];

    public static $arguments = [ 'subcommand' ];

    protected $subcommands;
    

    public function init()
    {
        if (! $this->initialized()) {
            $this->setBinary(env('BINARY_GIT'));
            $this->registerSubcommand('record');
            $this->registerSubcommand('status');
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
        if ($output = $this->call('rev-parse --verify HEAD 2> /dev/null')) {
            $hash = $output[0];
            $output = $this->call([ 'show -s --format=%s 2> /dev/null', escapeshellarg($hash) ]);
            $message = $output[0];
        }

        return $message;
    }


    // subcommand implementations _{subcommand}()

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

    protected function _versions()
    {
        return $this->call('tag');
    }
}
