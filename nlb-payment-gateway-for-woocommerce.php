<?php
/**
 * Plugin Name: WooCommerce Tebank Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/nlb-payment-gateway-for-woocommerce/
 * Description: Implements the Tebank payment gateway.
 * Author: Webpigment
 * Author URI: https://www.webpigment.com/
 * Version: 2.0.1
 * Text Domain: nlb-payment-gateway-for-woocommerce
 * Domain Path: /i18n/languages/
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Tebank-Gateway
 * @author    Mitko Kockovski
 * @category  Admin
 * @copyright Copyright (c) Mitko Kockovski
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

/**
 * Special thanks to https://www.skyverge.com/blog/how-to-create-a-simple-woocommerce-payment-gateway/,
 * Modified the Tebank php plugin for creating order.
 */

defined( 'ABSPATH' ) or exit;
require_once realpath( dirname( __FILE__ ) ) . '/tebank-files/e24PaymentPipe.php';


// Make sure WooCommerce is active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways.
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_tebank_add_to_gateways( $gateways ) {
	if ( ! class_exists( 'Woocommerce_Nlb_Payment_Bankar' ) ) {
		include_once dirname( __FILE__ ) . '/bankar-files/initClientAutoload.php';
		include_once dirname( __FILE__ ) . '/classes/class-woocomemrce-nlb-payment-bankart.php';
	}
	$gateways[] = 'WC_Gateway_tebank';
	$gateways[] = 'Woocommerce_Nlb_Payment_Bankart';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_tebank_add_to_gateways' );


/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links.
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_tebank_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tebank_gateway' ) . '">' . __( 'Configure', 'nlb-payment-gateway-for-woocommerce' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_tebank_gateway_plugin_links' );



add_action( 'plugins_loaded', 'wc_tebank_gateway_init', 11 );
/**
 * Register the payment gateway
 */
function wc_tebank_gateway_init() {

	/**
	 * Capture the payment on status change or after authorized transaction.
	 *
	 * @param  int $order_id order ID.
	 */
	function action_capture_nlb_payment( $order_id ) {
		global $woocommerce;
		$pg = new WC_Gateway_tebank();
		$pay_id = get_post_meta( $order_id, 'payID', true );
		$tran_id = get_post_meta( $order_id, 'tranID', true );
		$track_id = get_post_meta( $order_id, 'trackID', true );
		$order = wc_get_order( $order_id );
		add_post_meta( $order_id, 'nlb_update_post_status', '2');
		$payment_pipe = new e24PaymentPipe();
		$payment_pipe->setResourcePath( realpath( dirname( __FILE__ ) ) . '/' );
		$payment_pipe->setAlias( $pg->terminalalias );
		$payment_pipe->setAction( 5 );
		$payment_pipe->setAmt( apply_filters( 'nlb_payment_price', $order->get_total() ) );

		if ( ! empty( $pay_id ) ) {

			$payment_pipe->setPaymentId( $pay_id );
			$payment_pipe->setTranID( $tran_id );
			$payment_pipe->setTrackID( $track_id );
			if ( $payment_pipe->performPayment() !== $payment_pipe->SUCCESS ) {
				$order->update_status( 'on-hold', __( 'Unable to perform Payment.', 'nlb-payment-gateway-for-woocommerce' ) );
			} else {
				$order->update_status( 'processing', __( 'Payment captured.', 'nlb-payment-gateway-for-woocommerce' ) . $result );
				$order->payment_complete();
			}
		} else {
			$order->update_status( 'on-hold', __( 'Invalid Payment ID.', 'nlb-payment-gateway-for-woocommerce' ) );
		}
	}

	add_action( 'rest_api_init', function () {
		register_rest_route( 'tebank_payment_gateway/v1', '/order/(?P<id>\d+)', array(
			'methods' => 'POST',
			'args' => array( 'paymentid','result','auth','tranid' ),
			'accept_raw' => true,
			'callback' => 'check_tebank_response',
		) );
		register_rest_route( 'tebank_payment_gateway/v1', '/order/(?P<id>\d+)', array(
			'methods' => 'GET',
			'args' => array( 'paymentid','result','auth','tranid' ),
			'accept_raw' => true,
			'callback' => 'check_tebank_response_redirect',
		) );
	} );
	/**
	 * After redirect from server, makes get request and checks order status for proper redirection.
	 *
	 * @param  WP_REST_Request $request contains the order id.
	 */
	function check_tebank_response_redirect( WP_REST_Request $request ) {
		$pg = new WC_Gateway_tebank();
		$parameters = $request->get_params();
		$order_id = $parameters['id'];
		$order = new WC_Order( $order_id );
		if ( $order->has_status( 'processing' ) ) {
			$url = $pg->get_return_url( $order );
		} else {
			$url = get_permalink( $pg->error_url );
			wc_add_notice( __( 'Issue with processing credit card.', 'error' ) );
		}
		//wp_safe_redirect( $url );
		echo 'REDIRECT=' . $url;
		exit;
	}

	/**
	 * Calls the action that process the response.
	 *
	 * @param  WP_REST_Request $request Contains all data needed to process the order.
	 */
	function check_tebank_response( WP_REST_Request $request ) {
		$parameters = $request->get_params();
		if ( ! empty( $parameters ) ) {
			$posted = wp_unslash( $parameters );

			do_action( 'valid_tebank_response', $posted );

			exit;
		}
		echo 'REDIRECT=' . get_site_url();
		exit;
		//wp_safe_redirect( get_site_url() );

	}
	add_action( 'valid_tebank_response', 'valid_response' );

	/**
	 * Process the response from tebank service.
	 *
	 * @param array $posted contains data from tebank service.
	 */
	function valid_response( $posted ) {

		$pg = new WC_Gateway_tebank();

		if ( isset( $posted['Error'] ) ) {
			$err_msg = $posted['Error'];
		} else {
			$err_msg = '';
		}

		if ( isset( $posted['paymentid'] ) ) {
			$paymentid  = $posted['paymentid'];
		} else {
			$paymentid  = '';
		}
		$order_id = $posted['id'];
		$order = new WC_Order( $order_id );
		if ( '' !== $err_msg || 0 === $order_id ) {
			// Check if there is some error.
			$err_text = $posted['ErrorText'];
			if ( $order_id ) {
				$url = get_permalink( $pg->error_url ) . '?order=' . $order_id;
			} else {
				$url = get_permalink( $pg->error_url );
			}
			$order->update_status( 'on-hold', __( 'Error generating payment ID', 'nlb-payment-gateway-for-woocommerce' ) );
		} else {
			if ( isset( $posted['paymentid'] ) ) {
				$paymentid = $posted['paymentid'];
			} else {
				$paymentid = '';
			}
			if ( isset( $posted['result'] ) ) {
				$result = $posted['result'];
			} else {
				$result = '';
			}
			if ( isset( $posted['responsecode'] ) ) {
				$responsecode = $posted['responsecode'];
			} else {
				$responsecode = '';
			}
			if ( isset( $posted['postdate'] ) ) {
				$postdate = $posted['postdate'];
			} else {
				$postdate = '';
			}
			if ( isset( $posted['udf1'] ) ) {
				$udf1 = $posted['udf1'];
			} else {
				$udf1 = '';
			}
			if ( isset( $posted['udf2'] ) ) {
				$udf2 = $posted['udf2'];
			} else {
				$udf2 = '';
			}
			if ( isset( $posted['udf3'] ) ) {

				$udf3 = $posted['udf3'];
			} else {
				$udf3 = '';
			}
			if ( isset( $posted['udf4'] ) ) {
				$udf4 = $posted['udf4'];
			} else {
				$udf4 = '';
			}
			if ( isset( $posted['udf5'] ) ) {
				$udf5 = $posted['udf5'];
			} else {
				$udf5 = '';
			}
			if ( isset( $posted['tranid'] ) ) {
				$tranid = $posted['tranid'];
			} else {
				$tranid = '';
			}
			if ( isset( $posted['auth'] ) ) {
				$auth = $posted['auth'];
			} else {
				$auth = '';
			}
			if ( isset( $posted['trackid'] ) ) {
				$trackid = $posted['trackid'];
			} else {
				$trackid = '';
			}
			if ( isset( $posted['ref'] ) ) {
				$reference = $posted['ref'];
			} else {
				$reference = '';
			}
			if ( isset( $posted['eci'] ) ) {
				$eci = $posted['eci'];
			} else {
				$eci = '';
			}
			error_log( 'nonce ' . $posted['_wpnonce'] );
			add_post_meta( $order_id, 'tranID', $tranid );
			add_post_meta( $order_id, 'trackID', $trackid );
			add_post_meta( $order_id, 'Eci', $eci );
			add_post_meta( $order_id, 'Result', $result );
			add_post_meta( $order_id, 'Auth', $auth );
			add_post_meta( $order_id, 'Ref', $reference );
			add_post_meta( $order_id, 'ResponseCode', $responsecode );
			$tracking_id = get_post_meta( $order_id, 'tracking_id', true );
			if ( ( 'CAPTURED' === $result || 'APPROVED' === $result ) && $trackid === $tracking_id ) {

				$order->update_status( 'processing', __( 'Transaction ', 'nlb-payment-gateway-for-woocommerce' ) . $result );
				if ( 'CAPTURED' === $result ) {
					$order->payment_complete();
				}
				if ( 'APPROVED' === $result && 'yes' === $pg->instant_capture ) {

					action_capture_nlb_payment( $order_id );

				}
				$order->reduce_order_stock();

				$url = $pg->get_return_url( $order );
			} else {
				$url = get_permalink( $pg->error_url );
				$order->update_status( 'cancelled', __( 'Invalid response status', 'nlb-payment-gateway-for-woocommerce' ) );
			}
			echo 'REDIRECT=' . $url;
			exit;

		}
	}

	/**
	 * WooCommerce Tebank Payment Gateway
	 *
	 * Provides Payment gateway for Tebank service.
	 *
	 * @class 		WC_Gateway_tebank
	 * @extends		WC_Payment_Gateway
	 * @version		1.0.0
	 * @package		WooCommerce/Classes/Payment
	 * @author 		Mitko Kockovski
	 */
	class WC_Gateway_tebank extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'tebank_gateway';
			$this->icon               = '';
			$this->has_fields         = false;
			$this->method_title       = __( 'Tebank Payment Gateway', 'nlb-payment-gateway-for-woocommerce' );
			$this->method_description = __( 'Allows your store to use the Tebank Payment method.', 'nlb-payment-gateway-for-woocommerce' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables.
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instant_capture  = $this->get_option( 'instant_capture' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
			$this->terminalalias = $this->get_option( 'terminalalias' );
			$this->tranasction_type = $this->get_option( 'tranasction_type' );
			$this->transaction_language = $this->get_option( 'transaction_language' );
			$this->currency_code = $this->get_option( 'currency_code' );
			$this->error_url = $this->get_option( 'error_url' );
			$this->resource_path = realpath( dirname( __FILE__ ) );

			add_action( 'woocommerce_api_wc_gateway_tebank', array( $this, 'check_tebank_response' ) );

			// Actions.
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

			// Customer Emails.
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}

		/**
		 * Refund transaction
		 *
		 * @param  int   $order_id order id.
		 * @param  float $amount   order amount.
		 * @return bool           status of refund
		 */
		public function process_refund( $order_id, $amount = null, $message = '' ) {
			$pay_id = get_post_meta( $order_id, 'payID', true );
			$tran_id = get_post_meta( $order_id, 'tranID', true );
			$track_id = get_post_meta( $order_id, 'trackID', true );
			$order = wc_get_order( $order_id );

			$payment_pipe = new e24PaymentPipe();
			$payment_pipe->setResourcePath( realpath( dirname( __FILE__ ) ) . '/' );
			$payment_pipe->setAlias( $this->terminalalias );
			$payment_pipe->setAction( 9 );
			$payment_pipe->setAmt( apply_filters( 'nlb_payment_price', $order->get_total() ) );
			$payment_pipe->setPaymentId( $pay_id );
			$payment_pipe->setTranID( $tran_id );
			$payment_pipe->setTrackID( $track_id );
			if ( ! empty( $pay_id ) ) {
				if ( $payment_pipe->performPayment() !== $payment_pipe->SUCCESS ) {
					$order->update_status( 'on-hold', __( 'Unable to perform Payment.', 'nlb-payment-gateway-for-woocommerce' ) );
				} else {
					wc_add_notice( __( 'VOIDED', 'success' ) );
					return array(
						'result' 	=> 'success',
						'redirect'	=> $this->get_return_url( $order ),
					);
				}
			} else {
				wc_add_notice( __( 'Invlaid Pamyment ID', 'error' ) );
				return array(
					'result' 	=> 'error',
				);
			}
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
			$pages_args = array(
				'sort_order' => 'asc',
				'sort_column' => 'post_title',
				'hierarchical' => 1,
				'post_type' => 'page',
				'post_status' => 'publish',
			);
			$pages = get_pages( $pages_args );
			$pages_arr = array();
			foreach ( $pages as $k => $v ) {
				$pages_arr[ $v->ID ] = $v->post_title;
			}
			$this->form_fields = apply_filters( 'wc_tebank_form_fields',
				array(
					'enabled' => array(
						'title'   => __( 'Enable/Disable', 'nlb-payment-gateway-for-woocommerce' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable Tebank Payment', 'nlb-payment-gateway-for-woocommerce' ),
						'default' => 'yes',
					),
					'title' => array(
						'title'       => __( 'Title', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'text',
						'value' 	=> '123',
						'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => __( 'Pay with credit card', 'nlb-payment-gateway-for-woocommerce' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'Payment method description that the customer will see on your checkout.', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => __( 'NLB Credit Card.', 'nlb-payment-gateway-for-woocommerce' ),
						'desc_tip'    => true,
					),
					'instructions' => array(
						'title'       => __( 'Instructions', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'Instructions that will be added to the thank you page and emails.', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
					),
					'terminalalias' => array(
						'title'       => __( 'Terminal Alias', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Terminal ID. You can find this in the tebank console.', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
					),
					'currency_code' => array(
						'title'       => __( 'Currency Code', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Currency code for your account. This is provided by the bank', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
					),
					'tranasction_type' => array(
						'title'       => __( 'Tranasction Type', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'select',
						'options'		=> array(
							'1' => 'Purchase',
							'2' => 'Credit',
							'3' => 'Void Purchase',
							'4' => 'Authorization',
							'5' => 'Capture',
							'6' => 'Void Credit',
							'7' => 'Void Capture',
							'9' => 'Void Authorization',
						),
						'description' => __( 'Choose what transation type your going to use.', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => '1',
						'desc_tip'    => true,
					),
					'instant_capture' => array(
						'title'   => __( 'Capture payment after Authorization', 'nlb-payment-gateway-for-woocommerce' ),
						'type'    => 'checkbox',
						'label'   => __( 'Automatically Capture the payment after Authorization', 'nlb-payment-gateway-for-woocommerce' ),
						'default' => 'no',
					),
					'transaction_language' => array(
						'title'       => __( 'Language', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'select',
						'options'	    => array(
							'SI'	=> 'SI',
							'BS'	=> 'BS - Bosanscina',
							'CZ'	=> 'CZ - Cescina',
							'DE'	=> 'DE - Nemscina',
							'ESP'	=> 'ESP - Spanscina',
							'HR'	=> 'HR - Hrvascina',
							'HU'	=> 'HU - Madzarscina',
							'IT'	=> 'IT - Italijanscina',
							'RUS'	=> 'RUS - Ruscina',
							'SI'	=> 'SI - Slovenscina',
							'SVK'	=> 'SVK - Slovascina',
							'SR'	=> 'SR - Srbscina',
							'MKD'	=> 'MKD - Macedonia',
							'US'	=> 'US - Anglescina',
						),
						'description' => __( 'Credit card form language.', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => '1',
						'desc_tip'    => true,
					),
					'error_url' => array(
						'title'       => __( 'Error Page', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'select',
						'options'		=> $pages_arr,
						'description' => __( 'The page for the invalid processing response.', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => '1',
						'desc_tip'    => true,
					),
					'resource_path' => array(
						'title'       => __( 'Resource Path', 'nlb-payment-gateway-for-woocommerce' ),
						'type'        => 'file',
						'description' => __( 'File for the processing transactions. You can find this file in the console.', 'nlb-payment-gateway-for-woocommerce' ),
						'default'     => '1',
						'desc_tip'    => true,
					),
				)
);
}





		/**
		 * Uploads the resourse.cng file after sucessfull upload on server.
		 */
		public function save_account_details() {
			$target_file = realpath( dirname( __FILE__ ) ) . '/resource.cgn';
			if ( ! empty( $_FILES['woocommerce_tebank_gateway_resource_path']['tmp_name'] ) ) {
				move_uploaded_file( $_FILES['woocommerce_tebank_gateway_resource_path']['tmp_name'], $target_file );
			}
		}

		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wp_kses( wpautop( $this->instructions ), true );
			}
		}

		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order  Contains order data.
		 * @param bool     $sent_to_admin  tells if we notified the admin already.
		 * @param bool     $plain_text  should it send it as html or plain text.
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wp_kses( wpautop( $this->instructions ) . PHP_EOL, true );
			}
		}

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id  Created order ID.
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			$payment_pipe = new e24PaymentPipe();
			$payment_pipe->setResourcePath( realpath( dirname( __FILE__ ) ) . '/' );
			$payment_pipe->setAlias( $this->terminalalias );
			$payment_pipe->setAction( $this->tranasction_type );
			$payment_pipe->setAmt( apply_filters( 'nlb_payment_price', $order->get_total() ) );
			if ( '1' === $payment_pipe->getAction() || '4' === $payment_pipe->getAction() ) {
				$payment_pipe->setCurrency( $this->currency_code );
				$payment_pipe->setLanguage( $this->transaction_language );
				$payment_pipe->setResponseURL( get_site_url( '/' ) . '/wp-json/tebank_payment_gateway/v1/order/' . $order_id );
				$payment_pipe->setErrorURL( get_permalink( $this->error_url ) );
				// This is blank because the processor uses them for mortgage.
				$payment_pipe->setUdf1( '' );
				$payment_pipe->setUdf2( '' );
				$payment_pipe->setUdf3( $order_id );
				$payment_pipe->setUdf4( $order->get_billing_first_name() );
				$payment_pipe->setUdf5( $order->get_billing_last_name() );
				$track_id = md5( uniqid() );
				update_post_meta( $order_id, 'tracking_id', $track_id );
				$payment_pipe->setTrackId( $track_id );
				if ( $payment_pipe->performPaymentInitialization() !== $payment_pipe->SUCCESS ) {
					wc_add_notice( __( 'NOT INITIALIZED' ) , 'error' );
					return array(
						'result' 	=> 'error',
					);
				} else {
					update_post_meta( $order_id, 'payID', $payment_pipe->getPaymentID() );
					$redirect = $payment_pipe->getPaymentPage() . '?PaymentID=' . $payment_pipe->getPaymentId();
					return array(
						'result' 	=> 'success',
						'redirect'	=> $redirect,
					);
				}
			}

			// Mark as on-hold (we're awaiting the payment).
			$order->update_status( 'on-hold', __( 'Something went wrong', 'nlb-payment-gateway-for-woocommerce' ) );

			// Reduce stock levels.

			return array(
				'result' 	=> 'error',
				'redirect'	=> get_permalink( $this->error_url ),
			);
		}
	} // end \WC_Gateway_Tebank class
}
