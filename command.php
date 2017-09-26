<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Scan WordPress to determine PHP compatibility.
 *
 * Calls PHPCS PHPCompatibility sniffs and interprets the results.
 *
 * ## OPTIONS
 *
 * [--path=<path>]
 * : Path to the WordPress install.
 *
 * @when before_wp_load
 */
$php_compat_command = function() {

	$descriptors = array(
		0 => STDIN,
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$r = proc_open( 'phpcs -i', $descriptors, $pipes );
	$stdout = stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	$stderr = stream_get_contents( $pipes[2] );
	fclose( $pipes[2] );
	$return_code = proc_close( $r );

	if ( 127 === $return_code ) {
		WP_CLI::error( 'phpcs is not available on $PATH.' );
	} elseif ( 0 !== $return_code ) {
		WP_CLI::error( 'Unknown error running phpcs: ' . $stderr );
	}

	if ( false === strpos( $stdout, 'PHPCompatibility' ) ) {
		WP_CLI::error( 'PHPCompatibility standard is not installed.' );
	}

};
WP_CLI::add_command( 'php-compat', $php_compat_command );
