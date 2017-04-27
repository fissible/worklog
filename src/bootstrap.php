<?php
/**
 * Worklog application bootstrap
 *
 * @author      Allen McCabe
 */

use Worklog\Application;
use Worklog\CommandLine\Command;
use Worklog\CommandLine\Output;

date_default_timezone_set('America/Los_Angeles');

include(__DIR__.'/functions.php');

define('APPLICATION_PATH', __DIR__);
$app_dir = dirname(APPLICATION_PATH);
define('VENDOR_PATH', $app_dir.'/vendor');
$cache_dir = '/'.trim((getenv('CACHE_DIRECTORY') ?: 'cache'), '/');
$script = (isset($argv) ? ltrim(ltrim(basename($argv[0], '.'), '/')) : basename(__FILE__));
$loader = require(VENDOR_PATH.'/autoload.php');
$dotenv = new Dotenv\Dotenv($app_dir);
$dotenv->load();

define('MINIMUM_PHP_VERSION', '5.5.0');

define('CACHE_PATH', $app_dir.$cache_dir);
define('DATABASE_PATH', dirname(APPLICATION_PATH).'/database');
define('DATABASE_MIGRATIONS', DATABASE_PATH.'/migrations');
define('DEFAULT_COMMAND', 'today');
define('DEVELOPMENT_MODE', env('DEVELOPMENT_MODE'));
define('IS_CLI', php_sapi_name() == "cli");
define('ONE_HOUR_IN_SECONDS', 60*60);
define('SCRIPT_NAME', $script);

if (defined('MINIMUM_PHP_VERSION')) {
    if (version_compare(phpversion(), MINIMUM_PHP_VERSION, '<')) {
        printf("PHP %s required, current version of PHP is %s. ", MINIMUM_PHP_VERSION, phpversion());
        exit(2);
    }
}

if (defined('STDIN')) {
    chdir(dirname(__FILE__));
}

if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    // $loader->add('Acme\\Test\\', __DIR__);
}

Output::init(env('ALLOW_UNICODE_OUTPUT', false), env('MAX_LINE_LENGTH', 120));

/*********************************************/
// Bind commands
/*********************************************/

Command::bind('list', 'Worklog\CommandLine\ListCommand');
Command::bind('add', 'Worklog\CommandLine\WriteCommand');
Command::bind('edit', 'Worklog\CommandLine\EditCommand');
Command::bind('stop', 'Worklog\CommandLine\StopCommand');
Command::bind('help', 'Worklog\CommandLine\UsageCommand');
Command::bind('today', 'Worklog\CommandLine\TodayCommand');
Command::bind('start', 'Worklog\CommandLine\StartCommand');
Command::bind('test', 'Worklog\CommandLine\PhpunitCommand');
Command::bind('env', 'Worklog\CommandLine\UpdateEnvCommand');
Command::bind('detail', 'Worklog\CommandLine\DetailCommand');
Command::bind('delete', 'Worklog\CommandLine\DeleteCommand');
Command::bind('report', 'Worklog\CommandLine\ReportCommand');
Command::bind('cancel', 'Worklog\CommandLine\CancelCommand');
Command::bind('recover', 'Worklog\CommandLine\RecoverCommand');
Command::bind('migrate', 'Worklog\CommandLine\MigrateCommand');
Command::bind('listopts', 'Worklog\CommandLine\ListOptionsCommand');
Command::bind('view-cache', 'Worklog\CommandLine\ViewCacheCommand');
Command::bind('clear-cache', 'Worklog\CommandLine\ClearCacheCommand');
//Command::bind('table-info', 'Worklog\CommandLine\DatabaseTableInfoCommand');
//Command::bind('table-data', 'Worklog\CommandLine\DatabaseTableDataCommand');
Command::bind('migrate:status', 'Worklog\CommandLine\MigrationStatusCommand');
Command::bind('make:migration', 'Worklog\CommandLine\CreateMigrationCommand');
Command::bind('migrate:rollback', 'Worklog\CommandLine\MigrateRollbackCommand');
Command::bind('table-search', 'Worklog\CommandLine\DatabaseTableSearchCommand');

$db = database(getenv('DATABASE_DRIVER'));

// Check the .env file
if (Application::check_env_file()) {
    Output::line('The Environment file is out of sync with the example...');
    Application::update_env_file();
}