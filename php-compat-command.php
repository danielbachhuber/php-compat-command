<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

WP_CLI::add_command( 'php-compat', 'PHP_Compat_Command' );
WP_CLI::add_command( 'php-compat-cache', 'PHP_Compat_Cache_Command' );
