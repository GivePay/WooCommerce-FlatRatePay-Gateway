<?php
/*
Plugin Name: FlatRatePay Payment Gateway For WooCommerce
Description: Extends WooCommerce to Process Payments with the GivePay Gateway
Version: 0.12
Plugin URI: https://flatratepay.com/
Author: Ishan Verma, GivePay Commerce, LLC
Author URI: https://flatratepay.com/
License: Under GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

final class WC_GPG_Loader {
	/**
	 * @var WC_GPG_Loader singleton instance
	 */
	private static $instance;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load' ) );
	}

	public function load() {
		if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) {
			return;
		}

		require_once( __DIR__ . '/vendor/autoload.php' );

		require_once( __DIR__ . '/FlatRatePay/FlatRatePayWooCommerceGateway.php' );

		load_plugin_textdomain( 'wc-givepay-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_add_gpg' ) );
	}

	public function woocommerce_add_gpg( $methods ) {
		$methods[] = '\FlatRatePay\FlatRatePayWooCommerceGateway';

		return $methods;
	}
}

$GLOBALS['wc_givepay_gateway_loader'] = WC_GPG_Loader::get_instance();
