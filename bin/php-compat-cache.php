<?php

use WP_CLI\Utils;

/**
 * Scan a WordPress.org theme or plugin for compatibilty and cache results.
 *
 * ## OPTIONS
 *
 * <type>
 * : Whether this is a plugin or theme from WordPress.org
 * ---
 * options:
 *   - plugin
 *   - theme
 * ---
 *
 * <name>
 * : Plugin or theme name (aka slug).
 *
 * <cache-dir>
 * : Path to the cache directory.
 *
 * [--prior_versions=<count>]
 * : How many prior versions to scan.
 * ---
 * default: 20
 * ---
 *
 * @when before_wp_load
 */
WP_CLI::add_command( 'php-compat-cache', function( $args, $assoc_args ){
	list( $type, $name, $cache_dir ) = $args;

	if ( empty( $cache_dir ) || ! is_dir( $cache_dir ) ) {
		WP_CLI::error( "Please make sure the cache directory exists before proceeding." );
	}
	$cache_dir = Utils\trailingslashit( realpath( $cache_dir ) );

	exec( 'mkdir -p ' . escapeshellarg( $cache_dir . $type . 's/' . $name ), $output, $code );
	if ( 0 !== $code ) {
		WP_CLI::error( 'Failed to create target cache dir: '. $cache_dir . $name );
	}
	
	$phpcs_exec = false;
	$base_path = dirname( dirname( __FILE__ ) );
	$local_vendor = $base_path . '/vendor/bin/phpcs';
	$package_dir_vendor = dirname( dirname( dirname( $base_path ) ) ) . '/bin/phpcs';
	if ( file_exists( $local_vendor ) ) {
		$phpcs_exec = 'php ' . $local_vendor;
	} elseif( $package_dir_vendor ) {
		$phpcs_exec = 'php ' . $package_dir_vendor;
	}

	if ( ! $phpcs_exec ) {
		WP_CLI::error( "Couldn't find phpcs executable." );
	}

	$versions = array();
	if ( 'plugin' === $type ) {
		$request_url = sprintf( 'https://api.wordpress.org/plugins/info/1.0/%s.json', $name );
		$response = Utils\http_request( 'GET', $request_url );
		if ( 200 !== $response->status_code ) {
			WP_CLI::error( "{$response->status_code} HTTP response" );
		}
		if ( empty( $response->body ) || 'null' === $response->body ) {
			WP_CLI::error( 'Plugin not found.' );
		}
		$plugin_data = json_decode( $response->body, true );
		if ( empty( $plugin_data['versions'] ) ) {
			WP_CLI::error( 'No plugin versions found.' );
		}
		// Versions are returned lowest to highest
		$versions = array_reverse( $plugin_data['versions'], true );
		unset( $versions['trunk'] );
	} elseif ( 'theme' === $type ) {
		$request_url = sprintf( 'https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]=%s&request[fields][versions]=true', $name );
		$response = Utils\http_request( 'GET', $request_url );
		if ( 200 !== $response->status_code ) {
			WP_CLI::error( "{$response->status_code} HTTP response" );
		}
		if ( empty( $response->body ) || 'false' === $response->body ) {
			WP_CLI::error( 'Theme not found.' );
		}
		$theme_data = json_decode( $response->body, true );
		if ( empty( $theme_data['versions'] ) ) {
			WP_CLI::error( 'No theme versions found.' );
		}
		// Versions are returned lowest to highest
		$versions = array_reverse( $theme_data['versions'], true );
	}

	$base_tmp_dir = Utils\get_temp_dir();
	$prepare_dir = Utils\trailingslashit( $base_tmp_dir ) . $name . '-php-compat-cache/';

	$prior_versions = $assoc_args['prior_versions'] < count( $versions ) ? $assoc_args['prior_versions'] : count( $versions );
	WP_CLI::log( 'Scanning prior ' . $prior_versions . ' of ' . count( $versions ) . ' total ' . $name . ' versions' );
	$php_versions = array(
		'5.2',
		'5.3',
		'5.4',
		'5.6',
		'7.0',
		'7.1',
		'7.2',
	);
	$i = 0;
	foreach ( array_slice( $versions, 0, $prior_versions ) as $plugin_version => $download_link ) {
		$i++;
		if ( is_dir( $prepare_dir ) ) {
			exec( 'rm -r ' . escapeshellarg( $prepare_dir ), $output, $code );
			if ( 0 !== $code ) {
				WP_CLI::error( 'Failed to remove prepare dir: '. $prepare_dir );
			}
		}
		exec( 'mkdir -p ' . escapeshellarg( $prepare_dir ), $output, $code );
		if ( 0 !== $code ) {
			WP_CLI::error( 'Failed to create prepare dir: '. $prepare_dir );
		}
		$download_fname = basename( $download_link );
		WP_CLI::log( 'Downloading ' . $name . ' version ' . $plugin_version . ' (' . $i . '/' . $prior_versions . ')' );
		exec( 'wget -q -O ' . escapeshellarg( $prepare_dir . $download_fname ) . ' ' . escapeshellarg( $download_link ), $output, $code );
		if ( 0 !== $code ) {
			WP_CLI::warning( 'Download failed, skipping.' );
			continue;
		}
		exec( 'unzip ' . escapeshellarg( $prepare_dir . $download_fname ) . ' -d ' . escapeshellarg( $prepare_dir ), $output, $code );
		if ( 0 !== $code ) {
			WP_CLI::warning( 'Extraction failed, skipping.' );
			continue;
		}
		$cache_data = array(
			'name'         => $name,
			'type'         => $type,
			'version'      => $plugin_version,
			'php_versions' => array(),
			'failed_php_versions' => array(),
		);
		$descriptors = array(
			0 => STDIN,
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		foreach( $php_versions as $php_version ) {
			WP_CLI::log( 'Scanning plugin for PHP ' . $php_version );
			$base_check = $phpcs_exec . ' --standard=PHPCompatibility --runtime-set testVersion ' . $php_version . ' --extensions=php --ignore=/node_modules/,/bower_components/,/svn/ --report=json';
			$r = proc_open( $base_check . ' ' . escapeshellarg( $prepare_dir . $name ), $descriptors, $pipes );
			$stdout = stream_get_contents( $pipes[1] );
			fclose( $pipes[1] );
			$stderr = stream_get_contents( $pipes[2] );
			fclose( $pipes[2] );
			proc_close( $r );
			$scan_result = json_decode( $stdout, true );
			if ( empty( $scan_result['totals'] ) ) {
				WP_CLI::error( 'Scan failed. Please debug phpcs: ' . var_export( $stdout, true ) );
			}
			$php_version_data = array(
				'file_count'    => count( $scan_result['files'] ),
				'errors'        => array(),
				'error_count'   => $scan_result['totals']['errors'],
				'warnings'      => array(),
				'warning_count' => $scan_result['totals']['warnings'],
			);
			if ( 0 === $scan_result['totals']['errors'] ) {
				$php_version_data['status'] = 'success';
			} else {
				$php_version_data['status'] = 'failure';
				$cache_data['failed_php_versions'][] = $php_version;
			}
			foreach( $scan_result['files'] as $fdata ) {
				foreach( $fdata['messages'] as $message ) {
					$message_type = strtolower( $message['type'] ) . 's';
					$php_version_data[ $message_type ][] = $message['source'];
					$php_version_data[ $message_type ] = array_unique( $php_version_data[ $message_type ] );
				}
			}
			$cache_data['php_versions'][ $php_version ] = $php_version_data;
		}
		file_put_contents( $cache_dir . $type . 's/' . Utils\trailingslashit( $name ) . $name . '.' . $plugin_version . '.json', json_encode( $cache_data, JSON_PRETTY_PRINT ) );
		WP_CLI::log( 'Wrote results to cache file.' );
	}
	if ( is_dir( $prepare_dir ) ) {
		exec( 'rm -r ' . escapeshellarg( $prepare_dir ), $output, $code );
		if ( 0 !== $code ) {
			WP_CLI::error( 'Failed to remove prepare dir: '. $prepare_dir );
		}
	}
	WP_CLI::success( 'Scan and cache process complete.' );
});
