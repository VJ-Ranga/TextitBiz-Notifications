<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_GitHub_Updater {
	private $plugin_file;
	private $plugin_basename;
	private $version;
	private $owner;
	private $repo;
	private $cache_key;

	public function __construct( $plugin_file, $version, $owner, $repo ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->version         = (string) $version;
		$this->owner           = (string) $owner;
		$this->repo            = (string) $repo;
		$this->cache_key       = 'textitbiz_github_release';

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache_after_upgrade' ), 10, 2 );
	}

	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( empty( $release['version'] ) || empty( $release['zip_url'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], $this->version, '>' ) ) {
			$plugin_slug = dirname( $this->plugin_basename );

			$transient->response[ $this->plugin_basename ] = (object) array(
				'slug'        => $plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $release['version'],
				'url'         => $this->get_repo_url(),
				'package'     => $release['zip_url'],
			);
		}

		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		$plugin_slug = dirname( $this->plugin_basename );

		if ( $plugin_slug !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		return (object) array(
			'name'          => 'TextitBiz Notifications',
			'slug'          => $plugin_slug,
			'plugin'        => $this->plugin_basename,
			'version'       => ! empty( $release['version'] ) ? $release['version'] : $this->version,
			'author'        => '<a href="https://github.com/' . esc_attr( $this->owner ) . '">VJ Ranga</a>',
			'homepage'      => $this->get_repo_url(),
			'requires'      => '6.0',
			'tested'        => '6.8',
			'requires_php'  => '7.4',
			'download_link' => ! empty( $release['zip_url'] ) ? $release['zip_url'] : '',
			'sections'      => array(
				'description' => 'Send SMS alerts through Textit.biz for selected WordPress form submissions.',
				'changelog'   => ! empty( $release['body'] ) ? nl2br( esc_html( $release['body'] ) ) : 'See GitHub releases for details.',
			),
		);
	}

	public function purge_cache_after_upgrade( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}

		if ( empty( $hook_extra['plugins'] ) || ! is_array( $hook_extra['plugins'] ) ) {
			return;
		}

		if ( in_array( $this->plugin_basename, $hook_extra['plugins'], true ) ) {
			delete_transient( $this->cache_key );
		}
	}

	private function get_latest_release() {
		$cached = get_transient( $this->cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$api_url  = 'https://api.github.com/repos/' . rawurlencode( $this->owner ) . '/' . rawurlencode( $this->repo ) . '/releases/latest';
		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return array();
		}

		$version = ltrim( (string) $body['tag_name'], 'vV' );
		$zip_url = 'https://github.com/' . rawurlencode( $this->owner ) . '/' . rawurlencode( $this->repo ) . '/archive/refs/tags/' . rawurlencode( (string) $body['tag_name'] ) . '.zip';

		$release = array(
			'version' => $version,
			'zip_url' => $zip_url,
			'body'    => isset( $body['body'] ) ? (string) $body['body'] : '',
		);

		set_transient( $this->cache_key, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	private function get_repo_url() {
		return 'https://github.com/' . rawurlencode( $this->owner ) . '/' . rawurlencode( $this->repo );
	}
}
