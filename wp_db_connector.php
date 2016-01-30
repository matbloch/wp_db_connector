<?php
/*
 * Plugin Name: Wordpress Table Connector
 * Version: 1.0
 * Plugin URI: https://github.com/matbloch
 * Description: This is a plugin to test the wordpress table connector class
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

/**
 * Returns the main instance of WordPress_Plugin_Template to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object WordPress_Plugin_Template
 */

$test = new \wpdbc\WPDBCTest();

$test_data = array(
    'stupid' => 123,
    'name' => 'Muster',
    'vorname' => 'Fred',
    'code' => '#243645',
    'id_nummer' => 1214353
);

/*
// insert item
$result = $item->insert(array(
    'name'=>'Freddy',
    'vorname'=>'Krueger',
    'code'=>'#8239709',
    'alter'=> '22',
    'id_nummer' => 2543524
));
// insert item
$result = $item->insert(array(
    'name'=>'Martin',
    'vorname'=>'Solveign',
    'alter'=> '54',
    'code'=>'#000382',
    'id_nummer' => 134523
));

*/


//$test->test_unique_key_item();
$test->test_coupled_key_item();
// $test->test_validation();
// $test->test_sanitation();

die();