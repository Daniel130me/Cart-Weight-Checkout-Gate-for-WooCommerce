<?php
/**
 * Customer-facing progress indicator and Store API data.
 *
 * @package CartQuotaMinimumWeight
 */

namespace CartQuotaMinimumWeight;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use WC_Cart;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Progress_Presenter {
	const STORE_API_NAMESPACE = 'cartquota-minimum-weight';
	const REST_NAMESPACE      = 'cartquota-minimum-weight/v1';

	/** @var Settings */
	private $settings;

	/** @var Weight_Calculator */
	private $calculator;

	/** @var bool Prevent duplicate output in the classic checkout template. */
	private $checkout_progress_rendered = false;

	/**
	 * @param Settings          $settings   Settings service.
	 * @param Weight_Calculator $calculator Weight calculator.
	 */
	public function __construct( Settings $settings, Weight_Calculator $calculator ) {
		$this->settings   = $settings;
		$this->calculator = $calculator;
	}

	/** Register frontend, REST and block hooks. */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_cart', array( $this, 'output_progress' ), 8 );
		add_action( 'woocommerce_before_checkout_form_cart_notices', array( $this, 'output_checkout_progress' ), 8 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'output_checkout_progress' ), 8 );
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( $this, 'output_compact_progress' ), 8 );
		add_filter( 'render_block', array( $this, 'prepend_to_cart_and_checkout_blocks' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_status_route' ) );

		// WooCommerce can fire this event before late plugins_loaded callbacks.
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_store_api_data();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_data' ) );
		}
	}

	/** Load the small frontend assets only while enforcement is enabled. */
	public function enqueue_assets() {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		wp_enqueue_style( 'cqmfw-progress', CQMFW_URL . 'assets/css/frontend.css', array(), CQMFW_VERSION );
		wp_enqueue_script( 'cqmfw-progress', CQMFW_URL . 'assets/js/frontend.js', array(), CQMFW_VERSION, true );
		$cart = function_exists( 'WC' ) ? WC()->cart : null;
		/* translators: 1: remaining cart weight, 2: required minimum weight, both in kilograms. */
		$remaining_message = __( 'Add %1$s kg more to reach the %2$s kg checkout minimum.', 'cartquota-minimum-weight-woocommerce' );
		/* translators: 1: current cart weight, 2: required minimum weight, both in kilograms. */
		$reached_message = __( 'Minimum reached — your cart is %1$s kg of the required %2$s kg.', 'cartquota-minimum-weight-woocommerce' );

		wp_localize_script(
			'cqmfw-progress',
			'cqmfwCartQuota',
			array(
				'endpoint'         => esc_url_raw( rest_url( self::REST_NAMESPACE . '/status' ) ),
				'previewEndpoint'  => esc_url_raw( rest_url( self::REST_NAMESPACE . '/preview' ) ),
				'namespace'        => self::STORE_API_NAMESPACE,
				'minimum'          => $this->settings->get_minimum_kg(),
				'cartItems'        => $this->calculator->get_cart_item_preview_data( $cart ),
				'updatingMessage'  => __( 'Updating cart weight…', 'cartquota-minimum-weight-woocommerce' ),
				'remainingMessage' => $remaining_message,
				'reachedMessage'   => $reached_message,
			)
		);
	}

	/** Print the full progress indicator. */
	public function output_progress() {
		echo $this->get_progress_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the renderer.
	}

	/**
	 * Print checkout progress before WooCommerce can replace cart errors with its
	 * generic cart-errors template. The later form hook remains as a fallback.
	 */
	public function output_checkout_progress() {
		if ( $this->checkout_progress_rendered ) {
			return;
		}

		$html = $this->get_progress_html();
		if ( '' === $html ) {
			return;
		}

		$this->checkout_progress_rendered = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the renderer.
	}

	/** Print the compact indicator used by classic mini-cart widgets. */
	public function output_compact_progress() {
		echo $this->get_progress_html( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the renderer.
	}

	/**
	 * Prepend progress to Cart and Checkout blocks, whose templates do not run classic hooks.
	 *
	 * @param string $block_content Rendered HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function prepend_to_cart_and_checkout_blocks( $block_content, $block ) {
		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';

		if ( ! in_array( $block_name, array( 'woocommerce/cart', 'woocommerce/checkout' ), true ) ) {
			return $block_content;
		}

		return $this->get_progress_html() . $block_content;
	}

	/** Register a lightweight status endpoint for classic AJAX cart updates. */
	public function register_status_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status_response' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_preview_status_response' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'cart_item_key' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'quantity'      => array(
						'required' => true,
						'type'     => 'number',
						'minimum'  => 0,
						'maximum'  => Settings::MAXIMUM_MINIMUM_KG,
					),
				),
			)
		);
	}

	/**
	 * Return only non-sensitive, current-session progress data.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status_response() {
		$this->ensure_cart_is_loaded();

		$cart                 = function_exists( 'WC' ) ? WC()->cart : null;
		$status               = $this->get_public_status();
		$status['cart_items'] = $this->calculator->get_cart_item_preview_data( $cart );
		$response             = rest_ensure_response( $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * Return a read-only, server-calculated preview for one edited cart line.
	 *
	 * The cart session is not changed here. WooCommerce's normal cart submission
	 * remains responsible for validating and persisting the requested quantity.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function get_preview_status_response( WP_REST_Request $request ) {
		$this->ensure_cart_is_loaded();

		$cart          = function_exists( 'WC' ) ? WC()->cart : null;
		$cart_item_key = (string) $request->get_param( 'cart_item_key' );
		$quantity      = max( 0.0, (float) $request->get_param( 'quantity' ) );
		$overrides     = array();

		if ( $cart instanceof WC_Cart && isset( $cart->get_cart()[ $cart_item_key ] ) ) {
			$overrides[ $cart_item_key ] = $quantity;
		}

		$status   = $this->calculator->get_status( $this->calculator->get_cart_weight_kg( $cart, $overrides ) );
		$response = rest_ensure_response( $this->format_public_status( $status, ! $cart instanceof WC_Cart || $cart->is_empty() ) );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/** Register progress in every WooCommerce Store API cart response. */
	public function register_store_api_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( CartSchema::class ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => self::STORE_API_NAMESPACE,
				'data_callback'   => array( $this, 'get_public_status' ),
				'schema_callback' => array( $this, 'get_store_api_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Closed schema for Store API extension data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_store_api_schema() {
		$number = array(
			'type'     => 'number',
			'readonly' => true,
		);

		return array(
			'properties' => array(
				'enabled'             => array(
					'type'     => 'boolean',
					'readonly' => true,
				),
				'empty'               => array(
					'type'     => 'boolean',
					'readonly' => true,
				),
				'reached'             => array(
					'type'     => 'boolean',
					'readonly' => true,
				),
				'current'             => $number,
				'minimum'             => $number,
				'remaining'           => $number,
				'percent'             => $number,
				'current_formatted'   => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'minimum_formatted'   => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'remaining_formatted' => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'message'             => array(
					'type'     => 'string',
					'readonly' => true,
				),
			),
		);
	}

	/**
	 * Public progress payload shared by REST and Checkout Blocks.
	 *
	 * @return array<string, bool|float|string>
	 */
	public function get_public_status() {
		$cart          = function_exists( 'WC' ) ? WC()->cart : null;
		$cart_is_empty = ! $cart instanceof WC_Cart || $cart->is_empty();
		$status        = $this->calculator->get_status( $this->calculator->get_cart_weight_kg( $cart ) );

		return $this->format_public_status( $status, $cart_is_empty );
	}

	/**
	 * Format a calculated status for public frontend consumption.
	 *
	 * @param array<string,float|bool> $status Calculated weight status.
	 * @param bool                     $cart_is_empty Whether the persisted cart is empty.
	 * @return array<string, bool|float|string>
	 */
	private function format_public_status( $status, $cart_is_empty ) {
		$current = $this->format_weight( $status['current'] );
		$minimum = $this->format_weight( $status['minimum'] );
		$remain  = $this->format_weight( $status['remaining'] );

		$message = $status['reached']
			? sprintf(
				/* translators: 1: current cart weight, 2: required minimum weight. */
				__( 'Minimum reached — your cart is %1$s kg of the required %2$s kg.', 'cartquota-minimum-weight-woocommerce' ),
				$current,
				$minimum
			)
			: sprintf(
				/* translators: 1: remaining weight, 2: required minimum weight. */
				__( 'Add %1$s kg more to reach the %2$s kg checkout minimum.', 'cartquota-minimum-weight-woocommerce' ),
				$remain,
				$minimum
			);

		return array(
			'enabled'             => $this->settings->is_enabled(),
			'empty'               => $cart_is_empty,
			'reached'             => (bool) $status['reached'],
			'current'             => (float) $status['current'],
			'minimum'             => (float) $status['minimum'],
			'remaining'           => (float) $status['remaining'],
			'percent'             => round( (float) $status['percent'], 2 ),
			'current_formatted'   => $current,
			'minimum_formatted'   => $minimum,
			'remaining_formatted' => $remain,
			'message'             => $message,
		);
	}

	/** Ensure REST requests have access to the current WooCommerce cart session. */
	private function ensure_cart_is_loaded() {
		if ( function_exists( 'WC' ) && ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
	}

	/**
	 * Render progress HTML once from the shared status payload.
	 *
	 * @param bool $compact Whether to use the mini-cart variant.
	 * @return string
	 */
	private function get_progress_html( $compact = false ) {
		if ( ! $this->settings->is_enabled() ) {
			return '';
		}

		$status = $this->get_public_status();

		if ( $status['empty'] ) {
			return '';
		}

		$classes = array( 'cqmfw-progress', $status['reached'] ? 'is-reached' : 'is-short' );
		if ( $compact ) {
			$classes[] = 'is-compact';
		}

		ob_start();
		?>
		<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-cqmfw-progress aria-live="polite">
			<div class="cqmfw-progress__header">
				<strong><?php esc_html_e( 'Cart weight', 'cartquota-minimum-weight-woocommerce' ); ?></strong>
				<span><span data-cqmfw-current><?php echo esc_html( $status['current_formatted'] ); ?></span> / <span data-cqmfw-minimum><?php echo esc_html( $status['minimum_formatted'] ); ?></span> <?php esc_html_e( 'kg', 'cartquota-minimum-weight-woocommerce' ); ?></span>
			</div>
			<div class="cqmfw-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $status['minimum'] ); ?>" aria-valuenow="<?php echo esc_attr( min( $status['current'], $status['minimum'] ) ); ?>">
				<span class="cqmfw-progress__bar" data-cqmfw-bar style="width: <?php echo esc_attr( $status['percent'] ); ?>%"></span>
			</div>
			<p class="cqmfw-progress__message" data-cqmfw-message><?php echo esc_html( $status['message'] ); ?></p>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** @param float $weight Weight in kilograms. */
	private function format_weight( $weight ) {
		return wc_format_localized_decimal( wc_format_decimal( $weight, 3, true ) );
	}
}

