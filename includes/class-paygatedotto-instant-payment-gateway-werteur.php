<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_paygatedottogateway_werteur_gateway');

function init_paygatedottogateway_werteur_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }


class PayGateDotTo_Instant_Payment_Gateway_Werteur extends WC_Payment_Gateway {

    protected $icon_url;
    protected $werteur_wallet_address;

    public function __construct() {
        $this->id                 = 'paygatedotto-instant-payment-gateway-werteur';
        $this->icon = sanitize_url($this->get_option('icon_url'));
        $this->method_title       = esc_html__('Instant Approval Payment Gateway with Instant Payouts (Wert.io (EUR))', 'instant-approval-payment-gateway'); // Escaping title
        $this->method_description = esc_html__('Instant Approval High Risk Merchant Gateway with instant payouts to your USDC POLYGON wallet using Wert.io (EUR) infrastructure', 'instant-approval-payment-gateway'); // Escaping description
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->description = sanitize_text_field($this->get_option('description'));

        // Use the configured settings for redirect and icon URLs
        $this->werteur_wallet_address = sanitize_text_field($this->get_option('werteur_wallet_address'));
        $this->icon_url     = sanitize_url($this->get_option('icon_url'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'instant-approval-payment-gateway'), // Escaping title
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable Wert.io (EUR) payment gateway', 'instant-approval-payment-gateway'), // Escaping label
                'default' => 'no',
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Payment method title that users will see during checkout.', 'instant-approval-payment-gateway'), // Escaping description
                'default'     => esc_html__('Wert.io (EUR)', 'instant-approval-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'textarea',
                'description' => esc_html__('Payment method description that users will see during checkout.', 'instant-approval-payment-gateway'), // Escaping description
                'default'     => esc_html__('Pay via Wert.io (EUR)', 'instant-approval-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'werteur_wallet_address' => array(
                'title'       => esc_html__('Wallet Address', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Insert your USDC (Polygon) wallet address to receive instant payouts. Payouts maybe sent in USDC or USDT (Polygon or BEP-20) or POL native token. Same wallet should work to receive all. Make sure you use a self-custodial wallet to receive payouts.', 'instant-approval-payment-gateway'), // Escaping description
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
        $werteur_admin_wallet_address = isset($_POST[$this->plugin_id . $this->id . '_werteur_wallet_address']) ? sanitize_text_field( wp_unslash( $_POST[$this->plugin_id . $this->id . '_werteur_wallet_address'])) : '';

        // Check if wallet address starts with "0x"
        if (substr($werteur_admin_wallet_address, 0, 2) !== '0x') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDC Polygon wallet address.', 'instant-approval-payment-gateway'));
            return false;
        }

        // Check if wallet address matches the USDC contract address
        if (strtolower($werteur_admin_wallet_address) === '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDC Polygon wallet address.', 'instant-approval-payment-gateway'));
            return false;
        }

        // Proceed with the default processing if validations pass
        return parent::process_admin_options();
    }
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $paygatedottogateway_werteur_currency = get_woocommerce_currency();
		$paygatedottogateway_werteur_total = $order->get_total();
		$paygatedottogateway_werteur_nonce = wp_create_nonce( 'paygatedottogateway_werteur_nonce_' . $order_id );
		$paygatedottogateway_werteur_callback = add_query_arg(array('order_id' => $order_id, 'nonce' => $paygatedottogateway_werteur_nonce,), rest_url('paygatedottogateway/v1/paygatedottogateway-werteur/'));
		$paygatedottogateway_werteur_email = urlencode(sanitize_email($order->get_billing_email()));
		
		 // If the currency is not EUR
if ($paygatedottogateway_werteur_currency === 'EUR') {
        $paygatedottogateway_werteur_final_total = $paygatedottogateway_werteur_total;
		$paygatedottogateway_werteur_payment_final_total = (float)$paygatedottogateway_werteur_final_total;
		} else {
		
$paygatedottogateway_werteur_response = wp_remote_get('https://api.paygate.to/crypto/erc20/eurc/convert.php?value=' . $paygatedottogateway_werteur_total . '&from=' . strtolower($paygatedottogateway_werteur_currency), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_werteur_response)) {
    // Handle error
    paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {

$paygatedottogateway_werteur_body = wp_remote_retrieve_body($paygatedottogateway_werteur_response);
$paygatedottogateway_werteur_conversion_resp = json_decode($paygatedottogateway_werteur_body, true);

if ($paygatedottogateway_werteur_conversion_resp && isset($paygatedottogateway_werteur_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottogateway_werteur_final_total	= sanitize_text_field($paygatedottogateway_werteur_conversion_resp['value_coin']);
    $paygatedottogateway_werteur_payment_final_total = (float)$paygatedottogateway_werteur_final_total;	
} else {
    paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'instant-approval-payment-gateway'), 'error');
    return null;
}	
		}
		}
	
	if ($paygatedottogateway_werteur_payment_final_total < 1) {
paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Order total for this payment provider must be €1 or more.', 'instant-approval-payment-gateway'), 'error');
return null;
}	
		
if ($paygatedottogateway_werteur_currency === 'USD') {
$paygatedottogateway_werteur_usdconver_reference_total = (float)$paygatedottogateway_werteur_total;
} else {

$paygatedottogateway_werteur_usdconver_response = wp_remote_get('https://api.paygate.to/control/convert.php?value=' . $paygatedottogateway_werteur_total . '&from=' . strtolower($paygatedottogateway_werteur_currency), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_werteur_usdconver_response)) {
    // Handle error
    paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {

$paygatedottogateway_werteur_usdconver_body = wp_remote_retrieve_body($paygatedottogateway_werteur_usdconver_response);
$paygatedottogateway_werteur_usdconver_conversion_resp = json_decode($paygatedottogateway_werteur_usdconver_body, true);

if ($paygatedottogateway_werteur_usdconver_conversion_resp && isset($paygatedottogateway_werteur_usdconver_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottogateway_werteur_usdconver_finalusd_total	= sanitize_text_field($paygatedottogateway_werteur_usdconver_conversion_resp['value_coin']);
    $paygatedottogateway_werteur_usdconver_reference_total = (float)$paygatedottogateway_werteur_usdconver_finalusd_total;	
} else {
    paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'instant-approval-payment-gateway'), 'error');
    return null;
}	
		}

}
		
	
$paygatedottogateway_werteur_gen_wallet = wp_remote_get('https://api.paygate.to/control/wallet.php?address=' . $this->werteur_wallet_address .'&callback=' . urlencode($paygatedottogateway_werteur_callback), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_werteur_gen_wallet)) {
    // Handle error
    paygatedottogateway_add_notice(__('Wallet error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {
	$paygatedottogateway_werteur_wallet_body = wp_remote_retrieve_body($paygatedottogateway_werteur_gen_wallet);
	$paygatedottogateway_werteur_wallet_decbody = json_decode($paygatedottogateway_werteur_wallet_body, true);

 // Check if decoding was successful
    if ($paygatedottogateway_werteur_wallet_decbody && isset($paygatedottogateway_werteur_wallet_decbody['address_in'])) {
        // Store the address_in as a variable
        $paygatedottogateway_werteur_gen_addressIn = wp_kses_post($paygatedottogateway_werteur_wallet_decbody['address_in']);
        $paygatedottogateway_werteur_gen_polygon_addressIn = sanitize_text_field($paygatedottogateway_werteur_wallet_decbody['polygon_address_in']);
		$paygatedottogateway_werteur_gen_callback = sanitize_url($paygatedottogateway_werteur_wallet_decbody['callback_url']);
		// Save $werteurresponse in order meta data
    $order->add_meta_data('paygatedotto_werteur_tracking_address', $paygatedottogateway_werteur_gen_addressIn, true);
    $order->add_meta_data('paygatedotto_werteur_polygon_temporary_order_wallet_address', $paygatedottogateway_werteur_gen_polygon_addressIn, true);
    $order->add_meta_data('paygatedotto_werteur_callback', $paygatedottogateway_werteur_gen_callback, true);
	$order->add_meta_data('paygatedotto_werteur_converted_amount', $paygatedottogateway_werteur_payment_final_total, true);
	$order->add_meta_data('paygatedotto_werteur_expected_amount', $paygatedottogateway_werteur_usdconver_reference_total, true);
	$order->add_meta_data('paygatedotto_werteur_nonce', $paygatedottogateway_werteur_nonce, true);
    $order->save();
    } else {
        paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed, please try again (wallet address error)', 'instant-approval-payment-gateway'), 'error');

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
            'redirect' => 'https://checkout.paygate.to/process-payment.php?address=' . $paygatedottogateway_werteur_gen_addressIn . '&amount=' . (float)$paygatedottogateway_werteur_payment_final_total . '&provider=werteur&email=' . $paygatedottogateway_werteur_email . '&currency=' . $paygatedottogateway_werteur_currency,
        );
    }

public function paygatedotto_instant_payment_gateway_get_icon_url() {
        return !empty($this->icon_url) ? esc_url($this->icon_url) : '';
    }
}

function paygatedotto_add_instant_payment_gateway_werteur($gateways) {
    $gateways[] = 'PayGateDotTo_Instant_Payment_Gateway_Werteur';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'paygatedotto_add_instant_payment_gateway_werteur');
}

// Add custom endpoint for changing order status
function paygatedottogateway_werteur_change_order_status_rest_endpoint() {
    // Register custom route
    register_rest_route( 'paygatedottogateway/v1', '/paygatedottogateway-werteur/', array(
        'methods'  => 'GET',
        'callback' => 'paygatedottogateway_werteur_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action( 'rest_api_init', 'paygatedottogateway_werteur_change_order_status_rest_endpoint' );

// Callback function to change order status
function paygatedottogateway_werteur_change_order_status_callback( $request ) {
    $order_id = absint($request->get_param( 'order_id' ));
	$paygatedottogateway_werteurgetnonce = sanitize_text_field($request->get_param( 'nonce' ));
	$paygatedottogateway_werteurpaid_txid_out = sanitize_text_field($request->get_param('txid_out'));
	$paygatedottogateway_werteurpaid_value_coin = sanitize_text_field($request->get_param('value_coin'));
	$paygatedottogateway_werteurfloatpaid_value_coin = (float)$paygatedottogateway_werteurpaid_value_coin;

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
    if ( empty( $paygatedottogateway_werteurgetnonce ) || $order->get_meta('paygatedotto_werteur_nonce', true) !== $paygatedottogateway_werteurgetnonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'instant-approval-payment-gateway' ), array( 'status' => 403 ) );
    }

    // Check if the order is pending and payment method is 'paygatedotto-instant-payment-gateway-werteur'
    if ( $order && $order->get_status() !== 'processing' && $order->get_status() !== 'completed' && 'paygatedotto-instant-payment-gateway-werteur' === $order->get_payment_method() ) {
	$paygatedottogateway_werteurexpected_amount = (float)$order->get_meta('paygatedotto_werteur_expected_amount', true);
	$paygatedottogateway_werteurthreshold = 0.60 * $paygatedottogateway_werteurexpected_amount;
		if ( $paygatedottogateway_werteurfloatpaid_value_coin < $paygatedottogateway_werteurthreshold ) {
			// Mark the order as failed and add an order note
            $order->update_status('failed', __( 'Payment received is less than 60% of the order total. Customer may have changed the payment values on the checkout page.', 'instant-approval-payment-gateway' ));
			/* translators: 1: Transaction ID */
            $order->add_order_note(sprintf( __( 'Order marked as failed: Payment received is less than 60%% of the order total. Customer may have changed the payment values on the checkout page. TXID: %1$s', 'instant-approval-payment-gateway' ), $paygatedottogateway_werteurpaid_txid_out));
            return array( 'message' => 'Order status changed to failed due to partial payment.' );
			
		} else {
        // Change order status to processing
		$order->payment_complete();
		/* translators: 1: Transaction ID */
		$order->add_order_note( sprintf(__('Payment completed by the provider TXID: %1$s', 'instant-approval-payment-gateway'), $paygatedottogateway_werteurpaid_txid_out) );
        // Return success response
        return array( 'message' => 'Order marked as paid and status changed.' );
	}
    } else {
        // Return error response if conditions are not met
        return new WP_Error( 'order_not_eligible', __( 'Order is not eligible for status change.', 'instant-approval-payment-gateway' ), array( 'status' => 400 ) );
    }
}
?>