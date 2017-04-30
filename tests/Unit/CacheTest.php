<?php

namespace Tests;

use Worklog\Cache\Cache;
use Worklog\Cache\CacheItem;

class CacheTest extends TestCase {

	private $key;

	private $data;


    // public function testSetUp()
    // {
    //     $Cache = $this->make();
    //     $this->assertTrue($Cache->is_setup());
    // }

    // public function testGetsCacheItem()
    // {
    //     $Cache = $this->make();
    //     $this->assertEquals(CacheItem::class, get_class($Cache->Item()));
    // }

    public function testReadWrite() {
    	$get_raw = true;
    	$this->key = '_KEY_';
    	$this->data = '_VALUE_';
    	$Cache = $this->make();
    	$Cache->write($this->key, $this->data);

    	$value = $Cache->load($this->key, $get_raw);

    	$this->assertEquals($this->data, $value);
    	$this->resetData();
    }

    public function testDelete() {
    	$this->key = '_KEY_';
    	$this->data = '_VALUE_';
    	$Cache = $this->make();
    	$Cache->write($this->key, $this->data);
    	$CacheItem = $Cache->load($this->key, false);
    	$Cache->delete($CacheItem);
    	$value = $Cache->load($this->key, true);

    	$this->assertNull($value);
    	$this->resetData();
    }


	private function make() {
		return new Cache(env('CACHE_DRIVER'));
	}

	private function resetData() {
		unset($this->key);
		unset($this->data);
	}
}