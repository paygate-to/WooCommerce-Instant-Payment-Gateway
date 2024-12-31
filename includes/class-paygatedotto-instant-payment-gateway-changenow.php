<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_paygatedottogateway_changenow_gateway');

function init_paygatedottogateway_changenow_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }


class PayGateDotTo_Instant_Payment_Gateway_Changenow extends WC_Payment_Gateway {

    protected $icon_url;
    protected $changenowio_wallet_address;

    public function __construct() {
        $this->id                 = 'paygatedotto-instant-payment-gateway-changenow';
        $this->icon = sanitize_url($this->get_option('icon_url'));
        $this->method_title       = esc_html__('Instant Approval Payment Gateway with Instant Payouts (changenow.io)', 'instant-approval-payment-gateway'); // Escaping title
        $this->method_description = esc_html__('Instant Approval High Risk Merchant Gateway with instant payouts to your USDT POLYGON wallet using changenow.io infrastructure', 'instant-approval-payment-gateway'); // Escaping description
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->description = sanitize_text_field($this->get_option('description'));

        // Use the configured settings for redirect and icon URLs
        $this->changenowio_wallet_address = sanitize_text_field($this->get_option('changenowio_wallet_address'));
        $this->icon_url     = sanitize_url($this->get_option('icon_url'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'instant-approval-payment-gateway'), // Escaping title
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable changenow.io payment gateway', 'instant-approval-payment-gateway'), // Escaping label
                'default' => 'no',
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Payment method title that users will see during checkout.', 'instant-approval-payment-gateway'), // Escaping description
                'default'     => esc_html__('Credit Card', 'instant-approval-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'textarea',
                'description' => esc_html__('Payment method description that users will see during checkout.', 'instant-approval-payment-gateway'), // Escaping description
                'default'     => esc_html__('Pay via credit card', 'instant-approval-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'changenowio_wallet_address' => array(
                'title'       => esc_html__('Wallet Address', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Insert your USDT (Polygon) wallet address to receive instant payouts.', 'instant-approval-payment-gateway'), // Escaping description
                'desc_tip'    => true,
            ),
            'icon_url' => array(
                'title'       => esc_html__('Icon URL', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'url',
                'description' => esc_html__('Enter the URL of the icon image for the payment method.', 'instant-approval-payment-gateway'), // Escaping description
                'desc_tip'    => true,
            ),
        );
    }
	 // Add this method to validate the wallet address in wp-admin
    public function process_admin_options() {
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')) {
    WC_Admin_Settings::add_error(__('Nonce verification failed. Please try again.', 'instant-approval-payment-gateway'));
    return false;
}
        $changenowio_admin_wallet_address = isset($_POST[$this->plugin_id . $this->id . '_changenowio_wallet_address']) ? sanitize_text_field( wp_unslash( $_POST[$this->plugin_id . $this->id . '_changenowio_wallet_address'])) : '';

        // Check if wallet address starts with "0x"
        if (substr($changenowio_admin_wallet_address, 0, 2) !== '0x') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDT Polygon wallet address.', 'instant-approval-payment-gateway'));
            return false;
        }

        // Check if wallet address matches the USDT contract address
        if (strtolower($changenowio_admin_wallet_address) === '0xc2132d05d31c914a87c6611c10748aeb04b58e8f') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDT Polygon wallet address.', 'instant-approval-payment-gateway'));
            return false;
        }

        // Proceed with the default processing if validations pass
        return parent::process_admin_options();
    }
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $paygatedottogateway_changenowio_currency = get_woocommerce_currency();
		$paygatedottogateway_changenowio_total = $order->get_total();
		$paygatedottogateway_changenowio_nonce = wp_create_nonce( 'paygatedottogateway_changenowio_nonce_' . $order_id );
		$paygatedottogateway_changenowio_callback = add_query_arg(array('order_id' => $order_id, 'nonce' => $paygatedottogateway_changenowio_nonce,), rest_url('paygatedottogateway/v1/paygatedottogateway-changenowio/'));
		$paygatedottogateway_changenowio_email = urlencode(sanitize_email($order->get_billing_email()));
		$paygatedottogateway_changenowio_final_total = $paygatedottogateway_changenowio_total;
		
		if ($paygatedottogateway_changenowio_currency === 'USD') {
		$paygatedottogateway_changenowio_reference_total = (float)$paygatedottogateway_changenowio_final_total;
		} else {
		
$paygatedottogateway_changenowio_response = wp_remote_get('https://api.paygate.to/control/convert.php?value=' . $paygatedottogateway_changenowio_total . '&from=' . strtolower($paygatedottogateway_changenowio_currency), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_changenowio_response)) {
    // Handle error
    wc_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {

$paygatedottogateway_changenowio_body = wp_remote_retrieve_body($paygatedottogateway_changenowio_response);
$paygatedottogateway_changenowio_conversion_resp = json_decode($paygatedottogateway_changenowio_body, true);

if ($paygatedottogateway_changenowio_conversion_resp && isset($paygatedottogateway_changenowio_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottogateway_changenowio_finalusd_total	= sanitize_text_field($paygatedottogateway_changenowio_conversion_resp['value_coin']);
    $paygatedottogateway_changenowio_reference_total = (float)$paygatedottogateway_changenowio_finalusd_total;	
} else {
    wc_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'instant-approval-payment-gateway'), 'error');
    return null;
}	
		}
		}
	
$paygatedottogateway_changenowio_gen_wallet = wp_remote_get('https://api.paygate.to/control/wallet.php?address=' . $this->changenowio_wallet_address .'&callback=' . urlencode($paygatedottogateway_changenowio_callback), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_changenowio_gen_wallet)) {
    // Handle error
    wc_add_notice(__('Wallet error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {
	$paygatedottogateway_changenowio_wallet_body = wp_remote_retrieve_body($paygatedottogateway_changenowio_gen_wallet);
	$paygatedottogateway_changenowio_wallet_decbody = json_decode($paygatedottogateway_changenowio_wallet_body, true);

 // Check if decoding was successful
    if ($paygatedottogateway_changenowio_wallet_decbody && isset($paygatedottogateway_changenowio_wallet_decbody['address_in'])) {
        // Store the address_in as a variable
        $paygatedottogateway_changenowio_gen_addressIn = wp_kses_post($paygatedottogateway_changenowio_wallet_decbody['address_in']);
        $paygatedottogateway_changenowio_gen_polygon_addressIn = sanitize_text_field($paygatedottogateway_changenowio_wallet_decbody['polygon_address_in']);
		$paygatedottogateway_changenowio_gen_callback = sanitize_url($paygatedottogateway_changenowio_wallet_decbody['callback_url']);
		// Save $changenowioresponse in order meta data
    $order->add_meta_data('paygatedotto_changenowio_tracking_address', $paygatedottogateway_changenowio_gen_addressIn, true);
    $order->add_meta_data('paygatedotto_changenowio_polygon_temporary_order_wallet_address', $paygatedottogateway_changenowio_gen_polygon_addressIn, true);
    $order->add_meta_data('paygatedotto_changenowio_callback', $paygatedottogateway_changenowio_gen_callback, true);
	$order->add_meta_data('paygatedotto_changenowio_converted_amount', $paygatedottogateway_changenowio_final_total, true);
	$order->add_meta_data('paygatedotto_changenowio_expected_amount', $paygatedottogateway_changenowio_reference_total, true);
	$order->add_meta_data('paygatedotto_changenowio_nonce', $paygatedottogateway_changenowio_nonce, true);
    $order->save();
    } else {
        wc_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed, please try again (wallet address error)', 'instant-approval-payment-gateway'), 'error');

        return null;
    }
}

// Check if the Checkout page is using Checkout Blocks
if (paygatedottogateway_is_checkout_block()) {
    global $woocommerce;
	$woocommerce->cart->empty_cart();
}

        // Redirect to payment page
        return array(
            'result'   => 'success',
            'redirect' => 'https://checkout.paygate.to/process-payment.php?address=' . $paygatedottogateway_changenowio_gen_addressIn . '&amount=' . (float)$paygatedottogateway_changenowio_final_total . '&provider=changenow&email=' . $paygatedottogateway_changenowio_email . '&currency=' . $paygatedottogateway_changenowio_currency,
        );
    }

public function paygatedotto_instant_payment_gateway_get_icon_url() {
        return !empty($this->icon_url) ? esc_url($this->icon_url) : '';
    }
}

function paygatedotto_add_instant_payment_gateway_changenow($gateways) {
    $gateways[] = 'PayGateDotTo_Instant_Payment_Gateway_Changenow';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'paygatedotto_add_instant_payment_gateway_changenow');
}

// Add custom endpoint for changing order status
function paygatedottogateway_changenowio_change_order_status_rest_endpoint() {
    // Register custom route
    register_rest_route( 'paygatedottogateway/v1', '/paygatedottogateway-changenowio/', array(
        'methods'  => 'GET',
        'callback' => 'paygatedottogateway_changenowio_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action( 'rest_api_init', 'paygatedottogateway_changenowio_change_order_status_rest_endpoint' );

// Callback function to change order status
function paygatedottogateway_changenowio_change_order_status_callback( $request ) {
    $order_id = absint($request->get_param( 'order_id' ));
	$paygatedottogateway_changenowiogetnonce = sanitize_text_field($request->get_param( 'nonce' ));
	$paygatedottogateway_changenowiopaid_txid_out = sanitize_text_field($request->get_param('txid_out'));
	$paygatedottogateway_changenowiopaid_value_coin = sanitize_text_field($request->get_param('value_coin'));
	$paygatedottogateway_changenowiofloatpaid_value_coin = (float)$paygatedottogateway_changenowiopaid_value_coin;

    // Check if order ID parameter exists
    if ( empty( $order_id ) ) {
        return new WP_Error( 'missing_order_id', __( 'Order ID parameter is missing.', 'instant-approval-payment-gateway' ), array( 'status' => 400 ) );
    }

    // Get order object
    $order = wc_get_order( $order_id );

    // Check if order exists
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', __( 'Invalid order ID.', 'instant-approval-payment-gateway' ), array( 'status' => 404 ) );
    }
	
	// Verify nonce
    if ( empty( $paygatedottogateway_changenowiogetnonce ) || $order->get_meta('paygatedotto_changenowio_nonce', true) !== $paygatedottogateway_changenowiogetnonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'instant-approval-payment-gateway' ), array( 'status' => 403 ) );
    }

    // Check if the order is pending and payment method is 'paygatedotto-instant-payment-gateway-changenow'
    if ( $order && $order->get_status() !== 'processing' && $order->get_status() !== 'completed' && 'paygatedotto-instant-payment-gateway-changenow' === $order->get_payment_method() ) {
	$paygatedottogateway_changenowioexpected_amount = (float)$order->get_meta('paygatedotto_changenowio_expected_amount', true);
	$paygatedottogateway_changenowiothreshold = 0.60 * $paygatedottogateway_changenowioexpected_amount;
		if ( $paygatedottogateway_changenowiofloatpaid_value_coin < $paygatedottogateway_changenowiothreshold ) {
			// Mark the order as failed and add an order note
            $order->update_status('failed', __( 'Payment received is less than 60% of the order total. Customer may have changed the payment values on the checkout page.', 'instant-approval-payment-gateway' ));
            /* translators: 1: Transaction ID */
            $order->add_order_note(sprintf( __( 'Order marked as failed: Payment received is less than 60%% of the order total. Customer may have changed the payment values on the checkout page. TXID: %1$s', 'instant-approval-payment-gateway' ), $paygatedottogateway_changenowiopaid_txid_out));
            return array( 'message' => 'Order status changed to failed due to partial payment.' );
			
		} else {
        // Change order status to processing
		$order->payment_complete();
        $order->update_status( 'processing' );
		/* translators: 1: Transaction ID */
		$order->add_order_note( sprintf(__('Payment completed by the provider TXID: %1$s', 'instant-approval-payment-gateway'), $paygatedottogateway_changenowiopaid_txid_out) );
        // Return success response
        return array( 'message' => 'Order status changed to processing.' );
	}
    } else {
        // Return error response if conditions are not met
        return new WP_Error( 'order_not_eligible', __( 'Order is not eligible for status change.', 'instant-approval-payment-gateway' ), array( 'status' => 400 ) );
    }
}
?>