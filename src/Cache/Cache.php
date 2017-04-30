<?php
namespace Worklog\Cache;

use Worklog\Application;

/**
 * Cache file wrapper class
 */
class Cache {

	private $path;

	private $driver;

	private $registry;

	private static $DO_NOT_PURGE = false;

	private $Item;

	const DRIVER_ARRAY = 'array';

	const DRIVER_FILE = 'file';


	public function __construct($driver = 0) {
		$this->setDriver($driver);
		$this->load_registry();
	}

	protected function Item($data = []) {
		if (! isset($this->Item) || ! empty($data)) {
			$this->Item = new CacheItem($this, $data);
		}
		return $this->Item;
	}

	private function setDriver($driver) {
		$this->driver = $driver;
	}

    /**
     * Scan cache files and cache their name keys
     * @param  boolean $force Reload cache registry
     */
    protected function load_registry($force = false) {
        $this->registry = [];
        $this->setup();
    }

    public function setup() {
        return $this->is_setup();
    }

	public function is_setup() {        
		return isset($this->registry);
	}

	private function garbage_collect() {
		if ($this->Item()->is_expired()) {
            if (! static::$DO_NOT_PURGE && ! $do_not_delete) {
                $this->delete($this->Item);
            }
        }
	}

    /**
     * Load the cache data given a cache key or filename
     * @param  string $name The cache key name or filename
     * @param bool $get_raw
     * @return array The cache data
     */
    public function load($name, $get_raw = false, $do_not_delete = false) {
        if ($this->Item(compact('name'))->is_expired()) {
            $this->garbage_collect();
        }
        
        if ($get_raw) {
            return $this->Item();
        } else {
            return $this->Item()->data;
        }
    }

    protected function register(CacheItem $CacheItem = null) {
    	if (is_null($CacheItem)) {
    		$CacheItem = $this->Item();
    	}
        if (! $CacheItem->is_registered()) {
            $this->registry[$CacheItem->name] = $CacheItem;
            $CacheItem->register($this);
        }
    }

    protected function unregister($name) {
    	if ($CacheItem = $this->registry($name)) {
    		$CacheItem->unregister($this);
    	}
    }

    public function is_registered(CacheItem $CacheItem) {
    	if ($CacheItem->name && is_scalar($CacheItem->name)) {
    		return array_key_exists($CacheItem->name, $this->registry);
    	}
    }

    public function registry($name = null) {
        if (is_null($name)) {
            return $this->registry;
        } elseif (array_key_exists($name, $this->registry)) {
            return $this->registry[$name];
        }
    }

	/**
	 * Get/set cached data
	 * @param  string 	$name               Name of cache key
	 * @param  mixed 	$new_data  			callable to set (and get)
	 * @param  array 	$tags 				Array of tags to save to the cache item
	 * @param  integer 	$expires_in_seconds Number of seconds from "now" when it expires, 0 for never
	 * @return null|mixed
	 */
	public function data($name, $data = null, $tags = [], $expires_in_seconds = 0) {
		if (is_scalar($name)) {
			$CacheItem = $this->load($name);
		} else {
			throw new \InvalidArgumentException('Cache::data(): first argument must be a string.');
		}

		if (! $CacheItem->is_valid() && ! is_null($data)) {
			// get data from callable $new_data
			if (is_callable($data)) {
				try {
					$param = new \ReflectionParameter($data, 0);
					if (stristr($param->getClass(), 'Application', 'blue')) {
						$data = call_user_func($data, Application::instance());
					} else {
						$data = call_user_func($data);
					}
				} catch (\Exception $e) {
					$data = call_user_func($data);
				}
			}

			$CacheItem->setData($data);
			$CacheItem->addTag($tags);
			$CacheItem->setExpiry(intval(! $expires_in_seconds ?: strtotime('now') + $expires_in_seconds));

			if ($CacheItem->is_valid()) {
				$this->write($CacheItem);
				$this->register($CacheItem);
			}

			$data = $CacheItem->data;
		}

		return $data;
	}

	/**
	 * Delete a cache Item
	 */
	public function delete(CacheItem $Item = null) {
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
     * @param  string $name A cache key or tag name(s)
     * @param array|bool $tags $name is a tag string (or array of strings), clear entries with tag(s)
     * @return bool [type]             [description]
     */
	public function clear($name = null, $tags = false) {
		$deleted = false;

		if ($this->is_setup()) {

			if ($name instanceof CacheItem) {
				$name = $name->name;
			}

			if (is_null($name)) {
				foreach ($this->registry as $_name => $CacheItem) {
					$deleted = $this->delete($CacheItem);
				}
			} elseif ($tags) {
				$tags = $name;
				if (! is_array($tags)) {
					$tags = [ $tags ];
				}
				foreach ($this->registry as $_name => $CacheItem) {
					if (array_intersect($tags, $CacheItem->tags)) {
						$deleted = $this->delete($CacheItem);
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
     * @return mixed        The number of bytes written, or false on failure
     */
    public function write($name, $data = [], $tags = []) {
        $this->setup();

        if ($name instanceof CacheItem) {
        	$CacheItem = $name;
        	$name = $CacheItem->name;
        	$data = $CacheItem->data;
        	$tags = $CacheItem->tags;
        } else {
        	$CacheItem = $this->Item(compact('name', 'data', 'tags'));

        	if ($data) {
        		$CacheItem->setData($data);
        	}
	        if ($tags) {
	            $CacheItem->addTag($tags);
	        }
        }
        
        $this->register($CacheItem);

        return $CacheItem;
    }

	public function load_tags($tags) {
		$items = [];
		if (! empty($tags)) {
			$tags = static::tags($tags);

			foreach ($this->registry as $name => $CacheItem) {
				if (array_intersect($tags, $CacheItem->tags)) {
					if ($CacheItem->is_registered()) {
						$items[$name] = $CacheItem;
					}
				}
			}
		}

		return $items;
	}

	public static function tags($tags) {
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

    public static function disable_purge($set = true) {
        static::$DO_NOT_PURGE = (bool) $set;
    }
}