<?php
/*
Plugin Name: FlatRatePay Payment Gateway For WooCommerce
Description: Extends WooCommerce to Process Payments with the GivePay Gateway
Version: 1.0.0
Plugin URI: https://flatratepay.com/
Author: Ishan Verma, GivePay Commerce, LLC
Author URI: https://flatratepay.com/
License: Under GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'woocommerce_gpg_init', 0 );
function woocommerce_gpg_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/**
	 * Localisation
	 */
	load_plugin_textdomain( 'wc-givepay-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	/**
	 * GivePay Gateway class
	 */
	class WC_GivePay_Gateway extends WC_Payment_Gateway_CC {
		protected $msg = array();

		const LIVE_URL = 'https://gateway.givepaycommerce.com/';
		const TEST_URL = 'https://gpg-stage.flatratepay-staging.net/';
		CONST LIVE_TOKEN_URL = 'https://portal.flatratepay.com/connect/token';
		const TEST_TOKEN_URL = 'https://portal.flatratepay-staging.net/connect/token';

		/**
		 * The FRP_Gateway client
		 */
		private $client;

		/**
		 * 'true' if the transaction should be performed in test mode
		 */
		private $mode;

		private $failed_message;
		private $success_message;
		private $merchant_id;
		private $terminal_id;

		public function __construct() {
			$this->id           = 'givepay_gateway';
			$this->method_title = __( 'FlatRatePay', 'wc-givepay-gateway' );
			$this->icon         = WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/img/logo.png';
			$this->has_fields   = true;
			$this->supports     = array(
				'products',
				'refunds',
				'tokenization',
				'default_credit_card_form'
			);
			$this->init_form_fields();
			$this->init_settings();
			$this->title       = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->mode        = $this->settings['working_mode'];

			$this->merchant_id     = $this->settings['merchant_id'];
			$this->terminal_id     = $this->settings['terminal_id'];
			$this->success_message = $this->settings['success_message'];
			$this->failed_message  = $this->settings['failed_message'];

			$this->msg['message'] = "";
			$this->msg['class']   = "";

			require_once( dirname( __FILE__ ) . '/include/frp-gateway-logger.php' );
			require_once( dirname( __FILE__ ) . '/include/frp-gateway.php' );

			$client_id  = $this->settings['client_id'];
			$secret_key = $this->settings['secret_key'];

			if ( $this->mode == 'true' ) {
				$this->client = new FRP_Gateway( $client_id, $secret_key, self::TEST_TOKEN_URL, self::TEST_URL );
			} else {
				$this->client = new FRP_Gateway( $client_id, $secret_key, self::LIVE_TOKEN_URL, self::LIVE_URL );
			}

			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					&$this,
					'process_admin_options'
				) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			add_action( 'woocommerce_receipt_gpg', array( &$this, 'receipt_page' ) );
			add_action( 'woocommerce_thankyou_gpg', array( &$this, 'thankyou_page' ) );
		}

		function init_form_fields() {
			$this->form_fields = array(
				'enabled'         => array(
					'title'   => __( 'Enable/Disable', 'wc-givepay-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable FlatRatePay Payment Module.', 'wc-givepay-gateway' ),
					'default' => 'no'
				),
				'title'           => array(
					'title'       => __( 'Title:', 'wc-givepay-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wc-givepay-gateway' ),
					'default'     => __( 'FlatRatePay', 'wc-givepay-gateway' )
				),
				'description'     => array(
					'title'       => __( 'Description:', 'wc-givepay-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'wc-givepay-gateway' ),
					'default'     => __( 'Pay securely by Credit or Debit Card through GivePay Secure Servers.', 'wc-givepay-gateway' )
				),
				'client_id'       => array(
					'title'       => __( 'Client ID', 'wc-givepay-gateway' ),
					'type'        => 'text',
					'description' => __( 'The Client ID for the API', 'wc-givepay-gateway' )
				),
				'secret_key'      => array(
					'title'       => __( 'Secret Key', 'wc-givepay-gateway' ),
					'type'        => 'text',
					'description' => __( 'The API Secret Key (ssshhhh...)', 'wc-givepay-gateway' )
				),
				'merchant_id'     => array(
					'title'       => __( 'Merchant ID', 'wc-givepay-gateway' ),
					'type'        => 'text',
					'description' => __( 'Your merchant ID number', 'wc-givepay-gateway' )
				),
				'terminal_id'     => array(
					'title'       => __( 'Terminal ID', 'wc-givepay-gateway' ),
					'type'        => 'text',
					'description' => __( 'Your website\'s terminal ID number', 'wc-givepay-gateway' )
				),
				'success_message' => array(
					'title'       => __( 'Transaction Success Message', 'wc-givepay-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Message to be displayed on successful transaction.', 'wc-givepay-gateway' ),
					'default'     => __( 'Your payment has been processed successfully.', 'wc-givepay-gateway' )
				),
				'failed_message'  => array(
					'title'       => __( 'Transaction Failed Message', 'wc-givepay-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Message to be displayed on failed transaction.', 'wc-givepay-gateway' ),
					'default'     => __( 'Your transaction has been declined.', 'wc-givepay-gateway' )
				),
				'working_mode'    => array(
					'title'       => __( 'API Mode' ),
					'type'        => 'select',
					'options'     => array( 'false' => 'Live Mode', 'true' => 'Test/Sandbox Mode' ),
					'description' => "Live/Test Mode"
				)
			);

			$label       = __( 'Enable Logging', 'wc-givepay-gateway' );
			$description = __( 'Enable the logging of errors.', 'wc-givepay-gateway' );

			if ( defined( 'WC_LOG_DIR' ) ) {
				$log_url = add_query_arg( 'tab', 'logs', add_query_arg( 'page', 'wc-status', admin_url( 'admin.php' ) ) );
				$log_key = 'woocommerce-flatratepay-gateway-' . sanitize_file_name( wp_hash( 'woocommerce-flatratepay-gateway' ) ) . '-log';
				$log_url = add_query_arg( 'log_file', $log_key, $log_url );

				$label .= ' | ' . sprintf( __( '%1$sView Log%2$s', 'wc-givepay-gateway' ), '<a href="' . esc_url( $log_url ) . '">', '</a>' );
			}

			$this->form_fields['log'] = array(
				'title'       => __( 'Debug Log', 'wc-givepay-gateway' ),
				'label'       => $label,
				'description' => $description,
				'type'        => 'checkbox',
				'default'     => 'no'
			);
		}

		/**
		 * Admin Panel Options
		 *
		 **/
		public function admin_options() {
			echo '<h3>' . __( 'FlatRatePay Payment Gateway', 'wc-givepay-gateway' ) . '</h3>';
			echo '<p>' . __( 'FlatRatePay is the most popular payment gateway for online payment processing' ) . '</p>';

			if ( empty( $this->public_key ) ) : ?>
                <div class="simplify-commerce-banner updated">
                    <img src="<?php echo WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/img/logo.png'; ?>" />
                    <p class="main"><strong><?php _e( 'Get started', 'wc-givepay-gateway' ); ?></strong></p>
                    <p><?php _e( 'FlatRatePay is a merchant services provider made for businesses. Sign up with FlatRatePay to get low rates and great customer support!', 'wc-givepay-gateway' ); ?></p>

                    <p><a href="https://portal.flatratepay.com/merchants/new?promo=WC_WP" target="_blank" class="button button-primary"><?php _e( 'Sign up for FlatRatePay', 'wc-givepay-gateway' ); ?></a> <a href="https://flatratepay.com/?utm_source=WooCommerce" target="_blank" class="button"><?php _e( 'Learn more', 'wc-givepay-gateway' ); ?></a></p>

                </div>
			<?php
			endif;

			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		/**
		 * @return array
		 */
		private function get_card_from_request() {
			if ( isset( $_POST['wc-givepay_gateway-payment-token'] ) AND 'new' !== $_POST['wc-givepay_gateway-payment-token'] ) {
				return array(
					'token_id' => $_POST['wc-givepay_gateway-payment-token']
				);
			}

			$number = preg_replace( '/[^0-9]+/', '', $_POST['givepay_gateway-card-number'] );

			$date_string = preg_replace( '/[^0-9\/]+/', '',  $_POST['givepay_gateway-card-expiry'] );
			$expiry_dates = explode( '/', $date_string, 2 );
			if ( sizeof( $expiry_dates ) != 2 ) {
				wc_add_notice( __( '(Card Expiry Date) is not valid.', 'wc-givepay-gateway' ) );
			}

			return array(
				'card_number'      => $number,
				'expiration_month' => $expiry_dates[0],
				'expiration_year'  => $expiry_dates[1],
				'cvv'              => $_POST['givepay_gateway-card-cvc']
			);
		}

		/*
		* Basic Card validation
		*/
		public function validate_fields() {
			global $woocommerce;

			$card = $this->get_card_from_request();

			if ( NULL !== $card['token_id'] ) {
				return;
			}

			if ( ! $this->isCreditCardNumber( $card['card_number'] ) ) {
				wc_add_notice( __( '(Credit Card Number) is not valid.', 'wc-givepay-gateway' ), 'error' );
			}

			if ( ! $this->isCorrectExpireDate( $card['expiration_month'] ) ) {
				wc_add_notice( __( '(Card Expiry Date) is not valid.', 'wc-givepay-gateway' ) );
			}

			if ( ! $this->isCorrectExpireDate( $card['expiration_year'] ) ) {
				wc_add_notice( __( '(Card Expiry Date) is not valid.', 'wc-givepay-gateway' ) );
			}

			if ( ! $this->isCCVNumber( $card['cvv'] ) ) {
				wc_add_notice( __( '(Card Verification Number) is not valid.', 'wc-givepay-gateway' ) );
			}
		}

		/*
		* Check card
		*/
		private function isCreditCardNumber( $toCheck ) {
			$number = preg_replace( '/[^0-9]+/', '', $toCheck );
			$strlen = strlen( $number );
			$sum    = 0;
			if ( $strlen < 13 ) {
				return false;
			}

			for ( $i = 0; $i < $strlen; $i ++ ) {
				$digit = substr( $number, $strlen - $i - 1, 1 );
				if ( $i % 2 == 1 ) {
					$sub_total = $digit * 2;
					if ( $sub_total > 9 ) {
						$sub_total = 1 + ( $sub_total - 10 );
					}
				} else {
					$sub_total = $digit;
				}
				$sum += $sub_total;
			}

			if ( $sum > 0 AND $sum % 10 == 0 ) {
				return true;
			}

			FRP_Gateway_Logger::error( "card number did not pass LUHN check" );

			return false;
		}

		private function isCCVNumber( $toCheck ) {
			$length = strlen( $toCheck );

			return is_numeric( $toCheck ) AND $length > 2 AND $length < 5;
		}

		/**
		 * Check expiry date
		 *
		 * @param string $date
		 *
		 * @return bool
		 */
		private function isCorrectExpireDate( $date ) {
			return is_numeric( $date ) && ( strlen( $date ) == 2 );
		}

		public function thankyou_page( $order_id ) {
		}

		/**
		 * Output field name HTML
		 *
		 * Gateways which support tokenization do not require names - we don't want the data to post to the server.
		 *
		 * @since  2.6.0
		 *
		 * @param  string $name
		 *
		 * @return string
		 */
		public function field_name( $name ) {
			return ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
		}

		/**
		 * @param WC_Order $order the order
		 */
		function receipt_page( $order ) {
			echo '<p>' . __( 'Thank you for your order.', 'wc-givepay-gateway' ) . '</p>';
		}

		/**
		 * Adds the payment method
		 *
		 * @return array
		 */
		public function add_payment_method() {
			$card = $this->get_card_from_request();

			var_dump($_POST);
		}

		/**
		 * Saves the FRP token to the current user
		 *
		 * @param string $token
		 * @param array $card
		 *
		 * @return string
		 */
		private function save_payment_token( $token, $card ) {
			$p_token = new WC_Payment_Token_CC();

			$p_token->set_token($token);
			$p_token->set_card_type('visa');
			$p_token->set_last4( substr( $card['card_number'], -4 ) );
			$p_token->set_gateway_id( $this->id );
			$p_token->set_expiry_month( $card['expiration_month'] );
			$p_token->set_expiry_year( '20' . $card['expiration_year'] );
			if ( is_user_logged_in() ) {
				$p_token->set_user_id( get_current_user_id() );
			}

			$p_token->save();

			return $p_token;
		}

		/**
		 * Get the user token with the given ID
		 *
		 * @param $token_id
		 *
		 * @return WC_Payment_Token_CC
		 */
		private function get_user_token( $token_id ) {
			$customer_token  = null;
			if ( is_user_logged_in() ) {
				$tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id() );
				foreach ( $tokens as $token ) {
					if ( $token->get_id() == $token_id ) {
						$customer_token = $token;
						break;
					}
				}
			}
			return $customer_token;
		}

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 *
		 * @return array
		 **/
		function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			if ( $this->mode == 'true' ) {
				FRP_Gateway_Logger::warn( "performing transaction in test mode" );
			} else if ( ! wc_checkout_is_https() ) {
				FRP_Gateway_Logger::warn( "production mode not over TLS" );
				wc_add_notice( __( 'Live mode requires SSL/TLS', 'wc-givepay-gateway' ), 'error' );
				return;
			}

			$card = $this->get_card_from_request();

			if ( isset( $_POST['wc-givepay_gateway-payment-token'] ) AND 'new' == $_POST['wc-givepay_gateway-payment-token'] ) {
				$token = $this->client->storeCard( $this->merchant_id, $this->terminal_id, $card );
				$card['token'] = $token;

				$this->save_payment_token( $token, $card );
			} else if ( isset( $_POST['wc-givepay_gateway-payment-token'] ) AND 'new' !== $_POST['wc-givepay_gateway-payment-token']) {
				$token = $this->get_user_token( $card['token_id'] );

				if ( NULL !== $token ) {
					$card['token'] = $token->get_token();
				}
			}

			$sale_response = $this->client->chargeAmount( $order, $this->merchant_id, $this->terminal_id, $card );

			FRP_Gateway_Logger::debug( "Transaction completed" );

			if ( $sale_response->getSuccess() ) {
				$transaction_id = $sale_response->getTransactionId();

				$woocommerce->cart->empty_cart();

				$order->add_order_note( __( 'FlatRatePay payment complete!', 'wc-givepay-gateway' ) );
				$order->add_order_note( __( 'Transaction ID: ' . $transaction_id, 'wc-givepay-gateway' ) );

				$order->payment_complete( $transaction_id );

				FRP_Gateway_Logger::info( "Payment completed." );

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			} else {
				$order->add_order_note( $this->failed_message );
				$order->update_status( 'failed' );

				FRP_Gateway_Logger::debug( "Sale response: " . var_export( $sale_response, true ) );
				FRP_Gateway_Logger::error( "Payment failed." );

				wc_add_notice( __( '(Transaction Error) Error processing payment: ' . $sale_response->getErrorMessage(), 'wc-givepay-gateway' ), 'error' );
			}
		}

		function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order = new WC_Order( $order_id );

			$transaction_id = $order->get_transaction_id();

			$response = $this->client->voidTransaction( $transaction_id, $this->merchant_id, $this->terminal_id );

			if ( $response->getSuccess() ) {
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			} else {
				wc_add_notice( __( '(Transaction Error) Error processing void: ' . $response->getErrorMessage(), 'wc-givepay-gateway' ), 'error' );
			}
		}
	}

	/**
	 * Add this Gateway to WooCommerce
	 **/
	function woocommerce_add_gpg( $methods ) {
		$methods[] = 'WC_GivePay_Gateway';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_gpg' );
}
