<?php

use CSATF\CommandLine\Command;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
	// $loader->add('Acme\\Test\\', __DIR__);
}

$minimum_php_version = '5.4.28';
if (defined('MINIMUM_PHP_VERSION')) {
	$minimum_php_version = MINIMUM_PHP_VERSION;
}
if (version_compare(phpversion(), $minimum_php_version, '<')) {
	printf("PHP %s required, current version of PHP is %s. ", $minimum_php_version, phpversion());
	exit(2);
}


/*
 * ENVIRONMENT defined in app_env.php
 */
$user_path = exec('echo ~');
chdir(getenv('DEV_FILES_ROOT') ?: $user_path.'/www/stars20');
$CURRENT_DIR = getcwd();
$script = (isset($argv) ? ltrim(ltrim(basename($argv[0], '.'), '/')) : basename(__FILE__));

if (defined('DATABASE_CONFIG_PROFILE')) {
	$db_config = DATABASE_CONFIG_PROFILE;
} else {
	$db_config = 'default';
}

/**
 * Config for cached data
 */
if (defined('CACHE_DIRECTORY')) {
	$cache_dir = '/'.ltrim(CACHE_DIRECTORY, '/');
} else {
	$cache_dir = '/dev_cache';
}
define('CACHE_PATH', $user_path.$cache_dir);
define('ONE_HOUR_IN_SECONDS', 60*60);
define('CI_BOOTSTRAP_ROOT', $CURRENT_DIR);
define('PORTAL_ROOT', $CURRENT_DIR.'/portal');
define('IS_CLI', php_sapi_name() == "cli");
define('SCRIPT_NAME', $script);

if (defined('STDIN')) {
    chdir(dirname(__FILE__));
}
$env_file = CI_BOOTSTRAP_ROOT.'/app_env.php';
if (file_exists($env_file)) {
    include($env_file);
}
$system_path = CI_BOOTSTRAP_ROOT.'/system';
if (realpath($system_path) !== FALSE) {
    $system_path = realpath($system_path).'/';
}
$system_path = rtrim($system_path, '/').'/';
if ( ! is_dir($system_path)) {
    exit("Your system folder path does not appear to be set correctly.");
}
// Set BASEPATH before including CodeIgniter file
define('BASEPATH', str_replace("\\", "/", $system_path));
include(CI_BOOTSTRAP_ROOT.'/applications/jobs/config/'.(ENVIRONMENT == 'local' ? 'development' : ENVIRONMENT).'/database.php');
include(CI_BOOTSTRAP_ROOT.'/applications/jobs/third_party/composer/autoload.php');

if (defined('APPLICATION_PATH')) {
	chdir(APPLICATION_PATH);
	$CURRENT_DIR = getcwd();
}

// Change config profile string into the array at that offset in $db
$db_config = $db[$db_config];
$db = new CSATF\Database\Drivers\PostgresDatabaseDriver(
	$db_config['hostname'], $db_config['database'], $db_config['username'], $db_config['password']
);

$errors = [];

Command::bind([ 'listopts', 'commands' ], 'CSATF\CommandLine\ListOptionsCommand');
// CSATF\CommandLine\Command::bind('logs', 'CSATF\CommandLine\ViewLogsCommand');
CSATF\CommandLine\Command::bind([ 'clear-cache', 'clean' ], 'CSATF\CommandLine\ClearCacheCommand');
CSATF\CommandLine\Command::bind([ 'view-cache', 'cache' ], 'CSATF\CommandLine\ViewCacheCommand');
// CSATF\CommandLine\Command::bind('table-search', 'CSATF\CommandLine\DatabaseTableSearchCommand');
// CSATF\CommandLine\Command::bind([ 'table-info', 'table' ], 'CSATF\CommandLine\DatabaseTableInfoCommand');
// CSATF\CommandLine\Command::bind([ 'table-data', 'data' ], 'CSATF\CommandLine\DatabaseTableDataCommand');

/**
 * Get Application (singleton) instance
 */
function App() {
	static $App;
	global $db, $CURRENT_DIR, $user_path;
	if (! isset($App)) {
		$App = new CSATF\Application($db, $CURRENT_DIR, $user_path/*, SQL_FILES_DIRECTORY, JIRA_DATA_DIRECTORY*/);
	}
	return $App;
}

/**
 * Print a value
 * @param  mixed $value
 */
function dd($value) {
	dump($value);
	die();
}

function dump($value) {
	if (is_scalar($value)) {
		print $value;
	} else {
		var_dump($value);
	}
}

/**
 * Add an error to the errors array
 */
function error($error_msg = null, $command = null) {
	global $errors;
	if (!is_null($error_msg)) {
		if (IS_CLI) {
			printl($error_msg);
		} else {
			$errors[] = $error_msg;
		}
	}
	if (! is_null($command) && IS_CLI) {
		usage($command);
	}
}

/**
 * Output errors and return with status code 1
 */
function error_exit($error_msg = null, $exit_code = 1) {
	global $errors;
	error($error_msg);
	show_errors();
	exit($exit_code);
}
/**
 * Output errors
 * @return [type] [description]
 */
function show_errors() {
	global $errors;
	if (count($errors)) {
		printl(implode("\n", $errors));
	}
}

/**
 * Handle the Application->run() result
 * @param  mixed $result The return value of the Application->run() method
 */
function handle_result($result = null) {
	$nested_array = false;
	$coerce_string = App()->Command()->Options()->exist('s');
	
	if (is_array($result)) {
		foreach ($result as $key => $value) {
			if ($nested_array = is_array($value)) {
				break;
			}
		}
		if (! $nested_array && $coerce_string) {
			$result = implode(' ', $result);
		}
	}
	
	if (is_array($result)) {
		if (IS_CLI && ! $coerce_string) {
			if (! empty($result)) {
				print json_encode($result, JSON_PRETTY_PRINT);
			}
		} else {
			print json_encode($result);
		}
	} elseif ($result) {
		dump($result);
	}
}

function printl($value = '') {
	dump($value);
	print "\n";
}

/**
 * Local implementation of readline
 * @param  string $prompt The prompt message
 * @return string The user input
 */
if (! function_exists('readline')) {
	function readline($prompt = null) {
	    if ($prompt) echo $prompt;
	    $fp = fopen("php://stdin","r");
	    $line = rtrim(fgets($fp, 1024), "\r\n");
	    return $line;
	}
}