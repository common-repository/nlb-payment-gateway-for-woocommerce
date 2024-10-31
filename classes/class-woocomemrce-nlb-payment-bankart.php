<?php

use PaymentGateway\Client\Client;
use PaymentGateway\Client\Data\Customer;
use PaymentGateway\Client\Transaction\Debit;
use PaymentGateway\Client\Transaction\Result;
use PaymentGateway\Client\Callback\Result as CallResult;
class Woocommerce_Nlb_Payment_Bankart extends WC_Payment_Gateway {

	private $nlb_username;
	private $sandbox;
	private $nlb_password;
	private $nlb_api_key;
	private $nlb_shared_secret;
	private $error_url;

	public function __construct() {

		$this->id                 = 'nlb-payment-bankart';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'BankArt Payment Gateway', 'nlb-payment-bankart' );
		$this->method_description = __( 'Allows your store to use the BankArt Payment method.', 'nlb-payment-bankart' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title             = $this->get_option( 'title' );
		$this->sadnbox           = $this->get_option( 'sadnbox' ) === 'yes' ? 1 : 0;
		$this->description       = $this->get_option( 'description' );
		$this->nlb_username      = $this->get_option( 'nlb_username' );
		$this->nlb_password      = $this->get_option( 'nlb_password' );
		$this->nlb_api_key       = $this->get_option( 'nlb_api_key' );
		$this->nlb_shared_secret = $this->get_option( 'nlb_shared_secret' );
		$this->error_url         = $this->get_option( 'error_url' );
		if( $this->sandbox ) {
			define( 'NLB_PAYMENT_URL', 'https://gateway.bankart.si/' );
		} else {
			define( 'NLB_PAYMENT_URL', 'https://gateway.bankart.si/' );
		}
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'check_ipn_response' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_head', array( $this, 'handle_errors' ), 1 );
	}

	public function handle_errors() {
		if ( isset( $_GET[ $this->id ] ) ) {
			switch ( $_GET[ $this->id ] ) {
				case 'error':
					wc_add_notice( __( 'There has been an error processing your request.', true ), 'error' );
					break;
				case 'cancel':
					wc_add_notice( __( 'Order cancelled.' ), 'error' );
					break;

			}
		}
	}

	public function check_ipn_response() {

		$client = new Client( $this->nlb_username, $this->nlb_password, $this->nlb_api_key, $this->nlb_shared_secret );

		$client->validateCallbackWithGlobals();
		$callbackResult = $client->readCallback( file_get_contents( 'php://input' ) );

		$gatewayTransactionId = $callbackResult->getReferenceId();
		$transaction_data     = explode( '-', $callbackResult->getTransactionId() );
		$order                = wc_get_order( $transaction_data[0] );

		if ( $callbackResult->getResult() == CallResult::RESULT_OK ) {
			// result OK.
			$order->update_status( 'processing', __( 'Payment captured.', 'nlb-payment-bankart' ) . $result );
			$order->payment_complete( $gatewayTransactionId );
		} elseif ( $callbackResult->getResult() == CallResult::RESULT_ERROR ) {
			// there has been an error.
			$order->update_status( 'on-hold', __( 'Error with payment' ) );
		}
		echo 'OK';
		die;
	}


	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$pages_args = array(
			'sort_order'   => 'asc',
			'sort_column'  => 'post_title',
			'hierarchical' => 1,
			'post_type'    => 'page',
			'post_status'  => 'publish',
		);
		$pages      = get_pages( $pages_args );
		$pages_arr  = array();
		foreach ( $pages as $k => $v ) {
			$pages_arr[ $v->ID ] = $v->post_title;
		}
		$this->form_fields = apply_filters(
			'wc_bankart_form_fields',
			array(
				'enabled'           => array(
					'title'   => __( 'Enable/Disable', 'nlb-payment-bankart' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable BankArt Payment', 'nlb-payment-bankart' ),
					'default' => 'yes',
				),
				'sandbox' => array(
					'title'   => __( 'Enable/Disable', 'nlb-payment-bankart' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable BankArt Payment', 'nlb-payment-bankart' ),
					'default' => 'yes',
				),
				'title'             => array(
					'title'       => __( 'Title', 'nlb-payment-bankart' ),
					'type'        => 'text',
					'value'       => 'NLB new payment method',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'nlb-payment-bankart' ),
					'default'     => __( 'Pay with credit card', 'nlb-payment-bankart' ),
					'desc_tip'    => true,
				),
				'description'       => array(
					'title'       => __( 'Description', 'nlb-payment-bankart' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'nlb-payment-bankart' ),
					'default'     => __( 'NLB Credit Card.', 'nlb-payment-bankart' ),
					'desc_tip'    => true,
				),
				'nlb_username'      => array(
					'title'       => __( 'Username', 'nlb-payment-bankart' ),
					'type'        => 'text',
					'description' => __( 'The username provided by NLB.', 'nlb-payment-bankart' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'nlb_password'      => array(
					'title'       => __( 'Password', 'nlb-payment-bankart' ),
					'type'        => 'text',
					'description' => __( 'The Password provided by NLB.', 'nlb-payment-bankart' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'nlb_api_key'       => array(
					'title'       => __( 'API KEY', 'nlb-payment-bankart' ),
					'type'        => 'text',
					'description' => __( 'The API KEY provided by NLB.', 'nlb-payment-bankart' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'nlb_shared_secret' => array(
					'title'       => __( 'Shared Secret', 'nlb-payment-bankart' ),
					'type'        => 'text',
					'description' => __( 'The Shared Secret provided by NLB.', 'nlb-payment-bankart' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'error_url'         => array(
					'title'       => __( 'Error Page', 'nlb-payment-bankart' ),
					'type'        => 'select',
					'options'     => $pages_arr,
					'description' => __( 'The page for the invalid processing response.', 'nlb-payment-bankart' ),
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
		         ->setEmail( $order->get_billing_email() )
		         ->setBillingAddress1( $order->get_billing_address_1() )
		         ->setBillingAddress2( $order->get_billing_address_2() )
		         ->setbillingCity( $order->get_billing_city() )
		         ->setBillingPostcode( $order->get_billing_postcode() );

		$debit = new Debit();

		$merchantTransactionId = $order_id . '-' . wp_rand( 1000, 9999 ); // must be unique

		$debit->setTransactionId( $merchantTransactionId )
		      ->setSuccessUrl( $this->get_return_url( $order ) )
		      ->setCancelUrl( wc_get_checkout_url() . '?' . $this->id . '=cancel' )
		      ->setErrorUrl( wc_get_checkout_url() . '?' . $this->id . '=error' )
		      ->setCallbackUrl( get_site_url() . '/wc-api/' . $this->id )
		      ->setAmount( number_format( $order->get_total(), 2, '.', '' ) )
		      ->setCurrency( get_woocommerce_currency() )
		      ->setCustomer( $customer );

		// send the transaction
		$result = $client->debit( $debit );
		// now handle the result
		if ( $result->isSuccess() ) {
			$gatewayReferenceId = $result->getReferenceId(); //store it in your database
			if ( $result->getReturnType() == Result::RETURN_TYPE_ERROR ) {
				//error handling
				$errors = $result->getErrors();
				wc_add_notice( __( $errors ), 'error' );
				return array(
					'message' => $errors,
					'result'  => 'error',
				);
			} elseif ( $result->getReturnType() == Result::RETURN_TYPE_REDIRECT ) {
				//redirect the user
				return array(
					'result'   => 'success',
					'redirect' => $result->getRedirectUrl(),
				);
			}
		}
		return array(
			'message' => 'Issue processing checkout.',
			'result'  => 'error',
		);
	}
}
