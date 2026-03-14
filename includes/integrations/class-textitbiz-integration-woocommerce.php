<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TextitBiz_Integration_WooCommerce {
	private $plugin;

	public function __construct( TextitBiz_Notifications $plugin ) {
		$this->plugin = $plugin;
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'handle_submission' ), 10, 3 );
	}

	public function handle_submission( $order_id, $posted_data, $order ) {
		if ( ! $this->plugin->is_integration_enabled( 'woocommerce' ) ) {
			return;
		}

		$form_key = 'woocommerce:checkout';
		if ( ! $this->plugin->is_form_monitored( $form_key ) ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$fields = array(
			'billing_first_name' => $order->get_billing_first_name(),
			'billing_last_name'  => $order->get_billing_last_name(),
			'billing_phone'      => $order->get_billing_phone(),
			'billing_email'      => $order->get_billing_email(),
			'order_id'           => $order->get_order_number(),
			'total'              => $order->get_total(),
		);

		$payload             = TextitBiz_Notifications::build_payload( 'woocommerce', $order_id, 'Checkout Order', $fields, wc_get_checkout_url() );
		$payload['form_key'] = $form_key;

		$this->plugin->handle_submission( 'woocommerce', $payload );
	}
}
