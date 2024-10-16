<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_highriskshopgateway_simpleswap_gateway');

function init_highriskshopgateway_simpleswap_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }


class HighRiskShop_Instant_Payment_Gateway_Simpleswap extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'highriskshop-instant-payment-gateway-simpleswap';
        $this->icon = sanitize_url($this->get_option('icon_url'));
        $this->method_title       = esc_html__('Instant Approval Payment Gateway with Instant Payouts (simpleswap.io)', 'highriskshopgateway'); // Escaping title
        $this->method_description = esc_html__('Instant Approval High Risk Merchant Gateway with instant payouts to your USDC POLYGON wallet using simpleswap.io infrastructure', 'highriskshopgateway'); // Escaping description
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->description = sanitize_text_field($this->get_option('description'));

        // Use the configured settings for redirect and icon URLs
        $this->simpleswapio_wallet_address = sanitize_text_field($this->get_option('simpleswapio_wallet_address'));
        $this->icon_url     = sanitize_url($this->get_option('icon_url'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'highriskshopgateway'), // Escaping title
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable simpleswap.io payment gateway', 'highriskshopgateway'), // Escaping label
                'default' => 'no',
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'highriskshopgateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Payment method title that users will see during checkout.', 'highriskshopgateway'), // Escaping description
                'default'     => esc_html__('Credit Card', 'highriskshopgateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'highriskshopgateway'), // Escaping title
                'type'        => 'textarea',
                'description' => esc_html__('Payment method description that users will see during checkout.', 'highriskshopgateway'), // Escaping description
                'default'     => esc_html__('Pay via credit card', 'highriskshopgateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'simpleswapio_wallet_address' => array(
                'title'       => esc_html__('Wallet Address', 'highriskshopgateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Insert your USDC (Polygon) wallet address to receive instant payouts.', 'highriskshopgateway'), // Escaping description
                'desc_tip'    => true,
            ),
            'icon_url' => array(
                'title'       => esc_html__('Icon URL', 'highriskshopgateway'), // Escaping title
                'type'        => 'url',
                'description' => esc_html__('Enter the URL of the icon image for the payment method.', 'highriskshopgateway'), // Escaping description
                'desc_tip'    => true,
            ),
        );
    }
	 // Add this method to validate the wallet address in wp-admin
    public function process_admin_options() {
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')) {
    WC_Admin_Settings::add_error(__('Nonce verification failed. Please try again.', 'highriskshopgateway'));
    return false;
}
        $simpleswapio_admin_wallet_address = isset($_POST[$this->plugin_id . $this->id . '_simpleswapio_wallet_address']) ? sanitize_text_field( wp_unslash( $_POST[$this->plugin_id . $this->id . '_simpleswapio_wallet_address'])) : '';

        // Check if wallet address starts with "0x"
        if (substr($simpleswapio_admin_wallet_address, 0, 2) !== '0x') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDC Polygon wallet address.', 'highriskshopgateway'));
            return false;
        }

        // Check if wallet address matches the USDC contract address
        if (strtolower($simpleswapio_admin_wallet_address) === '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDC Polygon wallet address.', 'highriskshopgateway'));
            return false;
        }

        // Proceed with the default processing if validations pass
        return parent::process_admin_options();
    }
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $highriskshopgateway_simpleswapio_currency = get_woocommerce_currency();
		$highriskshopgateway_simpleswapio_total = $order->get_total();
		$highriskshopgateway_simpleswapio_nonce = wp_create_nonce( 'highriskshopgateway_simpleswapio_nonce_' . $order_id );
		$highriskshopgateway_simpleswapio_callback = add_query_arg(array('order_id' => $order_id, 'nonce' => $highriskshopgateway_simpleswapio_nonce,), rest_url('highriskshopgateway/v1/highriskshopgateway-simpleswapio/'));
		$highriskshopgateway_simpleswapio_email = urlencode(sanitize_email($order->get_billing_email()));
		$highriskshopgateway_simpleswapio_final_total = $highriskshopgateway_simpleswapio_total;
	
$highriskshopgateway_simpleswapio_gen_wallet = wp_remote_get('https://api.highriskshop.com/control/wallet.php?address=' . $this->simpleswapio_wallet_address .'&callback=' . urlencode($highriskshopgateway_simpleswapio_callback), array('timeout' => 30));

if (is_wp_error($highriskshopgateway_simpleswapio_gen_wallet)) {
    // Handle error
    wc_add_notice(__('Wallet error:', 'woocommerce') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'hrssimpleswapio'), 'error');
    return null;
} else {
	$highriskshopgateway_simpleswapio_wallet_body = wp_remote_retrieve_body($highriskshopgateway_simpleswapio_gen_wallet);
	$highriskshopgateway_simpleswapio_wallet_decbody = json_decode($highriskshopgateway_simpleswapio_wallet_body, true);

 // Check if decoding was successful
    if ($highriskshopgateway_simpleswapio_wallet_decbody && isset($highriskshopgateway_simpleswapio_wallet_decbody['address_in'])) {
        // Store the address_in as a variable
        $highriskshopgateway_simpleswapio_gen_addressIn = wp_kses_post($highriskshopgateway_simpleswapio_wallet_decbody['address_in']);
        $highriskshopgateway_simpleswapio_gen_polygon_addressIn = sanitize_text_field($highriskshopgateway_simpleswapio_wallet_decbody['polygon_address_in']);
		$highriskshopgateway_simpleswapio_gen_callback = sanitize_url($highriskshopgateway_simpleswapio_wallet_decbody['callback_url']);
		// Save $simpleswapioresponse in order meta data
    $order->add_meta_data('highriskshop_simpleswapio_tracking_address', $highriskshopgateway_simpleswapio_gen_addressIn, true);
    $order->add_meta_data('highriskshop_simpleswapio_polygon_temporary_order_wallet_address', $highriskshopgateway_simpleswapio_gen_polygon_addressIn, true);
    $order->add_meta_data('highriskshop_simpleswapio_callback', $highriskshopgateway_simpleswapio_gen_callback, true);
	$order->add_meta_data('highriskshop_simpleswapio_converted_amount', $highriskshopgateway_simpleswapio_final_total, true);
	$order->add_meta_data('highriskshop_simpleswapio_nonce', $highriskshopgateway_simpleswapio_nonce, true);
    $order->save();
    } else {
        wc_add_notice(__('Payment error:', 'woocommerce') . __('Payment could not be processed, please try again (wallet address error)', 'simpleswapio'), 'error');

        return null;
    }
}

// Check if the Checkout page is using Checkout Blocks
if (highriskshopgateway_is_checkout_block()) {
    global $woocommerce;
	$woocommerce->cart->empty_cart();
}

        // Redirect to payment page
        return array(
            'result'   => 'success',
            'redirect' => 'https://pay.highriskshop.com/process-payment.php?address=' . $highriskshopgateway_simpleswapio_gen_addressIn . '&amount=' . (float)$highriskshopgateway_simpleswapio_final_total . '&provider=simpleswap&email=' . $highriskshopgateway_simpleswapio_email . '&currency=' . $highriskshopgateway_simpleswapio_currency,
        );
    }

}

function highriskshop_add_instant_payment_gateway_simpleswap($gateways) {
    $gateways[] = 'HighRiskShop_Instant_Payment_Gateway_Simpleswap';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'highriskshop_add_instant_payment_gateway_simpleswap');
}

// Add custom endpoint for changing order status
function highriskshopgateway_simpleswapio_change_order_status_rest_endpoint() {
    // Register custom route
    register_rest_route( 'highriskshopgateway/v1', '/highriskshopgateway-simpleswapio/', array(
        'methods'  => 'GET',
        'callback' => 'highriskshopgateway_simpleswapio_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action( 'rest_api_init', 'highriskshopgateway_simpleswapio_change_order_status_rest_endpoint' );

// Callback function to change order status
function highriskshopgateway_simpleswapio_change_order_status_callback( $request ) {
    $order_id = absint($request->get_param( 'order_id' ));
	$highriskshopgateway_simpleswapiogetnonce = sanitize_text_field($request->get_param( 'nonce' ));
	$highriskshopgateway_simpleswapiopaid_txid_out = sanitize_text_field($request->get_param('txid_out'));

    // Check if order ID parameter exists
    if ( empty( $order_id ) ) {
        return new WP_Error( 'missing_order_id', __( 'Order ID parameter is missing.', 'highriskshop-instant-payment-gateway-simpleswap' ), array( 'status' => 400 ) );
    }

    // Get order object
    $order = wc_get_order( $order_id );

    // Check if order exists
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', __( 'Invalid order ID.', 'highriskshop-instant-payment-gateway-simpleswap' ), array( 'status' => 404 ) );
    }
	
	// Verify nonce
    if ( empty( $highriskshopgateway_simpleswapiogetnonce ) || $order->get_meta('highriskshop_simpleswapio_nonce', true) !== $highriskshopgateway_simpleswapiogetnonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'highriskshop-instant-payment-gateway-simpleswap' ), array( 'status' => 403 ) );
    }

    // Check if the order is pending and payment method is 'highriskshop-instant-payment-gateway-simpleswap'
    if ( $order && $order->get_status() !== 'processing' && $order->get_status() !== 'completed' && 'highriskshop-instant-payment-gateway-simpleswap' === $order->get_payment_method() ) {
        // Change order status to processing
		$order->payment_complete();
        $order->update_status( 'processing' );
		/* translators: 1: Transaction ID */
		$order->add_order_note( sprintf(__('Payment completed by the provider TXID: %1$s', 'highriskshop-instant-payment-gateway-simpleswap'), $highriskshopgateway_simpleswapiopaid_txid_out) );
        // Return success response
        return array( 'message' => 'Order status changed to processing.' );
    } else {
        // Return error response if conditions are not met
        return new WP_Error( 'order_not_eligible', __( 'Order is not eligible for status change.', 'highriskshop-instant-payment-gateway-simpleswap' ), array( 'status' => 400 ) );
    }
}
?>