<?php
/*
   Plugin Name: Himalayan Bank Debit / Credit Card
   Description: Extends WooCommerce to Process Payments with Himalyan Bank gateway. Akamai update compatible.
   Version: 3.8
   Plugin URI: https://www.anthropose.com
   Author: Anthropose Pvt. Ltd.
   Author URI: 
   License: Under GPL2

*/

add_action('plugins_loaded', 'woocommerce_tech_autho_init', 0);

function woocommerce_tech_autho_init() {

   if ( !class_exists( 'WC_Payment_Gateway' ) ) 
      return;

   /**
   * Localisation
   */
   load_plugin_textdomain('wc-tech-autho', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
   
   /**
   * HBL Payment Gateway class
   */
   class WC_Gateway_hbl extends WC_Payment_Gateway 
   {
      protected $msg = array();
 
      public function __construct(){

         $this->id               = 'hbl';
         $this->method_title     = __('HBL', 'tech');
         $this->icon             = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.gif';
         $this->has_fields       = false;
         $this->init_form_fields();
         $this->init_settings();
         $this->title            = $this->settings['title'];
         $this->description      = $this->settings['description'];
         $this->payment_gateway_id            = $this->settings['payment_gateway_id'];
         $this->mode             = $this->settings['working_mode'];
         $this->transaction_mode = $this->settings['transaction_mode'];
         $this->currency_code  = $this->settings['currency_code'];
         $this->secret_key         = $this->settings['secret_key'];
         $this->success_message  = $this->settings['success_message'];
         $this->failed_message   = $this->settings['failed_message'];
         $this->liveurl          = 'https://hblpgw.2c2p.com/HBLPGW/Payment/Payment/Payment';
         $this->testurl          = 'https://hblpgw.2c2p.com/HBLPGW/Payment/Payment/Payment';
         
         $this->msg['message']   = "";
         $this->msg['class']     = "";
         $this->hash_value       = "";
        

         add_action('init', array(&$this, 'check_hbl_response'));
         //update for woocommerce >2.0
         add_action( 'woocommerce_api_wc_gateway_hbl' , array( $this, 'check_hbl_response' ) );
         add_action('valid-hbl-request', array(&$this, 'successful_request'));
         
         
         if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
             add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
          } else {
             add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
         }

         add_action('woocommerce_receipt_hbl', array(&$this, 'receipt_page'));
         add_action('woocommerce_thankyou_hbl',array(&$this, 'thankyou_page'));
         
         if( function_exists('indatos_woo_auth_process_refund') ){
            $this->supports = array(
              'products',
              'refunds'
            );
         }else{
            
         }
      }
          
      function init_form_fields()
      {

         $this->form_fields = array(
            'enabled'      => array(
                  'title'        => __('Enable/Disable', 'tech'),
                  'type'         => 'checkbox',
                  'label'        => __('Enable HBL Payment Module.', 'tech'),
                  'default'      => 'no'),
            'title'        => array(
                  'title'        => __('Title:', 'tech'),
                  'type'         => 'text',
                  'description'  => __('This controls the title which the user sees during checkout.', 'tech'),
                  'default'      => __('Authorize.net', 'tech')),
            'description'  => array(
                  'title'        => __('Description:', 'tech'),
                  'type'         => 'textarea',
                  'description'  => __('This controls the description which the user sees during checkout.', 'tech'),
                  'default'      => __('Pay securely by Credit or Debit Card through HBL Secure Servers.', 'tech')),
            'payment_gateway_id'     => array(
                  'title'        => __('Payment Gateway ID', 'tech'),
                  'type'         => 'text',
                  'description'  => __('This is Payment Gateway ID')),
            'currency_code' => array(
                  'title'        => __('Currency Code', 'tech'),
                  'type'         => 'text',
                  'description'  =>  __('Currency Code', 'tech')),
            'secret_key' => array(
                  'title'        => __('Secret Key', 'tech'),
                  'type'         => 'text',
                  'description'  =>  __('Secret Key is required to validate the response from HBL.', 'tech')),
            'success_message' => array(
                  'title'        => __('Transaction Success Message', 'tech'),
                  'type'         => 'textarea',
                  'description'=>  __('Message to be displayed on successful transaction.', 'tech'),
                  'default'      => __('Your payment has been procssed successfully.', 'tech')),
            'failed_message'  => array(
                  'title'        => __('Transaction Failed Message', 'tech'),
                  'type'         => 'textarea',
                  'description'  =>  __('Message to be displayed on failed transaction.', 'tech'),
                  'default'      => __('Your transaction has been declined.', 'tech')),
            'working_mode'    => array(
                  'title'        => __('API Mode'),
                  'type'         => 'select',
                  'options'      => array('false'=>'Live/Production Mode', 'false_test' => 'Live/Production API in Test Mode', 'true'=>'Sandbox/Developer API Mode'),
                  'description'  => "Live or Production / Sandbox Mode" ),
            'transaction_mode'    => array(
                  'title'        => __('Transaction Mode'),
                  'type'         => 'select',
                  'options'      => array( 'auth_capture'=>'Authorize and Capture', 'authorize'=>'Authorize Only'),
                  'description'  => "Transaction Mode. If you are not sure what to use set to Authorize and Capture" )
         );
      }
      
     
      /**
       * Admin Panel Options
       * - Options for bits like 'title' and availability on a country-by-country basis
      **/
      public function admin_options()
      {

         echo '<h3>'.__('HBL Payment Gateway', 'tech').'</h3>';
         echo '<p>'.__('HBL is most popular payment gateway for online payment processing. For any support connect with Tech Support team on').'</p>';

         echo '<table class="form-table">';
         $this->generate_settings_html();
         echo '</table>';

      }
      
      /**
      *  There are no payment fields for HBL, but want to show the description if set.
      **/
      function payment_fields()
      {
         if ( $this->description ) 
            echo wpautop(wptexturize($this->description));
      }
      
      public function thankyou_page($order_id) 
      {
         
      }
      /**
      * Receipt Page
      **/
      function receipt_page($order)
      {


         echo '<p>'.__('Thank you for your order, please click the button below to pay with Credit / Debit Cards', 'tech').'</p>';
         echo $this->generate_hbl_form($order);
      }
      
      /**
       * Process the payment and return the result
      **/
      function process_payment($order_id){

         $order = new WC_Order($order_id);
         return array(
         				'result' 	=> 'success',
         				'redirect'	=> $order->get_checkout_payment_url( true )
         			);
      }
      
      /**
       * Check for valid HBL server callback to validate the transaction response.
      **/
      function check_hbl_response()
      {

         global $woocommerce;
         $temp_order            = new WC_Order();  

         foreach( $_POST as $key => $value ){
            $$key = $value;
         }

      
         if ( count($_POST) ){

         
            $redirect_url = '';
            $this->msg['class']     = 'error';
            $this->msg['message']   = $this->failed_message;
            $order_id = str_replace('Anthropose-', '', $invoiceNo );
            $order                  = new WC_Order( $order_id );

            $secret_key               = ($this->secret_key != '') ? $this->secret_key : '';

            if ( $paymentGatewayID != '' AND $Amount != '' AND $invoiceNo != '' AND $tranRef != '' AND $dateTime != '' AND $Status != '' AND $hashValue != '' ){
               try{       
                     
                  if ( $order->status != 'completed'){
                     
                     if ( $Status == 'AP' ){

                        $this->msg['message']   = $this->success_message;
                        $this->msg['class']     = 'success';
                        
                        if ( $order->status == 'processing' ){
                           
                        }
                        else{

                            $order->payment_complete( $tranRef );
                            $order->add_order_note('HBL Credit / Debit card payment successful<br/>Ref Number/Transaction ID: '.$tranRef );
                            $order->add_order_note($this->msg['message']);
                            $woocommerce->cart->empty_cart();

                            wc_add_notice($this->success_message,'notice');
                        }
                     }
                     else{
                        $this->msg['class'] = 'error';
                        $this->msg['message'] = $this->failed_message;

                        wc_add_notice($this->failed_message,'notice');

                        $order->add_order_note($this->msg['message'].' Transaction Status: '. $this->get_status_text($Status) );
                        $order->update_status('on-hold');
                        $woocommerce->cart->empty_cart();
                        //extra code can be added here such as sending an email to customer on transaction fail
                     }
                  }

               }
               catch(Exception $e){
                         // $errorOccurred = true;
                         $msg = "Error";

               }

            }else{

               wc_add_notice('Hey Hey! Transaction could not go through. Please contact info@anthropose.com','notice');
               $order->add_order_note('Hey Hey! Transaction could not go through. Please contact info@anthropose.com');
            }
            $redirect_url = $order->get_checkout_order_received_url();
            $this->web_redirect( $redirect_url); exit;
         }
         else{
            
            $redirect_url = $temp_order->get_checkout_order_received_url();
            $this->web_redirect($redirect_url.'?msg=Unknown_error_occured');
            exit;
         }
      }
      
      
      public function web_redirect($url){
         
         echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";
      
      }
      /**
      * Generate HBL button link
      **/
      public function generate_hbl_form($order_id)
      {
         global $woocommerce;
         
         $order      = new WC_Order($order_id);
         
         $another_number = explode('.', $order->order_total);


         if(isset( $another_number[1] )){
             $suffix = (int)$another_number[1];
             $suffix = sprintf('%02d', $suffix);
         }
         else{
             $suffix = '00';
         }


         $amount = sprintf('%010d', $order->order_total).$suffix;
         $new_order_id = 'Anthropose-'.$order_id;

         $signatureString = $this->payment_gateway_id.$new_order_id.$amount.$this->currency_code.'Y';

         $signData = hash_hmac('SHA256', $signatureString, $this->secret_key, false);
         $signData = strtoupper($signData);
         $signData = urlencode($signData);

         $this->hash_value = $signData;

         $hbl_args = [
            'paymentGatewayID'         => $this->payment_gateway_id,
            'amount'                   => $amount,
            'invoiceNo'                => $new_order_id,
            'currencyCode'             => $this->currency_code ,
            'nonSecure'                => 'Y',
            'productDesc'              => 'Test Prod',
            'hashValue'                => $this->hash_value
         ];

         $authorize_args_html = '';
         
         foreach($hbl_args as $key => $value){

            $authorize_args_html .= "<input type='hidden' name='{$key}' value='{$value}'/>";
         }
         
        $processURI = $this->liveurl;

         
         $html_form    = '<form action="'.$processURI.'" method="post" id="authorize_payment_form">' 
               . $authorize_args_html
               . '<input type="submit" class="button" id="submit_hbl_payment_form" value="'.__('Pay via Credit / Debit Cards', 'tech').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'tech').'</a>'
               . '<script type="text/javascript">
                  jQuery(function(){
                     jQuery("body").block({
                           message: "<img src=\"'.$woocommerce->plugin_url().'/images/logo.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to HBL to make payment.', 'tech').'",
                           overlayCSS:
                        {
                           background:       "#ccc",
                           opacity:          0.6,
                           "z-index": "99999999999999999999999999999999"
                        },
                     css: {
                           padding:          20,
                           textAlign:        "center",
                           color:            "#555",
                           border:           "3px solid #aaa",
                           backgroundColor:  "#fff",
                           cursor:           "wait",
                           lineHeight:       "32px",
                           "z-index": "999999999999999999999999999999999"
                     }
                     });
                  jQuery("#submit_hbl_payment_form").click();
               });
               </script>
               </form>';

         return $html_form;
      }

      function get_status_text( $Status){

       switch( $Status ){

            case 'AP':
                $status_text = 'Approved(Paid)';
            break;

            case 'SE':
                $status_text = 'Settled';
            break;

            case 'VO':
                $status_text = 'Voided (Canceled)';
            break;

            case 'DE':
                $status_text = 'Declined by the issuer Host';
            break;

            case 'FA':
                $status_text = 'Failed';
            break;

            case 'PE':
                $status_text = 'Pending';
            break;

            case 'EX':
                $status_text = 'Expired';
            break;

            case 'RE':
                $status_text = 'Refunded';
            break;

            case 'RS':
                $status_text = 'Ready to Settle';
            break;

            case 'AU':
                $status_text = 'Authenticated';
            break;

            case 'IN':
                $status_text = 'Initiated';
            break;

            case 'FP':
                $status_text = 'Fraud Passed';
            break;

            case 'PA':
                $status_text = 'Paid (Cash)';
            break;

            case 'MA':
                $status_text = 'Matched (Cash)';
            break;

            default:
                $status_text = 'No Data From HBL';
            break;

        }

        return $status_text;
   }  
}

   /**
    * Add this Gateway to WooCommerce
   **/
   function woocommerce_add_tech_autho_gateway($methods) 
   {
      $methods[] = 'WC_Gateway_hbl';
      return $methods;
   }

   add_filter('woocommerce_payment_gateways', 'woocommerce_add_tech_autho_gateway' );
}