<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs using the WC_Logger
 */
class FRP_Gateway_Logger {

	public static $logger;

	public static function debug( $message ) {
		self::log( $message, WC_Log_Levels::DEBUG );
	}

	public static function info( $message ) {
		self::log( $message, WC_Log_Levels::INFO );
	}

	public static function warn( $message ) {
		self::log( $message, WC_Log_Levels::WARNING );
	}

	public static function error( $message ) {
		self::log( $message, WC_Log_Levels::ERROR );
	}

	static function log( $message, $level ) {
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