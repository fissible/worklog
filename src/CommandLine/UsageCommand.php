<?php
namespace Worklog\CommandLine;

use \CSATF\CommandLine\Command as Command;

/**
 * UsageCommand
 */
class UsageCommand extends Command {

	public $command_name;

	public static $description = 'Display command menu';
	public static $options = [
		'l' => ['req' => null, 'description' => 'Show all available information'],
		's' => ['req' => null, 'description' => 'Show less information'],
		't' => ['req' => true, 'description' => 'Test flag: requires value']
	];
	public static $arguments = [ 'command' ];
	public static $usage = '%s [-ls] [opt1]';
	public static $menu = true;

	public function run() {
		parent::run();
		$commands = $this->getData('command');
		$long = $this->option('l');
		$short = $this->option('s');

		if (! is_array($commands)) {
			$commands = [ $commands ];
		}
		foreach ($commands as $key => $command) {
			if (! $this->validate_command($command)) {
				unset($commands[$key]);
			}
		}
		if (count($commands) > 0) {
			if (count($commands) > 1) {
				$short = true;
			}
			foreach ($commands as $command) {
				print implode($this->App()->menu($this->alias($command), $long, $short));
			}
		} else {
			print implode($this->App()->menu(null, $long, $short));
		}
	}
}