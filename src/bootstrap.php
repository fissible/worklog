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

define('APPLICATION_PATH', __DIR__);
define('ROOT_PATH', dirname(APPLICATION_PATH));
define('VENDOR_PATH', ROOT_PATH.'/vendor');
$cache_dir = '/'.trim((getenv('CACHE_DIRECTORY') ?: 'cache'), '/');
$script = (isset($argv) ? ltrim(ltrim(basename($argv[0], '.'), '/')) : basename(__FILE__));
$loader = require(VENDOR_PATH.'/autoload.php');
$dotenv = new Dotenv\Dotenv(ROOT_PATH);
$dotenv->load();

define('DEVELOPMENT_MODE', filter_var(getenv('DEVELOPMENT_MODE'), FILTER_VALIDATE_BOOLEAN));

include(__DIR__.'/functions.php');

define('MINIMUM_PHP_VERSION', '5.5.0');
define('CACHE_PATH', ROOT_PATH.$cache_dir);
define('DATABASE_PATH', dirname(APPLICATION_PATH).'/database');
define('DATABASE_MIGRATIONS', DATABASE_PATH.'/migrations');
define('DEFAULT_COMMAND', 'today');
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

Command::bind('start', 'Worklog\CommandLine\StartCommand');
Command::bind('cancel', 'Worklog\CommandLine\CancelCommand');
Command::bind('recover', 'Worklog\CommandLine\RecoverCommand');
Command::bind('stop', 'Worklog\CommandLine\StopCommand');

Command::bind('add', 'Worklog\CommandLine\WriteCommand');
Command::bind('detail', 'Worklog\CommandLine\DetailCommand');
Command::bind('edit', 'Worklog\CommandLine\EditCommand');
Command::bind('delete', 'Worklog\CommandLine\DeleteCommand');

Command::bind('list', 'Worklog\CommandLine\ListCommand');
Command::bind('report', 'Worklog\CommandLine\ReportCommand');
Command::bind('today', 'Worklog\CommandLine\TodayCommand');

Command::bind('text', 'Worklog\CommandLine\LaunchEditorCommand');

// Admin/Dev Commands
Command::bind('env', 'Worklog\CommandLine\UpdateEnvCommand');

Command::bind('migrate', 'Worklog\CommandLine\MigrateCommand');
Command::bind('make:migration', 'Worklog\CommandLine\CreateMigrationCommand');
Command::bind('migrate:status', 'Worklog\CommandLine\MigrationStatusCommand');
Command::bind('migrate:rollback', 'Worklog\CommandLine\MigrateRollbackCommand');

Command::bind('view-cache', 'Worklog\CommandLine\ViewCacheCommand');
Command::bind('clear-cache', 'Worklog\CommandLine\ClearCacheCommand');

Command::bind('table-search', 'Worklog\CommandLine\DatabaseTableSearchCommand');

// Binary Commands
Command::bind('vcr', 'Worklog\CommandLine\GitCommand');
Command::bind('test', 'Worklog\CommandLine\PhpunitCommand');
Command::bind('vendor', 'Worklog\CommandLine\ComposerCommand');

// Usage/autcompletion commands
Command::bind('listopts', 'Worklog\CommandLine\ListOptionsCommand');
Command::bind('help', 'Worklog\CommandLine\UsageCommand');

// Disabled Commands
//Command::bind('table-info', 'Worklog\CommandLine\DatabaseTableInfoCommand');
//Command::bind('table-data', 'Worklog\CommandLine\DatabaseTableDataCommand');

$db = database(getenv('DATABASE_DRIVER'));

// Check the .env file
if (Application::check_env_file()) {
    Output::line('The Environment file is out of sync with the example...');
    Application::update_env_file();
}
