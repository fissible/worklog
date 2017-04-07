<?php

namespace Worklog\CommandLine;

use Worklog\Database\Migration;
use Worklog\CommandLine\Command as Command;


/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/17/17
 * Time: 11:33 AM
 */
class MigrateCommand extends Command
{
    public $command_name;

    public static $description = 'Run database migrations.';
    public static $options = [];
    public static $arguments = [ 'name' ];
    public static $menu = false;

    public function run() {
        parent::run();

        Migration::set_migrations_path(DATABASE_MIGRATIONS);

        $MigrationMain = new Migration(App()->db());
        $MigrationMain->run();

        if ($name = strtolower($this->getData('name'))) {
            $Migration = new Migration(App()->db(), $name);

            if ($Migration->is_fresh()) {
                if ($result = $Migration->run(true)) {
                    printl(Output::color('Migrated: '.str_replace('.php', '', $Migration->name()).':up', 'green'));
                } else {
                    printl(Output::color('Error running Migration.', 'red'));
                }
            } else {
                printl('Migration already run.');
            }

        } else {
            if ($Migrations = $MigrationMain->get()) {
                foreach ($Migrations as $key => $Migration) {
                    if (! $Migration->is_fresh()) {
                        unset($Migrations[$key]);
                    }
                }
                if (count($Migrations)) {
                    $Migrations = $MigrationMain->sort($Migrations);
                    foreach ($Migrations as $Migration) {
                        if ($result = $Migration->run(true)) {
                            printl(Output::color('Migrated: ' . str_replace('.php', '', $Migration->name()), 'green'));
                        } else {
                            printl(Output::color('Error running Migration ' . str_replace('.php', '', $Migration->name()) . ':up', 'red'));
                        }
                    }
                } else {
                    printl('Nothing to migrate.');
                }
            } else {
                printl('Nothing to migrate.');
            }
        }
    }
}