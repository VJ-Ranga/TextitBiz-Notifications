<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/integrations/class-textitbiz-integration-metform.php';
require_once __DIR__ . '/integrations/class-textitbiz-integration-elementor-pro.php';
require_once __DIR__ . '/integrations/class-textitbiz-integration-cf7.php';
require_once __DIR__ . '/integrations/class-textitbiz-integration-woocommerce.php';

class TextitBiz_Integration_Manager {
	public function __construct( TextitBiz_Notifications $plugin, TextitBiz_Detector $detector ) {
		$plugins = $detector->get_supported_plugins();

		if ( $plugins['metform']['active'] ) {
			new TextitBiz_Integration_MetForm( $plugin );
		}

		$elementor_pro_available =
			$plugins['elementor_pro']['active'] ||
			defined( 'ELEMENTOR_PRO_VERSION' ) ||
			class_exists( '\\ElementorPro\\Plugin' ) ||
			did_action( 'elementor_pro/init' );

		if ( $elementor_pro_available ) {
			new TextitBiz_Integration_Elementor_Pro( $plugin );
		}

		if ( $plugins['contact_form_7']['active'] ) {
			new TextitBiz_Integration_CF7( $plugin );
		}

		if ( $plugins['woocommerce']['active'] ) {
			new TextitBiz_Integration_WooCommerce( $plugin );
		}
	}
}
