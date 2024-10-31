<?php
/*
 * Plugin Name: Onepipe Payment Gateway for Easy Digital Downloads
 * Plugin URL: https://onepipe.io
 * Description: Accept payments in your Easy Digital Downloads from multiple banks and fintechs using OnePipe.
 * Version: 1.0.0
 * Author: OnePipe
 * Author URI: https://tormuto.com
 * License: GPLv2 or later
 * WC tested up to: 5.7
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'Easy_Digital_Downloads' ) ) return;

add_action( 'edd_after_cc_fields', 'onepipe_edd_add_errors', 999 );
function onepipe_edd_add_errors() {
    echo '<div id="onepipe-payment-errors"></div>';
}

add_action( 'edd_onepipe_cc_form', '__return_false' );

define( 'onepipe_eddURL', plugin_dir_url( __FILE__ ) );
define( 'onepipe_eddVERSION', '1.0.0' );


add_filter( 'edd_settings_sections_gateways', 'onepipe_edd_settings_section' );
function onepipe_edd_settings_section( $sections ) {
    $sections['edd-onepipe'] = 'Onepipe';
    return $sections;
}

add_filter( 'edd_settings_gateways', 'onepipe_edd_settings', 1 );
function onepipe_edd_settings( $settings ) {
	$temp=array(
	  "Successful"=>array("bank.account","voucher","airtime","custom"),
	  "Failed"=>array("bank.account","voucher","airtime","custom")
	);
	$temp = json_encode($temp,JSON_PRETTY_PRINT);
    $temp=array(
		'onepipe_title' => array(
			'title' => __( 'Title', 'woothemes' ),
			'type' => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
			'default' => __( 'Pay via OnePipe', 'woothemes' )
		),
		'onepipe_environment' => array(
			'title' => __('Mock Mode', 'woo-onepipe'),
			'type' => 'select',
			'description' => __('Specifies if this is for real transactions or testing.', 'woo-onepipe'),
			'default' => 'production',
			'desc_tip' => false,
			'options' => array(
				'production' => __('Live (Production)', 'woo-onepipe'),
				'sandbox' => __('Inspect/Test (Sandbox)', 'woo-onepipe'),
			),
		),
		'onepipe_api_key'           => array(
			'title'       => __( 'API Key', 'woo-onepipe' ),
			'type'        => 'text',
			'desc_tip'    => false,
			'description' => __( 'Get your API Key from your OnePipe Merchant Dashboard.', 'woocommerce' ),
		),
		
		'onepipe_client_secret'           => array(
			'title'       => __( 'Client Secret', 'woo-onepipe' ),
			'type'        => 'password',
			'desc_tip'    => false,
			'description' => __( 'Get your Client Secret key from your OnePipe Merchant Dashboard.' ,'woocommerce' ),
		),			
		
		'onepipe_close_popup_timeout'           => array(
			'title'       => __( 'Close-popup Timeout', 'woo-onepipe' ),
			'type'        => 'number',
			'desc_tip'    => false,
			'description' => __( 'Number of seconds, before the popup closes automatically after transaction.' ,'woocommerce' ),
			'default'	=> 5
		),
		'onepipe_close_popup_status'           => array(
			'title'       => __( 'Close-popup Status', 'woo-onepipe' ),
			'type'        => 'textarea',
			'desc_tip'    => false,
			'description' => __( "Optional.  Supply value in a valid JSON format. E.G:
				<pre>$temp</pre>" ),
		),
		'onepipe_default_view_option'           => array(
			'title'       => __( 'Default-view Option', 'woo-onepipe' ),
			'type'        => 'text',
			'desc_tip'    => false,
			'description' => __( 'Optional. E.G: bank.account' ),
		),
		'onepipe_default_view_provider'           => array(
			'title'       => __( 'Default-view Provider', 'woo-onepipe' ),
			'type'        => 'text',
			'desc_tip'    => false,
			'description' => __( 'Optional. E.G: Suntrust' ),
		),
    );
	
	$onepipe_settings = array(
        array(
            'id' => 'edd_onepipe_settings',
            'name' => '<strong>OnePipe Settings</strong>',
            'desc' => 'Configure the gateway settings',
            'type' => 'header'
        ),
	);
	
	foreach($temp as $id=>$f){
		$opt=array(
			'id'=>$id,
			'name'=>$f['title'],
			'desc'=>$f['description'],
			'type'=>$f['type'],
			'size'=>'regular'
		);
		if(isset($f['default']))$opt['defaultdefault']=$f['default'];
		if(isset($f['options']))$opt['options']=$f['options'];
		
		$onepipe_settings[]=$opt;
	}

    if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
        $onepipe_settings = array( 'edd-onepipe' => $onepipe_settings );
    }

    return array_merge( $settings, $onepipe_settings );
}

add_filter( 'edd_payment_gateways', 'onepipe_edd_add_gateway' );
function onepipe_edd_add_gateway( $gateways ) {
    if ( true||onepipe_edd_is_setup() ) { //reall??
        $gateways['edd-onepipe'] = array(
            'admin_label' => 'OnePipe',
            'checkout_label' => trim(edd_get_option('onepipe_title'))
        );
    }
    return $gateways;
}

function onepipe_edd_check_config() {
    $is_enabled = edd_is_gateway_active( 'edd-onepipe' );
    if ( ( ! $is_enabled || false === onepipe_edd_is_setup() ) && edd_get_chosen_gateway()=='edd-onepipe' ) {
        edd_set_error( 'onepipe_gateway_not_configured', 'There is an error with the OnePipe configuration.' );
    }
}
add_action( 'edd_pre_process_purchase', 'onepipe_edd_check_config', 1  );


function onepipe_edd_is_setup() {
	return !empty(edd_get_option( 'onepipe_api_key'));
}

 function onepipe_edd_log_stuff($str){
	$ddate=date('jS M. Y g:ia');
	file_put_contents(__DIR__ .'/debug.log',"$ddate\n$str\n---------------\n",FILE_APPEND); 
}

add_action( 'edd_gateway_edd-onepipe', 'onepipe_edd_process_payment' );
function onepipe_edd_process_payment( $purchase_data ) {
	$currency_code = edd_get_currency();
	$user_info = $purchase_data['user_info'];
	
    $payment_data = array(
        'price'        => $purchase_data['price'],
        'date'         => $purchase_data['date'],
        'user_email'   => $user_info['email'], //$purchase_data['user_email'],
        'purchase_key' => $purchase_data['purchase_key'],
        'currency'     => $currency_code,
        'downloads'    => $purchase_data['downloads'],
        'cart_details' => $purchase_data['cart_details'],
        'user_info'    => $user_info,
        'status'       => 'pending',
        'gateway'      => 'edd-onepipe'
    );

	$firstname = $user_info['first_name'];
	$lastname = $user_info['last_name'];
	$phone = ''; //$purchase_data['user_phone'];
	$store_name=get_option('blogname');
	//---------
    $payment_id = edd_insert_payment( $payment_data );
	
    if ( ! $payment_id ) {
        edd_record_gateway_error( 'Payment Error', sprintf( 'Payment creation failed before sending buyer to OnePipe. Payment data: %s', json_encode( $payment_data ) ), $payment_id );
		// edd_set_error( 'onepipe_error', 'Can\'t connect to the gateway, Please try again.' );
        edd_send_back_to_checkout( '?payment-mode=onepipe' ); exit;
    } 
	else {
        $amount = $purchase_data['price'];
        $currency = $payment_data['currency'];
        $email = $purchase_data['user_email'];
        $txnid = "EDD-$payment_id-".uniqid();
		$callback_url=add_query_arg( 'onepipe_edd_ref', $txnid, home_url( 'index.php' ) );
		$cancel_url=edd_get_checkout_uri();
		$order_title = substr("EDD Payment #$payment_id - $store_name",0,65);
        edd_set_payment_transaction_id($payment_id, $txnid);
		
		//--------------- OnePipe Transaction Initiation
		if(true){
			$sandbox = edd_get_option('onepipe_environment');
			$mock_mode = empty($sandbox)?'live':'inspect';

			$default_view_option = edd_get_option('onepipe_default_view_option');
			$default_view_provider = edd_get_option('onepipe_default_view_provider');
			if (empty($default_view_option)) {
				$default_view_option=null;
			}

			if (empty($default_view_provider)) {
				$default_view_provider=null;
			}

			$close_popup_timeout = (float)edd_get_option('onepipe_close_popup_timeout');
			if ($close_popup_timeout<=0) {
				$close_popup_timeout=5;
			}

			$close_popup_status = @json_decode(edd_get_option('onepipe_close_popup_status'), true);
			if (empty($close_popup_status)) {
				$close_popup_status = null;
			}

			$requestData=array(
				'request_ref'=>$txnid,
				'request_type'=>'collect',
				'api_key'=>trim(edd_get_option('onepipe_api_key')),
				'auth'=>null,
				'transaction'=>array(
					'amount'=>ceil($amount*100),
					'currency'=>$currency_code,
					'mock_mode'=>$mock_mode,
					'transaction_ref'=>$txnid,
					'transaction_desc'=>$order_title,
					'customer'=>array(
						'customer_ref'=>$email,
						'firstname'=>$firstname,
						'surname'=>$lastname,
						'email'=>$email,
						'mobile_no'=>$phone
					),
					'details'=>null
				),
				'meta'=>null,
				'options'=>array(
					'close_popup'=>array(
						'timeout'=>$close_popup_timeout,
						'status'=>$close_popup_status,
					),
					'default_view'=>array(
						'option'=>$default_view_option,
						'provider'=>$default_view_provider
					)
				),
			);

			$onepipeData=array(
				'requestData'=>$requestData,
				'cancel_url' => $cancel_url,
				'success_url' => $callback_url,
				'callback_url' => $callback_url,
			);
		
			if(true){
				$domain=$_SERVER['HTTP_HOST'];
				if(substr($domain,0,4)=='www.')$domain=substr($domain,4);
				$mail_from="$store_name<no-reply@$domain>";
				$customer_fullname=ucwords(strtolower("$firstname $lastname"));
				
				$transaction_date=date('jS M. Y g:i a');
				$amountTotals=number_format($amount);
				//$mail_message="Hello $customer_fullname\r\n\r\nHere are the details of your transaction:\r\n\r\nDETAILS: $order_title\r\nAMOUNT: $amountTotals $currency_code \r\nDATE: $transaction_date\r\n\r\nYou can always confirm your transaction/payment status at $callback_url\r\n\r\nRegards.";
				
				$logo_src=onepipe_eddURL . 'assets/images/logo.png';
				
				$mail_message="<div style='background-color:#fbfbfb;width:100%;text-align:center;'>
			<div style='background-color:#fbfbfb;width:100%;max-width:650px;display:inline-block;'>
				<div style='text-align:left;padding:30px 0;'>
					<img src='$logo_src' style='width:130px;height:auto;border:0' alt='' />
				</div>
				<div style='border-top:7px solid #1c84c6;padding:30px 30px 60px 30px;color:#777777;font-family: Lato, Arial, sans-serif;font-size:17px;line-height:30px;'>			
					<h3 style='color:#000000;font-size:24px;line-height:32px;font-weight:bold;'>Transaction Information</h3>			
					<div><b>Customer Name :</b><span>$customer_fullname</span></div>
					<div><b>Order Details :</b><span>$order_title</span></div>
					<div><b>Amount :</b><span>$amountTotals $currency_code</span></div>
					<div><b>Date :</b><span>$transaction_date</span></div>
					<div style='margin-top:25px;'>
						<a href='$callback_url' target='_blank' style='color:#ffffff;text-decoration: none;background:#008bb1;font-size:14px; line-height:18px; padding:12px 20px;font-weight:bold; border-radius:22px;'>VIEW TRANSACTION DETAILS</a>
					</div>
				</div>
			</div>
		</div>";
				
				$mail_headers = "MIME-Version: 1.0\r\n";
				$mail_headers .= "Content-Type: text/html; charset=iso-8859-1\r\n";
				$mail_headers .= "X-Priority: 1 (Highest)\r\n";
				$mail_headers .= "X-MSMail-Priority: High\r\n";
				$mail_headers .= "Importance: High\r\n";
				$mail_headers .= "From: $mail_from\r\n";
				@mail($email,"OnePipe EDD Transaction Information",$mail_message,$mail_headers);
			}
		
		
			header('X-Frame-Options: ALLOWALL');
			$snippet = "
			
			<script type='text/javascript' >
			var onepipeHandler =null;
			window.addEventListener('load',function(){
				var onepipeData = ".json_encode($onepipeData).";
			
				var tempTimer = setInterval(function(){
					if(typeof OnePipePopup!=='undefined'){
						clearInterval(tempTimer);
						OnePipePopup.isInitialized || OnePipePopup.initialize();
						setTimeout(setupOnepipe,2000);
					}
				},200);
				
				function setupOnepipe(){
					onepipeHandler = OnePipePopup.setup({
						requestData: onepipeData.requestData,
						callback: function (response) {
							onepipe_processing = false;
							console.log('onepipe callback:',response);
							window.location.href=onepipeData.callback_url; //not success_url
						},
						onClose: function () {
							onepipe_processing = false;
							console.log('Payment process cancelled');
							window.location.href=onepipeData.cancel_url;
						}
					});
					
					onepipeHandler.execute();
				}
			});
			</script>";
			
			$html = "
			<!DOCTYPE html>
			<html>
				<head>
					<title>Order {$payment_id} - Payment via OnePipe</title>
					<script src = 'https://js.onepipe.io/v2'></script>
				</head>
				<body>";
			$html .= __("Please wait... the payment page will be loaded in a moment.");
			$html .= "$snippet
				</body>
			</html>";
			echo $html;
			exit;
		}
	}
}

add_action( 'init', 'onepipe_edd_ipn' );
function onepipe_edd_ipn() {
	if (empty($_GET['onepipe_edd_ref']))return;

	$trans_ref = sanitize_text_field($_GET['onepipe_edd_ref']);
	$payment_id = edd_get_purchase_id_by_transaction_id( $trans_ref );

	if ($payment_id && get_post_status($payment_id) == 'publish' ){
		edd_empty_cart();
		edd_send_to_success_page();
		exit;
	}

	//$temp= explode( '-', $trans_ref );
	//$payment_id= $temp[1];
	
	if(empty($payment_id)){
		$error = "No pending payment record found for transaction ref: $trans_ref";
	}
	else {
		$request_url='https://api.onepipe.io/v2/transact/query';
		$request_ref = time().'.'.mt_rand(1000,9999);
		$onepipe_api_key=trim(edd_get_option('onepipe_api_key'));
		$onepipe_client_secret=trim(edd_get_option('onepipe_client_secret'));
		
		$request_body=array(
			  'request_ref'=>$request_ref, 
			  'request_type'=>'collect',
			  'transaction'=>array(
				'transaction_ref'=>$trans_ref, 
			)
		);
		
		$arg = array(
			'body'=>json_encode($request_body),
			'timeout'=>50,'redirection'=>5,
			'headers'=>array(
				'Content-Type'=>'application/json',
				'Authorization'=>"Bearer {$onepipe_api_key}",
				'Signature'=>md5("$request_ref;{$onepipe_client_secret}"),
			)
		);
		$response = wp_remote_post($request_url, $arg);

		if(is_wp_error($response)) {
			$error_message = $response->get_error_message();
			//$error="Error verifying payment at OnePipe: $error_message";
			$error="Unable to verify transaction at OnePipe.";
		}
		else {
			$json = @json_decode($response['body'],true);
			
			if(empty($json))$error="Error interpreting OnePipe verification response: {$response['body']}";
			elseif(empty($json['data']))$error="Unsuccessful attempt at verifying payment from OnePipe: {$response['body']}";
			else {						
				$porder=$json['data'];						
				$amount_paid = null;
				$currency_paid = null;
				
				if(!empty($porder['provider_response']['transaction_final_amount'])){
					$amount_paid=$porder['provider_response']['transaction_final_amount']/100;
				}						
				
				$pstatus=$json['status'];
				$pmessage=empty($porder['message'])?trim($json['message']):trim($porder['message']);
				
				if($pstatus=='Successful'&&!empty($porder['error'])){
					$pstatus='Unsuccessful';
					$pmessage.=";; ".json_encode($porder['error']);
				}
			}
			
			if(!empty($error))onepipe_edd_log_stuff("$error\n\n$url\n".json_encode($_POST,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
		}
		
		$payment           = new EDD_Payment( $payment_id );
		$order_total       = floatval(edd_get_payment_amount( $payment_id ));
		$order_currency	   = $payment->currency;

		if(empty($error)&&!empty($porder)){
			$new_status = 0; //0:pending, 1:successful, -1: failed					
			
			if($pstatus=='Successful'){
				if($amount_paid!==null&&$amount_paid<$order_total) {
					$new_status = -1;
					$info = "Amount paid ($amount_paid) is less than the total order amount ($order_total).";
				}
				elseif($currency_paid!==null&&$currency_paid!=$order_currency) {
					$new_status = -1;
					$info = "Order currency ($order_currency) is different from the payment currency ($currency_paid).";
				}
				elseif(!$this->testmode&&!empty($testmode_payment)) {
					$new_status = -1;
					$info = "This store doesn't run on test-mode, where-as payment was made in test mode. Suspicious!";
				}
				else { $new_status = 1; $info = "Payment successful via OnePipe. Ref: $trans_ref. $pmessage"; }
			}
			elseif($pstatus=='Failed'){
				$new_status = -1;
				$info = "Payment cancelled. $pmessage";
			}
			else { $info = "Payment status: $pstatus. $pmessage"; }
		}
	}
	
	
	if(!empty($error)){
		edd_set_error('failed_payment_verification',$error);
		edd_send_back_to_checkout('?payment-mode=onepipe');
	}
	elseif(isset($new_status)){
		if($new_status==-1){			
			$payment->status = 'revoked';
			$payment->add_note( $info );
			//$payment->transaction_id = $trans_ref;
			$payment->save();
			//edd_empty_cart();
			edd_set_error( 'failed_payment',$info);
			edd_send_back_to_checkout( '?payment-mode=onepipe' );					
		}
		elseif($new_status==1){
			$payment->status = 'publish';
			$payment->add_note( $info );
			//$payment->transaction_id = $trans_ref;
			$payment->save();
			edd_empty_cart();
			edd_send_to_success_page();
		}
		else {
			edd_set_error('pending_payment',$temp_msg);
			edd_send_back_to_checkout( '?payment-mode=onepipe' );	
		}
	}
}

add_filter( 'edd_accepted_payment_icons', 'onepipe_edd_payment_icons' );
function onepipe_edd_payment_icons( $icons ) {
    $icons[onepipe_eddURL . 'assets/images/logo.png']   = 'OnePipe';
    return $icons;
}

add_filter((is_network_admin()?'network_admin_':'') .'plugin_action_links_'.plugin_basename(__FILE__),
	'onepipe_edd_action_links');
function onepipe_edd_action_links( $links ) {
    $settings_link = array(
        'settings' => '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=edd-onepipe' ) . '" title="Settings">Settings</a>'
    );
    return array_merge( $links, $settings_link, );
}
