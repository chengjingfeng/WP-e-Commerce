<?php
class WPSC_Payment_Gateway_WorldPay extends WPSC_Payment_Gateway {

	private $endpoints = array(
		'sandbox' => 'https://gwapi.demo.securenet.com/api/',
		'production' => 'https://gwapi.securenet.com/api/',
	);
	
	private $auth;
	private $payment_capture;
	private $order_handler;
	private $secure_net_id;
	private $secure_key;
	private $public_key;
	private $endpoint;

	

	/**
	 * Constructor of WorldPay Payment Gateway
	 *
	 * @access public
	 * @since 3.9
	 */
	public function __construct() {

		parent::__construct();

		$this->title = __( 'WorldPay Payment Gateway', 'wp-e-commerce' );
		$this->supports = array( 'default_credit_card_form', 'tev1' );

		$this->order_handler	= WPSC_WorldPay_Payments_Order_Handler::get_instance( $this );
		
		// Define user set variables
		$this->secure_net_id	= $this->setting->get( 'secure_net_id' );
		$this->secure_key  		= $this->setting->get( 'secure_key' );
		$this->public_key  		= $this->setting->get( 'public_key' );
		$this->sandbox			= $this->setting->get( 'sandbox_mode' ) == '1' ? true : false;
		$this->endpoint			= $this->sandbox ? $this->endpoints['sandbox'] : $this->endpoints['production'];
		$this->payment_capture 	= $this->setting->get( 'payment_capture' ) !== null ? $this->setting->get( 'payment_capture' ) : '';
		$this->auth				= 'Basic ' . base64_encode( $this->setting->get( 'secure_net_id' ) . ':' . $this->setting->get( 'secure_key' ) );
	}

	/**
	 * Settings Form Template
	 *
	 * @since 3.9
	 */
	public function setup_form() {
?>
		<!-- Account Credentials -->
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Account Credentials', 'wp-e-commerce' ); ?></h4>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-net-id"><?php _e( 'SecureNet ID', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'secure_net_id' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'secure_net_id' ) ); ?>" id="wpsc-worldpay-secure-net-id" />
				<br><span class="small description"><?php _e( 'The SecureNet ID can be obtained from the email that you should have received during the sign-up process.', 'wp-e-commerce' ); ?></span>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Secure Key', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'secure_key' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'secure_key' ) ); ?>" id="wpsc-worldpay-secure-key" />
				<br><span class="small description"><?php _e( 'You can obtain the Secure Key by signing into the Virtual Terminal with the login credentials that you were emailed to you during the sign-up process. You will then need to navigate to Settings and click on the Obtain Secure Key link.', 'wp-e-commerce' ); ?></span>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-public-key"><?php _e( 'Public Key', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'public_key' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'public_key' ) ); ?>" id="wpsc-worldpay-public-key" />
				<br><span class="small description"><?php _e( 'You can obtain the Public Key by signing into the Virtual Terminal. You will then need to navigate to Settings and click on the Obtain Public Key link.', 'wp-e-commerce' ); ?></span>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-payment-capture"><?php _e( 'Payment Capture', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<select id="wpsc-worldpay-payment-capture" name="<?php echo esc_attr( $this->setting->get_field_name( 'payment_capture' ) ); ?>">
					<option value='' <?php selected( '', $this->setting->get( 'payment_capture' ) ); ?>><?php _e( 'Authorize and capture the payment when the order is placed.', 'wp-e-commerce' )?></option>
					<option value='authorize' <?php selected( 'authorize', $this->setting->get( 'payment_capture' ) ); ?>><?php _e( 'Authorize the payment when the order is placed.', 'wp-e-commerce' )?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'Sandbox Mode', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'sandbox_mode' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox_mode' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wp-e-commerce' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'sandbox_mode' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox_mode' ) ); ?>" value="0" /> <?php _e( 'No', 'wp-e-commerce' ); ?></label>
			</td>
		</tr>
		<!-- Error Logging -->
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Error Logging', 'wp-e-commerce' ); ?></h4>
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'Enable Debugging', 'wp-e-commerce' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'debugging' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'debugging' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wp-e-commerce' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'debugging' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'debugging' ) ); ?>" value="0" /> <?php _e( 'No', 'wp-e-commerce' ); ?></label>
			</td>
		</tr>
<?php
	}

	/**
	 * Add scripts
	 */
	public function scripts() {

		$jsfile = $this->sandbox ? 'PayOSDev.js' : 'PayOS.js';
		wp_enqueue_script( 'worldpay_payos', WPSC_MERCHANT_V3_SDKS_URL . '/worldpay/assets/js/'.$jsfile, '', WPSC_VERSION );
	}

	public function head_script() {
		?>
		<script type='text/javascript'>

			jQuery(document).ready(function($) {
				$( ".wpsc_checkout_forms" ).submit(function( event ) {
					
					event.preventDefault();
					
					//jQuery( 'input[type="submit"]', this ).prop( { 'disabled': true } );

					var response = tokenizeCard(
						{
							"publicKey": '<?php echo $this->public_key; ?>',
							"card": {
								"number": document.getElementById('card_number').value,
								"cvv": document.getElementById('card_code').value,
							"expirationDate": document.getElementById('card_expiry_month').value + '/' + document.getElementById('card_expiry_year').value,
								"firstName": $( 'input[title="billingfirstname"]' ).val(),
								"lastName": $( 'input[title="billinglastname"]' ).val(),
								"address": {
									"zip": $( 'input[title="billingpostcode"]' ).val()
								}
							},
							"addToVault": false,
							"developerApplication": {
								"developerId": 12345678,
								"version": '1.2'

							}
						}
					).done(function (result) {

						var responseObj = $.parseJSON(JSON.stringify(result));

						if (responseObj.success) {

							var form$ = jQuery('.wpsc_checkout_forms');

							var token = responseObj.token;

							$("#worldpay_pay_token").val(token);
							// and submit
							form$.get(0).submit();

							// do something with responseObj.token
						} else {
							alert("token was not created");
							// do something with responseObj.message

						}

					}).fail(function ( response ) {
						jQuery( 'input[type="submit"]', this ).prop( { 'disabled': false } );
							console.log( response )
						// an error occurred
					});
				});

			});

		</script>
		<?php
	}
	
	public function te_v1_insert_hidden_field() {
		echo '<input type="hidden" id="worldpay_pay_token" name="worldpay_pay_token" value="" />';
	}

	public function init() {

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'wp_head'           , array( $this, 'head_script' ) );

		add_action( 'wpsc_inside_shopping_cart', array( $this, 'te_v1_insert_hidden_field' ) );
		
		add_filter( 'wpsc_gateway_checkout_form_worldpay', array( $this, 'payment_fields' ) );
		//add_filter( 'wpsc_get_checkout_payment_method_form_args', array( $this, 'te_v2_show_payment_fields' ) );
	}

	public function te_v2_show_payment_fields( $args ) {

		$default = '<div class="wpsc-form-actions">';
		ob_start();

		$this->payment_fields();
		$fields = ob_get_clean();

		$args['before_form_actions'] = $fields . $default;

		return $args;
	}

	public function process() {

		$order = $this->purchase_log;
		
		$status = $this->payment_capture === '' ? WPSC_Purchase_Log::ACCEPTED_PAYMENT : WPSC_Purchase_Log::ORDER_RECEIVED;
		
		$order->set( 'processed', $status )->save();
		
		$card_token = isset( $_POST['worldpay_pay_token'] ) ? sanitize_text_field( $_POST['worldpay_pay_token'] ) : '';
	
		$this->order_handler->set_purchase_log( $order->get( 'id' ) );
		
		switch ( $this->payment_capture ) {
			case 'authorize' :

				// Authorize only
				$result = $this->authorize_payment( $card_token );

				if ( $result ) {
					// Mark as on-hold
					$order->set( 'worldpay-status', __( 'WorldPay order opened. Capture the payment below. Authorized payments must be captured within 7 days.', 'wp-e-commerce' ) )->save();

				} else {
					$order->set( 'processed', WPSC_Purchase_Log::PAYMENT_DECLINED )->save();
					$order->set( 'worldpay-status', __( 'Could not authorize WorldPay payment.', 'wp-e-commerce' ) )->save();

					//$this->handle_declined_transaction( $order );
				}

			break;
			default:
					
				// Capture
				$result = $this->capture_payment( $card_token );

				if ( $result ) {
					// Payment complete
					$order->set( 'worldpay-status', __( 'WorldPay order completed.  Funds have been authorized and captured.', 'wp-e-commerce' ) );
				} else {
					$order->set( 'processed', WPSC_Purchase_Log::PAYMENT_DECLINED );
					$order->set( 'worldpay-status', __( 'Could not authorize WorldPay payment.', 'wp-e-commerce' ) );

					//$this->handle_declined_transaction( $order );
				}	
				
			break;
		}
		
		$order->save();
		$this->go_to_transaction_results();

	}
	
	public function capture_payment( $token ) {

		if ( $this->purchase_log->get( 'gateway' ) == 'worldpay' ) {
			
			$order = $this->purchase_log;
			
			$params = array (
				'amount'	=> $order->get( 'totalprice' ),
				'orderId'	=> $order->get( 'id' ),
				'invoiceNumber' => $order->get( 'sessionid' ),
				"addToVault" => false,
				"paymentVaultToken" => array(
					"paymentMethodId" => $token,
					"publicKey" => $this->public_key
				),
			);

			$response = $this->execute( 'Payments/Charge', $params );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}
			
			if ( isset( $response['ResponseBody']->transaction->transactionId ) ) {
				$transaction_id = $response['ResponseBody']->transaction->transactionId;
				$auth_code = $response['ResponseBody']->transaction->authorizationCode;
			} else {
				return false;
			}
			
			// Store transaction ID and Auth code in the order
			$order->set( 'wp_transactionId', $transaction_id )->save();
			$order->set( 'wp_order_status', 'Completed' )->save();
			$order->set( 'wp_authcode', $auth_code )->save();
				
			return true;
		}
		
		return false;
	}

	public function authorize_payment( $token ) {

		if ( $this->purchase_log->get( 'gateway' ) == 'worldpay' ) {
			
			$order = $this->purchase_log;
			
			$params = array (
				'amount'	=> $order->get( 'totalprice' ),
				'orderId'	=> $order->get( 'id' ),
				'invoiceNumber' => $order->get( 'sessionid' ),
				"addToVault" => false,
				"paymentVaultToken" => array(
					"paymentMethodId" => $token,
					"publicKey" => $this->public_key
				),
			);

			$response = $this->execute( 'Payments/Authorize', $params );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}
			
			if ( isset( $response['ResponseBody']->transaction->transactionId ) ) {
				$transaction_id = $response['ResponseBody']->transaction->transactionId;
				$auth_code = $response['ResponseBody']->transaction->authorizationCode;
			} else {
				return false;
			}
			
			// Store transaction ID and Auth code in the order
			$order->set( 'wp_transactionId', $transaction_id )->save();
			$order->set( 'wp_order_status', 'Open' )->save();
			$order->set( 'wp_authcode', $auth_code )->save();
				
			return true;
		}
		
		return false;
	}
	
	public function execute( $endpoint, $params = array(), $type = 'POST' ) {
       
	   // where we make the API petition
        $endpoint = $this->endpoint . $endpoint;
        
		if ( ! is_null( $params ) ) {
			$params += array(
				"developerApplication" => array(
					"developerId" => 12345678,
					"version" => "1.2"
				),
				"extendedInformation" => array(
					"typeOfGoods" => "PHYSICAL"
				),
			);			
		}
			
		$data = json_encode( $params );
		
		$args = array (
			'timeout' => 15,
			'headers' => array(
				'Authorization' => $this->auth,
				'Content-Type' => 'application/json',
			),
			'sslverify' => false,
			'body' => $data,
		);
  	
		$request  = $type == 'GET' ? wp_safe_remote_get( $endpoint, $args ) : wp_safe_remote_post( $endpoint, $args );
        $response = wp_remote_retrieve_body( $request );
		
		if ( ! is_wp_error( $request ) ) {

			$response_object = array();
			$response_object['ResponseBody'] = json_decode( $response );
			$response_object['Status']       = wp_remote_retrieve_response_code( $request );

			$request = $response_object;
		}
		
		return $request;
    }

}

class WPSC_WorldPay_Payments_Order_Handler {
	
	private static $instance;
	private $log;
	private $gateway;
	private $doing_ipn = false;

	public function __construct( &$gateway ) {

		$this->log     = $gateway->purchase_log;
		$this->gateway = $gateway;

		$this->init();

		return $this;
	}

	/**
	 * Constructor
	 */
	public function init() {
		add_action( 'wpsc_purchlogitem_metabox_start', array( $this, 'meta_box' ), 8 );
		add_action( 'wp_ajax_worldpay_order_action'    , array( $this, 'order_actions' ) );

	}

	public static function get_instance( $gateway ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new WPSC_WorldPay_Payments_Order_Handler( $gateway );
		}

		return self::$instance;
	}

	public function set_purchase_log( $id ) {
		$this->log = new WPSC_Purchase_Log( $id );
	}
	
	/**
	 * Perform order actions for amazon
	 */
	public function order_actions() {
		check_ajax_referer( 'wp_order_action', 'security' );

		$order_id = absint( $_POST['order_id'] );
		$id       = isset( $_POST['worldpay_id'] ) ? sanitize_text_field( $_POST['worldpay_id'] ) : '';
		$action   = sanitize_title( $_POST['worldpay_action'] );

		$this->set_purchase_log( $order_id );

		switch ( $action ) {
			case 'capture' :
				//Capture an AUTH
				$this->capture_payment($id);
			break;
			
			case 'void' :
				// void capture or auth before settled
				$this->void_payment( $id );
			break;
			
			case 'refund' :
				// refund a settled payment
				$this->refund_payment( $id );
			break;
		}

		echo json_encode( array( 'action' => $action, 'order_id' => $order_id, 'worldpay_id' => $id ) );

		die();
	}
	
	/**
	 * meta_box function.
	 *
	 * @access public
	 * @return void
	 */
	function meta_box( $log_id ) {
		$this->set_purchase_log( $log_id );

		$gateway = $this->log->get( 'gateway' );

		if ( $gateway == 'worldpay' ) {
			$this->authorization_box();
		}
	}

	/**
	 * pre_auth_box function.
	 *
	 * @access public
	 * @return void
	 */
	public function authorization_box() {
		
		$actions  = array();
		$order_id = $this->log->get( 'id' );

		// Get ids
		$wp_transaction_id 	= $this->log->get( 'wp_transactionId' );
		
		$order_info = $this->refresh_transaction_info( $wp_transaction_id );
	
		$wp_auth_code		= $this->log->get( 'wp_authcode' );
		$wp_order_status	= $this->log->get( 'wp_order_status' );
		
		?>
		
		<div class="metabox-holder">
			<div id="wpsc-worldpay-payments" class="postbox">
				<h3 class='hndle'><?php _e( 'WorldPay Payments' , 'wp-e-commerce' ); ?></h3>
				<div class='inside'>
					<p><?php
							_e( 'Current status: ', 'wp-e-commerce' );
							echo wp_kses_data( $this->log->get( 'worldpay-status' ) );
						?>
					</p>
					<p><?php
							_e( 'Transaction ID: ', 'wp-e-commerce' );
							echo wp_kses_data( $wp_transaction_id );
						?>
					</p>
		<?php
		
		//Show actions based on order status
		switch ( $wp_order_status ) {
			case 'Open' :
				//Order is only authorized and still not captured/voided
				$actions['capture'] = array(
					'id' => $wp_transaction_id,
					'button' => __( 'Capture funds', 'wp-e-commerce' )
				);
				
				//
				if ( ! $order_info['settled'] ) {
					//Void
					$actions['void'] = array(
						'id' => $wp_transaction_id,
						'button' => __( 'Void order', 'wp-e-commerce' )
					);					
				}
				
				break;
			case 'Completed' :
				//Order has been captured or its a direct payment
				if ( $order_info['settled'] ) {
					//Refund
					$actions['refund'] = array(
						'id' => $wp_transaction_id,
						'button' => __( 'Refund order', 'wp-e-commerce' )
					);
				} else {
					//Void
					$actions['void'] = array(
						'id' => $wp_transaction_id,
						'button' => __( 'Void order', 'wp-e-commerce' )
					);					
				}
				
			break;
			case 'Refunded' :
			case 'Voided' :
			
			break;
		}			
		
		if ( ! empty( $actions ) ) {

			echo '<p class="buttons">';

			foreach ( $actions as $action_name => $action ) {
				echo '<a href="#" class="button" data-action="' . $action_name . '" data-id="' . $action['id'] . '">' . $action['button'] . '</a> ';
			}

			echo '</p>';

		}		
		?>
		<script type="text/javascript">
		jQuery( document ).ready( function( $ ) {
			$('#wpsc-worldpay-payments').on( 'click', 'a.button, a.refresh', function( e ) {
				var $this = $( this );
				e.preventDefault();

				var data = {
					action: 		'worldpay_order_action',
					security: 		'<?php echo wp_create_nonce( "wp_order_action" ); ?>',
					order_id: 		'<?php echo $order_id; ?>',
					worldpay_action: 	$this.data('action'),
					worldpay_id: 		$this.data('id'),
					worldpay_refund_amount: jQuery('.worldpay_refund_amount').val(),
				};

				// Ajax action
				$.post( ajaxurl, data, function( result ) {
						location.reload();
					}, 'json' );

				return false;
			});
		} );

		</script>
		</div>
		</div>
		</div>
		<?php
	}

    /**
     * Get the order status from API
     *
     * @param  string $transaction_id
     */	
	public function refresh_transaction_info( $transaction_id ) {
		
		if ( $this->log->get( 'gateway' ) == 'worldpay' ) {
			
			$response = $this->gateway->execute( 'transactions/'. $transaction_id, null, 'GET' );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}
			
			$response_object = array();
			$response_object['trans_type'] 	= $response['ResponseBody']->transactions[0]->transactionType;
			$response_object['settled'] 	= isset( $response['ResponseBody']->transactions[0]->settlementData ) ? true : false;

			//Recheck status and update if required
			switch ( $response_object['trans_type'] ) {
				case 'AUTH_ONLY' :
					$this->log->set( 'wp_order_status', 'Open' )->save();
				break;
				
				case 'VOID' :
					$this->log->set( 'wp_order_status', 'Voided' )->save();
				break;
				
				case 'REFUND' :
				case 'CREDIT' :
					$this->log->set( 'wp_order_status', 'Refunded' )->save();
				break;				
				
				case 'AUTH_CAPTURE' :
				case 'PRIOR_AUTH_CAPTURE' :
					$this->log->set( 'wp_order_status', 'Completed' )->save();
				break;
			}
			
			return $response_object;
		}
	}
	
	
    /**
     * Void auth/capture
     *
     * @param  string $transaction_id
     */
    public function void_payment( $transaction_id ) {

		if ( $this->log->get( 'gateway' ) == 'worldpay' ) {
			
			$params = array(
				'amount'		=>  $this->log->get( 'totalprice' ),
				'transactionId' => $transaction_id,
			);
			
			$response = $this->gateway->execute( 'Payments/Void', $params );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}
			
			$this->log->set( 'wp_order_status', 'Voided' )->save();
			$this->log->set( 'worldpay-status', sprintf( __( 'Authorization voided (Auth ID: %s)', 'wp-e-commerce' ), $response['ResponseBody']->transaction->authorizationCode ) )->save();
			$this->log->set( 'processed', WPSC_Purchase_Log::INCOMPLETE_SALE )->save();
		}
    }
	
    /**
     * Refund payment
     *
     * @param  string $transaction_id
     */
    public function refund_payment( $transaction_id ) {

		if ( $this->log->get( 'gateway' ) == 'worldpay' ) {
			
			$params = array(
				'amount'		=> $this->log->get( 'totalprice' ),
				'transactionId' => $transaction_id,
				
			);
			
			$response = $this->gateway->execute( 'Payments/Refund', $params );
		
			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}
			
			$this->log->set( 'wp_order_status', 'Refunded' )->save();
			
			$this->log->set( 'worldpay-status', sprintf( __( 'Refunded (Auth ID: %s)', 'wp-e-commerce' ), $response['ResponseBody']->transaction->authorizationCode ) )->save();
			$this->log->set( 'processed', WPSC_Purchase_Log::REFUNDED )->save();
		}
    }
	
    /**
     * Capture authorized payment
     *
     * @param  string $transaction_id
     */
    public function capture_payment( $transaction_id ) {

		if ( $this->log->get( 'gateway' ) == 'worldpay' ) {
			
			$params = array(
				'amount'		=>  $this->log->get( 'totalprice' ),
				'transactionId' => $transaction_id,
			);
			
			$response = $this->gateway->execute( 'Payments/Capture', $params );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}
			
			$this->log->set( 'wp_order_status', 'Completed' )->save();
			
			$this->log->set( 'worldpay-status', sprintf( __( 'Authorization Captured (Auth ID: %s)', 'wp-e-commerce' ), $response['ResponseBody']->transaction->authorizationCode ) )->save();
			$this->log->set( 'processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT )->save();
		}
    }
}