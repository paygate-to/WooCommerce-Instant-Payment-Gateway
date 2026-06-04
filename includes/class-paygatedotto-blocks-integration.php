<?php

/**
 * WooCommerce Blocks (Cart & Checkout) payment method integration.
 *
 * Registers each PayGate gateway with the block checkout server-side so the
 * Store API recognises it as a real, submittable payment method. Without this,
 * the gateway only registers client-side (display only) and the block checkout
 * sends an empty payment_method, producing a "No payment method provided" error.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class PayGateDotTo_Instant_Payment_Gateway_Blocks_Integration extends AbstractPaymentMethodType
{
    /**
     * The WooCommerce gateway instance this integration wraps.
     *
     * @var WC_Payment_Gateway
     */
    protected $gateway;

    public function __construct($gateway_id, $gateway)
    {
        // The name MUST match the id used client-side in registerPaymentMethod().
        $this->name    = $gateway_id;
        $this->gateway = $gateway;
    }

    /**
     * No settings to load here; data is read live from the gateway instance.
     */
    public function initialize()
    {
    }

    /**
     * Whether this gateway is enabled/available.
     */
    public function is_active()
    {
        return $this->gateway && 'yes' === $this->gateway->enabled;
    }

    /**
     * Register (once) and return the shared client-side script handle.
     */
    public function get_payment_method_script_handles()
    {
        if (function_exists('paygatedottogateway_register_block_checkout_script')) {
            paygatedottogateway_register_block_checkout_script();
        }
        return array('paygatedottogateway-block-support');
    }

    /**
     * Data exposed to the client for this payment method
     * (available in JS via wc.wcSettings.getSetting( '<name>_data' )).
     */
    public function get_payment_method_data()
    {
        $icon_url = method_exists($this->gateway, 'paygatedotto_instant_payment_gateway_get_icon_url')
            ? $this->gateway->paygatedotto_instant_payment_gateway_get_icon_url()
            : '';

        return array(
            'id'          => $this->name,
            'title'       => $this->gateway->get_title(),
            'description' => $this->gateway->get_description(),
            'icon_url'    => $icon_url,
            'supports'    => array('products'),
        );
    }
}
