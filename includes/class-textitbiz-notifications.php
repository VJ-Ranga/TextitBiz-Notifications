<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Notifications {
	const OPTION_KEY = 'textitbiz_notifications_settings';
	const LOG_KEY    = 'textitbiz_notifications_logs';

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
			'admin_phone'           => '',
			'monitored_forms'       => array(),
			'message_template'      => "New {form_name}\nName: {name}\nPhone: {phone}",
			'enable_metform'        => '1',
			'enable_elementor_pro'  => '1',
			'enable_contact_form_7' => '1',
			'enable_woocommerce'    => '1',
		);

		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	public function get_setting( $key, $default = '' ) {
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
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
			'context' => $context,
		);

		if ( count( $logs ) > 20 ) {
			$logs = array_slice( $logs, -20 );
		}

		update_option( self::LOG_KEY, $logs, false );
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
