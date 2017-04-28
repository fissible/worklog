<?php
/**
 * Bootstrap unit testing
 */
require(dirname(__DIR__).'/src/bootstrap.php');

if (defined('STDIN')) {
    chdir(dirname(__FILE__));
}