<?php
/**
 * Created by PhpStorm.
 * User: allenmccabe
 * Date: 4/27/17
 * Time: 11:38 AM
 */

namespace Worklog\Testing;

// use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * An application instance
     */
    protected $app;

    /**
     * A database driver
     */
    protected $db;

    /**
     * The callbacks that should be run after the application is created.
     *
     * @var array
     */
    protected $afterApplicationCreatedCallbacks = [];

    /**
     * The callbacks that should be run before the application is destroyed.
     *
     * @var array
     */
    protected $beforeApplicationDestroyedCallbacks = [];

    /**
     * Indicates if we have made it through the base setUp function.
     *
     * @var bool
     */
    protected $setUpHasRun = false;

    /**
     * Creates the application.
     *
     * Needs to be implemented by subclasses.
     *
     * @return Application
     */
    public function createApplication()
    {
        return App();
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp()
    {
        require_once(dirname(__DIR__).'/bootstrap.php');

        if (! $this->app) {
            $this->refreshApplication();
        }
        $this->setUpTraits();
        foreach ($this->afterApplicationCreatedCallbacks as $callback) {
            call_user_func($callback);
        }
        $this->setUpHasRun = true;
    }
    /**
     * Refresh the application instance.
     *
     * @return void
     */
    protected function refreshApplication()
    {
        $this->app = $this->createApplication();
        $this->db = $this->app->db();
    }

    /**
     * Boot the testing helper traits.
     *
     * @return void
     */
    protected function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));
        if (method_exists($this, 'runDatabaseMigrations')) {
            $this->runDatabaseMigrations();
        }
        if (method_exists($this, 'beginDatabaseTransaction')) {
            $this->beginDatabaseTransaction();
        }
        if (method_exists($this, 'disableMiddlewareForAllTests')) {
            $this->disableMiddlewareForAllTests();
        }
        if (method_exists($this, 'disableEventsForAllTests')) {
            $this->disableEventsForAllTests();
        }
    }

    /**
     * Assert that a given where condition exists in the database.
     *
     * @param  string $table
     * @param  array  $data
     * @param  string $connection
     * @return $this
     */
    protected function seeInDatabase($table, array $where)
    {
        $count = count($this->db->select($table, $where));
        $this->assertGreaterThan(0, $count, sprintf(
            'Unable to find row in database table [%s] that matched attributes [%s].', $table, json_encode($where)
        ));

        return $this;
    }

    /**
     * Assert that a given where condition does not exist in the database.
     *
     * @param  string $table
     * @param  array  $data
     * @param  string $connection
     * @return $this
     */
    protected function notSeeInDatabase($table, array $where)
    {
        $count = count($this->db->select($table, $where));
        $this->assertEquals(0, $count, sprintf(
            'Found unexpected row in database table [%s] that matched attributes [%s].', $table, json_encode($where)
        ));

        return $this;
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    protected function tearDown()
    {
        if ($this->app) {
            foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
                call_user_func($callback);
            }
            $this->app->flush();
            $this->app = null;
        }
        $this->setUpHasRun = false;
        if (property_exists($this, 'serverVariables')) {
            $this->serverVariables = [];
        }
        if (class_exists('Mockery')) {
            Mockery::close();
        }
        $this->afterApplicationCreatedCallbacks = [];
        $this->beforeApplicationDestroyedCallbacks = [];
    }

    /**
     * Register a callback to be run after the application is created.
     *
     * @param  callable $callback
     * @return void
     */
    public function afterApplicationCreated(callable $callback)
    {
        $this->afterApplicationCreatedCallbacks[] = $callback;
        if ($this->setUpHasRun) {
            call_user_func($callback);
        }
    }

    /**
     * Register a callback to be run before the application is destroyed.
     *
     * @param  callable $callback
     * @return void
     */
    protected function beforeApplicationDestroyed(callable $callback)
    {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }

}
