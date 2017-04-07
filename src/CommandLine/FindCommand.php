<?php
namespace Worklog\CommandLine;

/**
 * FindCommand
 * Find files
 */
class FindCommand extends Command {

	public $command_name;
	
	public static $description = 'File and directory locator';
	public static $arguments = [/* 'pathname', 'expression' */];
	public static $options = [
		'type' => ['req' => true, 'description' => 'Return files of specified type (d,f,l)'],
		'name' => ['req' => true, 'description' => 'Find files by name']
		// 'f' => ['req' => false, 'description' => 'Filter output based on conditions provided (default [])'],
		// 'format' => ['req' => true, 'description' => 'Pretty-print containers using a Go template']
		// 'k' => ['req' => null, 'description' => 'Return commands that take arguments']
	];
	public static $menu = false;
	private $command_binary = '/usr/bin/find';
	private $type;
/*
Syntax
     find [-H | -L | -P] [-EXdsx] [-f pathname] [pathname ...] expression

     $ find location comparison-criteria search-term


 */
	public function run() {
		// $args = Options::argv();
		$arguments = $this->data(); /* find ->[pathname, pathname, expression]<- */
		$this->type = $this->option('type');

		if (IS_CLI) {
			// find files by name: 	$ find ./test -name "*.php"
			// ignore case: 		$ find ./test -iname "*.Php"
			// limit depth:			$ find ./test -maxdepth 2 -name "*.php"
			// invert match:		$ find ./test -not -name "*.php"
			// ! instead of not:	$ find ./test ! -name "*.php"
			// multi criteria 		$ find ./test -name 'abc*' ! -name '*.php'
			// or operator:			$ find -name '*.php' -o -name '*.txt'
			// Only files:			$ find ./test -type f -name "abc*"
			// Only directories:	$ find ./test -type d -name "abc*"


			if (empty($arguments)) {
				$find_files_dirs_or_both = strtolower(readline('Do you wish to find files, directories, or both? [f/d/b]: '));
				if (in_array($find_files_dirs_or_both, [ 'b', 'c', 'd', 'f', 'l', 'p', 's' ])) {
					if (! $this->type) {
						// $this->Options()->Option('type', $find_files_dirs_or_both);
						$this->type = $find_files_dirs_or_both;
					}
				} else {
					// $hint_rerun_tickets[] = $ticket;
				}
			}
		}

		var_dump($arguments);
		var_dump($this->Options()->all());
		/*
		ifind --type d --name '*.php' .

			var_dump($arguments);

				array(1) {
				  0 => "."
				}

			var_dump($this->Options()->all());

				array(2) {
				  "type" => "d"
				  "name" => "*.php ."
				}
		 */

		// exec($this->command_binary.escapeshellarg($arguments ? ' '.implode(' ', $arguments) : '')." > `tty`");
	}
}