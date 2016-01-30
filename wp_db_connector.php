<?php
/*
 * Plugin Name: Wordpress Table Connector
 * Version: 1.0
 * Plugin URI: http://www.hughlashbrooke.com/
 * Description: This is a plugin to test the wordpress table connector class
 * Author: Hugh Lashbrooke
 * Author URI: https://github.com/matbloch
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * @package WordPress
 * @author Matthias Bloch
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// Load plugin class files
require_once( 'src/wp_db_connector.class.php' );
require_once( 'test/test.class.php' );

/**
 * Returns the main instance of WordPress_Plugin_Template to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object WordPress_Plugin_Template
 */

$test = new \wpdbc\WPDBCTest();

//$test->test_unique_key_item();
// $test->test_coupled_key_item();
// $test->test_validation();
// $test->test_sanitation();