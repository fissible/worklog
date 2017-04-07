<?php
namespace Worklog\CommandLine;

/**
 * ViewLogsCommand
 * Display the last n lines from a log file
 */
class ViewLogsCommand extends Command {

	public $command_name;

	public static $description = 'Display the last n lines (default is 10) from a log file';
	public static $options = [
		'd' => [ 'req' => true, 'description' => 'Specify a date (YYYY-MM-DD)' ],
		'f' => [ 'req' => null, 'description' => 'When end of file is reached wait for additional data to append' ],
		'n' => [ 'req' => true, 'description' => 'Limit to n lines' ],
		'z' => [ 'req' => null, 'description' => 'Hey Steve' ]
	];
	public static $arguments = [ 'app', 'headtail', 'grep' ];
	public static $usage = '%s <portal|quasar> [head|tail] [grep] [-dfn]';
	public static $menu = true;

	public function run() {
		$output = '';
		$app = $this->getData('app');
		$headtail = $this->getData('headtail') ?: 'tail';
		$grep = $this->getData('grep');
		$follow = $this->option('f');
		$lines = $this->option('n');

		if ($this->option('z')) {
			return "Hey Steve";
		}

		if (! in_array($headtail, [ 'head', 'tail' ])) {
			$grep = $headtail;
			$headtail = 'tail';
		}

		if ($optd = $this->option('d')) {
			$optd = substr(trim($this->option('d'), '\'"'), 0, 10);
		}
		$date = date("Y-m-d", strtotime($optd ?: 'now'));
		$filepath = CI_BOOTSTRAP_ROOT;

		if ($follow) {
			if ($headtail == 'head')
				throw new \Exception('Cannot follow with "head".');
				
			if (! is_null($optd) && $optd != date("Y-m-d"))
				throw new \Exception('Cannot follow a log file from the past.');
		}

		switch ($app) {
			case 'p':
			case 'portal':
				$app = 'portal';
				$filepath .= sprintf('/portal/storage/logs/laravel-%s.log', $date);
				break;
			case 'q':
			case 'quasar':
			case 'stars':
				$app = 'quasar';
				$filepath .= sprintf('/applications/jobs/logs/log-%s.php', $date);
				break;
			default:
				throw new \InvalidArgumentException('The application must be specified as the first command argument.');
				break;
		}

		// example command: tail ~/www/stars20/portal/storage/logs/laravel-2017-01-11.log | grep --line-buffered \"Subject:\" | sed 's@\\@@g'

		// substitution: head|tail
		$command = "%s ";

		if ($grep) {
			$lines = $this->count_log_file_lines($filepath);
		}

		if (! is_null($lines) && is_numeric($lines) && intval($lines) > 0) {
			$command .= sprintf("-n %d ", $lines);
		}

		// substitution: filepath
		if ($follow) {
			$command .= "-f %s > `tty` ";
		} else {
			$command .= "%s ";
		}

		if ($grep) {
			$command .= sprintf("| grep --line-buffered \"%s\" | sed 's@\\\\@@g'", $grep);
		}

		exec(vsprintf($command, [$headtail, $filepath]), $output);
		$output = implode("\n", $output);

		return $output;
	}

	private function count_log_file_lines($filepath) {
		$linecount = 0;
		$handle = fopen($filepath, "r");
		while(! feof($handle)) {
			$line = fgets($handle);
			$linecount++;
		}
		fclose($handle);

		return $linecount;
	}
}