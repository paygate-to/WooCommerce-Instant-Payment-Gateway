<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'paygatedottogateway_customprovider_init_gateway');

/**
 * Read PayGate's live provider list (control/provider-status/) server side, through the
 * gateway's API domain, falling back to the shared API host if that fails. Not cached, so
 * the settings page always shows the current list, status and minimums.
 *
 * @param string $paygatedottogateway_api_domain Sanitised API domain.
 * @return array|WP_Error map of provider id => array('name', 'status', 'minimum_amount', 'minimum_currency').
 */
function paygatedottogateway_customprovider_fetch_providers($paygatedottogateway_api_domain) {
    $paygatedottogateway_domains = array_unique(array_filter(array((string) $paygatedottogateway_api_domain, PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN)));
    $paygatedottogateway_error   = new WP_Error('paygatedottogateway_provider_status', __('The provider list could not be loaded.', 'instant-approval-payment-gateway'));

    foreach ($paygatedottogateway_domains as $paygatedottogateway_domain) {
        $paygatedottogateway_response = wp_remote_get(paygatedottogateway_api_url($paygatedottogateway_domain, 'control/provider-status/'), array('timeout' => 15));
        if (is_wp_error($paygatedottogateway_response)) {
            $paygatedottogateway_error = $paygatedottogateway_response;
            continue;
        }
        if (200 !== (int) wp_remote_retrieve_response_code($paygatedottogateway_response)) {
            continue;
        }
        $paygatedottogateway_data = json_decode(wp_remote_retrieve_body($paygatedottogateway_response), true);
        if (!is_array($paygatedottogateway_data) || empty($paygatedottogateway_data['providers']) || !is_array($paygatedottogateway_data['providers'])) {
            continue;
        }

        $paygatedottogateway_providers = array();
        foreach ($paygatedottogateway_data['providers'] as $paygatedottogateway_provider) {
            if (!is_array($paygatedottogateway_provider) || empty($paygatedottogateway_provider['id'])) {
                continue;
            }
            $paygatedottogateway_id = sanitize_key($paygatedottogateway_provider['id']);
            if ('' === $paygatedottogateway_id) {
                continue;
            }
            $paygatedottogateway_providers[$paygatedottogateway_id] = array(
                'name'             => isset($paygatedottogateway_provider['provider_name']) ? sanitize_text_field($paygatedottogateway_provider['provider_name']) : $paygatedottogateway_id,
                'status'           => isset($paygatedottogateway_provider['status']) ? sanitize_key($paygatedottogateway_provider['status']) : '',
                'minimum_amount'   => (isset($paygatedottogateway_provider['minimum_amount']) && is_numeric($paygatedottogateway_provider['minimum_amount'])) ? 0 + $paygatedottogateway_provider['minimum_amount'] : '',
                'minimum_currency' => isset($paygatedottogateway_provider['minimum_currency']) ? strtoupper(sanitize_text_field($paygatedottogateway_provider['minimum_currency'])) : '',
            );
        }
        if (!empty($paygatedottogateway_providers)) {
            return $paygatedottogateway_providers;
        }
    }

    return $paygatedottogateway_error;
}

function paygatedottogateway_customprovider_init_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

class PayGateDotTo_Instant_Payment_Gateway_Customprovider extends WC_Payment_Gateway {

    protected $icon_url;
    protected $customprovider_wallet_address;
    protected $customprovider_custom_domain;
    protected $customprovider_custom_api_domain;
	protected $customprovider_custom_provider_tag;
	protected $customprovider_providers;
	protected $customprovider_custom_minimum_amount;
	protected $customprovider_tolerance_percentage;

    public function __construct() {
        $this->id                 = 'paygatedotto-instant-payment-gateway-customprovider';
        $this->icon = sanitize_url($this->get_option('icon_url'));
        $this->method_title       = esc_html__('Instant Approval Payment Gateway with Instant Payouts (custom provider mode)', 'instant-approval-payment-gateway'); // Escaping title
        $this->method_description = esc_html__('Instant Approval High Risk Merchant Gateway with instant payouts to your USDC POLYGON wallet using the providers you select', 'instant-approval-payment-gateway'); // Escaping description
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->description = sanitize_text_field($this->get_option('description'));

        // Use the configured settings for redirect and icon URLs
        $this->customprovider_custom_domain = paygatedottogateway_sanitize_domain($this->get_option('customprovider_custom_domain'), PAYGATEDOTTOGATEWAY_DEFAULT_CHECKOUT_DOMAIN);
        $this->customprovider_custom_api_domain = paygatedottogateway_sanitize_domain($this->get_option('customprovider_custom_api_domain'), PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN);
        $this->customprovider_wallet_address = sanitize_text_field($this->get_option('customprovider_wallet_address'));
		$this->customprovider_custom_provider_tag = sanitize_text_field($this->get_option('customprovider_custom_provider_tag'));
		$this->customprovider_providers = $this->paygatedotto_customprovider_get_selected_providers();
		// With more than one provider selected the customer picks one at checkout.
		$this->has_fields = count($this->customprovider_providers) > 1;
		$this->customprovider_tolerance_percentage = sanitize_text_field($this->get_option('customprovider_tolerance_percentage'));
		$this->customprovider_custom_minimum_amount = floatval($this->get_option('customprovider_custom_minimum_amount'));
        $this->icon_url     = sanitize_url($this->get_option('icon_url'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'instant-approval-payment-gateway'), // Escaping title
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable custom provider payment gateway', 'instant-approval-payment-gateway'), // Escaping label
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
                'default'     => esc_html__('Credit card', 'instant-approval-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'customprovider_custom_domain' => array(
                'title'       => esc_html__('Custom Domain', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => sprintf(
    /* translators: %s: link to the custom domain guide */
    esc_html__('Follow the %s to use your own domain name for the checkout pages and links.', 'instant-approval-payment-gateway'),
    '<a href="' . esc_url('https://paygate.to/white-label-api-custom-domain-guide/') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('custom domain guide', 'instant-approval-payment-gateway') . '</a>'
), // Escaping description
                'default'     => esc_html__('checkout.paygate.sbs', 'instant-approval-payment-gateway'), // Escaping default value
                'desc_tip'    => false,
            ),
            'customprovider_custom_api_domain' => array(
                'title'       => esc_html__('Custom API Domain', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => sprintf(
    /* translators: %s: link to the custom domain guide */
    esc_html__('Paste your free dedicated API endpoint from the %s (for example api-yourshop-1a2b….paygate.to) to keep payments running when the shared api.paygate.to is under attack. You can also use your own white-label domain. Leave as api.paygate.to to use the shared API.', 'instant-approval-payment-gateway'),
    '<a href="' . esc_url('https://paygate.to/white-label-api-custom-domain-guide/#dedicated-endpoint') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('custom domain guide', 'instant-approval-payment-gateway') . '</a>'
), // Escaping description
                'default'     => PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN,
                'placeholder' => PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN,
                'desc_tip'    => false,
            ),
            'customprovider_wallet_address' => array(
                'title'       => esc_html__('Wallet Address', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Insert your USDC (Polygon) wallet address to receive instant payouts. Payouts maybe sent in ETH or USDC or USDT (Polygon or BEP-20) or POL native token. Same wallet should work to receive all. Make sure you use a self-custodial wallet to receive payouts.', 'instant-approval-payment-gateway'), // Escaping description
                'desc_tip'    => true,
            ),
			'customprovider_providers' => array(
                'title'       => esc_html__('Payment Providers', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'paygatedotto_providers',
                'description' => esc_html__('Tick one or more providers. With one provider customers go straight to it; with more, they choose one at checkout. The list, status and minimums are loaded live from PayGate each time this page opens.', 'instant-approval-payment-gateway'), // Escaping description
                'default'     => '',
				'desc_tip'    => false,
            ),
			'customprovider_custom_minimum_amount' => array(
                'title'       => esc_html__('Minimum Amount', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'number',
                'description' => esc_html__('Minimum allowed order amount in USD for this payment method. Each provider\'s own minimum is checked too.', 'instant-approval-payment-gateway'), // Escaping description
                'default'     => esc_html__('15', 'instant-approval-payment-gateway'), // Escaping default value
				'desc_tip'    => true,
            ),
			'customprovider_tolerance_percentage' => array(
                'title'       => esc_html__('Fees Tolerance', 'instant-approval-payment-gateway'),
                'type'        => 'select',
                'description' => esc_html__('Select percentage to tolerate higher provider fees while detecting manipulation by bad customers at checkout page.', 'instant-approval-payment-gateway'),
                'desc_tip'    => true,
                'default'     => '0.60',
                'options'     => array(
                    '0.90'    => '10%',
                    '0.80' => '20%',
                    '0.70' => '30%',
                    '0.60' => '40%',
                    '0.50' => '50%',
                    '0.40' => '60%',
                    '0.30' => '70%',
                    '0.20' => '80%',
                    '0.10' => '90%',
                    '0' => 'Disabled amount detection'
                ),
            ),
            'icon_url' => array(
                'title'       => esc_html__('Icon URL', 'instant-approval-payment-gateway'), // Escaping title
                'type'        => 'url',
                'description' => esc_html__('Enter the URL of the icon image for the payment method.', 'instant-approval-payment-gateway'), // Escaping description
                'desc_tip'    => true,
            ),
        );
    }
    /**
     * Normalise the checkout domain on save (WC_Settings_API validate_{key}_field hook).
     */
    public function validate_customprovider_custom_domain_field($key, $value) {
        return paygatedottogateway_sanitize_domain(is_null($value) ? '' : wp_unslash($value), PAYGATEDOTTOGATEWAY_DEFAULT_CHECKOUT_DOMAIN);
    }

    /**
     * Normalise the API domain on save (WC_Settings_API validate_{key}_field hook).
     */
    public function validate_customprovider_custom_api_domain_field($key, $value) {
        return paygatedottogateway_sanitize_domain(is_null($value) ? '' : wp_unslash($value), PAYGATEDOTTOGATEWAY_DEFAULT_API_DOMAIN);
    }

    /**
     * The provider ids the merchant selected, in the order they were saved.
     *
     * Versions before 1.4.8 stored a single tag in "customprovider_custom_provider_tag";
     * that tag is used until the merchant saves a selection in the new list.
     *
     * @return array
     */
    public function paygatedotto_customprovider_get_selected_providers() {
        $paygatedottogateway_customprovider_saved = $this->get_option('customprovider_providers');
        if (is_array($paygatedottogateway_customprovider_saved) && !empty($paygatedottogateway_customprovider_saved)) {
            return array_values(array_unique(array_filter(array_map('sanitize_key', $paygatedottogateway_customprovider_saved))));
        }
        $paygatedottogateway_customprovider_legacy = sanitize_key($this->get_option('customprovider_custom_provider_tag'));
        return ('' !== $paygatedottogateway_customprovider_legacy) ? array($paygatedottogateway_customprovider_legacy) : array();
    }

    /**
     * Name and minimum of each selected provider, as listed by PayGate when the settings were last saved.
     *
     * @return array map of provider id => array('name', 'status', 'minimum_amount', 'minimum_currency').
     */
    public function paygatedotto_customprovider_get_provider_info() {
        $paygatedottogateway_customprovider_info = $this->get_option('customprovider_provider_info');
        return is_array($paygatedottogateway_customprovider_info) ? $paygatedottogateway_customprovider_info : array();
    }

    /**
     * The selected providers as shown to customers at checkout.
     *
     * @return array list of array('id', 'label').
     */
    public function paygatedotto_customprovider_get_checkout_providers() {
        $paygatedottogateway_customprovider_info = $this->paygatedotto_customprovider_get_provider_info();
        $paygatedottogateway_customprovider_out  = array();
        foreach ($this->customprovider_providers as $paygatedottogateway_customprovider_id) {
            $paygatedottogateway_customprovider_out[] = array(
                'id'    => $paygatedottogateway_customprovider_id,
                'label' => !empty($paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_id]['name']) ? sanitize_text_field($paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_id]['name']) : ucfirst($paygatedottogateway_customprovider_id),
            );
        }
        return $paygatedottogateway_customprovider_out;
    }

    /**
     * Settings field: the live provider list from PayGate's provider-status endpoint,
     * fetched server side every time the settings page loads, with a checkbox per provider.
     */
    public function generate_paygatedotto_providers_html($key, $data) {
        $paygatedottogateway_customprovider_field_key = $this->get_field_key($key);
        $paygatedottogateway_customprovider_selected  = $this->paygatedotto_customprovider_get_selected_providers();
        $paygatedottogateway_customprovider_info      = $this->paygatedotto_customprovider_get_provider_info();
        $paygatedottogateway_customprovider_live      = paygatedottogateway_customprovider_fetch_providers($this->customprovider_custom_api_domain);
        $paygatedottogateway_customprovider_statuses  = array(
            'active'   => array(esc_html__('Active', 'instant-approval-payment-gateway'), '#008a20'),
            'unstable' => array(esc_html__('Unstable', 'instant-approval-payment-gateway'), '#b26200'),
            'disabled' => array(esc_html__('Disabled', 'instant-approval-payment-gateway'), '#d63638'),
        );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($data['title']); ?></label>
            </th>
            <td class="forminp">
                <?php if (is_wp_error($paygatedottogateway_customprovider_live)) : ?>
                    <p style="color:#d63638;"><?php echo esc_html__('Could not load the provider list from PayGate right now. Reload this page in a moment. Your saved providers are kept when you save other settings.', 'instant-approval-payment-gateway'); ?></p>
                    <?php if (!empty($paygatedottogateway_customprovider_selected)) : ?>
                        <p><?php echo esc_html__('Saved providers:', 'instant-approval-payment-gateway') . ' ' . esc_html(implode(', ', $paygatedottogateway_customprovider_selected)); ?></p>
                    <?php endif; ?>
                <?php else :
                    // Saved providers PayGate no longer lists stay visible so the merchant can untick them.
                    $paygatedottogateway_customprovider_rows = $paygatedottogateway_customprovider_live;
                    foreach ($paygatedottogateway_customprovider_selected as $paygatedottogateway_customprovider_id) {
                        if (!isset($paygatedottogateway_customprovider_rows[$paygatedottogateway_customprovider_id])) {
                            $paygatedottogateway_customprovider_rows[$paygatedottogateway_customprovider_id] = array(
                                'name'             => !empty($paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_id]['name']) ? $paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_id]['name'] : $paygatedottogateway_customprovider_id,
                                'status'           => 'unlisted',
                                'minimum_amount'   => '',
                                'minimum_currency' => '',
                            );
                        }
                    }
                    $paygatedottogateway_customprovider_warn = false;
                    ?>
                    <input type="hidden" name="<?php echo esc_attr($paygatedottogateway_customprovider_field_key); ?>_loaded" value="1" />
                    <table class="widefat striped" style="max-width:640px;">
                        <thead>
                            <tr>
                                <th style="width:48px;"><?php echo esc_html__('Use', 'instant-approval-payment-gateway'); ?></th>
                                <th><?php echo esc_html__('Provider', 'instant-approval-payment-gateway'); ?></th>
                                <th><?php echo esc_html__('Status', 'instant-approval-payment-gateway'); ?></th>
                                <th><?php echo esc_html__('Minimum order', 'instant-approval-payment-gateway'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paygatedottogateway_customprovider_rows as $paygatedottogateway_customprovider_id => $paygatedottogateway_customprovider_row) :
                            $paygatedottogateway_customprovider_checked = in_array($paygatedottogateway_customprovider_id, $paygatedottogateway_customprovider_selected, true);
                            $paygatedottogateway_customprovider_status  = $paygatedottogateway_customprovider_row['status'];
                            if ($paygatedottogateway_customprovider_checked && 'active' !== $paygatedottogateway_customprovider_status) {
                                $paygatedottogateway_customprovider_warn = true;
                            }
                            if ('unlisted' === $paygatedottogateway_customprovider_status) {
                                $paygatedottogateway_customprovider_badge = array(esc_html__('No longer listed', 'instant-approval-payment-gateway'), '#d63638');
                            } elseif (isset($paygatedottogateway_customprovider_statuses[$paygatedottogateway_customprovider_status])) {
                                $paygatedottogateway_customprovider_badge = $paygatedottogateway_customprovider_statuses[$paygatedottogateway_customprovider_status];
                            } else {
                                $paygatedottogateway_customprovider_badge = array(ucfirst($paygatedottogateway_customprovider_status), '#b26200');
                            }
                            // A disabled provider cannot be newly ticked; one that is already selected can still be unticked.
                            $paygatedottogateway_customprovider_locked = ('disabled' === $paygatedottogateway_customprovider_status && !$paygatedottogateway_customprovider_checked);
                            ?>
                            <tr>
                                <td style="text-align:center;">
                                    <input type="checkbox"
                                           id="<?php echo esc_attr($paygatedottogateway_customprovider_field_key . '_' . $paygatedottogateway_customprovider_id); ?>"
                                           name="<?php echo esc_attr($paygatedottogateway_customprovider_field_key); ?>[]"
                                           value="<?php echo esc_attr($paygatedottogateway_customprovider_id); ?>"
                                           <?php checked($paygatedottogateway_customprovider_checked); ?>
                                           <?php disabled($paygatedottogateway_customprovider_locked); ?> />
                                </td>
                                <td>
                                    <label for="<?php echo esc_attr($paygatedottogateway_customprovider_field_key . '_' . $paygatedottogateway_customprovider_id); ?>"><?php echo esc_html($paygatedottogateway_customprovider_row['name']); ?></label>
                                    <code style="margin-left:6px;"><?php echo esc_html($paygatedottogateway_customprovider_id); ?></code>
                                </td>
                                <td><span style="color:<?php echo esc_attr($paygatedottogateway_customprovider_badge[1]); ?>;font-weight:600;"><?php echo esc_html($paygatedottogateway_customprovider_badge[0]); ?></span></td>
                                <td><?php echo ('' !== $paygatedottogateway_customprovider_row['minimum_amount']) ? esc_html($paygatedottogateway_customprovider_row['minimum_amount'] . ' ' . $paygatedottogateway_customprovider_row['minimum_currency']) : '&ndash;'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($paygatedottogateway_customprovider_warn) : ?>
                        <p style="color:#b26200;"><?php echo esc_html__('Some selected providers are not active right now. Customers may not be able to pay with them until they are active again, so consider unticking them.', 'instant-approval-payment-gateway'); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php echo $this->get_description_html($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WooCommerce. ?>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Save the ticked providers (WC_Settings_API validate_{key}_field hook) together with
     * each provider's name and minimum, read fresh from PayGate.
     */
    public function validate_customprovider_providers_field($key, $value) {
        $paygatedottogateway_customprovider_previous = $this->paygatedotto_customprovider_get_selected_providers();
        $paygatedottogateway_customprovider_old_info = $this->paygatedotto_customprovider_get_provider_info();

        // The list could not be loaded when the page opened, so no checkboxes were shown: keep the saved selection.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in process_admin_options().
        if (!isset($_POST[$this->get_field_key($key) . '_loaded'])) {
            return $paygatedottogateway_customprovider_previous;
        }

        $paygatedottogateway_customprovider_posted = is_array($value) ? array_values(array_unique(array_filter(array_map('sanitize_key', wp_unslash($value))))) : array();
        $paygatedottogateway_customprovider_live   = paygatedottogateway_customprovider_fetch_providers($this->customprovider_custom_api_domain);
        $paygatedottogateway_customprovider_known  = is_wp_error($paygatedottogateway_customprovider_live) ? array() : $paygatedottogateway_customprovider_live;

        $paygatedottogateway_customprovider_ids  = array();
        $paygatedottogateway_customprovider_info = array();
        foreach ($paygatedottogateway_customprovider_posted as $paygatedottogateway_customprovider_id) {
            if (isset($paygatedottogateway_customprovider_known[$paygatedottogateway_customprovider_id])) {
                $paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_id] = $paygatedottogateway_customprovider_known[$paygatedottogateway_customprovider_id];
            } elseif (in_array($paygatedottogateway_customprovider_id, $paygatedottogateway_customprovider_previous, true)) {
                // Kept from before (no longer listed, or the list failed to load just now).
                $paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_id] = isset($paygatedottogateway_customprovider_old_info[$paygatedottogateway_customprovider_id]) ? $paygatedottogateway_customprovider_old_info[$paygatedottogateway_customprovider_id] : array();
            } else {
                continue;
            }
            $paygatedottogateway_customprovider_ids[] = $paygatedottogateway_customprovider_id;
        }

        if (empty($paygatedottogateway_customprovider_ids)) {
            WC_Admin_Settings::add_error(esc_html__('Please tick at least one payment provider. Your previous selection was kept.', 'instant-approval-payment-gateway'));
            return $paygatedottogateway_customprovider_previous;
        }

        // Stored next to the selection in the same settings option.
        $this->settings['customprovider_provider_info'] = $paygatedottogateway_customprovider_info;
        return $paygatedottogateway_customprovider_ids;
    }

    /**
     * Only offered at checkout when at least one provider is selected.
     */
    public function is_available() {
        if (empty($this->customprovider_providers)) {
            return false;
        }
        return parent::is_available();
    }

    /**
     * Checkout: the description, plus a choice of provider when more than one is selected.
     */
    public function payment_fields() {
        parent::payment_fields();

        $paygatedottogateway_customprovider_list = $this->paygatedotto_customprovider_get_checkout_providers();
        if (count($paygatedottogateway_customprovider_list) < 2) {
            return;
        }

        echo '<div class="paygatedottogateway-provider-select">';
        echo '<p>' . esc_html__('Choose how you want to pay:', 'instant-approval-payment-gateway') . '</p>';
        $paygatedottogateway_customprovider_first = true;
        foreach ($paygatedottogateway_customprovider_list as $paygatedottogateway_customprovider_row) {
            echo '<label class="paygatedottogateway-provider-option" style="' . esc_attr('display:flex;align-items:center;gap:8px;margin:4px 0;') . '">';
            echo '<input type="radio" name="paygatedotto_selected_provider" value="' . esc_attr($paygatedottogateway_customprovider_row['id']) . '" ' . checked($paygatedottogateway_customprovider_first, true, false) . ' />';
            echo '<span>' . esc_html($paygatedottogateway_customprovider_row['label']) . '</span>';
            echo '</label>';
            $paygatedottogateway_customprovider_first = false;
        }
        echo '</div>';
    }

    /**
     * The provider the customer chose, or the only selected one. Empty when the choice is missing or not allowed.
     */
    protected function paygatedotto_customprovider_get_chosen_provider() {
        if (count($this->customprovider_providers) === 1) {
            return $this->customprovider_providers[0];
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
        $paygatedottogateway_customprovider_posted = isset($_POST['paygatedotto_selected_provider']) ? sanitize_key(wp_unslash($_POST['paygatedotto_selected_provider'])) : '';
        return in_array($paygatedottogateway_customprovider_posted, $this->customprovider_providers, true) ? $paygatedottogateway_customprovider_posted : '';
    }

    public function validate_fields() {
        if ('' === $this->paygatedotto_customprovider_get_chosen_provider()) {
            paygatedottogateway_add_notice(__('Please choose how you want to pay.', 'instant-approval-payment-gateway'), 'error');
            return false;
        }
        return true;
    }

	 // Add this method to validate the wallet address in wp-admin
    public function process_admin_options() {
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')) {
    WC_Admin_Settings::add_error(__('Nonce verification failed. Please try again.', 'instant-approval-payment-gateway'));
    return false;
}
        $customprovider_admin_wallet_address = isset($_POST[$this->plugin_id . $this->id . '_customprovider_wallet_address']) ? sanitize_text_field( wp_unslash( $_POST[$this->plugin_id . $this->id . '_customprovider_wallet_address'])) : '';

        // Check if wallet address starts with "0x"
        if (substr($customprovider_admin_wallet_address, 0, 2) !== '0x') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDC Polygon wallet address.', 'instant-approval-payment-gateway'));
            return false;
        }

        // Check if wallet address matches the USDC contract address
        if (strtolower($customprovider_admin_wallet_address) === '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359') {
            WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert your USDC Polygon wallet address.', 'instant-approval-payment-gateway'));
            return false;
        }

        // Proceed with the default processing if validations pass
        return parent::process_admin_options();
    }
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $paygatedottogateway_customprovider_provider = $this->paygatedotto_customprovider_get_chosen_provider();
        if ('' === $paygatedottogateway_customprovider_provider) {
            paygatedottogateway_add_notice(__('Please choose how you want to pay.', 'instant-approval-payment-gateway'), 'error');
            return null;
        }
        $paygatedottogateway_customprovider_currency = get_woocommerce_currency();
		$paygatedottogateway_customprovider_total = $order->get_total();
		// Reuse the order's existing callback nonce if one was already issued, so
		// re-running process_payment builds the identical callback URL. control/wallet.php
		// then returns the identical tracking address (same callback params => same
		// address), and the stored nonce can never diverge from the address's bound
		// callback. Only mint a fresh nonce the first time; it is still generated by
		// wp_create_nonce() (tied to WP secret salts) so it stays unguessable.
		$paygatedottogateway_customprovider_existing_nonce = $order->get_meta('paygatedotto_customprovider_nonce', true);
		$paygatedottogateway_customprovider_nonce = ('' !== $paygatedottogateway_customprovider_existing_nonce)
			? $paygatedottogateway_customprovider_existing_nonce
			: wp_create_nonce( 'paygatedottogateway_customprovider_nonce_' . $order_id );
		$paygatedottogateway_customprovider_tolerance_percentage = $this->customprovider_tolerance_percentage;
		$paygatedottogateway_customprovider_callback = add_query_arg(array('order_id' => $order_id, 'nonce' => $paygatedottogateway_customprovider_nonce,), rest_url('paygatedottogateway/v1/paygatedottogateway-customprovider/'));
		$paygatedottogateway_customprovider_email = urlencode(sanitize_email($order->get_billing_email()));
		
		if ($paygatedottogateway_customprovider_currency === 'USD') {
        $paygatedottogateway_customprovider_final_total = $paygatedottogateway_customprovider_total;
		$paygatedottogateway_customprovider_reference_total = (float)$paygatedottogateway_customprovider_final_total;
		} else {
		
$paygatedottogateway_customprovider_response = wp_remote_get(paygatedottogateway_api_url($this->customprovider_custom_api_domain, 'control/convert.php', array('value' => $paygatedottogateway_customprovider_total, 'from' => strtolower($paygatedottogateway_customprovider_currency))), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_customprovider_response)) {
    // Handle error
    paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {

$paygatedottogateway_customprovider_body = wp_remote_retrieve_body($paygatedottogateway_customprovider_response);
$paygatedottogateway_customprovider_conversion_resp = json_decode($paygatedottogateway_customprovider_body, true);

if ($paygatedottogateway_customprovider_conversion_resp && isset($paygatedottogateway_customprovider_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottogateway_customprovider_final_total	= sanitize_text_field($paygatedottogateway_customprovider_conversion_resp['value_coin']);
    $paygatedottogateway_customprovider_reference_total = (float)$paygatedottogateway_customprovider_final_total;	
} else {
    paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'instant-approval-payment-gateway'), 'error');
    return null;
}	
		}
		}
		
if ($paygatedottogateway_customprovider_reference_total < $this->customprovider_custom_minimum_amount) {
	// translators: %s: Minimum order amount in USD.
paygatedottogateway_add_notice(sprintf( __('Payment error: Order total for this payment provider must be at least $%s USD.', 'instant-approval-payment-gateway'),esc_html($this->customprovider_custom_minimum_amount)),'error');
return null;
}

// The chosen provider's own minimum, as listed by PayGate. Compared in USD, or in the store
// currency when the provider lists its minimum in that currency; otherwise the provider checks it.
$paygatedottogateway_customprovider_info = $this->paygatedotto_customprovider_get_provider_info();
if (!empty($paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_provider]['minimum_amount'])) {
	$paygatedottogateway_customprovider_pmin      = (float) $paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_provider]['minimum_amount'];
	$paygatedottogateway_customprovider_pmin_cur  = strtoupper((string) $paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_provider]['minimum_currency']);
	$paygatedottogateway_customprovider_pcompare  = null;
	if ('USD' === $paygatedottogateway_customprovider_pmin_cur) {
		$paygatedottogateway_customprovider_pcompare = $paygatedottogateway_customprovider_reference_total;
	} elseif (strtoupper($paygatedottogateway_customprovider_currency) === $paygatedottogateway_customprovider_pmin_cur) {
		$paygatedottogateway_customprovider_pcompare = (float) $paygatedottogateway_customprovider_total;
	}
	if (null !== $paygatedottogateway_customprovider_pcompare && $paygatedottogateway_customprovider_pcompare < $paygatedottogateway_customprovider_pmin) {
		$paygatedottogateway_customprovider_pname = !empty($paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_provider]['name']) ? $paygatedottogateway_customprovider_info[$paygatedottogateway_customprovider_provider]['name'] : $paygatedottogateway_customprovider_provider;
		// translators: 1: provider name, 2: minimum amount, 3: currency code.
		paygatedottogateway_add_notice(sprintf(__('Payment error: Order total for %1$s must be at least %2$s %3$s. Please choose another way to pay.', 'instant-approval-payment-gateway'), $paygatedottogateway_customprovider_pname, $paygatedottogateway_customprovider_pmin, $paygatedottogateway_customprovider_pmin_cur), 'error');
		return null;
	}
}
		
$paygatedottogateway_customprovider_gen_wallet = wp_remote_get(paygatedottogateway_api_url($this->customprovider_custom_api_domain, 'control/wallet.php', array('address' => $this->customprovider_wallet_address, 'callback' => $paygatedottogateway_customprovider_callback)), array('timeout' => 30));

if (is_wp_error($paygatedottogateway_customprovider_gen_wallet)) {
    // Handle error
    paygatedottogateway_add_notice(__('Wallet error:', 'instant-approval-payment-gateway') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'instant-approval-payment-gateway'), 'error');
    return null;
} else {
	$paygatedottogateway_customprovider_wallet_body = wp_remote_retrieve_body($paygatedottogateway_customprovider_gen_wallet);
	$paygatedottogateway_customprovider_wallet_decbody = json_decode($paygatedottogateway_customprovider_wallet_body, true);

 // Check if decoding was successful
    if ($paygatedottogateway_customprovider_wallet_decbody && isset($paygatedottogateway_customprovider_wallet_decbody['address_in'])) {
        // Store the address_in as a variable
        $paygatedottogateway_customprovider_gen_addressIn = wp_kses_post($paygatedottogateway_customprovider_wallet_decbody['address_in']);
        $paygatedottogateway_customprovider_gen_polygon_addressIn = sanitize_text_field($paygatedottogateway_customprovider_wallet_decbody['polygon_address_in']);
		$paygatedottogateway_customprovider_gen_callback = sanitize_url($paygatedottogateway_customprovider_wallet_decbody['callback_url']);
		// Save $customproviderresponse in order meta data
    $order->add_meta_data('paygatedotto_customprovider_tracking_address', $paygatedottogateway_customprovider_gen_addressIn, true);
    $order->add_meta_data('paygatedotto_customprovider_polygon_temporary_order_wallet_address', $paygatedottogateway_customprovider_gen_polygon_addressIn, true);
    $order->add_meta_data('paygatedotto_customprovider_callback', $paygatedottogateway_customprovider_gen_callback, true);
	$order->add_meta_data('paygatedotto_customprovider_converted_amount', $paygatedottogateway_customprovider_final_total, true);
	$order->add_meta_data('paygatedotto_customprovider_expected_amount', $paygatedottogateway_customprovider_reference_total, true);
	$order->add_meta_data('paygatedotto_customprovider_nonce', $paygatedottogateway_customprovider_nonce, true);
	$order->add_meta_data('paygatedotto_customprovider_tolerance_percentage', $paygatedottogateway_customprovider_tolerance_percentage, true);
	$order->add_meta_data('paygatedotto_customprovider_provider', $paygatedottogateway_customprovider_provider, true);
	// Signature verification: remember the callback URL WE registered with PayGate
	// (that exact string is what PayGate signs, with its payment parameters
	// appended), and mark this order as requiring a signed callback. Orders created
	// before this plugin version carry no such mark, so their pending callbacks are
	// still accepted unsigned.
	$order->add_meta_data('paygatedotto_customprovider_signature_base', $paygatedottogateway_customprovider_callback, true);
	$order->add_meta_data('paygatedotto_customprovider_signature_required', 'yes', true);
    $order->save();
    } else {
        paygatedottogateway_add_notice(__('Payment error:', 'instant-approval-payment-gateway') . __('Payment could not be processed, please try again (wallet address error)', 'instant-approval-payment-gateway'), 'error');

        return null;
    }
}

        // Redirect to payment page
        return array(
            'result'   => 'success',
            'redirect' => 'https://' . $this->customprovider_custom_domain . '/process-payment.php?address=' . $paygatedottogateway_customprovider_gen_addressIn . '&amount=' . (float)$paygatedottogateway_customprovider_total . '&provider=' . rawurlencode($paygatedottogateway_customprovider_provider) . '&email=' . $paygatedottogateway_customprovider_email . '&currency=' . $paygatedottogateway_customprovider_currency,
        );
    }

public function paygatedotto_instant_payment_gateway_get_icon_url() {
        return !empty($this->icon_url) ? esc_url($this->icon_url) : '';
    }
}

function paygatedottogateway_add_instant_payment_gateway_customprovider($gateways) {
    $gateways[] = 'PayGateDotTo_Instant_Payment_Gateway_Customprovider';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'paygatedottogateway_add_instant_payment_gateway_customprovider');
}

// Add custom endpoint for changing order status
function paygatedottogateway_customprovider_change_order_status_rest_endpoint() {
    // Register custom route
    register_rest_route( 'paygatedottogateway/v1', '/paygatedottogateway-customprovider/', array(
        'methods'  => 'GET',
        'callback' => 'paygatedottogateway_customprovider_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action( 'rest_api_init', 'paygatedottogateway_customprovider_change_order_status_rest_endpoint' );

// Callback function to change order status
function paygatedottogateway_customprovider_change_order_status_callback( $request ) {
    $order_id = absint($request->get_param( 'order_id' ));
	$paygatedottogateway_customprovidergetnonce = sanitize_text_field($request->get_param( 'nonce' ));
	$paygatedottogateway_customproviderpaid_txid_out = sanitize_text_field($request->get_param('txid_out'));
	$paygatedottogateway_customproviderpaid_value_coin = sanitize_text_field($request->get_param('value_coin'));
	$paygatedottogateway_customproviderpaid_name_coin = sanitize_text_field($request->get_param('coin'));
	
	if ($paygatedottogateway_customproviderpaid_name_coin == 'polygon_pol' || $paygatedottogateway_customproviderpaid_name_coin == 'eth' || $paygatedottogateway_customproviderpaid_name_coin == 'bep20_bnb') {
	$paygatedottogateway_customproviderpaid_strname_coin = str_replace('_', '/', $paygatedottogateway_customproviderpaid_name_coin);
	
	$paygatedottogateway_customprovidercprice_response_cbrates = wp_remote_get(paygatedottogateway_api_url(paygatedottogateway_get_api_domain('paygatedotto-instant-payment-gateway-customprovider', 'customprovider_custom_api_domain'), 'crypto/' . $paygatedottogateway_customproviderpaid_strname_coin . '/info.php'), array('timeout' => 30));
if (is_wp_error($paygatedottogateway_customprovidercprice_response_cbrates)) {
    return new WP_Error( 'paygatedottogateway_price_unavailable', __( 'Coin price is unavailable, please retry the callback.', 'instant-approval-payment-gateway' ), array( 'status' => 503 ) ); // never value coin units as USD
} else {
    $paygatedottogateway_customprovidercprice_body_cbrates = wp_remote_retrieve_body($paygatedottogateway_customprovidercprice_response_cbrates);
    $paygatedottogateway_customprovidercprice_conversion_resp_cbrates = json_decode($paygatedottogateway_customprovidercprice_body_cbrates, true);
    if ($paygatedottogateway_customprovidercprice_conversion_resp_cbrates && isset($paygatedottogateway_customprovidercprice_conversion_resp_cbrates['prices']['USD'])) {
        $paygatedottogateway_customproviderfloatpaid_value_coin = (float)$paygatedottogateway_customproviderpaid_value_coin * sanitize_text_field($paygatedottogateway_customprovidercprice_conversion_resp_cbrates['prices']['USD']);    
    } else {
        return new WP_Error( 'paygatedottogateway_price_unavailable', __( 'Coin price is unavailable, please retry the callback.', 'instant-approval-payment-gateway' ), array( 'status' => 503 ) ); // never value coin units as USD
    }
}

	} else {
	$paygatedottogateway_customproviderfloatpaid_value_coin = (float)$paygatedottogateway_customproviderpaid_value_coin;	
	}

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
    if ( empty( $paygatedottogateway_customprovidergetnonce ) || $order->get_meta('paygatedotto_customprovider_nonce', true) !== $paygatedottogateway_customprovidergetnonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'instant-approval-payment-gateway' ), array( 'status' => 403 ) );
    }

    // Verify PayGate's RSA signature over the exact callback URL before trusting
    // any payment parameter. value_coin above is caller-supplied, so without this
    // check a forged request could complete an unpaid order.
    $paygatedottogateway_customprovider_sig_error = paygatedottogateway_signature_guard( $request, $order, 'paygatedotto_customprovider' );
    if ( $paygatedottogateway_customprovider_sig_error instanceof WP_Error ) {
        return $paygatedottogateway_customprovider_sig_error;
    }

    // Check if the order is pending and payment method is 'paygatedotto-instant-payment-gateway-customprovider'
    if ( $order && $order->get_status() !== 'processing' && $order->get_status() !== 'completed' && 'paygatedotto-instant-payment-gateway-customprovider' === $order->get_payment_method() ) {
	$paygatedottogateway_customproviderexpected_amount = (float)$order->get_meta('paygatedotto_customprovider_expected_amount', true);
	$paygatedottogateway_customproviderthreshold = $order->get_meta('paygatedotto_customprovider_tolerance_percentage', true) * $paygatedottogateway_customproviderexpected_amount;
		if ( $paygatedottogateway_customproviderfloatpaid_value_coin < $paygatedottogateway_customproviderthreshold ) {
			// Mark the order as failed and add an order note
            $order->update_status('failed', __( 'Payment received is less than tolerance percentage of the order total. Customer may have changed the payment values on the checkout page.', 'instant-approval-payment-gateway' ));
            /* translators: 1: Transaction ID */
            $order->add_order_note(sprintf( __( 'Order marked as failed: Payment received is less than tolerance percentage of the order total. Customer may have changed the payment values on the checkout page. TXID: %1$s', 'instant-approval-payment-gateway' ), $paygatedottogateway_customproviderpaid_txid_out));
            return array( 'message' => 'Order status changed to failed due to partial payment.' );
			
		} else {
        // Change order status to processing
		$order->payment_complete();
		/* translators: 1: Transaction ID */
		$order->add_order_note( sprintf(__('Payment completed by the provider TXID: %1$s', 'instant-approval-payment-gateway'), $paygatedottogateway_customproviderpaid_txid_out) );
        // Return success response
        return array( 'message' => 'Order marked as paid and status changed.' );
		}
    } else {
        // Return error response if conditions are not met
        return new WP_Error( 'order_not_eligible', __( 'Order is not eligible for status change.', 'instant-approval-payment-gateway' ), array( 'status' => 400 ) );
    }
}
?>