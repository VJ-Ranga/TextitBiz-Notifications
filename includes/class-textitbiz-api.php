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
		$api_key = trim( (string) $this->plugin->get_api_password() );

		if ( '' === $user_id || '' === $api_key ) {
			return new WP_Error( 'textitbiz_missing_credentials', 'Textit.biz credentials are missing.' );
		}

		if ( '' === $recipient ) {
			return new WP_Error( 'textitbiz_missing_recipient', 'Recipient phone number is missing.' );
		}

		$payload = array(
			'id'   => $user_id,
			'pw'   => $api_key,
			'to'   => $recipient,
			'text' => $message,
			'ref'  => ! empty( $context['form_id'] ) ? substr( sanitize_text_field( (string) $context['form_id'] ), 0, 15 ) : '',
		);

		$attempts = array(
			array( 'method' => 'POST', 'url' => 'https://textit.biz/sendmsg/' ),
			array( 'method' => 'GET', 'url' => 'https://textit.biz/sendmsg/' ),
			array( 'method' => 'GET', 'url' => 'http://www.textit.biz/sendmsg' ),
		);

		$last_error = null;

		foreach ( $attempts as $attempt ) {
			$result = $this->send_request( $attempt['method'], $attempt['url'], $payload );

			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			$last_error = $result;
		}

		return $last_error ? $last_error : new WP_Error( 'textitbiz_send_failed', 'Textit.biz request failed.' );
	}

	private function send_request( $method, $url, array $payload ) {
		$args = array(
			'timeout' => 20,
		);

		if ( 'POST' === strtoupper( $method ) ) {
			$args['body'] = $payload;
			$response = wp_remote_post( $url, $args );
		} else {
			$response = wp_remote_get( add_query_arg( $payload, $url ), $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = trim( (string) wp_remote_retrieve_body( $response ) );

		if ( '' === $body ) {
			return new WP_Error( 'textitbiz_empty_response', 'Textit.biz returned an empty response.' );
		}

		if ( $this->is_success_response( $status_code, $body ) ) {
			return array(
				'raw'    => $body,
				'status' => $status_code,
			);
		}

		return new WP_Error( 'textitbiz_send_failed', 'Textit.biz error: HTTP ' . $status_code . ' - ' . $body );
	}

	private function is_success_response( $status_code, $body ) {
		if ( $status_code < 200 || $status_code >= 300 ) {
			return false;
		}

		$body_lc = strtolower( $body );

		if ( 0 === stripos( $body_lc, 'ok:' ) ) {
			return true;
		}

		if ( false !== strpos( $body_lc, 'accepted' ) || false !== strpos( $body_lc, 'queued' ) ) {
			return true;
		}

		return false;
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
