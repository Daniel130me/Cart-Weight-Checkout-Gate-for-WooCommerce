=== Cart Weight Checkout Gate for WooCommerce ===
Contributors: danielkay
Tags: woocommerce, checkout, cart, shipping, minimum order
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Require a configurable minimum cart weight before customers can proceed to checkout or payment.

== Description ==

Cart Weight Checkout Gate for WooCommerce helps stores protect shipping margins by requiring the combined weight of shippable cart items to reach a configurable minimum.

Customers see a clear progress indicator showing their current cart weight, the required minimum, and how much more they need to add. Quantity changes update the indicator immediately and are then verified against the WooCommerce cart session.

= Features =

* Configurable minimum cart weight in kilograms, with a default of 10 kg.
* On/off switch that disables enforcement without deactivating the plugin.
* Accessible progress indicator for the cart, mini-cart, and checkout.
* Immediate quantity-change preview followed by server verification.
* Support for classic cart and checkout templates.
* Support for WooCommerce Cart and Checkout Blocks.
* Store API validation for headless and Store API checkout clients.
* Server-side checks before checkout, order creation, and payment.
* Settings access for administrators and WooCommerce Shop Managers.
* Automatic conversion from the store's configured product-weight unit to kilograms.
* Compatibility declarations for High-Performance Order Storage (HPOS).

The plugin uses standard WordPress and WooCommerce APIs. It does not create custom database tables, load third-party services, display advertising, or collect analytics.

Products must have an accurate weight configured in WooCommerce. Only shippable, non-virtual products with a weight contribute to the minimum.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin ZIP through Plugins > Add Plugin > Upload Plugin, or install it from the WordPress.org Plugin Directory.
3. Activate "Cart Weight Checkout Gate for WooCommerce".
4. Open WooCommerce > Cart Weight Gate.
5. Enable enforcement and enter the required minimum in kilograms.

== Frequently Asked Questions ==

= Does this plugin work with every WordPress ecommerce plugin? =

It is designed specifically for WooCommerce. It does not depend on a particular theme or any other ecommerce, shipping, marketplace, or page-builder plugin.

= Can I change the default 10 kg minimum? =

Yes. Administrators and WooCommerce Shop Managers can change the minimum under WooCommerce > Cart Weight Gate.

= Can I temporarily disable the requirement? =

Yes. Turn off the enforcement setting without deactivating or uninstalling the plugin.

= Which products count toward the minimum? =

Shippable products with a WooCommerce product weight count toward the minimum. Virtual products and products without a configured weight do not count.

= What if my store uses grams, pounds, or ounces? =

The plugin reads WooCommerce's configured weight unit and converts product weights to kilograms before comparing the cart with the minimum.

= Can a customer bypass the rule by opening checkout or payment URLs directly? =

No. The progress indicator is only guidance. Independent server-side checks run during cart validation, classic checkout, Store API checkout, order creation, and payment.

= Does the plugin collect or transmit customer data? =

No. It does not use external services, cookies, tracking, telemetry, or advertising. A numeric checkout-weight snapshot is stored privately on the WooCommerce order so later payment attempts can be validated consistently.

== Privacy ==

The plugin does not collect, transmit, or sell personal data. It processes product weights and cart quantities within the WordPress/WooCommerce installation. No data is sent to the plugin author or any third party.

On uninstall, the plugin removes its configuration options. Existing private order-weight snapshots are retained with their related orders to preserve historical order integrity.

== Changelog ==

= 1.3.2 =
* Initial public release.
* Added configurable minimum-weight enforcement across classic checkout, Checkout Blocks, Store API, order creation, and payment.
* Added accessible cart-weight progress and immediate quantity-change feedback.

== Upgrade Notice ==

= 1.3.2 =
Initial public release.
