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
	 * [--fields=<fields>]
	 * : Limit output to specific fields.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check compatibility of a WordPress install in the 'danielbachhuber' path
	 *     $ wp php-compat --path=danielbachhuber
	 *     +-----------------------+--------+---------+---------+-------+-------+
	 *     | name                  | type   | compat  | version | time  | files |
	 *     +-----------------------+--------+---------+---------+-------+-------+
	 *     | wordpress             | core   | success | 4.7.6   |       |       |
	 *     | akismet               | plugin | success | 3.2     | 1.39s | 13    |
	 *     | debug-bar             | plugin | success | 0.8.4   | 0.29s | 10    |
	 *     | oembed-gist           | plugin | success | 4.7.1   | 0.08s | 1     |
	 *     | danielbachhuber-theme | theme  | success | 0.0.0   | 0.81s | 30    |
	 *     | twentyfifteen         | theme  | success | 1.7     | 0.42s | 22    |
	 *     | twentyseventeen       | theme  | success | 1.1     | 0.63s | 35    |
	 *     | twentysixteen         | theme  | success | 1.3     | 0.5s  | 23    |
	 *     +-----------------------+--------+---------+---------+-------+-------+
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
			'time'    => 'cached',
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
					'type'     => 'plugin',
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
					'type'     => 'theme',
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

		if ( isset( $assoc_args['fields'] ) ) {
			$fields = explode( ',', $assoc_args['fields'] );
		} else {
			$fields = array(
				'name',
				'type',
				'compat',
				'version',
				'time',
				'files',
			);
		}
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

		$php_compat_cache = getenv( 'WP_CLI_PHP_COMPAT_CACHE' );
		if ( $php_compat_cache ) {
			$cache_file = Utils\trailingslashit( realpath( $php_compat_cache ) ) . $extension['type'] . 's/' . $extension['basename'] . '/' . $extension['basename'] . '.' . $extension['version'] . '.json';
			if ( file_exists( $cache_file ) ) {
				$cache_data = json_decode( file_get_contents( $cache_file ), true );
				if ( ! empty( $cache_data['php_versions']['7.0'] ) ) {
					$result['time'] = 'cached';
					$result['files'] = $cache_data['php_versions']['7.0']['file_count'];
					if ( 0 === $cache_data['php_versions']['7.0']['error_count'] ) {
						$result['compat'] = 'success';
					} else {
						$result['compat'] = 'failure';
					}
					return $result;
				}
			}
		}

		$phpcs_exec = false;
		$base_path = dirname( dirname( __FILE__ ) );
		$local_vendor = $base_path . '/vendor/bin/phpcs';
		$package_dir_vendor = dirname( dirname( $base_path ) ) . '/bin/phpcs';
		if ( file_exists( $local_vendor ) ) {
			$phpcs_exec = self::get_php_binary() . ' ' . $local_vendor;
		} elseif( $package_dir_vendor ) {
			$phpcs_exec = self::get_php_binary() . ' ' . $package_dir_vendor;
		}

		if ( ! $phpcs_exec ) {
			WP_CLI::error( "Couldn't find phpcs executable." );
		}

		$base_check = $phpcs_exec . ' --standard=PHPCompatibility --runtime-set testVersion 7.0 --extensions=php --ignore=/node_modules/,/bower_components/,/svn/ --report=json';
		$start_time = microtime( true );
		$r = proc_open( $base_check . ' ' . escapeshellarg( dirname( $extension['path'] ) ), $descriptors, $pipes );
		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );
		$return_code = proc_close( $r );
		$end_time = microtime( true ) - $start_time;
		$result['time'] = round( $end_time, 2 ) . 's';
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

	private static function get_php_binary() {
		if ( getenv( 'WP_CLI_PHP_USED' ) )
			return getenv( 'WP_CLI_PHP_USED' );

		if ( getenv( 'WP_CLI_PHP' ) )
			return getenv( 'WP_CLI_PHP' );

		if ( defined( 'PHP_BINARY' ) )
			return PHP_BINARY;

		return 'php';
	}

}
