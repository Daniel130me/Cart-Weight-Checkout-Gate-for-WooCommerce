<?php
/**
 * Minimum-weight-only checkout error template.
 *
 * Loaded only when every checkout error belongs to this plugin. If another
 * extension reports an error, WooCommerce's original template is left intact.
 *
 * @package CartWeightCheckoutGate
 */

defined( 'ABSPATH' ) || exit;

$cwcgfw_minimum_weight_notices = wc_get_notices( 'error' );
?>

<?php foreach ( $cwcgfw_minimum_weight_notices as $cwcgfw_minimum_weight_notice ) : ?>
	<p><?php echo esc_html( wp_strip_all_tags( $cwcgfw_minimum_weight_notice['notice'] ) ); ?></p>
<?php endforeach; ?>

<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Required WooCommerce template compatibility hook. ?>
<?php do_action( 'woocommerce_cart_has_errors' ); ?>

<p>
	<a class="button wc-backward" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<?php esc_html_e( 'Return to cart', 'cart-weight-checkout-gate-for-woocommerce' ); ?>
	</a>
</p>
