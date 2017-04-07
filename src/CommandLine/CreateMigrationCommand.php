<?php
namespace Worklog\CommandLine;

use Worklog\Database\Migration;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/17/17
 * Time: 2:27 PM
 */
class CreateMigrationCommand extends Command {

    public $command_name;

    public static $description = 'Generate a new Migration';
    public static $options = [];
    public static $arguments = [ 'name' ];
    public static $menu = false;

    protected static $exception_strings = [
        'unknown' => 'Unable to generate new Migration'
    ];


    public function run() {
        parent::run();

        $name = $this->expectData('name')->getData('name');
        $placeholder = "\t\t// code";

        $newMigration = new Migration(App()->db(), $name);
        $newMigration->set_up($placeholder);
        $newMigration->set_down($placeholder);

        if ($newMigration->generate()) {
            return sprintf('Created: %s', str_replace('.php', '', $newMigration->name()));
        } else {
            throw new \Exception(static::$exception_strings['unknown']);
        }
    }
}