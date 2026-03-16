<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Integration_Elementor_Pro {
	private $plugin;
	private static $processed = array();

	public function __construct( TextitBiz_Notifications $plugin ) {
		$this->plugin = $plugin;
		add_action( 'elementor_pro/forms/process', array( $this, 'handle_submission' ), 10, 2 );
		add_action( 'elementor_pro/forms/new_record', array( $this, 'handle_submission' ), 10, 2 );
		add_action( 'elementor_pro/forms/mail_sent', array( $this, 'handle_mail_sent' ), 10, 2 );
	}

	public function handle_mail_sent( $settings, $record ) {
		$this->handle_submission( $record, null, is_array( $settings ) ? $settings : array() );
	}

	public function handle_submission( $record, $ajax_handler = null, array $settings = array() ) {
		if ( ! $this->plugin->is_integration_enabled( 'elementor_pro' ) ) {
			return;
		}

		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) || ! method_exists( $record, 'get_form_settings' ) ) {
			return;
		}

		$record_fields = $record->get( 'fields' );
		$fields        = array();

		if ( is_array( $record_fields ) ) {
			foreach ( $record_fields as $field ) {
				$key            = ! empty( $field['id'] ) ? $field['id'] : ( isset( $field['title'] ) ? $field['title'] : 'field' );
				$fields[ $key ] = isset( $field['value'] ) ? $field['value'] : '';
			}
		}

		$sent_data = $record->get( 'sent_data' );
		if ( is_array( $sent_data ) ) {
			foreach ( $sent_data as $key => $value ) {
				$key = (string) $key;
				if ( '' === $key ) {
					continue;
				}

				if ( ! isset( $fields[ $key ] ) || '' === (string) $fields[ $key ] ) {
					$fields[ $key ] = $value;
				}
			}
		}

		$form_name = (string) $record->get_form_settings( 'form_name' );

		if ( '' === trim( $form_name ) && ! empty( $settings['form_name'] ) ) {
			$form_name = (string) $settings['form_name'];
		}

		if ( '' === trim( $form_name ) ) {
			$form_name = 'Elementor Form';
		}

		$post_id  = $this->detect_post_id( $record );
		$form_key = 'elementor_pro:' . $post_id . ':' . sanitize_title( $form_name );

		$fingerprint = md5( $form_key . '|' . wp_json_encode( array_keys( $fields ) ) . '|' . wp_json_encode( $fields ) );

		if ( isset( self::$processed[ $fingerprint ] ) ) {
			return;
		}

		self::$processed[ $fingerprint ] = true;

		if ( ! $this->is_monitored_elementor_form( $form_key, $form_name, $post_id ) ) {
			$this->plugin->log(
				'warning',
				'elementor_pro',
				'Elementor submission skipped because form is not selected.',
				array(
					'payload' => array(
						'form_key'  => $form_key,
						'form_name' => $form_name,
						'form_id'   => (string) $post_id,
						'fields'    => $fields,
					),
				)
			);
			return;
		}

		$page_url            = $this->detect_referrer_url();
		$fields              = $this->enrich_dynamic_package_field( $fields, $post_id, $page_url );
		$payload             = TextitBiz_Notifications::build_payload( 'elementor_pro', $form_name, $form_name, $fields, $page_url );
		$payload['form_key'] = $form_key;

		$this->plugin->handle_submission( 'elementor_pro', $payload );
	}

	private function detect_post_id( $record ) {
		$candidates = array(
			$this->read_request_value( 'post_id' ),
			$this->read_request_value( 'queried_id' ),
			$this->read_request_value( 'form_post_id' ),
			$this->read_request_value( 'page_id' ),
			is_object( $record ) && method_exists( $record, 'get_form_settings' ) ? $record->get_form_settings( 'form_post_id' ) : '',
			is_object( $record ) && method_exists( $record, 'get_form_settings' ) ? $record->get_form_settings( 'post_id' ) : '',
		);

		foreach ( $candidates as $candidate ) {
			$normalized = absint( $candidate );

			if ( $normalized > 0 ) {
				return $normalized;
			}
		}

		return 0;
	}

	private function detect_referrer_url() {
		$referrer = $this->read_request_value( 'referrer' );

		if ( '' !== $referrer ) {
			return esc_url_raw( $referrer );
		}

		return isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	}

	private function read_request_value( $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}

		return '';
	}

	private function is_monitored_elementor_form( $form_key, $form_name, $post_id ) {
		if ( $this->plugin->is_form_monitored( $form_key ) ) {
			return true;
		}

		$form_slug         = sanitize_title( (string) $form_name );
		$selected_elementor = array();

		foreach ( $this->plugin->get_monitored_forms() as $selected ) {
			if ( 0 !== strpos( $selected, 'elementor_pro:' ) ) {
				continue;
			}

			$selected_elementor[] = $selected;

			if ( '' !== $form_slug ) {
				$suffix = ':' . $form_slug;
				if ( strlen( $selected ) >= strlen( $suffix ) && substr( $selected, -strlen( $suffix ) ) === $suffix ) {
					return true;
				}
			}

			if ( $post_id > 0 && 0 === strpos( $selected, 'elementor_pro:' . $post_id . ':' ) ) {
				return true;
			}
		}

		if ( $post_id <= 0 && ! empty( $selected_elementor ) ) {
			return true;
		}

		if ( 1 === count( $selected_elementor ) ) {
			return true;
		}

		return false;
	}

	private function enrich_dynamic_package_field( array $fields, $post_id, $page_url ) {
		$has_package = false;

		foreach ( $fields as $key => $value ) {
			$key_lc = strtolower( (string) $key );

			if ( false !== strpos( $key_lc, 'package' ) || false !== strpos( $key_lc, 'tour' ) || false !== strpos( $key_lc, 'subject' ) ) {
				if ( '' !== trim( (string) $value ) ) {
					$has_package = true;
					break;
				}
			}
		}

		if ( $has_package ) {
			return $fields;
		}

		$title = '';

		if ( '' !== trim( (string) $page_url ) ) {
			$mapped_post = $this->map_url_to_post_id( $page_url );

			if ( $mapped_post > 0 ) {
				$title = get_the_title( $mapped_post );
			}
		}

		if ( '' === trim( (string) $title ) && $post_id > 0 ) {
			$title = get_the_title( $post_id );
		}

		if ( false !== stripos( (string) $title, 'Elementor Single Page #' ) && '' !== trim( (string) $page_url ) ) {
			$mapped_post = $this->map_url_to_post_id( $page_url );

			if ( $mapped_post > 0 ) {
				$title = get_the_title( $mapped_post );
			}
		}

		if ( '' !== trim( (string) $title ) ) {
			$fields['package'] = (string) $title;
		}

		return $fields;
	}

	private function map_url_to_post_id( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return 0;
		}

		if ( function_exists( 'url_to_postid' ) ) {
			$mapped = absint( url_to_postid( $url ) );

			if ( $mapped > 0 ) {
				return $mapped;
			}
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = trim( $path, '/' );

		if ( '' === $path ) {
			return 0;
		}

		$post = get_page_by_path( $path, OBJECT, array( 'page', 'post', 'tour', 'product' ) );

		return $post ? (int) $post->ID : 0;
	}
}
