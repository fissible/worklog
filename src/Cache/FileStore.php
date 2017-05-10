<?php
namespace Worklog\Cache;

use Worklog\Filesystem\File;

/**
 * Cache file wrapper class
 */
class FileStore extends Cache
{
    private $registry = [];

    private static $DO_NOT_PURGE = false;

    private $Item;

    protected static $file_type = 'json';


    public function __construct($path = '')
    {
        $this->set_cache_path($path);
        parent::__construct(self::DRIVER_FILE);
    }

    public function Item($data = [])
    {
        if (! isset($this->Item) || ! empty($data)) {
            if (isset($data['name'])) {
                $this->Item = CacheItem::new_from_file($this, $data['name'], $data);
            }
        }

        return $this->Item;
    }

    public function set_cache_path($path)
    {
        if (! empty($path)) {
            $this->path = $path;
        } else {
            throw new \InvalidArgumentException('Cache path empty');
        }
    }

    public function path()
    {
        if (isset($this->path)) {
            return $this->path;
        }
    }

    /**
     * Scan cache files and cache their name keys
     * @param boolean $force Reload cache registry
     */
    protected function load_registry($force = false)
    {
        parent::load_registry($force);

        if (empty($this->registry) || $force) {
            foreach (glob($this->path.'/*.'.static::$file_type) as $key => $filename) {
                $this->register($this->load($filename, false, true));
            }
        }
    }

    public function setup()
    {
        if (! $this->is_setup()) {
            parent::setup();

            if (! empty($this->path)) {
                mkdir($this->path);
                chmod($this->path, 0777);
            } else {
                throw new \Exception('Cache path empty');
            }

        }

        return $this->is_setup();
    }

    public function is_setup()
    {
        $setup = false;

        if (! empty($this->path)) {
            $setup = is_dir($this->path);
        }

        return $setup;
    }

    /**
     * Load the cache data given a cache key or filename
     * @param  string $name    The cache key name or filename
     * @param  bool   $get_raw
     * @return array  The cache data
     */
    public function load($name, $get_raw = false, $do_not_delete = false)
    {
        $this->Item = $this->Item(compact('name'));

        if ($this->is_setup()) {
            if ($this->Item()->is_expired()) {
                $this->garbage_collect();
            }
        }

        if ($get_raw) {
            return $this->Item()->data;
        } else {
            return $this->Item();
        }
    }

    public function data($name, $data = null, $tags = [], $expires_in_seconds = 0)
    {
        // $data = parent::data($name, $data, $tags, $expires_in_seconds);

        // if ($this->Item()) {
        //     $this->Item()->setFile(new File($this->filename($name)));
        //     $this->write($this->Item(), $data, $tags, $expires_in_seconds);
        // }

        // return $data;

        if (is_scalar($name)) {
            $CacheItem = $this->load($name, false);
        } else {
            throw new \InvalidArgumentException('FileStore::data(): first argument must be a string.');
        }

        if (! $CacheItem->is_valid() && ! is_null($data)) {
            // get data from callable $new_data
            if (is_callable($data)) {
                try {
                    $param = new \ReflectionParameter($data, 0);
                    if (stristr($param->getClass(), 'Application')) {
                        $data = call_user_func($data, Application::instance());
                    } else {
                        $data = call_user_func($data);
                    }
                } catch (\Exception $e) {
                    $data = call_user_func($data);
                }

                // decorator function
                if (is_callable($data)) {
                    $data = $data($CacheItem);
                }
            }

            $CacheItem = $this->write($CacheItem, $data, $tags, $expires_in_seconds);

            $data = $CacheItem->data;
        } else {
            $data = $CacheItem->data;
        }

        return $data;
    }

    

    /**
     * The cache file filename
     * @param  string $name The unique array index
     * @return string The filename for a given cache key
     */
    public function filename($name)
    {
        if (substr($name, -5) !== '.'.static::$file_type) {
            $name = $this->path.'/'.File::sanitize(md5($name).'.'.static::$file_type);
        }

        return $name;
    }

    public static function file_type()
    {
        return static::$file_type;
    }

    public static function disable_purge($set = true)
    {
        static::$DO_NOT_PURGE = (bool) $set;
    }

    private static function is_past($raw_data)
    {
        $expired = false;

        if (! is_string($raw_data)) {
            if (isset($raw_data['expiry']) && strlen($raw_data['expiry']) > 9) {
                $date = $raw_data['expiry'];
            }
        } else {
            $date = $raw_data;
        }
        if ($date > 0 && $date <= strtotime('now')) {
            $expired = true;
        }

        return $expired;
    }
}
