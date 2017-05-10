<?php
namespace Worklog\Cache;

use Worklog\Application;

/**
 * Cache file wrapper class
 */
class Cache
{
    protected $path;

    private $Item;

    private $driver;

    private $registry;

    private static $store = [];

    private static $DO_NOT_PURGE = false;

    const DRIVER_ARRAY = 'array';

    const DRIVER_FILE = 'file';

    public function __construct($driver = 0)
    {
        $this->setDriver($driver);
        $this->load_registry();
    }

    public function Item($data = [])
    {
        if (! isset($this->Item) || (! empty($data) && isset($data['name']))) {
            $this->Item = CacheItem::new_from_store($this, $data['name'], $data);
        }

        return $this->Item;
    }

    public function Items($filter = null)
    {
        $Items = [];
        $apply_filter = function($_Item) use ($filter) {
            if (is_array($filter)) {
                foreach ($filter as $key => $value) {
                    if (isset($_Item->{$key}) && $_Item->{$key} == $value) {
                        return true;
                    }
                }
            } elseif (is_callable($filter)) {
                if ($filter($_Item)) {
                    return true;
                }
            }

            return false;
        };

        foreach ($this->registry as $name => $CacheItem) {
            if (is_null($filter) || $apply_filter($CacheItem) === true) {
                $Items[] = $CacheItem;
            }
        }

        return collect($Items);
    }

    public function setDriver($driver)
    {
        $this->driver = $driver;
    }

    public function setup()
    {
        $this->registry = [];

        return $this->is_setup();
    }

    public function is_setup()
    {
        return isset($this->registry) && ! empty($this->registry);
    }

    public static function file_type()
    {
        return self::DRIVER_ARRAY;
    }

    /**
     * Load the cache data given a cache key or filename
     * @param  string $name    The cache key name or filename
     * @param  bool   $get_raw
     * @return array  The cache data
     */
    public function load($name, $get_raw = false, $do_not_delete = false)
    {
        if ($this->Item(compact('name'))->is_expired()) {
            $this->garbage_collect($do_not_delete);
        }

        if ($get_raw) {
            return $this->Item()->data;
        } else {
            return $this->Item();
        }
    }

    /**
     * Scan cache files and cache their name keys
     * @param boolean $force Reload cache registry
     */
    protected function load_registry($force = false)
    {
        $this->setup();
        if (! empty(static::$store)) {
            foreach (static::$store as $key => $value) {
                $this->register($this->Item(['name' => $key, 'data' => $value]));
            }
        }
    }

    protected function garbage_collect($do_not_delete = false)
    {
        if ($this->Item()->is_expired()) {
            if (! static::$DO_NOT_PURGE && ! $do_not_delete) {
                $this->delete($this->Item);
            }
        }
    }

    protected function register(CacheItem $CacheItem = null)
    {
        if (is_null($CacheItem)) {
            $CacheItem = $this->Item();
        }

        if (! $CacheItem->is_registered()) {
            if ($CacheItem->is_valid()) {
                $this->registry[$CacheItem->name] = $CacheItem;
                $CacheItem->register($this);
            } else {
                throw new \Exception('Invalid cache item.');
            }
        }
    }

    protected function unregister($name)
    {
        if ($CacheItem = $this->registry($name)) {
            $CacheItem->unregister($this);
        }
    }

    public function is_registered(CacheItem $CacheItem)
    {
        if ($CacheItem->name && is_scalar($CacheItem->name) && isset($this->registry)) {
            return array_key_exists($CacheItem->name, $this->registry);
        }
    }

    public function registry($name = null)
    {
        if (is_null($name)) {
            return $this->registry;
        } elseif (array_key_exists($name, $this->registry)) {
            return $this->registry[$name];
        }
    }

    /**
     * Get/set cached data
     * @param  string     $name               Name of cache key
     * @param  mixed      $new_data           callable to set (and get)
     * @param  array      $tags               Array of tags to save to the cache item
     * @param  integer    $expires_in_seconds Number of seconds from "now" when it expires, 0 for never
     * @return null|mixed
     */
    public function data($name, $data = null, $tags = [], $expires_in_seconds = 0)
    {
        if (is_scalar($name)) {
            $CacheItem = $this->load($name, false);
        } else {
            throw new \InvalidArgumentException('Cache::data(): first argument must be a string.');
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
        }

        return $CacheItem->data;
    }

    /**
     * Delete a cache Item
     */
    public function delete(CacheItem $Item = null)
    {
        $deleted = false;

        if (is_null($Item)) {
            $Item = $this->Item();
        }
        if ($name = $Item->name) {
            $Item->unregister($this);
            unset($this->registry[$name]);
            $deleted = $Item->delete();
        }

        return $deleted;
    }

    /**
     * Delete a cache file
     * @param  string     $name A cache key or tag name(s)
     * @param  array|bool $tags $name is a tag string (or array of strings), clear entries with tag(s)
     * @return bool       [type]             [description]
     */
    public function clear($name = null, $tags = false)
    {
        $deleted = false;

        if ($this->is_setup()) {

            if ($name instanceof CacheItem) {
                $name = $name->name;
            }

            if (is_null($name) && ! $tags) {
                if (! is_null($this->registry)) {
                    foreach ($this->registry as $_name => $CacheItem) {
                        $deleted = $this->delete($CacheItem);
                    }
                }
                
            } elseif ($tags) {
                if (is_bool($tags)) {
                    $tags = (array) $name;
                    $name = null;
                }
                
                if (! is_array($tags)) {
                    $tags = [ $tags ];
                }
                if (! is_null($this->registry)) {
                    foreach ($this->registry as $_name => $CacheItem) {
                        if (array_intersect($tags, $CacheItem->tags)) {
                            $deleted = $this->delete($CacheItem);
                        }
                    }
                }
            } elseif (isset($this->registry[$name])) {
                $deleted = $this->delete($this->registry[$name]);
            }
        }

        return $deleted;
    }

    /**
     * Write the data array to a json cache file
     * @param  string $name The cache key
     * @param  array  $data The cache data
     * @param  array  $tags Tag string (or array of strings) used for taxonomy
     * @return mixed  The number of bytes written, or false on failure
     */
    public function write($name, $data = [], $tags = [], $expires_in_seconds = 0)
    {
        $this->setup();

        if ($name instanceof CacheItem) {
            $CacheItem = $name;
            $name = $CacheItem->name;
        } else {
            $CacheItem = $this->Item(compact('name'));
        }

        if ($data) {
            $CacheItem->setData($data);
        }

        if ($tags) {
            $CacheItem->setTags($tags);
        }

        $CacheItem->setExpiry($expires_in_seconds);
        $this->register($CacheItem);

        $CacheItem->write();

        return $CacheItem;
    }

    /*
     */
    public function load_tags($tags)
    {
        $this->setup();

        $items = [];
        if (! empty($tags)) {
            $tags = static::tags($tags);

            if (isset($this->registry)) {
                foreach ($this->registry as $name => $CacheItem) {
                    if (array_intersect($tags, $CacheItem->tags) && $CacheItem->is_registered()) {
                        $items[$name] = $CacheItem;
                    }
                }
            }
        }

        return $items;
    }

    /*
     */
    public function load_tagsMatch($match)
    {
        $this->setup();

        $items = [];
        if (! empty($match)) {
            $match = static::tags($match);

            if (isset($this->registry)) {
                foreach ($this->registry as $name => $CacheItem) {
                    foreach ($CacheItem->tags as $key => $value) {
                        foreach ($match as $_key => $_match) {
                            if (preg_match($_match, $value) && $CacheItem->is_registered()) {
                                $items[$name] = $CacheItem;
                            }
                        }
                    }
                }
            }
        }

        return $items;
    }

    public static function tags($tags)
    {
        if (! empty($tags)) {
            if (false !== strpos($tags, ',')) {
                $tags = explode(',', $tags);
            }
            if (! is_array($tags)) {
                $tags = [ $tags ];
            }
            $tags = array_map('trim', $tags);
        }

        return $tags;
    }

    public static function disable_purge($set = true)
    {
        static::$DO_NOT_PURGE = (bool) $set;
    }

    public static function remember($key, $value = null)
    {
        $return = null;

        if (is_null($value)) {
            if (isset(static::$store[$key])) {
                $return = static::$store[$key];
            }
        } else {
            if (! isset(static::$store[$key])) {
                static::$store[$key] = null;
            }
            static::$store[$key] = $value;
        }

        return $return;
    }

    public static function forget($key)
    {
        unset(static::$store[$key]);
    }
}
