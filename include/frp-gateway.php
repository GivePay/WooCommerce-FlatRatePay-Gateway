<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class FRP_Gateway {

    public $token_endpoint;

    public $gateway_url;

    private $client_secret;

    private $client_id;

    public function __construct(
        $client_id,
        $client_secret,
        $token_endpoint = 'https://portal.flatratepay.com/connect/token',
        $gateway_url = 'https://gateway.givepaycommerce.com'
    ) {
        $this->token_endpoint = $token_endpoint;
        $this->gateway_url = $gateway_url;

        $this->client_secret = $client_secret;
        $this->client_id = $client_id;

        require_once(dirname(__FILE__) . '/frp-gateway.php');
    }

    /**
     * @param $order WC_Order to purchase
     * @param $merchant_id (string) The merchant ID for the website
     * @param $terminal_id (string) The terminal ID for the website
     * @throws Exception
     * @return TransactionResult
     */
    public function chargeAmount( $order, $merchant_id, $terminal_id ) {
        if ( NULL ==  $order) {
            throw new Exception( '$order is null' );
        }

        $access_token = $this->getAccessToken( $this->client_id, $this->client_secret, $this->token_endpoint );
        if ( NULL == $access_token ) {
            throw new Exception( 'Could not authorize with gateway.' );
        }

        return $this->makeSaleRequest( $access_token, $order, $merchant_id, $terminal_id );
    }

    private function makeSaleRequest( $access_token, $order, $merchant_id, $terminal_id ) {
        $sale_request = $this->generate_gpg_sale_params($merchant_id, $terminal_id, $order);

        $body = json_encode($sale_request);

        FRP_Gateway_Logger::info("Starting transactions for $" . $order->get_total());

        $post_response = wp_safe_remote_post($this->gateway_url . 'api/v1/transactions/sale', array(
            'method'  => 'POST',
            'body'    => $body,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            )
        ));

        FRP_Gateway_Logger::debug("Transaction completed");

        $sale_response = json_decode($post_response['body']);

        if ($sale_response->success) {
            $transaction_id = $sale_response->result->transaction_id;

            FRP_Gateway_Logger::info('Payment completed. Transaction ID: ' . $transaction_id);

            return new TransactionResult(true, $transaction_id);
        } else {
            $error_message = $sale_response->error->message;
            $code = $sale_response->error->code;

            FRP_Gateway_Logger::debug("Sale response: " . var_export($sale_response, true));
            FRP_Gateway_Logger::error("Payment failed.");

            return new TransactionResult(false, NULL, $error_message, $code);
        }
    }

    /**
     * Gets an access token from the auth server
     * @param string $client_id the client ID
     * @param string $client_secret the client secret
     * @param string $token_url the token endpoint
     * @return string
     */
    private function getAccessToken($client_id, $client_secret, $token_url) {
        $token_data = array(
            'client_id' => $client_id,
            'grant_type' => 'client_credentials',
            'client_secret' => $client_secret,
            'scope' => 'authorize:transactions capture:transactions sale:transactions refund:transactions void:transactions'
        );

        $token_response = wp_safe_remote_post($token_url, array(
            'body'    => $token_data,
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => 'application/x-www-form-urlencoded'
            )
        ));

        if ($token_response['response']['code'] !== 200) {
            FRP_Gateway_Logger::debug('Token response status code was ' . $token_response['response']['code']);
            FRP_Gateway_Logger::debug("Token request ended in failure: " . var_export($token_response, true));
            FRP_Gateway_Logger::error("Gateway authorization failed. Check credentials.");
            return null;
        }

        FRP_Gateway_Logger::debug("Token request was a success: " . $token_response['body']);
        $token = json_decode($token_response['body']);

        return $token->access_token;
    }

    /**
     * Generate an Sale request
     **/
    private function generate_gpg_sale_params($merchant_id, $terminal_id, $order)
    {
        $sale_request = array(
            'mid'      => $merchant_id,
            'terminal' => array(
                'tid'           => $terminal_id,
                'terminal_type' => 'com.givepay.terminal-types.ecommerce'
            ),
            'amount' => array(
                'base_amount' => floatval($order->get_total()) * 100
            ),
            'card' => array(
                'card_number'      => $_POST['gpg_pan'],
                'card_present'     => false,
                'expiration_month' => $_POST['gpg_exp_month'],
                'expiration_year'  => $_POST['gpg_exp_year'],
                'cvv'              => $_POST['gpg_cvv']
            ),
            'payer' => array(
                'billing_address' => array(
                    'line_1'      => $order->get_billing_address_1(),
                    'line_2'      => $order->get_billing_address_2(),
                    'city'        => $order->get_billing_city(),
                    'state'       => $order->get_billing_state(),
                    'postal_code' => $order->get_billing_postcode()
                ),
                'email_address' => $order->get_billing_email(),
                'phone_number'  => $order->get_billing_phone()
            )
        );
        return $sale_request;
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

    public function __construct($success, $transaction_id = NULL, $error_message = NULL, $code = NULL)
    {
        $this->success = $success;
        $this->transaction_id = $transaction_id;
        $this->error_message = $error_message;
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * @return null
     */
    public function getTransactionId()
    {
        return $this->transaction_id;
    }

    /**
     * @return null
     */
    public function getErrorMessage()
    {
        return $this->error_message;
    }

    /**
     * @return null
     */
    public function getCode()
    {
        return $this->code;
    }
}