<?php
/**
 * Bootstrap unit testing
 */
require('../src/bootstrap.php');

if (defined('STDIN')) {
    chdir(dirname(__FILE__));
}