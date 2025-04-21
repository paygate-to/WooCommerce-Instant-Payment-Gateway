=== Instant Approval Payment Gateway with Instant Payouts ===
Contributors: paygatedotto
Donate link: https://paygate.to/
Tags: woocommerce,payment,instant,gateway
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.2.0
Requires PHP: 7.2
WC requires at least: 5.8
WC tested up to: 9.8.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Instant Approval High Risk Merchant Gateway with instant payouts to your USDC POLYGON wallet using fiat to crypto onramp providers.

== Description ==

Powerful plugin for WooCommerce that allows you to accept payments for your high risk business instantly. You can accept Credit/debit card (including MasterCard, Visa, and Maestro) – Apple Pay – Google Pay – SEPA or local bank transfer. Get paid instantly to your USDC Polygon (MATIC) wallet.

=== Features ===

* Instant Access & Instant Approval
* Start accepting Visa, Mastercard, Amex, Google Pay and Apple Pay instantly!
* No sign up - ZERO KYB (Merchant stay anonymous).
* Can be installed directly on any high risk business website.
* Instant payouts in crypto or USDC with every order directly to your own wallet.
* ZERO chargebacks (All payments are final and irreversible).
* New temporary wallet assigned for each order for privacy and precise payment.
* Automatic order processing (order will be marked as paid automatically after payment).
* Track payouts from wp-admin
* Minimum order amount starts from $1 USD only for some providers.
* Customizable payment gateway icon for each provider.
* Multi-currency support

Minimum allowed order amount varies per payment provider you can [check the full list of minimum order value per payment provider](https://paygate.to/instant-payment-gateway/#minimumorder).

The plugin and offered service through [PayGate.to Instant Payment Gateway API](https://paygate.to/instant-payment-gateway/) is subject to the [service terms](https://paygate.to/info/terms/) and [Privacy Policy](https://paygate.to/info/privacy-policy/).

== Installation ==

* After installing and activating this plugin go to WooCommerce >>> Settings >>> Payments >>> PayGate.to Payments gateway
* Activate the desired payment provider gateway and insert your USDC (Polygon) wallet address to receive instant payouts.
* Insert desired display label and description for the payment gateway.
* Save settings and you will be ready to accept Credit Cards or Debit Cards Visa, Mastercard, Amex, Google Pay and Apple Pay instantly!

= Minimum Requirements =

* WordPress 3.8 or greater
* PHP version 5.2.4 or greater

== Frequently Asked Questions ==

= Do I need to sign up as a merchant to use the plugin? =

No, the plugin is available to be used to accept credit card payments instantly without sign up because it depends on the fiat to crypto onramp providers.

= When will I receive payouts? =

You will receive payouts instantly to your USDC wallet with every order.

= How to fix There Are No Payment Methods Available Error? =

Follow the guide to [Fix WooCommerce There Are No Payment Methods Available Error](https://paygate.to/fix-woocommerce-there-are-no-payment-methods-available-error/)


= I have a problem with one of my orders? =

Please contact PayGate.to support team to guide you.

= I'm receiving payments to my wallet but orders are still pending payment? =

Our plugin is tested to mark orders as processing automatically after payment. You can follow our [guide for fixing common payment gateway issues](https://paygate.to/woocommerce-payment-gateway-troubleshooting/).

= Are there any restricted businesses? =

Anyone can use our payment plugin instantly without providing any information. However if your website category falls under our [prohibited business list](https://paygate.to/instant-payment-gateway/#prohibited) your domain will be blocked.

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Changelog ==

= V1.2.0 =

* New Providers

= V1.1.9 =

* Updated payment providers

= V1.1.8 =

* Allow currency conversion to EUR.
* Updated minimum check.

= V1.1.7 =

* Detailed error messages for checkout blocks.
* Check minimum order amount for each provider.

= V1.1.6 =

* Hosted checkout mode with multi-provider at the same page and auto customer geo location detection for maximum conversion.

= V1.1.5 =

* Fix processing order status bug for virtual downloadable products.

= V1.1.4 =

* Fix deprecated dynamic property PHP 8.2

= V1.1.3 =

* PayGate.to acquisition
* New providers

= V1.1.2 =

* Hot fix currency conversion for USD provider

= V1.1.1 =

* New providers added

= V1.1.0 =

* Checkout blocks support.
* USDC Wallet validation check to prevent incorrect settings.

= V1.0.9 =

* Hot fix for avoiding timeouts with high response time

= V1.0.8 =

* New Payment Providers.
* Adds support to UPS/IMPS Payments for India (INR).
* Fix payment detection tolerance.

= V1.0.7 =

* Tolerating higher fees for some of the providers.
* Order notes in wp-admin showing TXID of payout.

= V1.0.6 =

* Avoiding nonce conflict with caching plugins.
* Storing callback nonce and verifying it via the stored value to prevent the invalid nonce problem when the nonce expires.
* HPOS support.

= V1.0.5 =

* Adding a paid amount check logic for providers with unlocked amounts on the checkout page.

= V1.0.4 =

* USDC Payouts

= V1.0.3 =

* New payment providers

= V1.0.2 =

* Fix USD conversion

= V1.0.1 =

* New payment providers

= V1.0.0 =

* Initial release


== Upgrade Notice ==

Checkout new plugin features. Always make sure to insert your payout wallet for active gateways.