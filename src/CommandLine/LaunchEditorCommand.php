<?php
namespace Worklog\CommandLine;

use Worklog\Filesystem\File;

/**
 * LaunchEditorCommand
 * Launch an editor to create/edit the SQL for given ticket number (value required)
 */
class LaunchEditorCommand extends Command {

	public $command_name;

	public static $description = 'Launch text-editor';
	private $required_data = [ 'file' ];
	private $text_editor_binary = 'vim';

	public function run() {
		parent::run();
		$file = File::sanitize($this->getData('file'));
		exec($this->text_editor_binary.' '.$file." > `tty`");
	}
}