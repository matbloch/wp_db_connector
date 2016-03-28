<?php
/*
 * Plugin Name: Wordpress Table Connector
 * Version: 1.0
 * Plugin URI: https://github.com/matbloch
 * Description: This is a plugin to test the Wordpress table connector class
 * Author: Matthias Bloch
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

$test = new \wpdbc\WPDBCTest();

//$test->object_memory_usage();
//$test->add_dummy_data();
//$test->test_unique_key_item();
//$test->test_coupled_key_item();
//$test->test_direct_manipulation();
//$test->test_validation();
//$test->test_sanitation();

//die();