<?php
namespace Worklog\CommandLine;

/**
 * ClearCacheCommand
 * Deletes cache files
 */
class ClearCacheCommand extends Command {

	public $command_name;
	
	public static $description = 'Clear command line application cache (optionaly clear by tag(s))';
	public static $arguments = [ 'tags' ];
	public static $menu = false;

	public function run() {
		if ($tags = $this->getData('tags')) {
			$this->App()->Cache()->clear($tags, true);
		} else {
			$this->App()->Cache()->clear();
		}
		return 'OK';
	}
}