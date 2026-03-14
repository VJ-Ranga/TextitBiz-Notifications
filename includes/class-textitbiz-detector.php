<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Detector {
	public function get_supported_plugins() {
		return array(
			'metform' => array(
				'label'  => 'MetForm',
				'file'   => 'metform/metform.php',
				'active' => $this->is_plugin_active( 'metform/metform.php' ),
			),
			'elementor_pro' => array(
				'label'  => 'Elementor Pro',
				'file'   => 'elementor-pro/elementor-pro.php',
				'active' => $this->is_plugin_active( 'elementor-pro/elementor-pro.php' ),
			),
			'contact_form_7' => array(
				'label'  => 'Contact Form 7',
				'file'   => 'contact-form-7/wp-contact-form-7.php',
				'active' => $this->is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
			),
			'woocommerce' => array(
				'label'  => 'WooCommerce',
				'file'   => 'woocommerce/woocommerce.php',
				'active' => $this->is_plugin_active( 'woocommerce/woocommerce.php' ),
			),
		);
	}

	public function get_active_integrations() {
		return array_filter(
			$this->get_supported_plugins(),
			static function ( $plugin ) {
				return ! empty( $plugin['active'] );
			}
		);
	}

	public function get_detected_forms() {
		$forms  = array();
		$active = $this->get_active_integrations();

		if ( isset( $active['metform'] ) ) {
			$forms['metform'] = $this->get_metform_forms();
		}

		if ( isset( $active['elementor_pro'] ) ) {
			$forms['elementor_pro'] = $this->get_elementor_pro_forms();
		}

		if ( isset( $active['contact_form_7'] ) ) {
			$forms['contact_form_7'] = $this->get_cf7_forms();
		}

		if ( isset( $active['woocommerce'] ) ) {
			$forms['woocommerce'] = $this->get_woocommerce_forms();
		}

		return $forms;
	}

	public function is_plugin_active( $plugin_file ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( in_array( $plugin_file, $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network_active[ $plugin_file ] );
		}

		return false;
	}

	private function get_metform_forms() {
		$posts = get_posts(
			array(
				'post_type'      => 'metform-form',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$forms = array();

		foreach ( $posts as $post ) {
			$forms[] = array(
				'key'    => 'metform:' . $post->ID,
				'label'  => $post->post_title,
				'fields' => $this->extract_metform_fields( $post->ID ),
			);
		}

		return $forms;
	}

	private function get_cf7_forms() {
		$posts = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$forms = array();

		foreach ( $posts as $post ) {
			$forms[] = array(
				'key'    => 'contact_form_7:' . $post->ID,
				'label'  => $post->post_title,
				'fields' => $this->extract_cf7_fields( $post->ID ),
			);
		}

		return $forms;
	}

	private function get_woocommerce_forms() {
		return array(
			array(
				'key'    => 'woocommerce:checkout',
				'label'  => 'WooCommerce Checkout',
				'fields' => array(
					'billing_first_name',
					'billing_last_name',
					'billing_phone',
					'billing_email',
					'order_id',
					'total',
				),
			),
		);
	}

	private function get_elementor_pro_forms() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE pm.meta_key = '_elementor_data'
			AND p.post_status IN ('publish','draft','private')",
			ARRAY_A
		);

		$forms = array();

		foreach ( $results as $row ) {
			$data = json_decode( $row['meta_value'], true );

			if ( ! is_array( $data ) ) {
				continue;
			}

			$this->walk_elementor_elements( $data, (int) $row['ID'], $row['post_title'], $forms );
		}

		return array_values( $forms );
	}

	private function walk_elementor_elements( array $elements, $post_id, $post_title, array &$forms ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['widgetType'] ) && 'form' === $element['widgetType'] ) {
				$settings  = isset( $element['settings'] ) ? $element['settings'] : array();
				$form_name = ! empty( $settings['form_name'] ) ? $settings['form_name'] : 'Elementor Form';
				$key       = 'elementor_pro:' . $post_id . ':' . sanitize_title( $form_name );

				$forms[ $key ] = array(
					'key'    => $key,
					'label'  => $form_name . ' - ' . $post_title,
					'fields' => $this->extract_elementor_form_fields( $settings ),
				);
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->walk_elementor_elements( $element['elements'], $post_id, $post_title, $forms );
			}
		}
	}

	private function extract_elementor_form_fields( array $settings ) {
		$fields = array();

		if ( empty( $settings['form_fields'] ) || ! is_array( $settings['form_fields'] ) ) {
			return $fields;
		}

		foreach ( $settings['form_fields'] as $field ) {
			if ( empty( $field['custom_id'] ) ) {
				continue;
			}

			$fields[] = (string) $field['custom_id'];
		}

		return array_values( array_unique( $fields ) );
	}

	private function extract_metform_fields( $post_id ) {
		$data = get_post_meta( $post_id, '_elementor_data', true );
		$data = json_decode( $data, true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		$fields = array();
		$this->walk_metform_elements( $data, $fields );

		return array_values( array_unique( $fields ) );
	}

	private function walk_metform_elements( array $elements, array &$fields ) {
		foreach ( $elements as $element ) {
			if ( ! empty( $element['settings']['mf_input_name'] ) ) {
				$fields[] = (string) $element['settings']['mf_input_name'];
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->walk_metform_elements( $element['elements'], $fields );
			}
		}
	}

	private function extract_cf7_fields( $post_id ) {
		$content = (string) get_post_field( 'post_content', $post_id );

		if ( '' === $content ) {
			return array();
		}

		preg_match_all( '/\[[^\]]+\s+([A-Za-z0-9_-]+)/', $content, $matches );

		if ( empty( $matches[1] ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'strval', $matches[1] ) ) );
	}
}
