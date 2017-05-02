<?php

namespace Worklog\Cache;

use Worklog\Filesystem\File;

/**
 * Cache file wrapper class
 */
class CacheItem
{
    private $name;

    private $data;

    private $expiry = 0;

    private $tags = [];

    private $_registered = false;

    private $filename;

    private $File;

    private $Cache;

    public function __construct(Cache $Cache, $data)
    {
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        if (array_key_exists('name', $data)) {
            $this->setName($data['name']);
        }

        if (array_key_exists('data', $data)) {
            $this->setData($data['data']);
        }

        if (array_key_exists('expiry', $data)) {
            $this->setExpiry($data['expiry']);
        }

        if (array_key_exists('tags', $data)) {
            $this->setTags($data['tags']);
        }

        $this->Cache = $Cache;
    }

    public static function new_from_store(Cache $Cache, $name, $props = [])
    {
        $props = array_merge($props, ['data' => Cache::remember($name)]);

        return new static($Cache, $props);
    }

    public static function new_from_file(Cache $Cache, $path, $props = [])
    {
        $raw_data = null;

        if ($path instanceof File) {
            $File = $path;
            $path = $File->path();
        } else {
            $File = new File($path);
        }

        if ($File->exists()) {
            $raw_data = $File->contents();
            $raw_data = json_decode($raw_data, true);
            $props = array_merge($props, $raw_data);
        }

        $Item = new static($Cache, $props);
        if ($File->exists()) {
            $Item->setFile($File);
            $Item->setData($raw_data);
        }

        return $Item;
    }

    public function cacheClass()
    {
        $class_name = get_class($this->Cache);

        return $class_name;
    }

    public function File($path = null)
    {
        if (! isset($this->File) || ! is_null($path)) {
            $this->setFile(new File($path));
        }

        return $this->File;
    }

    public function setFile(File $File)
    {
        $this->File = $File;

        return $this;
    }

    public function isFile()
    {
        return isset($this->File);
    }

    public function fileExists($path = null)
    {
        if ($File = $this->File($path)) {
            return $this->File()->exists();
        }

        return false;
    }

    public function write()
    {
        if ($this->data) {
            if ($this->isFile()) {
                return $this->File()->write(json_encode($this->data), LOCK_EX);
            } else {
                Cache::remember($this->name, $this->data);
            }
        } else {
            return false;
        }

        return true;
    }

    public function register()
    {
        $this->_registered = $this->Cache->is_registered($this);

        return $this;
    }

    public function unregister(Cache $Cache)
    {
        $this->_registered = $this->Cache->is_registered($this);

        return $this;
    }

    public function is_registered()
    {
        return $this->_registered;
    }

    public function is_expired()
    {
        $expired = false;

        if (isset($this->expiry)) {
            if ($this->expiry > 0 && $this->expiry <= strtotime('now')) {
                $expired = true;
            }
        }

        return $expired;
    }

    public function persist()
    {
        if ($this->is_valid()) {
            return $this->Cache->write($this);
        }
    }

    public function delete()
    {
        $deleted = false;
        $this->expiry = 1;
        unset($this->data);

        if ($this->isFile() && $this->fileExists()) {
            $deleted = $this->File->delete();
        } else {
            Cache::forget($this->name);
        }

        return $deleted;
    }

    public function is_valid()
    {
        $valid = true;
        if (! isset($this->name)) {
            $valid = false;
        } elseif (! isset($this->expiry)) {
            $valid = false;
        } elseif (! isset($this->data)) {
            $valid = false;
        }

        return $valid;
    }

    public function addTag($tag)
    {
        if (is_array($tag)) {
            foreach ($tag as $key => $_tag) {
                $this->addTag($_tag);
            }
        } elseif (! in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    public function setName($value)
    {
        if (is_scalar($value)) {
            $this->name = $value;
        } else {
            throw new \InvalidArgumentException('CacheItem::name must be a string.');
        }

    }

    public function setData($value)
    {
        $this->data = $value;
    }

    public function setExpiry($value)
    {
        if ($value < strtotime('now')) {
            $value = strtotime('now') + $value;
        }
        $this->expiry = $value;
    }

    public function setTags($value)
    {
        $this->tags = $value;
    }

    public function __get($name)
    {
        if (isset($this->{$name})) {
            if ($name == 'data') {
                if (is_null($this->data)) {
                    if ($this->Cache instanceof Cache && isset($this->name)) {
                        $this->data = Cache::remember($this->name);
                    } elseif ($this->File->exists()) {
                        $this->data = $this->File->contents();
                    }
                }
                $data = $this->data;

                if (is_array($data)) {
                    $data = json_decode(json_encode($data));
                }

                if (isset($this->tags)) {
                    if (is_object($data)) {
                        $data->tags = $this->tags;
                    } elseif (is_array($data)) {
                        $data['tags'] = $this->tags;
                    }
                }

                return $data;
            } else {
                return $this->{$name};
            }
        }
    }

    public function __set($name, $value)
    {
        if (! in_array($name, '_registered', 'registered')) {
            $setter_name = 'set'.ucfirst($name);
            if (method_exists($this, $setter_name)) {
                call_user_func([$this, $setter_name], $value);
            } else {
                $this->{$name} = $value;
            }
        }
    }

    public function __isset($name)
    {
        return isset($this->{$name});
    }
}
