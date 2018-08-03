<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class FRP_Gateway {

	public $token_endpoint;

	public $gateway_url;

	private $client_secret;

	private $client_id;

	/**
	 * FRP_Gateway constructor.
	 *
	 * @param string $client_id
	 * @param string $client_secret
	 * @param string $token_endpoint
	 * @param string $gateway_url
	 */
	public function __construct(
		$client_id,
		$client_secret,
		$token_endpoint = 'https://portal.flatratepay.com/connect/token',
		$gateway_url = 'https://gateway.givepaycommerce.com'
	) {
		$this->token_endpoint = $token_endpoint;
		$this->gateway_url    = $gateway_url;

		$this->client_secret = $client_secret;
		$this->client_id     = $client_id;

		require_once( dirname( __FILE__ ) . '/frp-gateway-logger.php' );
	}

	/**
	 * @param WC_Order $order WC_Order to purchase
	 * @param string $merchant_id The merchant ID for the website
	 * @param string $terminal_id The terminal ID for the website
	 * @param array $card The credit card info
	 *
	 * @throws Exception
	 * @return TransactionResult
	 */
	public function chargeAmount( $order, $merchant_id, $terminal_id, $card ) {
		if ( null == $order ) {
			throw new Exception( '$order is null' );
		}

		$access_token = $this->getAccessToken( $this->client_id, $this->client_secret, $this->token_endpoint );
		if ( null == $access_token ) {
			throw new Exception( 'Could not authorize with gateway.' );
		}

		return $this->makeSaleRequest( $access_token, $order, $merchant_id, $terminal_id, $card );
	}

	/**
	 * @param string $transaction_id
	 * @param string $merchant_id
	 * @param string $terminal_id
	 *
	 * @throws Exception
	 * @return TransactionResult
	 */
	public function voidTransaction( $transaction_id, $merchant_id, $terminal_id ) {
		if ( null == $transaction_id ) {
			throw new Exception( 'Transaction ID is null' );
		}

		$access_token = $this->getAccessToken( $this->client_id, $this->client_secret, $this->token_endpoint );
		if ( null == $access_token ) {
			throw new Exception( 'Could not authorize with gateway.' );
		}

		return $this->makeVoidRequest( $access_token, $transaction_id, $merchant_id, $terminal_id );
	}

	/**
	 * Stores the card and gets a token from the gateway
	 *
	 * @param string $merchant_id
	 * @param string $terminal_id
	 * @param array $card
	 *
	 * @return string
	 * @throws Exception
	 */
	public function storeCard( $merchant_id, $terminal_id, $card ) {
		if ( null == $card ) {
			throw new Exception( 'Card is null' );
		}

		$access_token = $this->getAccessToken( $this->client_id, $this->client_secret, $this->token_endpoint );
		if ( null == $access_token ) {
			throw new Exception( 'Could not store card with gateway.' );
		}

		return $this->makeStoreCardRequest( $access_token, $merchant_id, $terminal_id, $card );
	}

	/**
	 * @param string $access_token
	 * @param WC_Order $order the order
	 * @param string $merchant_id
	 * @param string $terminal_id
	 * @param array $card
	 *
	 * @return TransactionResult
	 */
	private function makeSaleRequest( $access_token, $order, $merchant_id, $terminal_id, $card ) {
		$sale_request = $this->generateSalesRequest( $merchant_id, $terminal_id, $order, $card );

		$body = json_encode( $sale_request );

		FRP_Gateway_Logger::info( "Starting transactions for $" . $order->get_total() );

		$post_response = wp_safe_remote_post( $this->gateway_url . 'api/v1/transactions/sale', array(
			'method'  => 'POST',
			'body'    => $body,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json'
			)
		) );

		FRP_Gateway_Logger::debug( "Transaction completed" );

		$sale_response = json_decode( $post_response['body'] );

		if ( $sale_response->success ) {
			$transaction_id = $sale_response->result->transaction_id;

			FRP_Gateway_Logger::info( 'Payment completed. Transaction ID: ' . $transaction_id );

			return new TransactionResult( true, $transaction_id );
		} else {
			$error_message = $sale_response->error->message;
			$code          = $sale_response->error->code;

			FRP_Gateway_Logger::debug( "Sale response: " . var_export( $sale_response, true ) );
			FRP_Gateway_Logger::error( "Payment failed." );

			return new TransactionResult( false, null, $error_message, $code );
		}
	}

	/**
	 * Makes a VOID request
	 *
	 * @param string $access_token
	 * @param string $transaction_id
	 * @param string $merchant_id
	 * @param string $terminal_id
	 *
	 * @return TransactionResult
	 */
	private function makeVoidRequest( $access_token, $transaction_id, $merchant_id, $terminal_id ) {
		$void_request = $this->generateVoidRequest( $merchant_id, $terminal_id, $transaction_id );

		$body = json_encode( $void_request );

		FRP_Gateway_Logger::info( "Starting void transaction for transaction# " . $transaction_id );

		$post_response = wp_safe_remote_post( $this->gateway_url . 'api/v1/transactions/void', array(
			'method'  => 'POST',
			'body'    => $body,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json'
			)
		) );

		FRP_Gateway_Logger::debug( "Transaction completed" );

		$void_response = json_decode( $post_response['body'] );

		if ( $void_response->success ) {
			$transaction_id = $void_response->result->transaction_id;

			FRP_Gateway_Logger::info( 'Void completed. Transaction ID: ' . $transaction_id );

			return new TransactionResult( true, $transaction_id );
		} else {
			$error_message = $void_response->error->message;
			$code          = $void_response->error->code;

			FRP_Gateway_Logger::debug( "Void response: " . var_export( $void_response, true ) );
			FRP_Gateway_Logger::error( "Void failed." );

			return new TransactionResult( false, null, $error_message, $code );
		}
	}

	/**
	 * stores a card in the gateway
	 *
	 * @param string $access_token
	 * @param string $terminal_id
	 * @param string $merchant_id
	 * @param mixed $card
	 *
	 * @return string
	 */
	private function makeStoreCardRequest( $access_token, $merchant_id, $terminal_id, $card ) {
		$token_request = $this->generateTokenizationRequest( $merchant_id, $terminal_id, $card );

		$body = json_encode( $token_request );

		FRP_Gateway_Logger::info( "Starting request for tokenization" );

		$post_response = wp_safe_remote_post( $this->gateway_url . 'api/v1/transactions/tokenize', array(
			'method'  => 'POST',
			'body'    => $body,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json'
			)
		) );

		FRP_Gateway_Logger::debug( "Tokenization request completed" );

		$token_response = json_decode( $post_response['body'] );

		if ( $token_response->success ) {
			$transaction_id = $token_response->result->transaction_id;

			FRP_Gateway_Logger::info( 'Tokenization completed. Transaction ID: ' . $transaction_id );

			return $token_response->result->token;
		} else {
			FRP_Gateway_Logger::debug( "Tokenization response: " . var_export( $token_response, true ) );
			FRP_Gateway_Logger::error( "Tokenization failed." );

			return '';
		}
	}

	/**
	 * Gets an access token from the auth server
	 *
	 * @param string $client_id the client ID
	 * @param string $client_secret the client secret
	 * @param string $token_url the token endpoint
	 *
	 * @return string
	 */
	private function getAccessToken( $client_id, $client_secret, $token_url ) {
		$token_data = array(
			'client_id'     => $client_id,
			'grant_type'    => 'client_credentials',
			'client_secret' => $client_secret,
			'scope'         => 'authorize:transactions capture:transactions sale:transactions refund:transactions void:transactions tokenize:transactions'
		);

		$token_response = wp_safe_remote_post( $token_url, array(
			'body'    => $token_data,
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'application/x-www-form-urlencoded'
			)
		) );

		if ( $token_response['response']['code'] !== 200 ) {
			FRP_Gateway_Logger::debug( 'Token response status code was ' . $token_response['response']['code'] );
			FRP_Gateway_Logger::debug( "Token request ended in failure: " . var_export( $token_response, true ) );
			FRP_Gateway_Logger::error( "Gateway authorization failed. Check credentials." );

			return null;
		}

		FRP_Gateway_Logger::debug( "Token request was a success: " . $token_response['body'] );
		$token = json_decode( $token_response['body'] );

		return $token->access_token;
	}

	/**
	 * Generate a Sale request
	 *
	 * @param string $merchant_id
	 * @param string $terminal_id
	 * @param WC_Order $order
	 * @param array $card
	 *
	 * @return array
	 */
	private function generateSalesRequest( $merchant_id, $terminal_id, $order, $card ) {
		$sale_request = array(
			'mid'      => $merchant_id,
			'terminal' => array(
				'tid'           => $terminal_id,
				'terminal_type' => 'com.givepay.terminal-types.ecommerce'
			),
			'amount'   => array(
				'base_amount' => floatval( $order->get_total() ) * 100
			),
			'payer'    => array(
				'billing_address' => array(
					'line_1'      => $order->get_billing_address_1(),
					'line_2'      => $order->get_billing_address_2(),
					'city'        => $order->get_billing_city(),
					'state'       => $order->get_billing_state(),
					'postal_code' => $order->get_billing_postcode()
				),
				'email_address'   => $order->get_billing_email(),
				'phone_number'    => $order->get_billing_phone()
			)
		);

		if ( isset( $card['token'] ) ) {
			$sale_request['card'] = array(
				'token' => $card['token']
			);
		} else {
			$sale_request['card'] = array(
				'card_number'      => $card['card_number'],
				'card_present'     => false,
				'expiration_month' => $card['expiration_month'],
				'expiration_year'  => $card['expiration_year'],
				'cvv'              => $card['cvv']
			);
		}

		return $sale_request;
	}

	/**
	 * Generate a void request
	 *
	 * @param string $merchant_id
	 * @param string $terminal_id
	 * @param string $transaction_id
	 *
	 * @return array
	 **/
	private function generateVoidRequest( $merchant_id, $terminal_id, $transaction_id ) {
		$refund_request = array(
			'mid'            => $merchant_id,
			'terminal'       => array(
				'tid'           => $terminal_id,
				'terminal_type' => 'com.givepay.terminal-types.ecommerce'
			),
			'transaction_id' => $transaction_id
		);

		return $refund_request;
	}

	/**
	 * Generates a tokenization request
	 *
	 * @param string $merchant_id
	 * @param string $terminal_id
	 * @param array $card
	 *
	 * @return array
	 */
	private function generateTokenizationRequest( $merchant_id, $terminal_id, $card ) {
		$token_request = array(
			'mid'      => $merchant_id,
			'terminal' => array(
				'tid'           => $terminal_id,
				'terminal_type' => 'com.givepay.terminal-types.ecommerce'
			),
			'card'     => array(
				'card_number'      => $card['card_number'],
				'card_present'     => false,
				'expiration_month' => $card['expiration_month'],
				'expiration_year'  => $card['expiration_year'],
				'cvv'              => $card['cvv']
			)
		);

		return $token_request;
	}
}

/**
 * Class TransactionResult
 */
final class TransactionResult {
	private $success;
	private $transaction_id;
	private $error_message;
	private $code;

	public function __construct( $success, $transaction_id = null, $error_message = null, $code = null ) {
		$this->success        = $success;
		$this->transaction_id = $transaction_id;
		$this->error_message  = $error_message;
		$this->code           = $code;
	}

	/**
	 * @return bool
	 */
	public function getSuccess() {
		return $this->success;
	}

	/**
	 * @return string
	 */
	public function getTransactionId() {
		return $this->transaction_id;
	}

	/**
	 * @return string
	 */
	public function getErrorMessage() {
		return $this->error_message;
	}

	/**
	 * @return string
	 */
	public function getCode() {
		return $this->code;
	}
}