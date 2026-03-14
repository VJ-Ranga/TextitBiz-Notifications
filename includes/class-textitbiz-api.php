<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_API {
	private $plugin;

	public function __construct( TextitBiz_Notifications $plugin ) {
		$this->plugin = $plugin;
	}

	public function send( $recipient, $message, array $context = array() ) {
		$user_id = trim( (string) $this->plugin->get_setting( 'user_id', '' ) );
		$api_key = trim( (string) $this->plugin->get_setting( 'api_key', '' ) );

		if ( '' === $user_id || '' === $api_key ) {
			return new WP_Error( 'textitbiz_missing_credentials', 'Textit.biz credentials are missing.' );
		}

		if ( '' === $recipient ) {
			return new WP_Error( 'textitbiz_missing_recipient', 'Recipient phone number is missing.' );
		}

		$response = wp_remote_post(
			'https://textit.biz/sendmsg/',
			array(
				'timeout' => 20,
				'body'    => array(
					'id'   => $user_id,
					'pw'   => $api_key,
					'to'   => $recipient,
					'text' => $message,
					'ref'  => ! empty( $context['form_id'] ) ? substr( sanitize_text_field( (string) $context['form_id'] ), 0, 15 ) : '',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = trim( (string) wp_remote_retrieve_body( $response ) );

		if ( '' === $body ) {
			return new WP_Error( 'textitbiz_empty_response', 'Textit.biz returned an empty response.' );
		}

		if ( 0 !== stripos( $body, 'OK:' ) ) {
			return new WP_Error( 'textitbiz_send_failed', $body );
		}

		return array(
			'raw' => $body,
		);
	}

	public static function normalize_phone( $value ) {
		$number = preg_replace( '/[^0-9]/', '', (string) $value );

		if ( 9 === strlen( $number ) ) {
			return '94' . $number;
		}

		if ( 10 === strlen( $number ) && '0' === substr( $number, 0, 1 ) ) {
			return '94' . substr( $number, 1 );
		}

		if ( 12 === strlen( $number ) && '940' === substr( $number, 0, 3 ) ) {
			return '94' . substr( $number, 3 );
		}

		return $number;
	}
}
