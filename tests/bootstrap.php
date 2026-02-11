<?php
/**
 * PHPUnit bootstrap file for My REST Mailer plugin tests.
 *
 * Loads the WordPress test framework and the plugin under test.
 *
 * @package My_REST_Mailer
 * @since   2.1.0
 */

// Composer autoloader (if available).
$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

// Path to the WordPress test suite installation (set via environment or default).
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Ensure the WP test suite config file exists.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Have you run the WP test suite installer?" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 *
 * @since 2.1.0
 * @return void
 */
function _manually_load_plugin(): void {

	// Define plugin path constant used throughout the test suite.
	if ( ! defined( 'MRM_PLUGIN_FILE' ) ) {
		define( 'MRM_PLUGIN_FILE', dirname( __DIR__ ) . '/my-rest-mailer.php' );
	}

	require MRM_PLUGIN_FILE;
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
