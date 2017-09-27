<?php

use WP_CLI\Utils;

class PHP_Compat_Command {

	/**
	 * Scan WordPress, plugins and themes for PHP version compatibility.
	 *
	 * Uses the [PHPCompatibility PHPCS sniffs](https://github.com/wimg/PHPCompatibility)
	 * and interprets the WordPress-specific results.
	 *
	 * ## OPTIONS
	 *
	 * [--path=<path>]
	 * : Path to the WordPress install. Defaults to current directory.
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
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

		if ( ! is_readable( ABSPATH . 'wp-includes/version.php' ) ) {
			WP_CLI::error(
				"This does not seem to be a WordPress install.\n" .
				'Pass --path=`path/to/wordpress`'
			);
		}

		global $wp_version;
		include ABSPATH . 'wp-includes/version.php';

		$results = array();
		$wp_result = array(
			'name'    => 'wordpress',
			'type'    => 'core',
			'version' => $wp_version,
			'files'   => '',
		);
		if ( version_compare( $wp_version, '4.4', '>=' ) ) {
			$wp_result['compat'] = 'success';
		} else {
			$wp_result['compat'] = 'failure';
		}
		$results[] = $wp_result;

		$plugins = array();
		// @todo handle non-standard plugin dirs
		foreach( glob( ABSPATH . '/wp-content/plugins/*/*.php' ) as $file ) {
			$fp = fopen( $file, 'r' );
			$file_data = fread( $fp, 8192 );
			fclose( $fp );
			$file_data = str_replace( "\r", "\n", $file_data );
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( 'Version', '/' ) . ':(.*)$/mi', $file_data, $match ) ) {
				$plugins[] = array(
					'path'     => $file,
					'version'  => trim( $match[1] ),
					'basename' => basename( dirname( $file ) ),
				);
			}
		}

		foreach( $plugins as $plugin ) {
			$result = self::scan_extension( $plugin );
			$result['type'] = 'plugin';
			$results[] = $result;
		}

		$themes = array();
		// @todo handle non-standard theme dirs
		foreach( glob( ABSPATH . '/wp-content/themes/*/style.css' ) as $file ) {
			$fp = fopen( $file, 'r' );
			$file_data = fread( $fp, 8192 );
			fclose( $fp );
			$file_data = str_replace( "\r", "\n", $file_data );
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( 'Version', '/' ) . ':(.*)$/mi', $file_data, $match ) ) {
				$themes[] = array(
					'path'     => $file,
					'version'  => trim( $match[1] ),
					'basename' => basename( dirname( $file ) ),
				);
			}
		}

		foreach( $themes as $theme ) {
			$result = self::scan_extension( $theme );
			$result['type'] = 'theme';
			$results[] = $result;
		}

		$fields = array(
			'name',
			'type',
			'compat',
			'version',
			'files',
		);
		WP_CLI\Utils\format_items( 'table', $results, $fields );
	}

	/**
	 * Scan an extension for its PHP compatibility
	 *
	 * @param array $extension Details about the extension.
	 * @return array
	 */
	private static function scan_extension( $extension ) {
		$result = array(
			'name'     => $extension['basename'],
			'version'  => $extension['version'],
		);
		$descriptors = array(
			0 => STDIN,
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$base_check = 'phpcs --standard=PHPCompatibility --extensions=php --ignore=/node_modules/,/bower_components/,/svn/ --report=json';
		$r = proc_open( $base_check . ' ' . escapeshellarg( dirname( $extension['path'] ) ), $descriptors, $pipes );
		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );
		$return_code = proc_close( $r );
		$scan_result = json_decode( $stdout, true );
		$result['files'] = isset( $scan_result['files'] ) ? count( $scan_result['files'] ) : '';
		if ( isset( $scan_result['totals']['errors'] )
			&& 0 === $scan_result['totals']['errors'] ) {
			$result['compat'] = 'success';
		} else {
			$result['compat'] = 'failure';
		}
		return $result;
	}

}
