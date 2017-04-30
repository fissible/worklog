<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 11:38 AM
 */

namespace Worklog\Testing;

class TestCase extends \PHPUnit_Framework_TestCase {

	/**
	 * An application instance
	 */
	protected $app;

	public function __construct() {
		require_once(dirname(__DIR__).'/bootstrap.php');
	}


	protected function refreshApplication()
	{
		$this->app = App();
	}

}