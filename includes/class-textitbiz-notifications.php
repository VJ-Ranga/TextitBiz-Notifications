<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Notifications {
	const OPTION_KEY = 'textitbiz_notifications_settings';
	const LOG_KEY    = 'textitbiz_notifications_logs';
	const COOLDOWN_SECONDS = 60;

	private static $instance = null;
	private $detector;
	private $api;
	private $admin;
	private $integration_manager;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->detector            = new TextitBiz_Detector();
		$this->api                 = new TextitBiz_API( $this );
		$this->admin               = new TextitBiz_Admin( $this, $this->detector );
		$this->integration_manager = new TextitBiz_Integration_Manager( $this, $this->detector );
	}

	public function get_settings() {
		$defaults = array(
			'enabled'               => '0',
			'user_id'               => '',
			'api_key'               => '',
			'api_key_enc'           => '',
			'admin_phone'           => '',
			'monitored_forms'       => array(),
			'message_template'      => "New {form_name}\nName: {name}\nPhone: {phone}",
			'enable_metform'        => '1',
			'enable_elementor_pro'  => '1',
			'enable_contact_form_7' => '1',
			'enable_woocommerce'    => '1',
		);

		$stored   = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( $stored, $defaults );

		if ( ! empty( $settings['api_key'] ) && empty( $settings['api_key_enc'] ) ) {
			$encrypted = $this->encrypt_secret( $settings['api_key'] );

			if ( '' !== $encrypted ) {
				$settings['api_key_enc'] = $encrypted;
				unset( $settings['api_key'] );

				$stored['api_key_enc'] = $encrypted;
				unset( $stored['api_key'] );
				update_option( self::OPTION_KEY, $stored, false );
			}
		}

		return $settings;
	}

	public function get_setting( $key, $default = '' ) {
		if ( 'api_key' === $key ) {
			$password = $this->get_api_password();

			return '' === $password ? $default : $password;
		}

		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public function get_api_password() {
		$settings = $this->get_settings();

		if ( ! empty( $settings['api_key_enc'] ) ) {
			$decrypted = $this->decrypt_secret( $settings['api_key_enc'] );

			if ( '' !== $decrypted ) {
				return $decrypted;
			}
		}

		return isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
	}

	public function encrypt_secret( $plain_text ) {
		$plain_text = (string) $plain_text;

		if ( '' === $plain_text || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}

		$key = $this->get_crypto_key();
		if ( '' === $key ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		if ( false === $iv_length ) {
			return '';
		}

		try {
			$iv = random_bytes( $iv_length );
		} catch ( Exception $exception ) {
			return '';
		}
		$encrypted = openssl_encrypt( $plain_text, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	public function decrypt_secret( $encoded_cipher ) {
		$encoded_cipher = (string) $encoded_cipher;

		if ( '' === $encoded_cipher || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$key = $this->get_crypto_key();
		if ( '' === $key ) {
			return '';
		}

		$raw = base64_decode( $encoded_cipher, true );
		if ( false === $raw ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		if ( false === $iv_length || strlen( $raw ) <= $iv_length ) {
			return '';
		}

		$iv         = substr( $raw, 0, $iv_length );
		$ciphertext = substr( $raw, $iv_length );
		$plain      = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : (string) $plain;
	}

	private function get_crypto_key() {
		$salt = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|textitbiz-notifications';

		return hash( 'sha256', $salt, true );
	}

	public function is_integration_enabled( $key ) {
		return '1' === (string) $this->get_setting( 'enable_' . $key, '0' );
	}

	public function get_monitored_forms() {
		$forms = $this->get_setting( 'monitored_forms', array() );

		return is_array( $forms ) ? array_values( array_filter( array_map( 'strval', $forms ) ) ) : array();
	}

	public function is_form_monitored( $form_key ) {
		$selected = $this->get_monitored_forms();

		if ( empty( $selected ) ) {
			return false;
		}

		return in_array( (string) $form_key, $selected, true );
	}

	public function handle_submission( $source, array $payload ) {
		if ( '1' !== (string) $this->get_setting( 'enabled', '0' ) ) {
			$this->log( 'info', $source, 'Submission captured but notifications are disabled.', $payload );
			return;
		}

		$recipient = TextitBiz_API::normalize_phone( $this->get_setting( 'admin_phone', '' ) );

		if ( '' === $recipient ) {
			$this->log( 'warning', $source, 'Submission captured but admin phone is missing.', $payload );
			return;
		}

		if ( $this->is_rate_limited( $source, $payload ) ) {
			$this->log( 'warning', $source, 'SMS skipped due to cooldown protection.', $payload );
			return;
		}

		$message = $this->build_message( $source, $payload );
		$result  = $this->api->send( $recipient, $message, $payload );

		if ( is_wp_error( $result ) ) {
			$this->log(
				'error',
				$source,
				$result->get_error_message(),
				array(
					'payload'   => $payload,
					'message'   => $message,
					'recipient' => $recipient,
				)
			);
			return;
		}

		$this->log(
			'success',
			$source,
			'Notification sent successfully.',
			array(
				'payload'   => $payload,
				'message'   => $message,
				'recipient' => $recipient,
				'response'  => $result,
			)
		);
	}

	public function build_message( $source, array $payload ) {
		$template = $this->get_setting( 'message_template', '' );

		$replacements = array(
			'{source}'    => ucfirst( str_replace( '_', ' ', $source ) ),
			'{form_name}' => isset( $payload['form_name'] ) ? $payload['form_name'] : '',
			'{form_id}'   => isset( $payload['form_id'] ) ? $payload['form_id'] : '',
			'{name}'      => isset( $payload['name'] ) ? $payload['name'] : '',
			'{phone}'     => isset( $payload['phone'] ) ? $payload['phone'] : '',
			'{email}'     => isset( $payload['email'] ) ? $payload['email'] : '',
			'{subject}'   => isset( $payload['subject'] ) ? $payload['subject'] : '',
			'{message}'   => isset( $payload['message'] ) ? $payload['message'] : '',
			'{page_url}'  => isset( $payload['page_url'] ) ? $payload['page_url'] : '',
		);

		$message = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		if ( ! empty( $payload['fields'] ) && is_array( $payload['fields'] ) ) {
			$message = preg_replace_callback(
				'/\{field:([^}]+)\}/',
				static function ( $matches ) use ( $payload ) {
					$field_key = trim( $matches[1] );
					return isset( $payload['fields'][ $field_key ] ) ? (string) $payload['fields'][ $field_key ] : '';
				},
				$message
			);
		}

		return trim( $message );
	}

	public function log( $level, $source, $message, array $context = array() ) {
		$logs   = get_option( self::LOG_KEY, array() );
		$logs[] = array(
			'time'    => current_time( 'mysql' ),
			'level'   => $level,
			'source'  => $source,
			'message' => $message,
			'context' => $this->sanitize_log_context( $context ),
		);

		if ( count( $logs ) > 20 ) {
			$logs = array_slice( $logs, -20 );
		}

		update_option( self::LOG_KEY, $logs, false );
	}

	public function get_logs() {
		$logs = get_option( self::LOG_KEY, array() );

		return is_array( $logs ) ? array_reverse( $logs ) : array();
	}

	public function clear_logs() {
		delete_option( self::LOG_KEY );
	}

	private function sanitize_log_context( array $context ) {
		$clean = array();

		if ( isset( $context['payload'] ) && is_array( $context['payload'] ) ) {
			$payload = $context['payload'];
			$clean['payload'] = array(
				'source'    => isset( $payload['source'] ) ? $payload['source'] : '',
				'form_id'   => isset( $payload['form_id'] ) ? $payload['form_id'] : '',
				'form_name' => isset( $payload['form_name'] ) ? $payload['form_name'] : '',
				'form_key'  => isset( $payload['form_key'] ) ? $payload['form_key'] : '',
				'page_url'  => isset( $payload['page_url'] ) ? esc_url_raw( $payload['page_url'] ) : '',
				'field_keys' => isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? array_keys( $payload['fields'] ) : array(),
			);
		}

		if ( isset( $context['recipient'] ) ) {
			$clean['recipient'] = $this->mask_phone( $context['recipient'] );
		}

		if ( isset( $context['message'] ) ) {
			$clean['message_length'] = strlen( (string) $context['message'] );
		}

		if ( isset( $context['response'] ) ) {
			$clean['response'] = $context['response'];
		}

		return $clean;
	}

	private function mask_phone( $phone ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $phone );

		if ( strlen( $digits ) <= 4 ) {
			return $digits;
		}

		return str_repeat( '*', max( 0, strlen( $digits ) - 4 ) ) . substr( $digits, -4 );
	}

	private function is_rate_limited( $source, array $payload ) {
		$form_key = isset( $payload['form_key'] ) ? (string) $payload['form_key'] : $source;
		$lock_key = 'textitbiz_lock_' . md5( $source . '|' . $form_key );

		if ( get_transient( $lock_key ) ) {
			return true;
		}

		set_transient( $lock_key, 1, self::COOLDOWN_SECONDS );

		return false;
	}

	public static function find_value( array $fields, array $needles ) {
		foreach ( $fields as $key => $value ) {
			$key_lc = strtolower( (string) $key );

			foreach ( $needles as $needle ) {
				if ( false !== strpos( $key_lc, $needle ) && '' !== trim( (string) $value ) ) {
					return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
				}
			}
		}

		return '';
	}

	public static function flatten_fields( array $fields ) {
		$flat = array();

		foreach ( $fields as $key => $value ) {
			$flat[ $key ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		return $flat;
	}

	public static function build_payload( $source, $form_id, $form_name, array $fields, $page_url = '' ) {
		$fields = self::flatten_fields( $fields );

		$name = self::find_value( $fields, array( 'name', 'first_name', 'firstname', 'full_name', 'full-name', 'mf-first-name' ) );
		if ( '' === $name ) {
			$first = self::find_value( $fields, array( 'first' ) );
			$last  = self::find_value( $fields, array( 'last' ) );
			$name  = trim( $first . ' ' . $last );
		}

		return array(
			'source'    => $source,
			'form_id'   => (string) $form_id,
			'form_name' => (string) $form_name,
			'name'      => $name,
			'phone'     => self::find_value( $fields, array( 'phone', 'mobile', 'tel', 'whatsapp' ) ),
			'email'     => self::find_value( $fields, array( 'email', 'e-mail' ) ),
			'subject'   => self::find_value( $fields, array( 'subject', 'package', 'tour', 'booking', 'adults', 'guest', 'checkin', 'date' ) ),
			'message'   => self::find_value( $fields, array( 'message', 'comment', 'details', 'note', 'inquiry' ) ),
			'page_url'  => (string) $page_url,
			'fields'    => $fields,
		);
	}
}
