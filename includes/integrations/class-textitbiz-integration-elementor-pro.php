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

		$record_fields = $record->get( 'fields' );
		$fields        = array();

		foreach ( $record_fields as $field ) {
			$key            = ! empty( $field['id'] ) ? $field['id'] : $field['title'];
			$fields[ $key ] = isset( $field['value'] ) ? $field['value'] : '';
		}

		$form_name = $record->get_form_settings( 'form_name' );
		$post_id   = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$form_key  = 'elementor_pro:' . $post_id . ':' . sanitize_title( $form_name );

		if ( ! $this->plugin->is_form_monitored( $form_key ) ) {
			return;
		}

		$page_url            = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';
		$payload             = TextitBiz_Notifications::build_payload( 'elementor_pro', $form_name, $form_name, $fields, $page_url );
		$payload['form_key'] = $form_key;

		$this->plugin->handle_submission( 'elementor_pro', $payload );
	}
}
