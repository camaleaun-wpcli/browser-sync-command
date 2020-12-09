<?php

class_exists( 'WP_CLI' ) || exit;

class Browser_Sync_Command extends WP_CLI_Command {

	/**
	 * Keep multiple browsers & devices in sync when building websites.
	 */
	public function __invoke( $args, $assoc_args ) {

		$this->nodejs_maybe_install();

		$this->nodejs_maybe_install_primary_package( 'browser-sync' );

		$domain = preg_replace( "#^https?://#", '', $this->url() );

		$homedir = WP_CLI\Utils\get_home_dir();

		$bs_config = array(
			'proxy'     => "https://$domain",
			'host'      => $domain,
			'open'      => 'external',
			'ui'        => false,
			'watch'     => true,
			'ghostMode' => false,
			'files'     => array(
				getcwd() . '/assets/scss/*.scss',
				getcwd() . '/**/*.php',
			)
		);

		$https = array();

		$key = "$homedir/.valet/Certificates/$domain.key";
		if ( file_exists( $key ) ) {
			$https['key'] = $key;
		}

		$cert = "$homedir/.valet/Certificates/$domain.crt";
		if ( file_exists( $cert ) ) {
			$https['cert'] = $cert;
		}

		if ( ! empty( $https ) ) {
			$bs_config['https'] = $https;
		}

		$temp_dir = WP_CLI\Utils\get_temp_dir();

		$file = $temp_dir . 'bs-config-' . $domain . '.js';

		file_put_contents(
			$file,
			'module.exports = ' . wp_json_encode( $bs_config, JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE ) . "\n"
		);

		$cmd = "browser-sync start --config $file";

		WP_CLI::debug( $cmd );
		passthru( $cmd );
	}

	public function path() {
		$path = rtrim( ABSPATH, '/' );
		if ( empty( $path ) ) {
			$config_path = WP_CLI::runcommand( 'config path', array( 'return' => true ) );
			$path        = preg_replace( '#/wp-config.php$#', '', $config_path );
		}
		if ( empty( $path ) ) {
			$path = $this->get_config( 'path' );
		}
		return $path;
	}

	public function url() {
		$url = WP_CLI::runcommand( 'option get home', array( 'return' => true ) );
		if ( empty( $url ) ) {
			$url = $this->get_config( 'url' );
		}
		return $url;
	}

	private function get_config( $name ) {
		$configs = array();
		foreach ( WP_CLI::get_configurator()->to_array() as $config ) {
			$configs = array_merge( $configs, $config );
		}
		if ( ! isset( $configs[ $name ] ) ) {
			WP_CLI::error( "The config '$name' is not defined." );
		}
		return $configs[ $name ];
	}

	private function nodejs_maybe_install() {
		if ( ! $this->nodejs_is_installed() ) {
			WP_CLI::warning( "Node js not installed" );
			$this->nodejs_install();
		}
	}

	private function nodejs_is_installed() {
		$process_run = WP_CLI::launch( 'node -v', false, true );
		$version = $process_run->stdout;
		return ! empty( $version );
	}

	private function nodejs_install() {
		WP_CLI::log( WP_CLI::colorize( "%GInstalling 'nodejs'...%n%_" ) );
		$process_run = WP_CLI::launch( 'curl -sL https://deb.nodesource.com/setup_14.x | sudo -E bash -', false, true );
		$process_run = WP_CLI::launch( 'sudo apt-get install -y nodejs', false, true );
	}

	private function nodejs_maybe_install_primary_package( $name ) {
		if ( ! $this->nodejs_is_installed_primary_package( $name ) ) {
			WP_CLI::warning(
				sprintf( "Package npm '%s' not installed", $name )
			);
			$this->nodejs_install_primary_package( $name );
		}
	}

	private function nodejs_install_primary_package( $name ) {
		WP_CLI::log( WP_CLI::colorize( "%GInstalling '$name'...%n%_" ) );
		WP_CLI::launch( 'sudo npm i -g ' . $name );
	}

	private function nodejs_is_installed_primary_package( $name ) {
		return in_array(
			$name,
			$this->nodejs_installed_primary_packages(),
			true
		);
	}

	private function nodejs_installed_primary_packages() {
		$packages    = array();
		$process_run = WP_CLI::launch( 'npm list -g --depth 0', false, true );
		$packages = $process_run->stdout;
		preg_match_all( '/[+`]--\s+(.+)/', $packages, $output );
		if ( 2 === count( $output ) ) {
			$packages = $output[1];
			foreach ( $packages as &$package ) {
				$package = preg_replace( '/@[\.\d]+$/', '', $package );
			}
		}
		return $packages;
	}
}
WP_CLI::add_command( 'browser-sync', 'Browser_Sync_Command' );
