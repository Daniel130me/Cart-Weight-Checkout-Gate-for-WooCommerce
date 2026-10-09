<?php
/**
 * Remove plugin configuration when the plugin is uninstalled.
 *
 * Order weight snapshots are intentionally retained with their WooCommerce
 * orders as historical operational data.
 *
 * @package CartWeightCheckoutGate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cwcgfw_enabled' );
delete_option( 'cwcgfw_minimum_weight_kg' );
