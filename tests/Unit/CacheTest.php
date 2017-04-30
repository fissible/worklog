<?php

namespace Tests;

use Worklog\Cache\Cache;
use Worklog\Cache\CacheItem;

class CacheTest extends TestCase {

	private $key;

	private $data;

	private static $cache_file_path = '/tmp';


	protected function setUp()
	{
		parent::setUp();

	    $this->key = '_KEY_';
    	$this->data = '_VALUE_';
	}


    public function testDeleteArray()
    {
    	$Cache = $this->make(Cache::DRIVER_ARRAY);
    	$Cache->write($this->key, $this->data);
    	$CacheItem = $Cache->load($this->key, false);
    	$Cache->delete($CacheItem);
    	$value = $Cache->load($this->key, true);

    	$this->assertNull($value);
    }

    public function testDeleteFile()
    {
    	$Cache = $this->make(Cache::DRIVER_FILE);
    	$Cache->write($this->key, $this->data);
    	$CacheItem = $Cache->load($this->key, false);
    	$Cache->delete($CacheItem);
    	$value = $Cache->load($this->key, true);

    	$this->assertNull($value);
    }

    public function testReadWriteArray()
    {
    	$get_raw = true;
    	$Cache = $this->make(Cache::DRIVER_ARRAY);
    	$Cache->write($this->key, $this->data);

    	$value = $Cache->load($this->key, $get_raw);

    	$this->assertEquals($this->data, $value);

    	$this->deleteArray();
    }

    public function testReadWriteFile()
    {
    	$get_raw = true;
    	$Cache = $this->make(Cache::DRIVER_FILE);
    	$Cache->write($this->key, $this->data);

    	$value = $Cache->load($this->key, $get_raw);

    	$this->assertEquals($this->data, $value);

    	$this->deleteFile();
    }

    public function testDataMethodArray()
    {
    	$Cache = $this->make(Cache::DRIVER_ARRAY);

    	$value = $Cache->load($this->key, true);

    	$this->assertNull($value);

		$value = $Cache->data($this->key, function() {
			return strtolower($this->data);
		}, [], 5000);

    	$this->assertEquals(strtolower($this->data), $value);

    	$this->deleteArray();
    }

    public function testDataMethodFile()
    {
    	$Cache = $this->make(Cache::DRIVER_FILE);

    	$value = $Cache->load($this->key, true);

    	$this->assertNull($value);

		$value = $Cache->data($this->key, function() {
			return strtolower($this->data);
		}, [], 5000);

    	$this->assertEquals(strtolower($this->data), $value);

    	$this->deleteFile();
    }


	private function make($type = null)
	{
		if (is_null($type)) {
			$type = env('CACHE_DRIVER');
		}

		if ($type == Cache::DRIVER_FILE) {
			return new Cache($type, static::$cache_file_path);
		} else {
			return new Cache($type);
		}
	}

	private function deleteArray() {
		$Cache = $this->make(Cache::DRIVER_ARRAY);
    	$CacheItem = $Cache->load($this->key, false);
    	$Cache->delete($CacheItem);
	}

	private function deleteFile() {
		$Cache = $this->make(Cache::DRIVER_FILE);
    	$CacheItem = $Cache->load($this->key, false);
    	$Cache->delete($CacheItem);
	}

	protected function tearDown()
	{
		$this->deleteArray();
    	$this->deleteFile();

		unset($this->key);
		unset($this->data);

		parent::tearDown();
	}
}