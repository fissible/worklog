<?php
namespace Worklog\Cache;

use Worklog\Application;
use Worklog\Filesystem\File;

/**
 * Cache file wrapper class
 */
class FileStore extends Cache {

	private $registry = [];

	private static $DO_NOT_PURGE = false;

	private $Item;


	public function __construct($path = '') {
		$this->set_cache_path($path);
		parent::__construct(self::DRIVER_FILE);
	}

	protected function Item($data = []) {
		if (! isset($this->Item) || ! empty($data)) {
			if (isset($data['name'])) {
				$this->Item = CacheItem::new_from_file($this, $this->filename($data['name']), $data);
			}
		}
		
		return $this->Item;
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
    protected function load_registry($force = false) {
        parent::load_registry($force);

        if (empty($this->registry) || $force) {
            foreach (glob($this->path.'/*.json') as $key => $filename) {
                $this->register($this->load($filename, false, true));
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

    public function data($name, $data = null, $tags = [], $expires_in_seconds = 0) {
    	$data = parent::data($name, $data, $tags, $expires_in_seconds);

    	if ($this->Item()) {
    		$this->Item()->setFile(new File($this->filename($name)));
    		$this->write($this->Item());
			$this->register($this->Item());
    	}

    	return $data;
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
     * Write the data array to a cache json file
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

        return $CacheItem->write();
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