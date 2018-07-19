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
add_action('plugins_loaded', 'woocommerce_gpg_init', 0);
function woocommerce_gpg_init() {
   if ( !class_exists( 'WC_Payment_Gateway' ) ) 
      return;
   /**
   * Localisation
   */
   load_plugin_textdomain('wc-givepay-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
   
   /**
   * GivePay Gateway class
   */
   class WC_GivePay_Gateway extends WC_Payment_Gateway 
   {
      protected $msg = array();
      
      public function __construct(){
         $this->id               = 'givepay_gateway';
         $this->method_title     = __('FlatRatePay', 'wc-givepay-gateway');
         $this->icon             = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/img/logo.png';
         $this->has_fields       = true;
         $this->init_form_fields();
         $this->init_settings();
         $this->title            = $this->settings['title'];
         $this->description      = $this->settings['description'];
         $this->mode             = $this->settings['working_mode'];
         $this->client_id        = $this->settings['client_id'];
         $this->secret_key       = $this->settings['secret_key'];
         $this->merchant_id      = $this->settings['merchant_id'];
         $this->terminal_id      = $this->settings['terminal_id'];
         $this->success_message  = $this->settings['success_message'];
         $this->failed_message   = $this->settings['failed_message'];
         $this->liveurl          = 'https://gateway.givepaycommerce.com/';
         $this->testurl          = 'https://gpg-stage.flatratepay-staging.net/';
         $this->live_token_url   = 'https://portal.flatratepay.com/connect/token';
         $this->test_token_url   = 'https://portal.flatratepay-staging.net/connect/token';
         $this->msg['message']   = "";
         $this->msg['class']     = "";
         
         if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
             add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
          } else {
             add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
         }
         add_action('woocommerce_receipt_gpg', array(&$this, 'receipt_page'));
         add_action('woocommerce_thankyou_gpg',array(&$this, 'thankyou_page'));
      }
      function init_form_fields()
      {
         $this->form_fields = array(
            'enabled'      => array(
                  'title'        => __('Enable/Disable', 'wc-givepay-gateway'),
                  'type'         => 'checkbox',
                  'label'        => __('Enable FlatRatePay Payment Module.', 'wc-givepay-gateway'),
                  'default'      => 'no'),
            'title'        => array(
                  'title'        => __('Title:', 'wc-givepay-gateway'),
                  'type'         => 'text',
                  'description'  => __('This controls the title which the user sees during checkout.', 'wc-givepay-gateway'),
                  'default'      => __('FlatRatePay', 'wc-givepay-gateway')),
            'description'  => array(
                  'title'        => __('Description:', 'wc-givepay-gateway'),
                  'type'         => 'textarea',
                  'description'  => __('This controls the description which the user sees during checkout.', 'wc-givepay-gateway'),
                  'default'      => __('Pay securely by Credit or Debit Card through GivePay Secure Servers.', 'wc-givepay-gateway')),
            'client_id' => array(
                  'title'        => __('Client ID', 'wc-givepay-gateway'),
                  'type'         => 'text',
                  'description'  =>  __('The Client ID for the API', 'wc-givepay-gateway')),
            'secret_key' => array(
                  'title'        => __('Secret Key', 'wc-givepay-gateway'),
                  'type'         => 'text',
                  'description'  =>  __('The API Secret Key (ssshhhh...)', 'wc-givepay-gateway')),
            'merchant_id' => array(
                  'title'        => __('Merchant ID', 'wc-givepay-gateway'),
                  'type'         => 'text',
                  'description'  =>  __('Your merchant ID number', 'wc-givepay-gateway')),
            'terminal_id' => array(
                  'title'        => __('Terminal ID', 'wc-givepay-gateway'),
                  'type'         => 'text',
                  'description'  =>  __('Your website\'s terminal ID number', 'wc-givepay-gateway')),
            'success_message' => array(
                  'title'        => __('Transaction Success Message', 'wc-givepay-gateway'),
                  'type'         => 'textarea',
                  'description'=>  __('Message to be displayed on successful transaction.', 'wc-givepay-gateway'),
                  'default'      => __('Your payment has been procssed successfully.', 'wc-givepay-gateway')),
            'failed_message'  => array(
                  'title'        => __('Transaction Failed Message', 'wc-givepay-gateway'),
                  'type'         => 'textarea',
                  'description'  =>  __('Message to be displayed on failed transaction.', 'wc-givepay-gateway'),
                  'default'      => __('Your transaction has been declined.', 'wc-givepay-gateway')),
            'working_mode'    => array(
                  'title'        => __('API Mode'),
                  'type'         => 'select',
            'options'      => array('false'=>'Live Mode', 'true'=>'Test/Sandbox Mode'),
                  'description'  => "Live/Test Mode" )
         );
      }
      
      /**
       * Admin Panel Options
       * 
      **/
      public function admin_options()
      {
         echo '<h3>'.__('FlatRatePay Payment Gateway', 'wc-givepay-gateway').'</h3>';
         echo '<p>'.__('FlatRatePay is the most popular payment gateway for online payment processing').'</p>';
         echo '<table class="form-table">';
         $this->generate_settings_html();
         echo '</table>';
      }
      
      /**
      *  Fields for GPG
      **/
      function payment_fields()
      {
         if ($this->description) {
            echo wpautop(wptexturize($this->description));
            echo '<label style="margin-right:46px; line-height:40px;">Credit Card :</label> <input type="text" name="gpg_pan" /><br/>';
            echo '<label style="margin-right:30px; line-height:40px;">Expiry (MM YY) :</label> <input type="text" placeholder="MM" style="width:60px; display: inline;" name="gpg_exp_month" maxlength="2" />';
            echo '<input type="text" style="width:60px; display: inline;" name="gpg_exp_year" maxlength="2" placeholder="YY" /><br/>';
            echo '<label style="margin-right:89px; line-height:40px;">CVV :</label> <input type="text" name="gpg_cvv"  maxlength=4 style="width:60px;" /><br/>';
          }
      }
      
      /*
      * Basic Card validation
      */
      public function validate_fields()
      {
        global $woocommerce;

        if (!$this->isCreditCardNumber($_POST['gpg_pan'])) {
           wc_add_notice(__('(Credit Card Number) is not valid.', 'wc-givepay-gateway'), 'error'); 
        }

        if (!$this->isCorrectExpireDate($_POST['gpg_exp_year'])) {
           wc_add_notice(__('(Card Expiry Date) is not valid.', 'wc-givepay-gateway')); 
        }  

        if (!$this->isCorrectExpireDate($_POST['gpg_exp_month'])) {
           wc_add_notice(__('(Card Expiry Date) is not valid.', 'wc-givepay-gateway')); 
        } 

        if (!$this->isCCVNumber($_POST['gpg_cvv'])) {
           wc_add_notice(__('(Card Verification Number) is not valid.', 'wc-givepay-gateway')); 
        }
      }
      
      /*
      * Check card 
      */
      private function isCreditCardNumber($toCheck) 
      {
         if (!is_numeric($toCheck))
            return false;
        
        $number = preg_replace('/[^0-9]+/', '', $toCheck);
        $strlen = strlen($number);
        $sum    = 0;
        if ($strlen < 13)
            return false; 
            
        for ($i=0; $i < $strlen; $i++)
        {
            $digit = substr($number, $strlen - $i - 1, 1);
            if($i % 2 == 1)
            {
                $sub_total = $digit * 2;
                if($sub_total > 9)
                {
                    $sub_total = 1 + ($sub_total - 10);
                }
            } 
            else 
            {
                $sub_total = $digit;
            }
            $sum += $sub_total;
        }
        
        if ($sum > 0 AND $sum % 10 == 0)
            return true; 
        return false;
      }
        
      private function isCCVNumber($toCheck) 
      {
         $length = strlen($toCheck);
         return is_numeric($toCheck) AND $length > 2 AND $length < 5;
      }
    
      /*
      * Check expiry date
      */
      private function isCorrectExpireDate($date) 
      {
          
         if (is_numeric($date) && (strlen($date) == 2)){
            return true;
         }
         return false;
      }
      
      public function thankyou_page($order_id) 
      {
      
       
      }
      
      /**
      * Receipt Page
      **/
      function receipt_page($order)
      {
         echo '<p>'.__('Thank you for your order.', 'wc-givepay-gateway').'</p>';
        
      }
      
      /**
       * Process the payment and return the result
      **/
      function process_payment($order_id)
      {
        global $woocommerce;
        $order = new WC_Order($order_id);
        if ($this->mode == 'true') {
          $process_url = $this->testurl;
          $token_url = $this->test_token_url;
        } else {
          $process_url = $this->liveurl;
          $token_url = $this->live_token_url;
        }

        $client_id = $this->client_id;
        $secret = $this->secret_key;

        $token = $this->get_oauth_token($token_url, $client_id, $secret);

        $sale_request = $this->generate_gpg_sale_params($order);

        $body = json_encode($sale_request);

        write_log('Making SALE request to ' . $process_url);

        $ch = curl_init(); // initiate curl object
        curl_setopt($ch, CURLOPT_URL, $process_url . 'api/v1/transactions/sale');
        curl_setopt($ch, CURLOPT_HEADER, false); // set to 0 to eliminate header info from response
        curl_setopt($ch, CURLOPT_POST, true); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Returns response data instead of TRUE(1)
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body); // use HTTP POST to send form data
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // uncomment this line if you get no gateway response.
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Accept: application/json',
          'Content-Type: application/json',
          'Authorization: Bearer ' . $token,
          'Content-Length: ' . strlen($body)
        ));
        $post_response = curl_exec($ch); // execute curl post and store results in $post_response
        curl_close($ch);
        write_log($post_response);
        $sale_response = json_decode($post_response);

        if ($sale_response->success) {
          $transaction_id = $sale_response->result->transaction_id;

          $order->reduce_order_stock();
          $woocommerce->cart->empty_cart();

          $order->add_order_note( __('FlatRatePay payment complete!', 'wc-givepay-gateway') );
          $order->add_order_note( __('Transaction ID: ' . $transaction_id, 'wc-givepay-gateway') );

          $order->payment_complete();

          return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
          );
        } else {
          $order->add_order_note($this->failed_message);
          $order->update_status('failed');

          $error_message = $sale_response->error->message;
          $code = $sale_response->error->code;

          wc_add_notice(__('(Transaction Error) Error processing payment: ' . $error_message, 'wc-givepay-gateway'), 'error'); 
        }
      }

      /**
      * Retrieves a new OAuth Token from the OIDC Token endpoint
      **/
      public function get_oauth_token($token_url, $client_id, $client_secret)
      {
        $token_data = array(
          'client_id' => $client_id,
          'grant_type' => 'client_credentials',
          'client_secret' => $client_secret,
          'scope' => 'authorize:transactions capture:transactions sale:transactions refund:transactions void:transactions'
        );

        $body = "";
        foreach ($token_data as $key => $value) { 
           $body .= "$key=" . rawurlencode( $value ) . "&"; 
        }
        $body = rtrim( $body, "& " );

        write_log('Making token request to ' . $token_url);

        $ch = curl_init(); // initiate curl object

        curl_setopt($ch, CURLOPT_HEADER, FALSE); // set to 0 to eliminate header info from response
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);      
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // Returns response data instead of TRUE(1)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE); // uncomment this line if you get no gateway response.
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Accept: application/json',
          'Content-Length: ' . strlen($body)
        ));
         
        $token_response = curl_exec($ch); // execute curl post and store results in $post_response
        write_log("Token request ended");
        curl_close($ch);

        $token = json_decode($token_response);
        return $token->access_token;
      }
      
      /**
      * Generate a Sale request
      **/
      public function generate_gpg_sale_params($order)
      {
        $sale_request = array(
          'mid'      => $this->merchant_id,
          'terminal' => array(
            'tid' => $this->terminal_id,
            'terminal_type' => 'com.givepay.terminal-types.ecommerce'
          ),
          'amount' => array(
            'base_amount' => floatval($order->get_total()) * 100
          ),
          'card' => array(
            'card_number' => $_POST['gpg_pan'],
            'card_present' => false,
            'expiration_month' => $_POST['gpg_exp_month'],
            'expiration_year' => $_POST['gpg_exp_year'],
            'cvv' => $_POST['gpg_cvv']
          ),
          'payer' => array(
            'billing_address' => array(
              'line_1' => $order->get_billing_address_1(),
              'line_2' => $order->get_billing_address_2(),
              'city' => $order->get_billing_city(),
              'state' => $order->get_billing_state(),
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
    * Add this Gateway to WooCommerce
   **/
   function woocommerce_add_gpg($methods) 
   {
      $methods[] = 'WC_GivePay_Gateway';
      return $methods;
   }
   add_filter('woocommerce_payment_gateways', 'woocommerce_add_gpg' );
}


if ( ! function_exists('write_log')) {
   function write_log ( $log )  {
      if ( is_array( $log ) || is_object( $log ) ) {
         error_log( print_r( $log, true ) );
      } else {
         error_log( $log );
      }
   }
}
