<?php

namespace FlatRatePay\Log;

use \Psr\Log\AbstractLogger;
use \WC_Logger;

/**
 * Logs using the WC_Logger
 */
class WCLogger extends AbstractLogger {

	public static $logger;

	public function log($level, $message, array $context = array()) {
		if ( self::should_skip() ) {
			return;
		}

		self::$logger = new WC_Logger();
		self::$logger->log( $level, $message, array( 'source' => self::log_key() ) );
	}

	static function should_skip() {
		$settings = get_option( 'woocommerce_givepay_gateway_settings' );

		return empty( $settings ) || isset( $settings['log'] ) && 'yes' !== $settings['log'];
	}

	static function log_key() {
		return 'woocommerce-flatratepay-gateway';
	}
}