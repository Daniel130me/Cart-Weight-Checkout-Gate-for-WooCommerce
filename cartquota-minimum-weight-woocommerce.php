<?php
/**
 * Plugin Name:       CartQuota – Minimum Weight for WooCommerce
 * Description:       Requires shippable cart items to meet a configurable minimum weight before checkout or payment.
 * Version:           1.3.3
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Oluwagbenga Kosoko
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cartquota-minimum-weight-woocommerce
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.1
 *
 * @package CartQuotaMinimumWeight
 */

/*
 * CartQuota – Minimum Weight for WooCommerce is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 2 of the License,
 * or any later version.
 *
 * CartQuota – Minimum Weight for WooCommerce is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 */

defined( 'ABSPATH' ) || exit;

define( 'CQMFW_VERSION', '1.3.3' );
define( 'CQMFW_FILE', __FILE__ );
define( 'CQMFW_PATH', plugin_dir_path( __FILE__ ) );
define( 'CQMFW_URL', plugin_dir_url( __FILE__ ) );

require_once CQMFW_PATH . 'includes/class-settings.php';
require_once CQMFW_PATH . 'includes/class-weight-calculator.php';
require_once CQMFW_PATH . 'includes/class-checkout-validator.php';
require_once CQMFW_PATH . 'includes/class-progress-presenter.php';
require_once CQMFW_PATH . 'includes/class-plugin.php';

register_activation_hook( CQMFW_FILE, array( 'CartQuotaMinimumWeight\Settings', 'add_defaults' ) );

/**
 * Declare compatibility with WooCommerce features before WooCommerce initializes.
 */
function cqmfw_declare_woocommerce_compatibility() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CQMFW_FILE, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CQMFW_FILE, true );
}
add_action( 'before_woocommerce_init', 'cqmfw_declare_woocommerce_compatibility' );

/**
 * Start the plugin after all plugins have loaded.
 */
function cqmfw_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'cqmfw_missing_woocommerce_notice' );
		return;
	}

	\CartQuotaMinimumWeight\Settings::add_defaults();
	\CartQuotaMinimumWeight\Plugin::instance()->register();
}
add_action( 'plugins_loaded', 'cqmfw_bootstrap', 20 );

/**
 * Explain why the plugin is inactive when WooCommerce is unavailable.
 */
function cqmfw_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'CartQuota – Minimum Weight for WooCommerce requires WooCommerce to be installed and active.', 'cartquota-minimum-weight-woocommerce' )
	);
}
