<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_paygatedottogateway_unlimit_gateway');

function init_paygatedottogateway_unlimit_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }


class PayGateDotTo_Instant_Payment_Gateway_Unlimit extends WC_Payment_Gateway {

    protected $icon_url;
    protected $gateficom_wallet_address;
    protected $gateficom_custom_domain;

    public function __construct() {
        $this->id                 = 'paygatedotto-instant-payment-gateway-unlimit';
        $this->icon = sanitize_url($this->get_option('icon_url'));
        $this->method_title       = esc_html__('Instant Approval Payment Gateway with Instant Payouts (gatefi.com)', 'instant-approval-payment-gateway'); // Escaping title
        $this->method_description = esc_html__('Instant Approval High Risk Merchant Gateway with instant payouts to your USDC POLYGON wallet using gatefi.com infrastructure', 'instant-approval-payment-gateway'); // Escaping description
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->description = sanitize_text_field($this->get_option('description'));

        // Use the configured settings for redirect and icon URLs
        $this->gateficom_custom_domain = rtrim(str_replace(['https://','http://'], '', sanitize_text_field($this->get_option('gateficom_custom_domain'))), '/');
        $this->gateficom_wallet_address = sanitize_text_field($this->get_option('gateficom_wallet_address'));
        $this->icon_url     = sanitize_url($this->get_option('icon_url'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'instant-approval-payment-gateway'), // Escaping title
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable gatefi.com payment gateway', 'instant-approval-payment-gateway'), // Escaping label
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
            'gateficom_custom_domain' => array(
                'title'       => esc_html__('Custom Domain', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Follow the custom domain guide to use your domain for the checkout pages and links.', 'instant-approval-payment-gateway'), // Escaping description
                'default'     => esc_html__('checkout.paygate.to', 'instant-approval-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'gateficom_wallet_address' => array(
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
        $gateficom_admin_wallet_address = isset($_POST[$this->plugin_id . $this->id . '_gateficom_wallet_address']) ? sanitize_text_field( wp_unslash( $_POST[$this->plugin_id . $this->id . '_gateficom_wallet_address'])) : '';

        // Check if wallet address starts with "0x"
        if (substr($gateficom_admin_wallet_address, 0, 2) !== '0x') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDC Polygon wallet address.', 'instant-approval-payment-gateway'));
            return false;
        }

        // Check if wallet address matches the USDC contract address
        if (strtolower($gateficom_admin_wallet_address) === '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDC Polygon wallet address.', 'instant-approval-payment-gateway'));
            return false;
        }

        // Proceed with the default processing if validations pass
        return parent::process_admin_options();
    }
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $paygatedottogateway_gateficom_currency = get_woocommerce_currency();
		$paygatedottogateway_gateficom_total = $order->get_total();
		$paygatedottogateway_gateficom_nonce = wp_create_nonce( 'paygatedottogateway_gateficom_nonce_' . $order_id );
		$paygatedottogateway_gateficom_callback = add_query_arg(array('order_id' => $order_id, 'nonce' => $paygatedottogateway_gateficom_nonce,), rest_url('paygatedottogateway/v1/paygatedottogateway-gateficom/'));
		$paygatedottogateway_gateficom_email = urlencode(sanitize_email($order->get_billing_email()));
		$paygatedottogateway_gateficom_final_total = $paygatedottogateway_gateficom_total;
	
if ($paygatedottogateway_gateficom_currency === 'USD') {
        $paygatedottogateway_gateficom_minimumcheck = $paygatedottogateway_gateficom_total;
		} else {
		
$paygatedottogateway_gateficom_minimumcheck_response = wp_remote_get('https://api.paygate.to/control/convert.php?value=' . $paygatedottogateway_gateficom_total . '&from=' . strtolower($paygatedottogateway_gateficom_currency), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_gateficom_minimumcheck_response)) {
    // Handle error
    paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {

$paygatedottogateway_gateficom_minimumcheck_body = wp_remote_retrieve_body($paygatedottogateway_gateficom_minimumcheck_response);
$paygatedottogateway_gateficom_minimum_conversion_resp = json_decode($paygatedottogateway_gateficom_minimumcheck_body, true);

if ($paygatedottogateway_gateficom_minimum_conversion_resp && isset($paygatedottogateway_gateficom_minimum_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottogateway_gateficom_minimum_conversion_total	= sanitize_text_field($paygatedottogateway_gateficom_minimum_conversion_resp['value_coin']);
    $paygatedottogateway_gateficom_minimumcheck = (float)$paygatedottogateway_gateficom_minimum_conversion_total;	
} else {
    paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'instant-approval-payment-gateway'), 'error');
    return null;
}	
		}
		}
		
if ($paygatedottogateway_gateficom_minimumcheck < 10) {
paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Order total for this payment provider must be $10 USD or more.', 'instant-approval-payment-gateway'), 'error');
return null;
}
	
$paygatedottogateway_gateficom_gen_wallet = wp_remote_get('https://api.paygate.to/control/wallet.php?address=' . $this->gateficom_wallet_address .'&callback=' . urlencode($paygatedottogateway_gateficom_callback), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_gateficom_gen_wallet)) {
    // Handle error
    paygatedottogateway_add_notice(__('Wallet error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {
	$paygatedottogateway_gateficom_wallet_body = wp_remote_retrieve_body($paygatedottogateway_gateficom_gen_wallet);
	$paygatedottogateway_gateficom_wallet_decbody = json_decode($paygatedottogateway_gateficom_wallet_body, true);

 // Check if decoding was successful
    if ($paygatedottogateway_gateficom_wallet_decbody && isset($paygatedottogateway_gateficom_wallet_decbody['address_in'])) {
        // Store the address_in as a variable
        $paygatedottogateway_gateficom_gen_addressIn = wp_kses_post($paygatedottogateway_gateficom_wallet_decbody['address_in']);
        $paygatedottogateway_gateficom_gen_polygon_addressIn = sanitize_text_field($paygatedottogateway_gateficom_wallet_decbody['polygon_address_in']);
		$paygatedottogateway_gateficom_gen_callback = sanitize_url($paygatedottogateway_gateficom_wallet_decbody['callback_url']);
		// Save $gateficomresponse in order meta data
    $order->add_meta_data('paygatedotto_gateficom_tracking_address', $paygatedottogateway_gateficom_gen_addressIn, true);
    $order->add_meta_data('paygatedotto_gateficom_polygon_temporary_order_wallet_address', $paygatedottogateway_gateficom_gen_polygon_addressIn, true);
    $order->add_meta_data('paygatedotto_gateficom_callback', $paygatedottogateway_gateficom_gen_callback, true);
	$order->add_meta_data('paygatedotto_gateficom_converted_amount', $paygatedottogateway_gateficom_final_total, true);
	$order->add_meta_data('paygatedotto_gateficom_nonce', $paygatedottogateway_gateficom_nonce, true);
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
            'redirect' => 'https://' . $this->gateficom_custom_domain . '/process-payment.php?address=' . $paygatedottogateway_gateficom_gen_addressIn . '&amount=' . (float)$paygatedottogateway_gateficom_final_total . '&provider=unlimit&email=' . $paygatedottogateway_gateficom_email . '&currency=' . $paygatedottogateway_gateficom_currency,
        );
    }

public function paygatedotto_instant_payment_gateway_get_icon_url() {
        return !empty($this->icon_url) ? esc_url($this->icon_url) : '';
    }
}

function paygatedotto_add_instant_payment_gateway_unlimit($gateways) {
    $gateways[] = 'PayGateDotTo_Instant_Payment_Gateway_Unlimit';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'paygatedotto_add_instant_payment_gateway_unlimit');
}

// Add custom endpoint for changing order status
function paygatedottogateway_gateficom_change_order_status_rest_endpoint() {
    // Register custom route
    register_rest_route( 'paygatedottogateway/v1', '/paygatedottogateway-gateficom/', array(
        'methods'  => 'GET',
        'callback' => 'paygatedottogateway_gateficom_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action( 'rest_api_init', 'paygatedottogateway_gateficom_change_order_status_rest_endpoint' );

// Callback function to change order status
function paygatedottogateway_gateficom_change_order_status_callback( $request ) {
    $order_id = absint($request->get_param( 'order_id' ));
	$paygatedottogateway_gateficomgetnonce = sanitize_text_field($request->get_param( 'nonce' ));
	$paygatedottogateway_gateficompaid_txid_out = sanitize_text_field($request->get_param('txid_out'));

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
    if ( empty( $paygatedottogateway_gateficomgetnonce ) || $order->get_meta('paygatedotto_gateficom_nonce', true) !== $paygatedottogateway_gateficomgetnonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'instant-approval-payment-gateway' ), array( 'status' => 403 ) );
    }

    // Check if the order is pending and payment method is 'paygatedotto-instant-payment-gateway-unlimit'
    if ( $order && $order->get_status() !== 'processing' && $order->get_status() !== 'completed' && 'paygatedotto-instant-payment-gateway-unlimit' === $order->get_payment_method() ) {
        // Change order status to processing
		$order->payment_complete();
		/* translators: 1: Transaction ID */
		$order->add_order_note( sprintf(__('Payment completed by the provider TXID: %1$s', 'instant-approval-payment-gateway'), $paygatedottogateway_gateficompaid_txid_out) );
        // Return success response
        return array( 'message' => 'Order marked as paid and status changed.' );
    } else {
        // Return error response if conditions are not met
        return new WP_Error( 'order_not_eligible', __( 'Order is not eligible for status change.', 'instant-approval-payment-gateway' ), array( 'status' => 400 ) );
    }
}
?>