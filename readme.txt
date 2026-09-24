=== Ranau Simple Checkout for WooCommerce ===
Contributors: yudins
Tags: woocommerce, checkout, phone, city, delivery
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reduce WooCommerce checkout to the delivery city and phone number while preserving native order and shipping flows.

== Description ==

Ranau Simple Checkout provides a focused checkout for delivery stores that only need a customer phone number and delivery city before the customer chooses a shipping method.

The plugin works with WooCommerce Checkout Blocks and classic checkout. It keeps WooCommerce as the owner of the cart, shipping rates, totals, payment, and order creation.

Features:

* Required phone number and delivery city.
* One cart refresh after the customer confirms the city.
* Internal placeholders for WooCommerce fields hidden by the minimal flow.
* HPOS and Cart/Checkout Blocks compatibility declarations.
* No tracking, remote Ranau service, account, or license key.

The placeholder email uses the reserved `example.invalid` domain and cannot receive mail. Stores that require customer email, postal address, tax, fraud checks, or gateway address verification should not hide those fields and may use the documented filters to extend the flow.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin ZIP in Plugins > Add New > Upload Plugin.
3. Activate Ranau Simple Checkout.
4. Test every enabled payment and shipping method before using the plugin on a live store.

By default the minimal field rules apply to Russia (`RU`). Developers can change the country list with the `ranau_simple_checkout_countries` filter.

== Frequently Asked Questions ==

= Does the plugin send data to Ranau? =

No. The plugin does not call Ranau or any analytics service. Phone and city values are stored only through the WooCommerce customer session and order flow.

= Why is there an internal placeholder email? =

WooCommerce and some checkout clients expect an email-shaped value. When the shopper does not provide an email, the plugin generates a non-deliverable address under `example.invalid` and marks the order with internal metadata.

= Will every payment gateway work with only city and phone? =

No. Some gateways, tax providers, fraud tools, and carriers require a name, email, postcode, or full address. Test the complete checkout with every integration you enable.

= Is paid support required? =

No. All included functionality is free and open source. Optional installation, compatibility work, and custom development are available from https://ranau.uk/.

== Privacy ==

The plugin processes the phone number and delivery city entered during checkout and stores them through WooCommerce. It makes no external requests and adds no tracking. Store owners remain responsible for their privacy notice and retention policy.

== Support ==

Community issues: https://github.com/yudin-s/ranau-simple-checkout/issues

Optional paid installation, compatibility work, and custom development are available from https://ranau.uk/ and are not required to use the plugin.

== Changelog ==

= 0.1.1 =
* Corrected the WordPress.org contributor account.

= 0.1.0 =
* Initial development release with Blocks and classic checkout support.
