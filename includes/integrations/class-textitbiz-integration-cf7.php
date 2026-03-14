<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Integration_CF7 {
	private $plugin;

	public function __construct( TextitBiz_Notifications $plugin ) {
		$this->plugin = $plugin;
		add_action( 'wpcf7_mail_sent', array( $this, 'handle_submission' ), 10, 1 );
	}

	public function handle_submission( $contact_form ) {
		if ( ! $this->plugin->is_integration_enabled( 'contact_form_7' ) ) {
			return;
		}

		$form_key = 'contact_form_7:' . $contact_form->id();
		if ( ! $this->plugin->is_form_monitored( $form_key ) ) {
			return;
		}

		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$fields              = (array) $submission->get_posted_data();
		$page_url            = $submission->get_meta( 'url' );
		$payload             = TextitBiz_Notifications::build_payload( 'contact_form_7', $contact_form->id(), $contact_form->title(), $fields, $page_url );
		$payload['form_key'] = $form_key;

		$this->plugin->handle_submission( 'contact_form_7', $payload );
	}
}
