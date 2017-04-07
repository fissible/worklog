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
class MigrateRollbackCommand extends Command
{
    public $command_name;

    public static $description = 'Roll back database migrations.';
    public static $options = [];
    public static $arguments = [ 'name' ];
    public static $menu = false;

    public function run() {
        parent::run();

        Migration::set_migrations_path(DATABASE_MIGRATIONS);


        ob_start();
        $MigrationMain = new Migration(App()->db());
        $result = $MigrationMain->run();
        $output = ob_get_clean();
        if (! $result) {
            throw new \Exception('Error creating migrations table: '.$output);
        }

        if ($name = strtolower($this->getData('name'))) {
            $Migration = new Migration(App()->db(), $name);

            if (! $Migration->is_fresh()) {
                if ($result = $Migration->run(false)) {
                    printl(Output::color('Migrated: '.str_replace('.php', '', $Migration->name()).':down', 'green'));
                } else {
                    printl(Output::color('Error running Migration.', 'red'));
                }
            } else {
                printl('Migration cannot be rolled back.');
            }

        } else {
            if ($Migrations = $MigrationMain->get()) {
                $Migrations = $MigrationMain->sort($Migrations, true);
                foreach ($Migrations as $key => $Migration) {
                    if ($Migration->is_fresh()) {
                        unset($Migrations[$key]);
                    }
                }
                if (count($Migrations)) {
                    foreach ($Migrations as $Migration) {
                        if ($result = $Migration->run(false)) {
                            printl(Output::color('Migrated: '.str_replace('.php', '', $Migration->name()), 'green'));
                        } else {
                            printl(Output::color('Error running Migration '.str_replace('.php', '', $Migration->name()).':down', 'red'));
                            printl(App()->db()->last_error());
                        }
                    }
                } else {
                    printl('Nothing to roll back.');
                }
            } else {
                printl('Nothing to roll back.');
            }
        }
    }
}