<?php
namespace Worklog\Database;

use Worklog\CommandLine\Output;
use Worklog\Filesystem\File;

/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 3/13/17
 * Time: 10:22 AM
 */
class Migration extends File
{
    protected $db;

    private $name;

    private $date;

    private $class_name;

    private $fresh;

    private $up_method;

    private $down_method;

    public static $migrations_path;

    protected static $setup;

    private static $schema = [
        'fields' => [
            'migration' => [ 'type' => 'text' ]
        ],
        'primary_keys' => []
    ];

    const DATABASE_TABLE = 'migrations';

    public function __construct($db, $name = 'BASE', $path = null)
    {
        $this->set_database_driver($db);

        if (! is_null($path)) {
            static::set_migrations_path($path);
        }

        if (! isset(static::$migrations_path)) {
            if (defined('DATABASE_MIGRATIONS')) {
                static::set_migrations_path(DATABASE_MIGRATIONS);
            } else {
                throw new \Exception('Migrations file path not configured');
            }
        }

        if ($name !== 'BASE') {
            if ($path = static::find($name)) {
                parent::__construct($path);
            } else {
                parent::__construct($this->get_file_path());
            }

            $this->set_migration_name($name)->set_date(); // create_task_table
            $this->set_class_name($name); // CreateTaskTable
        }

        if (! $this->is_setup()) {
            $this->setup();
        }
    }

    /**
     * Return the migration name (not the filename), eg. create_task_table
     * @return mixed
     */
    public function migration_name()
    {
        return $this->name;
    }

    /**
     * This sets the name as a snake_case_variant
     * @param $name
     * @return $this
     */
    public function set_migration_name($name)
    {
        if (! isset($this->name)) {
            if (! is_null($name) && $name !== 'BASE') {
                $name = str_replace('.php', '', basename($name));
                $this->name = static::snake_case(trim(trim($name), '.'));

//                if ($filename = static::find($this->name)) {
//                    $this->name = $name;
//                } else {
//                    $this->name = $this->prepend_date_string($name);
//                }
            }
        }

        return $this;
    }

    /**
     * This sets the short_name as a CamelCaseVariant
     * @param $name
     * @return $this
     */
    public function set_class_name($name)
    {
        if (! isset($this->class_name)) {
            $name = $this->trim_date_string(str_replace('.php', '', $name));
            $this->class_name = static::camel_case($name);
        }

        return $this;
    }

    public function set_date()
    {
        list($timestamp, $name) = static::trim_date_string(basename($this->get_file_path()), true);
        if ($timestamp) {
            // 2017_03_17_140949
            $parts = explode('_', $timestamp);
            $date = $parts[0].'-'.$parts[1].'-'.$parts[2];
            $time = substr($parts[3], 0, 2).':'.substr($parts[3], 2, 2);
            $this->date = \Carbon\Carbon::parse($date.' '.$time);
        }

        return $this;
    }

    /**
     * Return the path to the file, eg. /migrations/2017_3_14_090300_snake_case_variant.php
     * @return string
     */
    public function get_file_path()
    {
        $path = null;
//        $name = $this->name;
//        $name = $this->prepend_date_string($name);
//        if (false === strstr($name, '.php')) {
//            $name .= '.php';
//        }
        if ($name = static::find($this->name)) {
            $path = $name;
        } else {
            $path = static::$migrations_path.'/'.$this->prepend_date_string($this->name);
        }

        if (false === strpos($path, '.php')) {
            $path .= '.php';
        }

        return $path;
    }

    /**
     * Prefix the supplied string with a date_time string
     * @param $name
     * @return string
     */
    public function prepend_date_string($name)
    {
        if (! is_numeric(substr($name, 0, 4))) {
            $name = date("Y_m_d_His_").ltrim($name, '_');
        }

        return $name;
    }

    /**
     * Return the string without a date_time prefix string
     * @param $input
     * @param $return_both
     * @return string
     */
    public static function trim_date_string($input, $return_both = false)
    {
        $timestamp = '';
        $output = $input;
        if (is_numeric(substr($input, 0, 4))) {
            $strlen = strlen($input);
            $n = 0;
            for ($i = 0; $i < $strlen; $i++) {
                if ($input[$i] == '_') {
                    $n++;
                    if ($n >= 4) {
                        break;
                    }
                }
            }
            $timestamp = substr($input, 0, $i);
            $output = substr($input, $i+1, $strlen);
        }

        if ($return_both) {
            return [ $timestamp, $output ];
        } else {
            return $output;
        }

    }

    public static function set_migrations_path($path)
    {
        if (is_dir($path)) {
            static::$migrations_path = $path;
        } else {
            throw new \InvalidArgumentException(sprintf('Cannot set Migrations path, "%s" not found.', $path));
        }
    }

    public function date()
    {
        return $this->date;
    }

    public function set_database_driver(Driver $driver)
    {
        $this->db = $driver;
    }

    public function insert()
    {
        $migration_name = $this->migration_name();
        if (! is_null($migration_name)) {
            return $this->db->insertRow(self::DATABASE_TABLE, [ 'migration' => $migration_name ]);
        }
    }

    public function delete()
    {
        $migration_name = $this->migration_name();
        if (! is_null($migration_name)) {
            return $this->db->deleteRow(self::DATABASE_TABLE, [ 'migration' => $migration_name ]);
        }
    }

    public function is_fresh($force_check = false)
    {
        if ($this->is_setup() && ($force_check || (! isset($this->fresh) || $this->fresh === false))) {
            $this->fresh = true;
            if ($rows = $this->db->select(self::DATABASE_TABLE)) {
                foreach ($rows as $row) {
                    if ($row->migration == $this->migration_name()) {
                        $this->fresh = false;
                        break;
                    }
                }
            }
        }

        return $this->fresh;
    }

    public static function make($migration_name, $db = null)
    {
        if (is_null($db)) {
            $db = App()->db();
        }
        $Migration = new Migration($db, 'BASE');

        if ($filename = $Migration->find($migration_name)) {
            require_once($filename);
            $namespace = static::extract_namespace($filename);
            $migration_name = str_replace('.php', '', basename($filename));
            $migration_name = static::trim_date_string($migration_name);
            $classname = $namespace.'\\'.static::camel_case($migration_name);

            return new $classname($db, $migration_name);
        } else {
            throw new \Exception('Unable to find Migration "'.$migration_name.'"');
        }
    }

    protected static function extract_namespace($filename)
    {
        $namespace = '';
        $handle = @fopen($filename, "r");
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                if (false !== ($pos = stripos($buffer, 'namespace'))) {
                    $namespace = substr($buffer, ($pos + strlen('namespace')), strpos($buffer, ';'));
                    $namespace = trim(trim($namespace), ';');
                    break;
                }
            }
            fclose($handle);
        }

        return $namespace;
    }

    public function get()
    {
        $Migrations = [];
        foreach (glob(static::$migrations_path."/*.php") as $filename) {
            $name = static::trim_date_string(str_replace('.php', '', $filename));
            if ($Migration = static::make($name, $this->db)) {
                if ($Migration->name() !== 'BASE') {
                    $Migrations[] = $Migration;
                }
            }
        }

        return $Migrations;
    }

    public function get_fresh()
    {
        $Migrations = $this->get();
        foreach ($Migrations as $key => $Migration) {
            if (! $Migration->is_fresh() || $Migration->name() === 'BASE') {
                unset($Migrations[$key]);
            }
        }

        return $Migrations;
    }

    public function sort($Migrations, $reverse = false)
    {
        uasort($Migrations, function ($a, $b) use ($reverse) {
            if ($a instanceof Migration && $b instanceof Migration) {
                if ($reverse) {
                    return ($a->date()->timestamp - $b->date()->timestamp) * -1;
                } else {
                    return $a->date()->timestamp - $b->date()->timestamp;
                }
            }

            return 0;
        });

        return $Migrations;
    }

    public function run($up = true)
    {
        $result = false;
        $fresh = $this->is_fresh();
        $name = $this->migration_name() ?: '';

        if ($up) {
            if ($fresh) {
                if (IS_CLI && DEVELOPMENT_MODE && $name) {
                    printl(Output::color($name.' Fresh', 'green'));
                }
                $result = $this->up();

                if ($result && $name) {
                    $this->insert();
                }
            }
        } else {
            if (! $fresh) {
                if (IS_CLI && DEVELOPMENT_MODE && $name) {
                    printl(Output::color($name.' Unfresh', 'green'));
                }
                $result = $this->down();

                if ($result && $name) {
                    $this->delete();
                }
            }
        }

        return $result;
    }

    protected function up()
    {
        return ($this->is_fresh(true));
    }

    protected function down()
    {
        return $this->is_fresh(true);
    }

    public function set_up($content)
    {
        $this->up_method = $content;
    }

    public function set_down($content)
    {
        $this->down_method = $content;
    }

    public function is_setup()
    {
        if (! isset(static::$setup) || false === static::$setup) {
            if ($tables = $this->db->getTables()) {
                static::$setup = in_array(self::DATABASE_TABLE, $tables);
            }
        }

        return static::$setup;
    }

    public function setup()
    {
        return $this->db->create_table(
            self::DATABASE_TABLE,
            static::$schema['fields'],
            static::$schema['primary_keys']
        );
    }

    public static function find($name)
    {
        if ($name) {
            $name = str_replace(basename($name), static::snake_case(basename($name)), $name);
            foreach (glob(static::$migrations_path."/*.php") as $filename) {
                if (false !== stristr($filename, $name)) {
                    return $filename;
                }

            }
        }
    }

    public static function get_namespace()
    {
        return __NAMESPACE__;
    }

    public function generate()
    {
        if (! $this->exists() && null === $this->find($this->name)) {
            $NAMESPACE = static::get_namespace();
            $user = exec('whoami');
            $date = date("n/j/y");
            $time = date("g:i A");
            $content = <<<EOC
<?php
namespace $NAMESPACE;


/**
 * Created by Worklog\Database\Migration.
 * User: {$user}
 * Date: {$date}
 * Time: {$time}
 */
class {$this->class_name} extends Migration
{
    private \$name = '{$this->name}';

    private \$class_name = '{$this->class_name}';

EOC;
            if (isset($this->up_method)) {
                $content .= <<<EOC

    /**
     * Migrate up
     * @return bool Return TRUE on success, FALSE on failure
     */
    protected function up()
    {
        parent::up();
{$this->up_method}

        return (\$this->is_fresh(true));
    }

EOC;
            }
            if (isset($this->down_method)) {
                $content .= <<<EOC

    /**
     * Migrate down
     * @return bool Return TRUE on success, FALSE on failure
     */
    protected function down()
    {
        parent::down();
{$this->down_method}

        return \$this->is_fresh(true);
    }

EOC;
            }

            $content .= '}'."\n";

            $this->write($content);
        } else {
            $name = $this->migration_name() ?: $this->find($this->name);
            throw new \Exception(sprintf('Migration "%s" already exists.', $name));
        }

        return $this->exists();
    }
}
