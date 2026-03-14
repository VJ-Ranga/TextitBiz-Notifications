<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Integration_MetForm {
	private $plugin;

	public function __construct( TextitBiz_Notifications $plugin ) {
		$this->plugin = $plugin;
		add_action( 'metform_after_store_form_data', array( $this, 'handle_submission' ), 10, 4 );
	}

	public function handle_submission( $form_id, $form_data, $form_settings, $attributes ) {
		if ( ! $this->plugin->is_integration_enabled( 'metform' ) ) {
			return;
		}

		$form_key = 'metform:' . $form_id;
		if ( ! $this->plugin->is_form_monitored( $form_key ) ) {
			return;
		}

		$page_url = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$payload  = TextitBiz_Notifications::build_payload(
			'metform',
			$form_id,
			get_the_title( $form_id ),
			is_array( $form_data ) ? $form_data : array(),
			$page_url
		);

		$payload['attributes'] = $attributes;
		$payload['form_key']   = $form_key;

		$this->plugin->handle_submission( 'metform', $payload );
	}
}
