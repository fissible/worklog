<?php

namespace Worklog\CommandLine;

use Worklog\Services\Git;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 9:03 AM
 */

class GitBranchCommand extends GitCommand
{
	public static $description = 'Run git branch commands';

    public static $options = [
        // 'm' => ['req' => true, 'description' => 'Message text'],
        't' => ['req' => true, 'description' => 'Branch type (feature, release, hotfix)'],
    ];

    public static $arguments = [ 'name', 'type' ];

    private $branch;

    private $type;


    public function run()
    {
        $this->init();

        $this->branch = coalesce($this->argument('name'), $this->getData('name'));
    	$this->type = coalesce($this->argument('type'), $this->getData('type'), $this->flag('t'));
        
        return $this->branch();
    }

    protected function close()
    {
    	$Command = Command::instance('vcr');
    	$Command->init();
        return $Command->runSubcommand('close');
    }

    private function branch()
    {
    	if (false === ($branch = Git::branch($this->branch, $this->type))) {
        	throw new \Exception("Canceled. Branch not created...");
        }
        
        return $branch;
    }

    protected function commit()
    {
    	$Command = Command::instance('vcr');
    	$Command->init();
        return $Command->runSubcommand('commit');
    }

    protected function merge()
    {
    	$Command = Command::instance('vcr');
    	$Command->init();
        return $Command->runSubcommand('merge');
    }

    /*
	wlog feature <name>
	    wlog vcr branch <name> feature
	        switched to a new branch 'development-feature-test'

	wlog feature
	    wlog vcr commit -p
	    wlog vcr merge
	    wlog vcr close

	wlog hotfix <name>
	    wlog vcr branch <name> hotifx

	wlog hotfix
	    wlog vcr commit -p
	    wlog vcr merge
	    wlog vcr close

	wlog release <name>
	    wlog vcr branch <name> release

	wlog release
	    wlog vcr commit -p
	    wlog version increment
	        > 4.0.0
	        > Release new feature
	    wlog vcr merge
	    wlog vcr close
    */
}