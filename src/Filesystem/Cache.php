<?php
namespace Worklog\Filesystem;

use Worklog\Application;

/**
 * Cache file wrapper class
 */
class Cache {

	private $path;

	private $registry = [];

	private static $DO_NOT_PURGE = false;


	public function __construct($path = '') {
		$this->set_cache_path($path);
		$this->load_registry();
	}

	public function set_cache_path($path) {
		if (! empty($path)) {
			$this->path = $path;
		} else {
			throw new \InvalidArgumentException('Cache path empty');
		}
	}

    /**
     * Scan cache files and cache their name keys
     * @param  boolean $force Reload cache registry
     */
    private function load_registry($force = false) {
        $this->setup();
        if (empty($this->registry) || $force) {
            foreach (glob($this->path.'/*.json') as $key => $filename) {
                $raw_data = $this->load($filename, true, true);

                if (! is_null($raw_data['name']) && ! empty($raw_data['name']) && ! $raw_data['expired']) {
                    $this->register($raw_data['name']);
                }
            }
        }
    }

    public function setup() {
        if (! $this->is_setup()) {
            if (! empty($this->path)) {
                mkdir($this->path);
                chmod($this->path, 0777);
            } else {
                throw new \Exception('Cache path empty');
            }

        }
        return $this->is_setup();
    }

	public function is_setup() {
	    $setup = false;
        if (! empty($this->path)) {
            $setup = is_dir($this->path);
        }
		return $setup;
	}

    /**
     * Load the cache data given a cache key or filename
     * @param  string $name The cache key name or filename
     * @param bool $get_raw
     * @return array The cache data
     */
    public function load($name, $get_raw = false, $do_not_delete = false) {
        $data = [ 'name' => '', 'data' => null, 'expiry' => 0, 'tags' => [] ];
        if (! ($filename = $this->registry($name))) {
            $filename = $this->filename($name);
        }

        if ($this->is_setup() && file_exists($filename)) {
            $raw_data = file_get_contents($filename);
            $raw_data = json_decode($raw_data, true);

            $data = $raw_data;
            $data['expired'] = static::is_past($raw_data);

            if ($data['expired'] === true) {
                if (! static::$DO_NOT_PURGE && ! $do_not_delete) {
                    unlink($filename);
                }
            }
        }

        if ($get_raw) {
            return $data;
        } else {
            return $data['data'];
        }
    }

    public function register($name) {
        if (! $this->registry($name)) {
            $this->registry[$name] = $this->filename($name);
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
	 * The cache file filename
	 * @param  string $name The unique array index
	 * @return string       The filename for a given cache key
	 */
	public function filename($name) {
		if (substr($name, -5) !== '.json') {
			$name = $this->path.'/'.File::sanitize(md5($name).'.json');
		}
		return $name;
	}

	/**
	 * Get/set cached data
	 * @param  string 	$name               Name of cache key
	 * @param  mixed 	$new_data  			callable to set (and get)
	 * @param  array 	$tags 				Array of tags to save to the cache item
	 * @param  integer 	$expires_in_seconds Number of seconds from "now" when it expires, 0 for never
	 * @return null|mixed
	 */
	public function data($name, $new_data = null, $tags = [], $expires_in_seconds = 0) {
		$data = $this->load($name);
		if (is_null($data) && ! is_null($new_data)) {
			// if (DEVELOPMENT_MODE && IS_CLI) print 'CACHE MISS: '.$name."\n";
			$data = $new_data;
			$expiry = intval(! $expires_in_seconds ?: strtotime('now') + $expires_in_seconds);

			// get data from callable $new_data
			if (is_callable($new_data)) {
				try {
					$param = new \ReflectionParameter($new_data, 0);
					if (stristr($param->getClass(), 'Application')) {
						$data = call_user_func($new_data, Application::instance());
					} else {
						$data = call_user_func($new_data);
					}
				} catch (\Exception $e) {
					$data = call_user_func($new_data);
				}
				
			}
			if ($data) {
				$this->write($name, compact([ 'name', 'data', 'expiry', 'tags' ]));
				$this->register($name);
			}
		} else {
			// if (DEVELOPMENT_MODE && IS_CLI) print 'CACHE HIT: '.$name."\n";
		}
		return $data;
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
			if (is_null($name)) {
				foreach ($this->registry as $_name => $filename) {
					unlink($filename);
					$deleted = true;
				}
			} elseif ($tags) {
				$tags = $name;
				if (! is_array($tags)) {
					$tags = [ $tags ];
				}
				foreach ($this->registry as $_name => $filename) {
					$raw_data = $this->load($filename, true);
					if (array_intersect($tags, $raw_data['tags'])) {
						if (file_exists($filename)) unlink($filename);
						unset($this->registry[$_name]);
						$deleted = true;
					}
				}
			} elseif (isset($this->registry[$name])) {
				$filename = $this->filename($this->registry[$name]);
				$deleted = file_exists($filename) && unlink($filename);
				unset($this->registry[$name]);
			} else {
				$filename = $this->filename($name);
				$deleted = file_exists($filename) && unlink($filename);
			}
		}
		return $deleted;
	}

	public function load_tags($tags) {
		$items = [];
		if (! empty($tags)) {
			if (! is_array($tags)) {
				$tags = [ $tags ];
			}
			foreach ($this->registry as $name => $filename) {
				$raw_data = $this->load($filename, true);
				if (array_intersect($tags, $raw_data['tags'])) {
					if (file_exists($filename)) {
						$items[$name] = $this->filename($name);
					}
				}
			}
		}
		
		return $items;
	}

    /**
     * Write the data array to a cache json file
     * @param  string $name The cache key
     * @param  array  $data The cache data
     * @param  array  $tags Tag string (or array of strings) used for taxonomy
     * @return mixed        The number of bytes written, or false on failure
     */
    public function write($name, $data = [], $tags = []) {
        $this->setup();
        $this->register($name);
        if (! empty($tags)) {
            if (! is_array($tags)) {
                $tags = [ $tags ];
            }
            if (array_key_exists('tags', $data)) {
                $data['tags'] = array_merge($data['tags'], $tags);
            } else {
                $data['tags'] = $tags;
            }
        }
        return file_put_contents($this->filename($name), json_encode($data));
    }

    public static function disable_purge($set = true) {
        static::$DO_NOT_PURGE = (bool) $set;
    }

	private static function is_past($raw_data) {
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