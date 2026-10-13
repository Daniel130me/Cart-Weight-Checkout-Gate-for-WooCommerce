<?php
/**
 * Remove plugin configuration when the plugin is uninstalled.
 *
 * Order weight snapshots are intentionally retained with their WooCommerce
 * orders as historical operational data.
 *
 * @package CartQuotaMinimumWeight
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cqmfw_enabled' );
delete_option( 'cqmfw_minimum_weight_kg' );
