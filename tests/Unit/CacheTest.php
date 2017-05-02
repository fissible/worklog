<?php

namespace Tests\Unit;

use Tests\TestCase;
use Worklog\Cache\Cache;

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


    /**
     * @dataProvider driverProvider
     * @param $Driver
     */
    public function testDelete($Driver)
    {
        $Driver->write($this->key, $this->data);
    	$CacheItem = $Driver->load($this->key, false);
        $Driver->delete($CacheItem);
    	$value = $Driver->load($this->key, true);

    	$this->assertNull($value);
    }

    /**
     * @dataProvider driverProvider
     * @param $Driver
     */
    public function testReadWrite($Driver)
    {
    	$get_raw = true;
        $Driver->write($this->key, $this->data);

    	$value = $Driver->load($this->key, $get_raw);

    	$this->assertEquals($this->data, $value);

    	$this->deleteFile();
    }

    /**
     * @dataProvider driverProvider
     * @param $Driver
     */
    public function testDataMethod($Driver)
    {
        $value = $Driver->load($this->key, true);

        $this->assertNull($value);

        $value = $Driver->data($this->key, function() {
            return strtolower($this->data);
        }, [], 5000);

        $this->assertEquals(strtolower($this->data), $value);

        $this->deleteArray();
    }


	public function driverProvider()
    {
        return [
            'Array driver' => [ $this->make(Cache::DRIVER_FILE)  ],
            'File driver'  => [ $this->make(Cache::DRIVER_ARRAY) ]
        ];
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