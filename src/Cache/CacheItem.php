<?php

namespace Worklog\Cache;

use Worklog\Filesystem\File;

/**
 * Cache file wrapper class
 */
class CacheItem
{
    private $hash;

    private $updated = false;

    private $name;

    private $meta;

    private $data;

    private $expiry = 0;

    private $filename;

    private $tags = [];

    private $_registered = false;

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

        if (array_key_exists('meta', $data)) {
            $this->setMeta($data['meta']);
        }

        if (array_key_exists('data', $data)) {
            $this->setData($data['data']);
        }

        if (array_key_exists('path', $data)) {
            $this->File($data['path']);

        if (array_key_exists('expiry', $data)) {
            $this->setExpiry($data['expiry']);
        }
        }

        if (array_key_exists('tags', $data)) {
            $this->setTags($data['tags']);
        }

        $this->Cache = $Cache;
        
        $this->hash();
    }

    protected function hash($regen = false)
    {
        $_hash = md5($this);
        
        if (! isset($this->hash) || $_hash !== $this->hash || $regen) {
            $_updated = $this->updated;
            $this->updated = ($regen ? false : isset($this->hash));
            $this->hash = $_hash;
        }

        return $this->hash;
    }

    public function updated()
    {
        $this->hash();

        return $this->updated;
    }

    public static function new_from_store(Cache $Cache, $name, $props = [])
    {
        $props = array_merge($props, ['data' => Cache::remember($name), 'meta' => Cache::remember($name.'_meta') ?: null]);

        return new static($Cache, $props);
    }

    public static function new_from_file(Cache $Cache, $name, $props = [])
    {
        $path = $Cache->filename($name);
        $Item = new static($Cache, $props);
        $File = new File($path);

        if ($File->exists()) {
            $Data = json_decode($File->contents(), false);
            $Item = $Item->decode($Data);
        }

        $Item->setFile($File)->hash(true);

        return $Item;
    }

    public function cacheClass()
    {
        $class_name = get_class($this->Cache);

        return $class_name;
    }

    public function filepath()
    {
        if (isset($this->filename)) {
            return $this->filename;
        }
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
        $this->filename = $File->path();

        return $this;
    }

    public function isFile()
    {
        return isset($this->File);
    }

    public function fileExists($path = null)
    {
        if (is_null($path) && isset($this->filename)) {
            $path = $this->filename;
        }
        if ($File = $this->File($path)) {
            return $this->File()->exists();
        }

        return false;
    }

    public function decode(\stdClass $Contents)
    {
        $Item = new CacheItem($this->Cache, [
              'name' => $Contents->name,
              'meta' => (property_exists($Contents, 'meta') ? unserialize($Contents->meta) : null),
              'data' => (property_exists($Contents, 'data') ? unserialize($Contents->data) : null),
          'filename' => (property_exists($Contents, 'filename') ? $Contents->filename : null),
            'expiry' => $Contents->expiry,
              'tags' => $Contents->tags
        ]);

        $Item->register()->hash(true);

        return $Item;
    }

    public function encode()
    {
        $Contents = new \stdClass;
        $Contents->name     = (isset($this->name) ? $this->name : null);
        $Contents->meta     = (isset($this->meta) ? serialize($this->meta) : null);
        $Contents->data     = (isset($this->data) ? serialize($this->data) : null);
        $Contents->filename = (isset($this->filename) ? $this->filename : null);
        $Contents->expiry   = $this->expiry;
        $Contents->tags     = $this->tags;

        return $Contents;
    }

    public function write()
    {
        $written = false;

        if ($this->updated()) {
            if ($this->data) {
                if ($this->isFile()) {
                    $written = $this->File()->write(json_encode($this->encode()), LOCK_EX);
                } else {
                    Cache::remember($this->name, $this->data);
                    if (isset($this->meta)) {
                        Cache::remember($this->name.'_meta', $this->meta);
                    }
                    $written = true;
                }
            }
        }

        if ($written) {
            $this->changed = false;
        }

        return $written;
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

    public function meta($key, $value = null)
    {
        if (! isset($this->meta[$key])) {
            $this->meta[$key] = $value;
        }
        
        $return = $this->meta[$key];

        if (! is_null($value)) {
            $this->meta[$key] = $value;
        }
        
        return $return;
    }

    protected function setMeta($meta)
    {
        $this->meta = $meta;
    }

    public function del_meta($key)
    {
        if (isset($this->meta[$key])) {
            unset($this->meta[$key]);
        }
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
        if (! in_array($name, ['_registered', 'registered'])) {
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

    public function __toString()
    {
        return json_encode($this->encode());
    }
}
