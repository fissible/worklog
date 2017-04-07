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
class MigrationStatusCommand extends Command
{
    public $command_name;

    public static $description = 'Show database migrations.';
    public static $options = [];
    public static $arguments = [];
    public static $menu = false;

    public function run() {
        parent::run();

        $migration_data = [];

        Migration::set_migrations_path(DATABASE_MIGRATIONS);

        $MigrationMain = new Migration(App()->db());

        foreach ($MigrationMain->get() as $Migration) {
            $migration_data[] = [ ($Migration->is_fresh() ? 'N' : 'Y'), str_replace('.php', '', $Migration->name()) ];
        }

        return Output::data_grid([ 'Ran?', 'Migration' ], $migration_data);
    }
}