<?php

/**
 * Plugin Name: Instant Approval Payment Gateway with Instant Payouts
 * Plugin URI: https://paygate.to/instant-payment-gateway/
 * Description: Instant Approval High Risk Merchant Gateway with instant payouts to your USDC wallet.
 * Version: 1.4.7
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Tested up to: 7.1.3
 * WC requires at least: 5.8
 * WC tested up to: 11.2.1
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

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Register (once) the shared block-checkout client script and localise the list
 * of enabled PayGate gateways for it.
 *
 * Called from the block payment integration so WooCommerce loads it within the
 * Cart/Checkout block context only — never on the front end at large and never
 * in the block editor. Dependencies are intentionally minimal: pulling in the
 * editor runtime (wp-editor/wp-blocks) on the storefront makes WooCommerce load
 * its editor "preview cart" (sample products Beanie/Cap) and emit phantom
 * "removed from your cart" notices.
 */
function paygatedottogateway_register_block_checkout_script()
{
    $handle = 'paygatedottogateway-block-support';
    $path   = 'assets/js/paygatedottogateway-block-checkout-support.js';

    if (wp_script_is($handle, 'registered')) {
        return;
    }

    wp_register_script(
        $handle,
        plugin_dir_url(__FILE__) . $path,
        array('wc-blocks-registry', 'wp-element'),
        filemtime(plugin_dir_path(__FILE__) . $path),
        true
    );

    $paygatedottogateway_gateways_data = array();
    foreach (WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway) {
        if (strpos($gateway_id, 'paygatedotto-instant-payment-gateway') === 0 && 'yes' === $gateway->enabled) {
            $icon_url = method_exists($gateway, 'paygatedotto_instant_payment_gateway_get_icon_url') ? $gateway->paygatedotto_instant_payment_gateway_get_icon_url() : '';
            $paygatedottogateway_gateways_data[] = array(
                'id'          => $gateway_id,
                'label'       => sanitize_text_field($gateway->get_title()),
                'description' => wp_kses_post($gateway->get_description()),
                'icon_url'    => sanitize_url($icon_url),
            );
        }
    }

    wp_localize_script($handle, 'paygatedottogatewayData', $paygatedottogateway_gateways_data);
}

/**
 * Register each PayGate gateway with the WooCommerce Cart & Checkout blocks.
 *
 * Server-side registration is required (in addition to the client-side
 * registerPaymentMethod call) so the Store API recognises the gateway as a
 * submittable payment method. Without it the block checkout sends an empty
 * payment_method and fails with "No payment method provided".
 */
add_action('woocommerce_blocks_payment_method_type_registration', function ($paygatedottogateway_payment_method_registry) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-blocks-integration.php';

    foreach (WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway) {
        if (strpos($gateway_id, 'paygatedotto-instant-payment-gateway') === 0) {
            $paygatedottogateway_payment_method_registry->register(
                new PayGateDotTo_Instant_Payment_Gateway_Blocks_Integration($gateway_id, $gateway)
            );
        }
    }
});

/**
 * Enqueue styles for the gateway on checkout page.
 */
function paygatedottogateway_enqueue_styles()
{
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

/**
 * Default PayGate hosts, used when a gateway's custom domain setting is empty.
 */
if (!defined('PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN')) {
    define('PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN', 'api.paygate.to');
}
if (!defined('PAYGATEDOTTOGATEWAY_DEFAULT_CHECKOUT_DOMAIN')) {
    define('PAYGATEDOTTOGATEWAY_DEFAULT_CHECKOUT_DOMAIN', 'checkout.paygate.sbs');
}

/**
 * Normalise a custom domain setting to a bare host (optionally with a path).
 *
 * Strips the scheme, surrounding whitespace and trailing slashes, so admins can
 * paste either "api.example.com" or "https://api.example.com/". Falls back to
 * the given default when the value is empty or not a valid host.
 *
 * @param string $paygatedottogateway_domain  Raw setting value.
 * @param string $paygatedottogateway_default Fallback host.
 * @return string
 */
function paygatedottogateway_sanitize_domain($paygatedottogateway_domain, $paygatedottogateway_default = '')
{
    $paygatedottogateway_domain = trim(sanitize_text_field((string) $paygatedottogateway_domain));
    $paygatedottogateway_domain = preg_replace('#^https?://#i', '', $paygatedottogateway_domain);
    $paygatedottogateway_domain = rtrim($paygatedottogateway_domain, '/');

    if ('' === $paygatedottogateway_domain) {
        return $paygatedottogateway_default;
    }

    $paygatedottogateway_host = wp_parse_url('https://' . $paygatedottogateway_domain, PHP_URL_HOST);
    if (empty($paygatedottogateway_host) || !preg_match('/^[A-Za-z0-9.-]+$/', $paygatedottogateway_host)) {
        return $paygatedottogateway_default;
    }

    return $paygatedottogateway_domain;
}

/**
 * Read a gateway's configured API domain outside the gateway instance (e.g. in
 * the REST payment callback), falling back to the default PayGate API host.
 *
 * @param string $paygatedottogateway_gateway_id Gateway id.
 * @param string $paygatedottogateway_option_key Settings key holding the API domain.
 * @return string
 */
function paygatedottogateway_get_api_domain($paygatedottogateway_gateway_id, $paygatedottogateway_option_key)
{
    $paygatedottogateway_settings = get_option('woocommerce_' . $paygatedottogateway_gateway_id . '_settings', array());
    $paygatedottogateway_value    = (is_array($paygatedottogateway_settings) && isset($paygatedottogateway_settings[$paygatedottogateway_option_key]))
        ? $paygatedottogateway_settings[$paygatedottogateway_option_key]
        : '';

    return paygatedottogateway_sanitize_domain($paygatedottogateway_value, PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN);
}

/**
 * Build a PayGate API URL on the configured (custom or default) API domain.
 *
 * Query values are RFC 3986 encoded via add_query_arg() and the final URL is
 * passed through esc_url_raw() before being used in wp_remote_get().
 *
 * @param string $paygatedottogateway_domain Sanitised API domain.
 * @param string $paygatedottogateway_path   Endpoint path, e.g. "control/wallet.php".
 * @param array  $paygatedottogateway_args   Query arguments (unencoded).
 * @return string
 */
function paygatedottogateway_api_url($paygatedottogateway_domain, $paygatedottogateway_path, $paygatedottogateway_args = array())
{
    if ('' === (string) $paygatedottogateway_domain) {
        $paygatedottogateway_domain = PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN;
    }

    $paygatedottogateway_url = 'https://' . $paygatedottogateway_domain . '/' . ltrim($paygatedottogateway_path, '/');

    if (!empty($paygatedottogateway_args)) {
        $paygatedottogateway_url = add_query_arg(array_map('rawurlencode', array_map('strval', $paygatedottogateway_args)), $paygatedottogateway_url);
    }

    return esc_url_raw($paygatedottogateway_url);
}

/**
 * Admin notice: while an enabled PayGate gateway still uses the shared api.paygate.to,
 * point the store manager to a free dedicated API endpoint. Dismissed per user.
 */
function paygatedottogateway_endpoint_notice()
{
    if (!current_user_can('manage_woocommerce') || get_user_meta(get_current_user_id(), 'paygatedottogateway_endpoint_notice_dismissed', true)) {
        return;
    }

    $paygatedottogateway_gateways = array(
        'paygatedotto-instant-payment-gateway-hostedpaygatedotto' => 'hostedpaygatedotto_custom_api_domain',
        'paygatedotto-instant-payment-gateway-customprovider'     => 'customprovider_custom_api_domain',
    );
    $paygatedottogateway_section = '';
    foreach ($paygatedottogateway_gateways as $paygatedottogateway_id => $paygatedottogateway_key) {
        $paygatedottogateway_settings = get_option('woocommerce_' . $paygatedottogateway_id . '_settings', array());
        if (is_array($paygatedottogateway_settings) && isset($paygatedottogateway_settings['enabled']) && 'yes' === $paygatedottogateway_settings['enabled']
            && PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN === paygatedottogateway_get_api_domain($paygatedottogateway_id, $paygatedottogateway_key)) {
            $paygatedottogateway_section = $paygatedottogateway_id;
            break;
        }
    }
    if ('' === $paygatedottogateway_section) {
        return;
    }

    $paygatedottogateway_guide_url    = 'https://paygate.to/white-label-api-custom-domain-guide/#dedicated-endpoint';
    $paygatedottogateway_settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $paygatedottogateway_section);
    $paygatedottogateway_dismiss_url  = wp_nonce_url(add_query_arg('paygatedottogateway_dismiss_endpoint_notice', '1'), 'paygatedottogateway_dismiss_endpoint_notice');

    echo '<div class="notice notice-info"><p><strong>' . esc_html__('PayGate: protect your payments with a dedicated API endpoint.', 'instant-approval-payment-gateway') . '</strong> '
        . esc_html__('Your store still uses the shared api.paygate.to. A free DDoS-protected endpoint of your own keeps your payments running when the shared address is under attack.', 'instant-approval-payment-gateway') . '</p><p>'
        . sprintf(
            /* translators: 1: "Get your dedicated endpoint" link, 2: "Custom API Domain" field name, 3: "PayGate settings" link */
            esc_html__('%1$s (about one minute), then paste it into %2$s in %3$s.', 'instant-approval-payment-gateway'),
            '<a href="' . esc_url($paygatedottogateway_guide_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Get your dedicated endpoint', 'instant-approval-payment-gateway') . '</a>',
            '<strong>' . esc_html__('Custom API Domain', 'instant-approval-payment-gateway') . '</strong>',
            '<a href="' . esc_url($paygatedottogateway_settings_url) . '">' . esc_html__('PayGate settings', 'instant-approval-payment-gateway') . '</a>'
        )
        . ' <a href="' . esc_url($paygatedottogateway_dismiss_url) . '">' . esc_html__('Dismiss', 'instant-approval-payment-gateway') . '</a></p></div>';
}
add_action('admin_notices', 'paygatedottogateway_endpoint_notice');

/**
 * Remember that the current user dismissed the dedicated endpoint notice.
 */
function paygatedottogateway_dismiss_endpoint_notice()
{
    if (!isset($_GET['paygatedottogateway_dismiss_endpoint_notice']) || !current_user_can('manage_woocommerce')) {
        return;
    }
    check_admin_referer('paygatedottogateway_dismiss_endpoint_notice');
    update_user_meta(get_current_user_id(), 'paygatedottogateway_endpoint_notice_dismissed', 1);
    wp_safe_redirect(remove_query_arg(array('paygatedottogateway_dismiss_endpoint_notice', '_wpnonce')));
    exit;
}
add_action('admin_init', 'paygatedottogateway_dismiss_endpoint_notice');

include_once(plugin_dir_path(__FILE__) . 'includes/paygatedottogateway-callback-signature.php'); // PayGate callback signature verification (loads before the gateways)
include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-hostedpaygatedotto.php'); // Include the payment gateway class
include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-instant-payment-gateway-customprovider.php'); // Include the payment gateway class

// Conditional function that check if Checkout page use Checkout Blocks
function paygatedottogateway_is_checkout_block()
{
    return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout');
}

function paygatedottogateway_add_notice($paygatedottogateway_message, $paygatedottogateway_notice_type = 'error')
{
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


/**
 * Whether the given order belongs to one of this plugin's payment methods.
 */
function paygatedottogateway_is_plugin_order($order)
{
    return $order instanceof WC_Order
        && strpos((string) $order->get_payment_method(), 'paygatedotto-instant-payment-gateway') === 0;
}

/**
 * Remember which customer session placed a PayGate order.
 *
 * The payment provider confirms payment via a server-to-server callback and the
 * customer is NOT redirected back to the store (no return URL / no thank-you
 * page). So when payment completes we have no access to the buyer's session and
 * cannot clear their cart unless we recorded the session key at checkout time.
 * Runs in the customer's session for both classic and block checkout.
 */
function paygatedottogateway_record_order_session($order)
{
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }
    if (! paygatedottogateway_is_plugin_order($order)) {
        return;
    }
    if (function_exists('WC') && WC()->session) {
        $paygatedottogateway_session_key = WC()->session->get_customer_id();
        if ($paygatedottogateway_session_key) {
            $order->update_meta_data('_paygatedotto_session_key', $paygatedottogateway_session_key);
            $order->save();
        }
    }
}
add_action('woocommerce_checkout_order_processed', 'paygatedottogateway_record_order_session', 20, 1);
add_action('woocommerce_store_api_checkout_order_processed', 'paygatedottogateway_record_order_session', 20, 1);

/**
 * Older WooCommerce versions (before 7.0) may not fire the Store API action above for
 * block checkout, so also listen to the legacy Blocks action there. It is deprecated
 * since WooCommerce Blocks 7.2.0 and logs a notice on current versions, so it is only
 * hooked on those older versions.
 */
function paygatedottogateway_hook_legacy_blocks_order_processed()
{
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '7.0', '<')) {
        add_action('woocommerce_blocks_checkout_order_processed', 'paygatedottogateway_record_order_session', 20, 1);
    }
}
add_action('plugins_loaded', 'paygatedottogateway_hook_legacy_blocks_order_processed');

/**
 * Empty the cart belonging to a specific WooCommerce session key.
 *
 * Used from the payment-confirmation callback, which runs outside the buyer's
 * session, so WC()->cart cannot be used directly. Clears the cart-related keys
 * in the stored session row and busts the session cache.
 */
function paygatedottogateway_clear_session_cart($paygatedottogateway_session_key)
{
    global $wpdb;

    if (empty($paygatedottogateway_session_key)) {
        return;
    }

    /*
     * The table name is composed solely from $wpdb->prefix (a trusted,
     * server-controlled value) and a hard-coded string literal — no
     * user-supplied input is involved. esc_sql() is applied as an extra
     * safety layer to satisfy static-analysis tools.
     */
    $paygatedottogateway_sessions_table = esc_sql( $wpdb->prefix . 'woocommerce_sessions' );

    $paygatedottogateway_cache_group = defined('WC_SESSION_CACHE_GROUP') ? WC_SESSION_CACHE_GROUP : 'wc_session_id';
    $paygatedottogateway_cache_key   = 'paygatedotto_sess_' . md5($paygatedottogateway_session_key);

    $paygatedottogateway_session_value = wp_cache_get($paygatedottogateway_cache_key, $paygatedottogateway_cache_group);

    if (false === $paygatedottogateway_session_value) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $paygatedottogateway_session_value = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT session_value FROM {$paygatedottogateway_sessions_table} WHERE session_key = %s",
                $paygatedottogateway_session_key
            )
        );
        wp_cache_set($paygatedottogateway_cache_key, $paygatedottogateway_session_value, $paygatedottogateway_cache_group, 300);
    }

    if (null === $paygatedottogateway_session_value || false === $paygatedottogateway_session_value) {
        return;
    }

    $paygatedottogateway_session_data = maybe_unserialize($paygatedottogateway_session_value);
    if (! is_array($paygatedottogateway_session_data)) {
        return;
    }

    // Each session value is itself serialised (see WC_Session::set()).
    $paygatedottogateway_empty = maybe_serialize(array());
    foreach (array('cart', 'cart_totals', 'applied_coupons', 'coupon_discount_totals', 'coupon_discount_tax_totals', 'removed_cart_contents') as $paygatedottogateway_key) {
        if (isset($paygatedottogateway_session_data[$paygatedottogateway_key])) {
            $paygatedottogateway_session_data[$paygatedottogateway_key] = $paygatedottogateway_empty;
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $wpdb->update(
        $paygatedottogateway_sessions_table,
        array('session_value' => maybe_serialize($paygatedottogateway_session_data)),
        array('session_key' => $paygatedottogateway_session_key)
    );

    // Invalidate our cache entry and bust WooCommerce's cached copy of the session.
    wp_cache_delete($paygatedottogateway_cache_key, $paygatedottogateway_cache_group);
    wp_cache_delete($paygatedottogateway_session_key, $paygatedottogateway_cache_group);
}

/**
 * Clear the buyer's cart once a PayGate order is actually paid.
 *
 * Fires on payment_complete(), which this plugin's providers call from their
 * payment-confirmation callbacks. Works for every checkout type (classic,
 * shortcode, page builder, blocks). Unpaid/abandoned orders are never touched,
 * so an abandoned checkout keeps its cart for an easy retry.
 */
function paygatedottogateway_empty_cart_on_payment_complete($order_id)
{
    $order = wc_get_order($order_id);
    if (! paygatedottogateway_is_plugin_order($order)) {
        return;
    }

    // Logged-in buyers: remove the persistent cart so it is not restored later.
    $paygatedottogateway_user_id = $order->get_customer_id();
    if ($paygatedottogateway_user_id) {
        delete_user_meta($paygatedottogateway_user_id, '_woocommerce_persistent_cart_' . get_current_blog_id());
    }

    // Clear the originating session's cart (covers guests and logged-in buyers).
    paygatedottogateway_clear_session_cart($order->get_meta('_paygatedotto_session_key'));

    // If this ever runs inside the buyer's own session, clear the live cart too.
    if (function_exists('WC') && WC()->cart) {
        WC()->cart->empty_cart();
    }
}
add_action('woocommerce_payment_complete', 'paygatedottogateway_empty_cart_on_payment_complete', 20, 1);

/**
 * Safety net: if a store IS configured so the buyer lands on the order-received
 * page, clear the cart there too. Harmless for the callback-only flow.
 */
add_action('woocommerce_thankyou', function ($order_id) {
    if (! $order_id) {
        return;
    }
    $order = wc_get_order($order_id);
    if (! paygatedottogateway_is_plugin_order($order)) {
        return;
    }
    if (WC()->cart && ! WC()->cart->is_empty()) {
        WC()->cart->empty_cart();
    }
});
?>