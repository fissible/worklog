<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 11:38 AM
 */

namespace Tests;

use Worklog\TestCase as BaseTestCase;

class TestCase extends BaseTestCase {

    public function testSetUp()
    {
        $this->assetTrue(defined('APPLICATION_PATH'));
        $this->assertNull(null);
        $this->assertTrue(is_dir(APPLICATION_PATH));
    }
}