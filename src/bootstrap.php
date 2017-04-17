<?php

use Worklog\CommandLine\Command;
use Worklog\CommandLine\Output;

$app_dir = dirname(__DIR__);
$loader = require $app_dir.'/vendor/autoload.php';
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
$dotenv = new Dotenv\Dotenv($app_dir);
$dotenv->load();
$dotenv->required('CACHE_DIRECTORY')->notEmpty();
if (false == ($cache_dir = getenv('CACHE_DIRECTORY'))) {
    $cache_dir = '/cache';
    if (defined('CACHE_DIRECTORY')) {
        $cache_dir = '/'.trim(CACHE_DIRECTORY, '/');
    }
}
define('CACHE_PATH', $app_dir.$cache_dir);
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
//$db = new Worklog\Database\Drivers\PostgresDatabaseDriver(
//	$db_config['hostname'], $db_config['database'], $db_config['username'], $db_config['password']
//);

Output::init((getenv('ALLOW_UNICODE_OUTPUT') === 'true'));

$errors = [];

Command::bind([ 'listopts', 'commands' ], 'Worklog\CommandLine\ListOptionsCommand');
// Worklog\CommandLine\Command::bind('logs', 'Worklog\CommandLine\ViewLogsCommand');
Worklog\CommandLine\Command::bind([ 'clear-cache', 'clean' ], 'Worklog\CommandLine\ClearCacheCommand');
Worklog\CommandLine\Command::bind([ 'view-cache', 'cache' ], 'Worklog\CommandLine\ViewCacheCommand');
// Worklog\CommandLine\Command::bind('table-search', 'Worklog\CommandLine\DatabaseTableSearchCommand');
// Worklog\CommandLine\Command::bind([ 'table-info', 'table' ], 'Worklog\CommandLine\DatabaseTableInfoCommand');
// Worklog\CommandLine\Command::bind([ 'table-data', 'data' ], 'Worklog\CommandLine\DatabaseTableDataCommand');

/**
 * Get Application (singleton) instance
 */
function App() {
	static $App;
	global $db, $CURRENT_DIR, $user_path;
	if (! isset($App)) {
		$App = new Worklog\Application($db, $CURRENT_DIR, $user_path/*, SQL_FILES_DIRECTORY, JIRA_DATA_DIRECTORY*/);
	}
	return $App;
}

function caller($key = null, $index = 0) {
    $trace = debug_backtrace(false);
    $caller = $trace[$index];
    /*
    [   'file' => "... /src/Models/Task.php"
        'line' => 172
        'function' => "caller"
        'args' => []                            ]
     */
    if (! is_null($key)) {
        if (array_key_exists($key, $caller)) {
            return $caller[$key];
        } else {
            $output = $key;
            foreach ($caller as $caller_key => $val) {
                if (is_array($val)) continue;
                if (false !== strpos($key, $caller_key)) {
                    $output = str_replace($caller_key, $val, $output);
                }
            }

            return $output;
        }
    }

    return $caller;
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
//    if (IS_CLI) {
//        Output::line(caller('file:line', 1));
//    }
	if (is_scalar($value)) {
		print $value;
	} else {
		var_dump($value);
	}
}

function deprecated($class = null, $function = null, $line = null) {
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    if (is_null($class)) {
        $class = $caller['file'];
    }
    if (is_null($function)) {
        $function = $caller['function'];
    }
    if (is_null($line)) {
        $line = $caller['line'];
    }
    if (IS_CLI) {
        printl(sprintf('DEPRECATED - %s %s[%d]', $function, $class, $line));
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
		printl(implode("\n", Output::color($errors, 'red')));
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

if (! function_exists('readline_secret')) {
    function readline_secret($prompt = null) {
        if ($prompt) echo $prompt;
        exec('stty -echo');
        $line = trim(fgets(STDIN));
        exec('stty echo');
        printl();
        return $line;
    }
}

if (! function_exists('tap')) {
    function tap($value, $callback) {
        $callback($value);
        return $value;
    }
}

function database_config($key = '') {
    global $db_config;
    if ($key) {
        return $db_config[$key];
    } else {
        return $db_config;
    }
}

/**
 * @param $driver
 * @param int $attempts
 * @return mixed
 * @throws \Predis\Connection\ConnectionException
 */
function database($driver, $attempts = 0) {
    $Handle = null;
    $db_config = include(DATABASE_PATH.'/config/local.php');

    $config = $db_config[$driver];

    // Illuminate/Capsule BEGIN
    $_config = [];
    switch($driver) {
        case 'Sqlite':
            $_config = [
                'driver'   => 'sqlite',
                'database' => $config['path'],
                'prefix'   => '',
            ];
            break;
    }
    $Connection = new Worklog\Database\Connection($_config);
    // Illuminate/Capsule END

    if (false === strpos($driver, 'Worklog\\Database\\Drivers')) {
        $driver = 'Worklog\\Database\\Drivers\\'.$driver;
    }
    if (false === stripos($driver, 'DatabaseDriver')) {
        $driver .= 'DatabaseDriver';
    }
    if (false === stripos($driver, 'Driver')) {
        $driver .= 'Driver';
    }
    try {
        $Handle = new $driver($config);
    } catch (Predis\Connection\ConnectionException $e) {
        if ($attempts < 10) {
            passthru('bash '.APPLICATION_PATH.'/start-redis-server > /dev/null');
            sleep(1);
            return database($driver, $config, ++$attempts);
        } else {
            throw $e;
        }
    }

    return $Handle;
}

function mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT) {
    $str_len = mb_strlen($str);
    $pad_str_len = mb_strlen($pad_str);
    if (!$str_len && ($dir == STR_PAD_RIGHT || $dir == STR_PAD_LEFT)) {
        $str_len = 1; // @debug
    }
    if (!$pad_len || !$pad_str_len || $pad_len <= $str_len) {
        return $str;
    }

    $result = null;
    if ($dir == STR_PAD_BOTH) {
        $length = ($pad_len - $str_len) / 2;
        $repeat = ceil($length / $pad_str_len);
        $result = mb_substr(str_repeat($pad_str, $repeat), 0, floor($length))
            . $str
            . mb_substr(str_repeat($pad_str, $repeat), 0, ceil($length));
    } else {
        $repeat = ceil($str_len - $pad_str_len + $pad_len);
        if ($dir == STR_PAD_RIGHT) {
            $result = $str . str_repeat($pad_str, $repeat);
            $result = mb_substr($result, 0, $pad_len);
        } else if ($dir == STR_PAD_LEFT) {
            $result = str_repeat($pad_str, $repeat);
            $result = mb_substr($result, 0,
                    $pad_len - (($str_len - $pad_str_len) + $pad_str_len))
                . $str;
        }
    }

    return $result;
}