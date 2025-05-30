<?php
/**
 * Plugin Name: Instant Approval Payment Gateway with Instant Payouts
 * Plugin URI: https://paygate.to/instant-payment-gateway/
 * Description: Instant Approval High Risk Merchant Gateway with instant payouts to your USDC wallet.
 * Version: 1.2.2
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Tested up to: 6.8
 * WC requires at least: 5.8
 * WC tested up to: 9.8.5
 * Requires PHP: 7.2
 * Author: PayGate.to
 * Author URI: https://paygate.to/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

    // Exit if accessed directly.
    if (!defined('ABSPATH')) {
        exit;
    }

    add_action('before_woocommerce_init', function() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    });
	
	add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Enqueue block assets for the gateway.
 */
function paygatedottogateway_enqueue_block_assets() {
    // Fetch all enabled WooCommerce payment gateways
    $paygatedottogateway_available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    $paygatedottogateway_gateways_data = array();

    foreach ($paygatedottogateway_available_gateways as $gateway_id => $gateway) {
		if (strpos($gateway_id, 'paygatedotto-instant-payment-gateway') === 0) {
        $icon_url = method_exists($gateway, 'paygatedotto_instant_payment_gateway_get_icon_url') ? $gateway->paygatedotto_instant_payment_gateway_get_icon_url() : '';
        $paygatedottogateway_gateways_data[] = array(
            'id' => sanitize_key($gateway_id),
            'label' => sanitize_text_field($gateway->get_title()),
            'description' => wp_kses_post($gateway->get_description()),
            'icon_url' => sanitize_url($icon_url),
        );
		}
    }

    wp_enqueue_script(
        'paygatedottogateway-block-support',
        plugin_dir_url(__FILE__) . 'assets/js/paygatedottogateway-block-checkout-support.js',
        array('wc-blocks-registry', 'wp-element', 'wp-i18n', 'wp-components', 'wp-blocks', 'wp-editor'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/paygatedottogateway-block-checkout-support.js'),
        true
    );

    // Localize script with gateway data
    wp_localize_script(
        'paygatedottogateway-block-support',
        'paygatedottogatewayData',
        $paygatedottogateway_gateways_data
    );
}
add_action('enqueue_block_assets', 'paygatedottogateway_enqueue_block_assets');

/**
 * Enqueue styles for the gateway on checkout page.
 */
function paygatedottogateway_enqueue_styles() {
    if (is_checkout()) {
        wp_enqueue_style(
            'paygatedottogateway-styles',
            plugin_dir_url(__FILE__) . 'assets/css/paygatedottogateway-payment-gateway-styles.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/paygatedottogateway-payment-gateway-styles.css')
        );
    }
}
add_action('wp_enqueue_scripts', 'paygatedottogateway_enqueue_styles');

    include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-hostedpaygatedotto.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-revolut.php'); // Include the payment gateway class	
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-stripe.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-simpleswap.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-rampnetwork.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-mercuryo.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-transak.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-moonpay.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-banxa.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-guardarian.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-particle.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-utorg.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-transfi.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-alchemypay.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-sardine.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-topper.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-unlimit.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-bitnovo.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-robinhood.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-coinbase.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-finchpay.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-upi.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-interac.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-simplex.php'); // Include the payment gateway class
	include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-swipelux.php'); // Include the payment gateway class

	// Conditional function that check if Checkout page use Checkout Blocks
function paygatedottogateway_is_checkout_block() {
    return WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' );
}

function paygatedottogateway_add_notice($paygatedottogateway_message, $paygatedottogateway_notice_type = 'error') {
    // Check if the Checkout page is using Checkout Blocks
    if (paygatedottogateway_is_checkout_block()) {
        // For blocks, throw a WooCommerce exception
        if ($paygatedottogateway_notice_type === 'error') {
            throw new \WC_Data_Exception('checkout_error', esc_html($paygatedottogateway_message)); 
        }
        // Handle other notice types if needed
    } else {
        // Default WooCommerce behavior
        wc_add_notice(esc_html($paygatedottogateway_message), $paygatedottogateway_notice_type); 
    }
}	
	
?>