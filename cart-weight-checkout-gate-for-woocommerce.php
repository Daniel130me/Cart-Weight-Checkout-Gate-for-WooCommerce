<?php
/**
 * Plugin Name:       Cart Weight Checkout Gate for WooCommerce
 * Description:       Requires shippable cart items to meet a configurable minimum weight before checkout or payment.
 * Version:           1.3.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Oluwagbenga Kosoko
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cart-weight-checkout-gate-for-woocommerce
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.1
 *
 * @package CartWeightCheckoutGate
 */

/*
 * Cart Weight Checkout Gate for WooCommerce is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 2 of the License,
 * or any later version.
 *
 * Cart Weight Checkout Gate for WooCommerce is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 */

defined( 'ABSPATH' ) || exit;

define( 'CWCGFW_VERSION', '1.3.2' );
define( 'CWCGFW_FILE', __FILE__ );
define( 'CWCGFW_PATH', plugin_dir_path( __FILE__ ) );
define( 'CWCGFW_URL', plugin_dir_url( __FILE__ ) );

require_once CWCGFW_PATH . 'includes/class-settings.php';
require_once CWCGFW_PATH . 'includes/class-weight-calculator.php';
require_once CWCGFW_PATH . 'includes/class-checkout-validator.php';
require_once CWCGFW_PATH . 'includes/class-progress-presenter.php';
require_once CWCGFW_PATH . 'includes/class-plugin.php';

register_activation_hook( CWCGFW_FILE, array( 'CartWeightCheckoutGate\Settings', 'add_defaults' ) );

/**
 * Declare compatibility with WooCommerce features before WooCommerce initializes.
 */
function cwcgfw_declare_woocommerce_compatibility() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CWCGFW_FILE, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CWCGFW_FILE, true );
}
add_action( 'before_woocommerce_init', 'cwcgfw_declare_woocommerce_compatibility' );

/**
 * Start the plugin after all plugins have loaded.
 */
function cwcgfw_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'cwcgfw_missing_woocommerce_notice' );
		return;
	}

	\CartWeightCheckoutGate\Settings::add_defaults();
	\CartWeightCheckoutGate\Plugin::instance()->register();
}
add_action( 'plugins_loaded', 'cwcgfw_bootstrap', 20 );

/**
 * Explain why the plugin is inactive when WooCommerce is unavailable.
 */
function cwcgfw_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Cart Weight Checkout Gate for WooCommerce requires WooCommerce to be installed and active.', 'cart-weight-checkout-gate-for-woocommerce' )
	);
}
