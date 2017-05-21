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
    

    public function run()
    {
        $this->init();

        $name = coalesce($this->argument('name'), $this->getData('name'));
        $type = coalesce($this->argument('type'), $this->getData('type'), $this->flag('t'));

        if (false === ($branch = Git::branch($name, $type))) {
        	throw new \Exception("Canceled. Branch not created...");
        }
        
        return $branch;
    }
}