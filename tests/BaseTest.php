<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 11:38 AM
 */

namespace Worklog\Tests;

use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase {

    public function testSetUp()
    {
        $this->assertEquals(
            true,
            is_dir(APPLICATION_PATH)
        );
    }
}