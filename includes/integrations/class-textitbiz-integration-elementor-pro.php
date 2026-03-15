<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Integration_Elementor_Pro {
	private $plugin;

	public function __construct( TextitBiz_Notifications $plugin ) {
		$this->plugin = $plugin;
		add_action( 'elementor_pro/forms/new_record', array( $this, 'handle_submission' ), 10, 2 );
	}

	public function handle_submission( $record, $ajax_handler ) {
		if ( ! $this->plugin->is_integration_enabled( 'elementor_pro' ) ) {
			return;
		}

		$post_id_raw = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
		$referrer_raw = filter_input( INPUT_POST, 'referrer', FILTER_UNSAFE_RAW );
		$record_fields = $record->get( 'fields' );
		$fields        = array();

		foreach ( $record_fields as $field ) {
			$key            = ! empty( $field['id'] ) ? $field['id'] : $field['title'];
			$fields[ $key ] = isset( $field['value'] ) ? $field['value'] : '';
		}

		$form_name = $record->get_form_settings( 'form_name' );
		$post_id   = absint( $post_id_raw );
		$form_key  = 'elementor_pro:' . $post_id . ':' . sanitize_title( $form_name );

		if ( ! $this->plugin->is_form_monitored( $form_key ) ) {
			return;
		}

		$page_url            = $referrer_raw ? esc_url_raw( wp_unslash( $referrer_raw ) ) : '';
		$payload             = TextitBiz_Notifications::build_payload( 'elementor_pro', $form_name, $form_name, $fields, $page_url );
		$payload['form_key'] = $form_key;

		$this->plugin->handle_submission( 'elementor_pro', $payload );
	}
}
