<?php
namespace Worklog\CommandLine;

/**
 * ListOptionsCommand
 * Lists options this script takes (CLI)
 */
class ListOptionsCommand extends Command {

	public $command_name;

	public static $description = 'List CLI command options (these commands)';
	public static $options = [
		'a' => ['req' => null, 'description' => 'Include all commands'],
		'k' => ['req' => null, 'description' => 'Return commands that take arguments']
	];
	public static $usage = '[-ak] %s';
	public static $menu = false;

	public function run() {
		if (! isset(static::$registry) || empty(static::$registry)) {
			throw new \Exception('ListOptionsCommand: command registry not set');
		}
		return implode(' ', array_keys(array_filter(static::$registry, function ($info) {
			if ((isset($info['menu']) && $info['menu'] == true) || $this->option('a')) {
				if ($this->option('k')) {
					if (! empty($info['arguments'])) {
						return true;
					}
				} else {
					return true;
				}
			}
			return false;
		})));
	}
}