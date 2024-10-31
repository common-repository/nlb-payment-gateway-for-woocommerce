<?php

use PaymentGateway\Client\Client;
use PaymentGateway\Client\Data\Customer;
use PaymentGateway\Client\Transaction\Debit;
use PaymentGateway\Client\Transaction\Result;


class Woocommerce_Nlb_Payment_Bankar extends WC_Payment_Gateway {

	private $nlb_username;
	private $nlb_password;
	private $nlb_api_key;
	private $nlb_shared_secret;
	private $error_url;

	public function __construct() {

		$this->id                 = 'nlb-payment-bankar';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'Bankar payment gateway ( nlb new )', 'nlb-payment-bankar' );
		$this->method_description = __( 'Allows your store to use the Bankar Payment method.', 'nlb-payment-bankar' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->nlb_username = $this->get_option( 'nlb_username' );
		$this->nlb_password = $this->get_option( 'nlb_password' );
		$this->nlb_api_key = $this->get_option( 'nlb_api_key' );
		$this->nlb_shared_secret = $this->get_option( 'nlb_shared_secret' );
		$this->error_url = $this->get_option( 'error_url' );

		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'check_ipn_response' ) );

	}

	public function check_ipn_response() {
		$log = new WC_Logger();
		$log_entry = print_r( $_REQUEST, true );
		$log->add( 'new-woocommerce-log-name', $log_entry );
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
		$this->form_fields = apply_filters(
			'wc_bankar_form_fields',
			array(
				'enabled'           => array(
					'title'   => __( 'Enable/Disable', 'nlb-payment-bankar' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Tebank Payment', 'nlb-payment-bankar' ),
					'default' => 'yes',
				),
				'title'             => array(
					'title'       => __( 'Title', 'nlb-payment-bankar' ),
					'type'        => 'text',
					'value'       => 'NLB new payment method',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'nlb-payment-bankar' ),
					'default'     => __( 'Pay with credit card', 'nlb-payment-bankar' ),
					'desc_tip'    => true,
				),
				'description'       => array(
					'title'       => __( 'Description', 'nlb-payment-bankar' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'nlb-payment-bankar' ),
					'default'     => __( 'NLB Credit Card.', 'nlb-payment-bankar' ),
					'desc_tip'    => true,
				),
				'nlb_username'      => array(
					'title'       => __( 'Username', 'nlb-payment-bankar' ),
					'type'        => 'text',
					'description' => __( 'The username provided by NLB.', 'nlb-payment-bankar' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'nlb_password'      => array(
					'title'       => __( 'Password', 'nlb-payment-bankar' ),
					'type'        => 'text',
					'description' => __( 'The Password provided by NLB.', 'nlb-payment-bankar' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'nlb_api_key'       => array(
					'title'       => __( 'API KEY', 'nlb-payment-bankar' ),
					'type'        => 'text',
					'description' => __( 'The API KEY provided by NLB.', 'nlb-payment-bankar' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'nlb_shared_secret' => array(
					'title'       => __( 'Shared Secret', 'nlb-payment-bankar' ),
					'type'        => 'text',
					'description' => __( 'The Shared Secret provided by NLB.', 'nlb-payment-bankar' ),
					'default'     => '',
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
			)
		);
	}


	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		$client = new Client( $this->nlb_username, $this->nlb_password, $this->nlb_api_key, $this->nlb_shared_secret );

		$customer = new Customer();
		$customer->setBillingCountry( $order->get_billing_country() )
				 ->setEmail( $order->get_billing_email() );

		$debit = new Debit();

		// define your transaction ID: e.g. 'myId-'.date('Y-m-d').'-'.uniqid()
		$merchantTransactionId = $order_id; // must be unique

		$debit->setTransactionId( $merchantTransactionId )
			  ->setSuccessUrl( $this->get_return_url( $order ) )
			  ->setCancelUrl( wc_get_checkout_url() )
			  ->setCallbackUrl( get_site_url() . '?wc-api=' . $this->id )
			  ->setAmount( number_format( $order->get_total(), 2, '.', '' ) )
			  ->setCurrency( get_woocommerce_currency_symbol() )
			  ->setCustomer( $customer );

		// send the transaction
		$result = $client->debit( $debit );

		// now handle the result
		if ( $result->isSuccess() ) {
			//act depending on $result->getReturnType()

			$gatewayReferenceId = $result->getReferenceId(); //store it in your database

			if ( $result->getReturnType() == Result::RETURN_TYPE_ERROR ) {
				//error handling
				$errors = $result->getErrors();
				wc_add_notice( __( $errors ), 'error' );
				return array(
					'result' => 'error',
				);
				//cancelCart();

			} elseif ( $result->getReturnType() == Result::RETURN_TYPE_REDIRECT ) {
				//redirect the user
				return array(
					'result'   => 'success',
					'redirect' => $result->getRedirectUrl(),
				);

			} elseif ( $result->getReturnType() == Result::RETURN_TYPE_PENDING ) {
				//payment is pending, wait for callback to complete

				//setCartToPending();

			} elseif ( $result->getReturnType() == Result::RETURN_TYPE_FINISHED ) {
				//payment is finished, update your cart/payment transaction

				//finishCart();
			}
		}
		return array(
			'result' => 'error',
		);
		// return parent::process_payment( $order_id ); // TODO: Change the autogenerated stub
	}
}
