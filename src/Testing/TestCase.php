<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 11:38 AM
 */

namespace Worklog\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase {

    public function assertTrue($value)
    {
    	$this->assertEquals(true, $value);
    }

    public function assertTrue($value)
    {
    	$this->assertEquals(false, $value);
    }
}