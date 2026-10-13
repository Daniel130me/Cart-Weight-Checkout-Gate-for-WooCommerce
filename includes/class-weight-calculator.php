<?php
/**
 * Cart and order weight calculations.
 *
 * @package CartQuotaMinimumWeight
 */

namespace CartQuotaMinimumWeight;

use WC_Cart;
use WC_Order;
use WC_Product;

defined( 'ABSPATH' ) || exit;

final class Weight_Calculator {
	const ORDER_WEIGHT_META = '_cqmfw_checkout_weight_kg';

	/** @var Settings */
	private $settings;

	/** @param Settings $settings Settings service. */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Calculate the weight of shippable cart items without loading products again.
	 *
	 * WooCommerce and product-extension plugins may adjust the WC_Product objects
	 * stored in the cart (for example, an assembled bundle). Reading those existing
	 * objects keeps this calculation aligned with shipping and avoids extra queries.
	 *
	 * @param WC_Cart|null        $cart               Cart instance. Defaults to the active cart.
	 * @param array<string,float> $quantity_overrides Optional cart-item quantities used for a read-only preview.
	 * @return float Weight in kilograms.
	 */
	public function get_cart_weight_kg( $cart = null, $quantity_overrides = array() ) {
		if ( null === $cart && function_exists( 'WC' ) ) {
			$cart = WC()->cart;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return 0.0;
		}

		$store_weight = 0.0;

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product  = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			$quantity = array_key_exists( $cart_item_key, $quantity_overrides )
				? max( 0.0, (float) $quantity_overrides[ $cart_item_key ] )
				: ( isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 0.0 );

			if ( ! $product instanceof WC_Product || ! $product->needs_shipping() || ! $product->has_weight() ) {
				continue;
			}

			$store_weight += (float) $product->get_weight() * $quantity;
		}

		$weight_kg = (float) wc_get_weight( $store_weight, 'kg' );

		/**
		 * Filter the calculated cart weight in kilograms.
		 *
		 * This extension point supports unusual product/container implementations.
		 * Returning a value should be based only on server-trusted cart data.
		 *
		 * @param float   $weight_kg Calculated weight in kilograms.
		 * @param WC_Cart $cart      Cart being calculated.
		 */
		return max( 0.0, (float) apply_filters( 'cqmfw_cart_weight_kg', $weight_kg, $cart ) );
	}

	/**
	 * Provide lightweight line data for an immediate, non-authoritative UI preview.
	 *
	 * @param WC_Cart|null $cart Cart instance.
	 * @return array<string,array<string,float>>
	 */
	public function get_cart_item_preview_data( $cart = null ) {
		if ( null === $cart && function_exists( 'WC' ) ) {
			$cart = WC()->cart;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return array();
		}

		$items = array();

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

			if ( ! $product instanceof WC_Product || ! $product->needs_shipping() || ! $product->has_weight() ) {
				continue;
			}

			$items[ $cart_item_key ] = array(
				'unitWeight' => max( 0.0, (float) wc_get_weight( (float) $product->get_weight(), 'kg' ) ),
				'quantity'   => max( 0.0, (float) $cart_item['quantity'] ),
			);
		}

		return $items;
	}

	/**
	 * Read a trusted checkout snapshot or calculate an existing order's weight.
	 *
	 * @param WC_Order $order Order to inspect.
	 * @return float Weight in kilograms.
	 */
	public function get_order_weight_kg( WC_Order $order ) {
		$snapshot = $order->get_meta( self::ORDER_WEIGHT_META, true );

		if ( '' !== $snapshot && is_numeric( $snapshot ) ) {
			return max( 0.0, (float) $snapshot );
		}

		$store_weight = 0.0;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;

			if ( ! $product instanceof WC_Product || ! $product->needs_shipping() || ! $product->has_weight() ) {
				continue;
			}

			$store_weight += (float) $product->get_weight() * (float) $item->get_quantity();
		}

		$weight_kg = (float) wc_get_weight( $store_weight, 'kg' );

		/**
		 * Filter the fallback weight for an order that has no checkout snapshot.
		 *
		 * @param float    $weight_kg Calculated weight in kilograms.
		 * @param WC_Order $order     Order being calculated.
		 */
		return max( 0.0, (float) apply_filters( 'cqmfw_order_weight_kg', $weight_kg, $order ) );
	}

	/**
	 * Build the status used by both validation and presentation.
	 *
	 * @param float $weight_kg Current weight in kilograms.
	 * @return array<string, float|bool>
	 */
	public function get_status( $weight_kg ) {
		$minimum   = $this->settings->get_minimum_kg();
		$current   = max( 0.0, (float) $weight_kg );
		$remaining = max( 0.0, $minimum - $current );
		$percent   = $minimum > 0 ? min( 100.0, ( $current / $minimum ) * 100 ) : 100.0;

		return array(
			'current'   => $current,
			'minimum'   => $minimum,
			'remaining' => $remaining,
			'percent'   => $percent,
			'reached'   => $remaining <= 0.000001,
		);
	}
}
