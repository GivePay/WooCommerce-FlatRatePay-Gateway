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
         $this->login            = $this->settings['login_id'];
         $this->mode             = $this->settings['working_mode'];
         $this->client_id        = $this->settings['client_id'];
         $this->secret_key       = $this->settings['secret_key'];
         $this->success_message  = $this->settings['success_message'];
         $this->failed_message   = $this->settings['failed_message'];
         $this->liveurl          = 'https://gateway.givepaycommerce.com/';
         $this->testurl          = 'https://gateway.flatratepay-staging.net';
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
            'login_id'     => array(
                  'title'        => __('Login ID', 'wc-givepay-gateway'),
                  'type'         => 'text',
                  'description'  => __('This is API Login ID')),
            'transaction_key' => array(
                  'title'        => __('Transaction Key', 'wc-givepay-gateway'),
                  'type'         => 'text',
                  'description'  =>  __('API Transaction Key', 'wc-givepay-gateway')),
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
         if ( $this->description ) 
            echo wpautop(wptexturize($this->description));
            echo '<label style="margin-right:46px; line-height:40px;">Credit Card :</label> <input type="text" name="aim_credircard" /><br/>';
            echo '<label style="margin-right:30px; line-height:40px;">Expiry (MMYY) :</label> <input type="text"  style="width:50px;" name="aim_ccexpdate" maxlength="4" /><br/>';
            echo '<label style="margin-right:89px; line-height:40px;">CVV :</label> <input type="text" name="aim_ccvnumber"  maxlength=4 style="width:40px;" /><br/>';
      }
      
      /*
      * Basic Card validation
      */
      public function validate_fields()
      {
           global $woocommerce;
           if (!$this->isCreditCardNumber($_POST['aim_credircard'])) 
               $woocommerce->add_error(__('(Credit Card Number) is not valid.', 'wc-givepay-gateway')); 
           if (!$this->isCorrectExpireDate($_POST['aim_ccexpdate']))    
               $woocommerce->add_error(__('(Card Expiry Date) is not valid.', 'wc-givepay-gateway')); 
           if (!$this->isCCVNumber($_POST['aim_ccvnumber'])) 
               $woocommerce->add_error(__('(Card Verification Number) is not valid.', 'wc-givepay-gateway')); 
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
          
         if (is_numeric($date) && (strlen($date) == 4)){
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
         if($this->mode == 'true'){
           $process_url = $this->testurl;
         }
         else{
           $process_url = $this->liveurl;
         }
         
         $params = $this->generate_authorizeaim_params($order);
         
         $post_string = "";
         foreach( $params as $key => $value ){ 
            $post_string .= "$key=" . urlencode( $value ) . "&"; 
         }
         $post_string = rtrim( $post_string, "& " );
         
         $request = curl_init($process_url); // initiate curl object
         curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
         curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
         curl_setopt($request, CURLOPT_POSTFIELDS, $post_string); // use HTTP POST to send form data
         curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment this line if you get no gateway response.
         $post_response = curl_exec($request); // execute curl post and store results in $post_response
         curl_close ($request);
         
           
       $response_array = explode('|',$post_response);
   
      
         if ( count($response_array) > 1 ){
         
            if($response_array[0] == '1' ){
                if ($order->status != 'completed') {
                    $order->payment_complete( $response_array[6]);
                     $woocommerce->cart->empty_cart();
                     $order->add_order_note($this->success_message. $response_array[3] . 'Transaction ID: '. $response_array[6] );
                     unset($_SESSION['order_awaiting_payment']);
                 }
                  return array('result'   => 'success',
                     'redirect'  => get_site_url().'/checkout/order-received/'.$order->id.'/?key='.$order->order_key );
            }
            else{
            
                $order->add_order_note($this->failed_message .$response_array[3] );
                $woocommerce->add_error(__('(Transaction Error) '. $response_array[3], 'wc-givepay-gateway'));
            }
        }
        else {
            
            $order->add_order_note($this->failed_message);
            $order->update_status('failed');
            
            $woocommerce->add_error(__('(Transaction Error) Error processing payment.', 'wc-givepay-gateway')); 
        }
         
         
         
      }
      
      /**
      * Generate authorize.net AIM button link
      **/
      public function generate_authorizeaim_params($order)
      {
         $authorizeaim_args = array(
            'x_login'                  => $this->login,
            'x_tran_key'               => $this->transaction_key,
            'x_version'                => '3.1',
            'x_delim_data'             => 'TRUE',
            'x_delim_char'             => '|',
            'x_relay_response'         => 'FALSE',
            'x_type'                   => 'AUTH_CAPTURE',
            'x_method'                 => 'CC',
            'x_card_num'               => $_POST['aim_credircard'],
            'x_exp_date'               => $_POST['aim_ccexpdate' ],
            'x_description'            => 'Order #'.$order->id,
            'x_amount'                 => $order->order_total,
            'x_first_name'             => $order->billing_first_name ,
            'x_last_name'              => $order->billing_last_name ,
            'x_company'                => $order->billing_company ,
            'x_address'                => $order->billing_address_1 .' '. $order->billing_address_2,
            'x_country'                => $order->billing_country,
            'x_phone'                  => $order->billing_phone,
            'x_state'                  => $order->billing_state,
            'x_city'                   => $order->billing_city,
            'x_zip'                    => $order->billing_postcode,
            'x_email'                  => $order->billing_email,
            'x_card_code'              => $_POST['aim_cvvnumber'], 
            'x_ship_to_first_name'     => $order->shipping_first_name,
            'x_ship_to_last_name'      => $order->shipping_last_name,
            'x_ship_to_address'        => $order->shipping_address_1,
            'x_ship_to_city'           => $order->shipping_city,
            'x_ship_to_zip'            => $order->shipping_postcode,
            'x_ship_to_state'          => $order->shipping_state,
            
             );
         return $authorizeaim_args;
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