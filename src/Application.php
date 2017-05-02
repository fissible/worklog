<?php
namespace Worklog;

use Worklog\Cache\Cache;
use Worklog\Cache\FileStore;
use Illuminate\Container\Container;

class Application
{
    private $Cache;

    private $Command;

    private $ProjectPath;

    private $UserPath;

    private $Options;

    private $database_primary_keys = ['id'];

    private $database_indices = ['ticket'];

    private $db_table_exists = [];

    private static $instance;

    /**
     * @param $db
     * @param $project_path
     * @param string $user_path
     * @internal param string $userdir The path to the user directory
     */
    public function __construct($db, $project_path, $user_path = '~')
    {
        $this->db = ($db ?: database(getenv('DATABASE_DRIVER')));
        $this->set_project_paths($project_path);
        $this->set_user_paths($user_path);
        static::$instance = $this;
    }

    public static function instance($db = null, $project_path = '', $user_path = '~')
    {
        if (! isset(static::$instance)) {
            return new static($db, $project_path, $user_path);
        }

        return static::$instance;
    }

    /**
     * Cache getter/setter
     */
    public function Cache()
    {
        if (! isset($this->Cache)) {
            $driver = env('CACHE_DRIVER');
            if ($driver == Cache::DRIVER_FILE) {
                $this->Cache = new FileStore(CACHE_PATH);
            } else {
                $this->Cache = new Cache();
            }
        }

        return $this->Cache;
    }

    /**
     * Command getter
     */
    public function Command()
    {
        return $this->Command;
    }

    public function db()
    {
        return $this->db;
    }

    /**
     * @return bool|int|string
     */
    public static function check_env_file()
    {
        $Command = new CommandLine\UpdateEnvCommand();
        $Command->set_invocation_flag();
        $Command->setData('query', true);

        return $Command->run();
    }

    /**
     * @return bool|int|string
     */
    public static function update_env_file()
    {
        $Command = new CommandLine\UpdateEnvCommand();
        $Command->set_invocation_flag();

        return $Command->run();
    }

    public function make($abstract)
    {
        $Container = static::Container();

        return $Container->make($abstract);
    }

    public static function Container()
    {
        return new Container(APPLICATION_PATH);
    }

    /**
     * Return menu lines as an array
     * @param  string $command Optionaly get usage for a specific command
     * @param  boolean long: more information
     * @param  boolean short: less information
     * @return array
     */
    public function menu($command = null, $flag_l = false, $flag_s = false)
    {
        $ignore_menu = false;
        $included_commands = $lines = [];
        $long = ! empty($command);
        $short = $flag_s;
        if (! $long) {
            $lines[] = sprintf("Usage: %s [options] <command> [<arg1>, ...]\n", SCRIPT_NAME);
            $ignore_menu = $flag_l;
        } else {
            $ignore_menu = true;
        }

        foreach (CommandLine\Command::registry() as $_command => $info) {
            if (in_array($_command, $included_commands)) continue;
            if (empty($command) || $command == $_command) {
                $included_commands[] = $_command;
                $Command = (new CommandLine\Command($this))->resolve([ $_command ]);
                $class = get_class($Command);
                $display_in_menu = isset($class::$menu) ? $class::$menu : true;
                if ($ignore_menu || $display_in_menu) {
                    $lines = array_merge($lines, $Command->usage($long, $short));
                }
            }
        }

        return $lines;
    }

    /**
     * Run the specified command
     * @return mixed
     * @throws \Exception
     * @internal param string $command The command to run
     */
    public function run()
    {
        if (! $this->is_setup()) {
            $this->setup();
        }

        CommandLine\Command::set_data('project_path', $this->ProjectPath);

        try {
            $this->Command = (new CommandLine\Command())->resolve();
            $this->Command->scan();

            return $this->Command->run();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Timer start/stop
     * @return mixed null or time elapsed since prior call
     */
    public static function timer()
    {
        static $timer_start;
        if ($timer_start > 0) {
            $seconds = round(microtime(true) - $timer_start, 4);
            $timer_start = 0;

            return $seconds;
        }
        $timer_start = microtime(true);
    }

    /**
     * Set the local project path
     * @param string $path The path to the project root
     */
    public function set_project_paths($path = ''/*, $sql_files_directory = ''*/)
    {
        $this->ProjectPath = rtrim(($path ?: getcwd()), '/');
        // $this->SqlPath = $this->ProjectPath.'/'.($sql_files_directory ?: 'database');
    }

    /**
     * Set the local file paths
     * @param string $userdir The path to the user directory
     */
    public function set_user_paths($user_path = ''/*, $Atlassian_path = ''*/)
    {
        $this->UserPath = rtrim(($user_path ?: '~'), '\\/');
        // if ($Atlassian_path) {
        // 	$this->AtlassianPath = $this->ProjectPath.'/'.$Atlassian_path;
        // }
    }

    /**
     * Return the Options object
     */
    public function Options()
    {
        if (! isset($this->Options)) {
            $Command = new CommandLine\Command($this);
            $this->Options = $Command->Options();
        }

        return $this->Options;
    }

    /**
     * Check if the database table exists
     */
    private function db_table_exists($table)
    {
        if (! is_null($table)) {
            if (! array_key_exists($table, $this->db_table_exists)) {
                $this->db_table_exists[$table] = false;
            }
            $this->db_table_exists[$table] = $this->db->tableExists($table);

            return $this->db_table_exists[$table];
        }

        return false;
    }

    /**
     * Check if setup has been done
     * @return boolean
     */
    private function is_setup($table = null)
    {
        $setup = true;
        // if ($setup && ! file_exists($this->AtlassianPath)) {
        // 	$setup = false;
        // }
        if ($setup && ! file_exists($this->ProjectPath)) {
            $setup = false;
        }
        if ($setup && ! is_null($table) && ! $this->db_table_exists($table)) {
            $setup = false;
        }

        return $setup;
    }

    /**
     * Configure local environment
     * @return boolean
     */
    private function setup($table = null)
    {
        printl("Setting up Application ... ");

        if (! file_exists($this->ProjectPath)) {
            throw new \Exception(sprintf("%s: No such file or directory", $this->ProjectPath));
        }

        // foreach ([$this->SqlPath, $this->UserPath, $this->AtlassianPath] as $path) {
        // 	if ($path) {
        // 		if (! file_exists($path)) {
        // 		    if(!@mkdir($path, 0777, true))
        //          	throw new \Exception("Unable to create directory: {$path}");
        //          }
        //      }
        // }

        if ($table) {
            if (! $this->db_table_exists($table)) {
                if ($this->db->create_table($table, $this->database_table_fields, $this->database_primary_keys, $this->database_indices)) {
                    println(sprintf('Created database table "%s"', DATABASE_TABLE));
                } else {
                    throw new \Exception(sprintf('Error creating database table %s', DATABASE_TABLE));
                }
            }
        }

        return $this->is_setup();
    }

    public static function call_trace()
    {
        $e = new \Exception();
        $trace = explode("\n", $e->getTraceAsString());
        // reverse array to make steps line up chronologically
        $trace = array_reverse($trace);
        array_shift($trace); // remove {main}
        array_pop($trace); // remove call to this method
        $length = count($trace);
        $result = array();

        for ($i = 0; $i < $length; $i++) {
            $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' '));
        }

        return "\t" . implode("\n\t", $result);
    }

    public function __call($name, $arguments)
    {
        $Container = static::Container();

        if (method_exists($Container, $name)) {
            return call_user_func_array([$Container, $name], $arguments);
        }
    }
}
