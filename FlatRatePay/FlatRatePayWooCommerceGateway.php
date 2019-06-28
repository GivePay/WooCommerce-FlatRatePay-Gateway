<?php
/**
 * Created by PhpStorm.
 * User: WilliamWard
 * Date: 8/6/2018
 * Time: 2:23 PM
 */

namespace FlatRatePay;

use FlatRatePay\Log\WCLogger;
use GivePay\Gateway\GivePayGatewayClient;
use GivePay\Gateway\Transactions\Address;
use GivePay\Gateway\Transactions\Card;
use GivePay\Gateway\Transactions\Order;
use GivePay\Gateway\Transactions\Sale;
use GivePay\Gateway\Transactions\TerminalType;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway_CC;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;

/**
 * GivePay Gateway class
 */
class FlatRatePayWooCommerceGateway extends WC_Payment_Gateway_CC {
	protected $msg = array();

	const LIVE_URL = 'https://gateway.givepaycommerce.com/';
	const TEST_URL = 'https://gpg-stage.flatratepay-staging.net/';
	const LIVE_TOKEN_URL = 'https://portal.flatratepay.com/connect/token';
	const TEST_TOKEN_URL = 'https://portal.flatratepay-staging.net/connect/token';

	/**
	 * @var GivePayGatewayClient
	 */
	private $client;

	/**
	 * 'true' if the transaction should be performed in test mode
	 */
	private $mode;

	/**
	 * @var LoggerInterface logger
	 */
	private $logger;

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
			'default_credit_card_form',
            'credit_card_form_cvc_on_saved_method'
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

		$client_id  = $this->settings['client_id'];
		$secret_key = $this->settings['secret_key'];

		$this->logger = new WCLogger();

		if ( $this->mode == 'true' ) {
			$this->client = new GivePayGatewayClient( $client_id, $secret_key, self::TEST_TOKEN_URL, self::TEST_URL, $this->logger );
		} else {
			$this->client = new GivePayGatewayClient( $client_id, $secret_key, self::LIVE_TOKEN_URL, self::LIVE_URL, $this->logger );
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
                <img src="<?php echo WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/../img/logo.png'; ?>"/>
                <p class="main"><strong><?php _e( 'Get started', 'wc-givepay-gateway' ); ?></strong></p>
                <p><?php _e( 'FlatRatePay is a merchant services provider made for businesses. Sign up with FlatRatePay to get low rates and great customer support!', 'wc-givepay-gateway' ); ?></p>

                <p><a href="https://portal.flatratepay.com/merchants/new?promo=WC_WP" target="_blank"
                      class="button button-primary"><?php _e( 'Sign up for FlatRatePay', 'wc-givepay-gateway' ); ?></a>
                    <a href="https://flatratepay.com/?utm_source=WooCommerce" target="_blank"
                       class="button"><?php _e( 'Learn more', 'wc-givepay-gateway' ); ?></a></p>

            </div>
		<?php
		endif;

		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Enqueues our tokenization script to handle some of the new form options.
	 *
	 * @since 2.6.0
	 */
	public function tokenization_script() {
		parent::tokenization_script();

		wp_enqueue_script(
			'givepay-tokenization-form',
			WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/assets/js/dist/flatratepay.js',
			array(),
			WC()->version
		);
	}

	/**
	 * Inserts the connection info into the document in a javascript tag
	 */
	private function gpgConnectionInfo() {
		?>
        <script>
            const gpgMid = "<?php echo esc_js( $this->merchant_id ) ?>";
            const gpgAccessToken = "<?php echo esc_js( $this->client->getTokenizationApiKey() ) ?>";
            const gpgUrl = <?php echo $this->mode == 'true' ? '"' . esc_js( self::TEST_URL . 'api/v1/transactions/tokenize' ) . '"' : "undefined" ?>;
        </script>
		<?php
	}

	/**
	 * Outputs fields for entering credit card information.
	 *
	 * @since 2.6.0
	 */
	public function form() {
		$this->gpgConnectionInfo();
		parent::form();
	}

	/**
	 * @return array
	 */
	private function get_card_from_request() {
		if ( isset( $_POST['wc-givepay_gateway-payment-token'] ) AND 'new' !== $_POST['wc-givepay_gateway-payment-token'] ) {
			return array(
				'token_id' => $_POST['wc-givepay_gateway-payment-token'],
				'cvv'      => $_POST['givepay_gateway-card-cvc']
			);
		}

		$token = $_POST['givepay_gateway-gpg-token'];

		$number = preg_replace( '/[^0-9]+/', '', $_POST['givepay_gateway-card-number-last4'] );

		$date_string  = preg_replace( '/[^0-9\/]+/', '', $_POST['givepay_gateway-card-expiry'] );
		$expiry_dates = explode( '/', $date_string, 2 );
		if ( sizeof( $expiry_dates ) != 2 ) {
			wc_add_notice( __( '(Card Expiry Date) is not valid.', 'wc-givepay-gateway' ) );
		}

		return array(
			'card_number'      => $number,
			'token'            => $token,
			'expiration_month' => $expiry_dates[0],
			'expiration_year'  => $expiry_dates[1],
			'cvv'              => $_POST['givepay_gateway-card-cvc']
		);
	}

	/**
	 * Basic Card validation
	 */
	public function validate_fields() {
		global $woocommerce;

		$card = $this->get_card_from_request();

		if ( null !== $card['token_id'] ) {
			return;
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
	 * @param string $name
	 *
	 * @return string
	 * @since  2.6.0
	 *
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

		$p_token->set_token( $token );
		$p_token->set_card_type( 'visa' );
		$p_token->set_last4( substr( $card['card_number'], - 4 ) );
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
		$customer_token = null;
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
	 **@throws \Exception
	 */
	function process_payment( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );
		if ( $this->mode == 'true' ) {
			$this->logger->warning( "performing transaction in test mode" );
		} else if ( ! wc_checkout_is_https() ) {
			$this->logger->warning( "production mode not over TLS" );
			wc_add_notice( __( 'Live mode requires SSL/TLS', 'wc-givepay-gateway' ), 'error' );

			return;
		}

		$card_info = $this->get_card_from_request();
		$card      = Card::withToken(
			$card_info['token'],
			$card_info['cvv']
		);

		if ( isset( $_POST['wc-givepay_gateway-new-payment-method'] ) AND 'true' == $_POST['wc-givepay_gateway-new-payment-method'] ) {
			$this->save_payment_token( $card_info['token'], $card_info );

		} else if ( isset( $_POST['wc-givepay_gateway-payment-token'] ) AND 'new' !== $_POST['wc-givepay_gateway-payment-token'] ) {
			$token = $this->get_user_token( $card_info['token_id'] );

			if ( null !== $token ) {
				$card = Card::withToken( $token->get_token(), $card_info['cvv'] );
			}
		}

		$sale_response = $this->client->chargeAmount( $this->merchant_id, $this->terminal_id, new Sale(
			$order->get_total(),
			TerminalType::$ECommerce,
			new Address(
				$order->get_billing_address_1(),
				$order->get_billing_address_2(),
				$order->get_billing_city(),
				$order->get_billing_state(),
				$order->get_billing_postcode()
			),
			$order->get_billing_email(),
			$order->get_billing_phone(),
			$card,
			new Order( $order->get_order_number() )
		) );

		$this->logger->debug( "Transaction completed" );

		if ( $sale_response->getSuccess() ) {
			$transaction_id = $sale_response->getTransactionId();

			$woocommerce->cart->empty_cart();

			$order->add_order_note( __( 'FlatRatePay payment complete!', 'wc-givepay-gateway' ) );
			$order->add_order_note( __( 'Transaction ID: ' . $transaction_id, 'wc-givepay-gateway' ) );

			$order->payment_complete( $transaction_id );

			$this->logger->info( "Payment completed." );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		} else {
			$order->add_order_note( $this->failed_message );
			$order->update_status( 'failed' );

			$this->logger->debug( "Sale response: " . var_export( $sale_response, true ) );
			$this->logger->error( "Payment failed." );

			wc_add_notice( __( '(Transaction Error) Error processing payment: ' . $sale_response->getErrorMessage(), 'wc-givepay-gateway' ), 'error' );
		}
	}

	/**
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return array|bool
	 * @throws \Exception
	 */
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