<?php 
/**
* Plugin Name: PayToTask Payment Gateway
* Plugin URI: https://github.com/ittihadsoft/paytotask-payment-gateway-for-woocommerce
* Description: PayToTask is woocommerce payment gateway
* Version: 1.0.0
* Author: ittihadsoft
* Author URI: http://ittihadsoft.net
* Text Domain: paytotask
* License: GPL/GNU.
* Domain Path: /languages
*/

defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function paytotask_payment_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_PayToTask';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'paytotask_payment_add_to_gateways' );

// paytotask front-end ajax action
function paytotask_enqueue_scripts()	{			
	$from_paytotask = new WC_PayToTask();
	$endpoint = is_wc_endpoint_url('order-pay') ? '?wc-ajax=ajax_process_payment' : '?wc-ajax=ajax_process_checkout';
	wp_enqueue_script( 'paytotask-payment', plugins_url('assets/js/payment.js', __FILE__), array('jquery'));
	wp_localize_script('paytotask-payment','wcAjaxObj', array(
		'process_checkout'=> home_url( '/'.$endpoint),
		'vendor_id'=> $from_paytotask->vendor_id
	));

	wp_enqueue_style( 'paytotask-css', plugins_url('assets/css/payment.css', __FILE__));
}

add_action('wp_enqueue_scripts', 'paytotask_enqueue_scripts');

// Receives our AJAX callback to process the checkout
function paytotask_ajax_process_checkout() {
	WC()->checkout()->process_checkout();
	// hit "process_payment()"
}
add_action('wc_ajax_ajax_process_checkout', 'paytotask_ajax_process_checkout');
add_action('wc_ajax_nopriv_ajax_process_checkout','paytotask_ajax_process_checkout');

// paytotask payment gateway init
function paytotask_payment_gateway_init() {
	
/**
 * Register and enqueue a custom stylesheet in the WordPress admin.
 */

    class WC_PayToTask extends WC_Payment_Gateway {

        /**
		 * Constructor for the gateway.
		 */

		public function __construct() {
	  
			$this->id                 = 'paytotask_payment_gateway';
			$this->icon               = plugins_url('assets/images/logo.png', __FILE__);
			$this->has_fields         = false;
			$this->method_title       = esc_html__( 'PayToTask', 'paytotask' );
			$this->method_description = esc_html__( 'Accept payments through credit card.', 'paytotask' );
			$this->supports           = array('products');
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        	 = $this->get_option( 'title' );
			$this->description 		 = $this->get_option( 'description' );
			$this->vendor_id  		 = $this->get_option( 'vendor_id' );
			$this->api_key  = $this->get_option( 'api_key' );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action('woocommerce_api_' . $this->id, array($this, 'webhook_response'));
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  		
	  		if ($this->get_option( 'vendor_id' ) && $this->get_option( 'api_key' )) {
				$connection_button = '<p style=\'color:green\'>Your paytotask account has already been connected</p>' .
				'<a class=\'button-primary open_paytotask_integration_window\'>'.esc_html__('Reconnect your paytotask Account','paytotask').'</a>';
			} else {
				$connection_button = '<a class=\'button-primary open_paytotask_integration_window\'>'.esc_html__('Connect your paytotask Account','paytotask').'</a>';
			}

			$this->form_fields = array(
		  
				'enabled' => array(
					'title'   => esc_html__( 'Enable/Disable', 'paytotask' ),
					'type'    => 'checkbox',
					'label'   => esc_html__( 'Enable', 'paytotask' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => esc_html__( 'Title', 'paytotask' ),
					'type'        => 'text',
					'description' => esc_html__( 'This controls the title for the payment method the customer sees during checkout.', 'paytotask' ),
					'default'     => esc_html__( 'PayToTask', 'paytotask' ),
					'desc_tip'    => true,
				),

				'vendor_id' => array(
					'title'       => esc_html__( 'Vendor ID', 'paytotask' ),
					'type'        => 'text',
					'description' => '<a href="https://www.paytotask.com/user/payment_gateway" target="_blank">'.esc_html__( 'Get User ID.', 'paytotask' ).'</a>'
				),

				'api_key' => array(
					'title'       => esc_html__( 'API Key', 'paytotask' ),
					'type'        => 'text',
					'description' => '<a href="https://www.paytotask.com/user/payment_gateway" target="_blank">'.esc_html__( 'Get API Key.', 'paytotask' ).'</a>'
				),
				'description' => array(
					'title'       => esc_html__( 'Description', "paytotask" ),
					'type'        => 'textarea',
					'description' => esc_html__( 'This controls the description which the user sees during checkout.', "paytotask" ),
					'default'     => esc_html__( 'Pay using Visa, Mastercard, Maestro, American Express, Discover, Diners Club, JCB, UnionPay or Mada', "paytotask" )
				)				
			);
		}

		// hit from "ajax_process_checkout()"
		public function process_payment( $order_id ) {

    		global $woocommerce;
		   $order = new WC_Order($order_id);
		    
		   foreach ( $order->get_items() as $item ) {
			   $product_name[] = $item->get_name();
			   $product_id = $item->get_product_id();
			}

		   $response = wp_remote_retrieve_body(wp_remote_post( 'https://www.paytotask.com/api/2.0/product/generate_pay_link', array( 
			   'method' => 'POST',
				'timeout' => 30,
				'httpversion' => '1.1',
				'body' => array(
					'user_id' => $this->get_option( 'vendor_id' ),
					'api_key' => $this->get_option( 'api_key' ),
					'title' => implode(', ', $product_name),
					'image_url' => wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), array('220','220'),true )[0],
					'amount' => $order->get_total(),
					'name' => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
					'email' => $order->get_billing_email(),
					'country' => $order->get_billing_country(),
					'domain' => get_site_option( 'siteurl' ),
					'return_url' => $order->get_checkout_order_received_url(),
					'webhook_url' => get_bloginfo('url') . '/wc-api/'. $this->id.'?order_id=' . $order_id
				)
			)));

		   $api_response = json_decode($response);

			if ($api_response && $api_response->success === true) {

				$order->update_status( 'pending' );

				// Remove cart
				$woocommerce->cart->empty_cart();

				update_post_meta( $order_id, 'paytotask_transaction', $api_response->transaction );

				// We got a valid response
				return array(
					'result' => 'success',
					'checkout_url' => $api_response->checkout_url
				);

				// Exit is important
				exit;
			} else {
				return array(
					'result' => 'success',
					'success' => false,
					'messages' => $api_response->message,
				);
				// We got a response, but it was an error response
				wc_print_notices(__('Something went wrong getting checkout url. Check if gateway is integrated.','paytotask'), 'error');
			}
		}

		// hit webhook after complete payment "https://localhost.com/wc-api/paytotask_payment_gateway?order_id=xx"
		public function webhook_response()	{
			if (get_post_meta( $_GET['order_id'], 'paytotask_transaction', true ) === $_GET['paytotask_transaction']) {
				$order = wc_get_order($_GET['order_id']);
				exit;
			} else {
				error_log(__( 'PayToTask error. Unable to complete payment - order ' ,'paytotask'). $order_id . __( ' does not exist' ,'paytotask'));
			}
   	}

   }
}

add_action( 'plugins_loaded', 'paytotask_payment_gateway_init');