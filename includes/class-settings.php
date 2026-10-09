<?php
/**
 * Settings storage and administration page.
 *
 * @package CartWeightCheckoutGate
 */

namespace CartWeightCheckoutGate;

defined( 'ABSPATH' ) || exit;

final class Settings {
	const OPTION_ENABLED     = 'cwcgfw_enabled';
	const OPTION_MINIMUM_KG  = 'cwcgfw_minimum_weight_kg';
	const SETTINGS_GROUP     = 'cwcgfw_settings';
	const DEFAULT_MINIMUM_KG = 10.0;
	const MAXIMUM_MINIMUM_KG = 1000000.0;

	/**
	 * Add options without overwriting an existing configuration.
	 *
	 * @return void
	 */
	public static function add_defaults() {
		add_option( self::OPTION_ENABLED, 'yes' );
		add_option( self::OPTION_MINIMUM_KG, (string) self::DEFAULT_MINIMUM_KG );
	}

	/** Register WordPress hooks. */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 60 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CWCGFW_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Whether minimum-weight enforcement is active.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === get_option( self::OPTION_ENABLED, 'yes' );
	}

	/**
	 * Minimum weight, always stored and returned in kilograms.
	 *
	 * @return float
	 */
	public function get_minimum_kg() {
		$value = (float) get_option( self::OPTION_MINIMUM_KG, self::DEFAULT_MINIMUM_KG );

		return $value > 0 ? $value : self::DEFAULT_MINIMUM_KG;
	}

	/** Add the settings screen below WooCommerce. */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Cart Weight Checkout Gate', 'cart-weight-checkout-gate-for-woocommerce' ),
			__( 'Cart Weight Gate', 'cart-weight-checkout-gate-for-woocommerce' ),
			'manage_woocommerce',
			'cwcgfw-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/** Register and sanitize plugin options. */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_enabled' ),
				'default'           => 'yes',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_MINIMUM_KG,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_minimum_kg' ),
				'default'           => (string) self::DEFAULT_MINIMUM_KG,
			)
		);
	}

	/**
	 * Normalize the checkbox value.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_enabled( $value ) {
		return 'yes' === $value ? 'yes' : 'no';
	}

	/**
	 * Validate a locale-aware positive weight.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_minimum_kg( $value ) {
		$normalized = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $value, 3 ) : (string) $value;
		$minimum    = (float) $normalized;

		if ( $minimum <= 0 || $minimum > self::MAXIMUM_MINIMUM_KG ) {
			add_settings_error(
				self::OPTION_MINIMUM_KG,
				'cwcgfw_invalid_minimum',
				sprintf(
					/* translators: %s: maximum supported weight in kilograms. */
					__( 'Enter a minimum weight greater than 0 kg and no more than %s kg.', 'cart-weight-checkout-gate-for-woocommerce' ),
					number_format_i18n( self::MAXIMUM_MINIMUM_KG )
				),
				'error'
			);

			return (string) get_option( self::OPTION_MINIMUM_KG, self::DEFAULT_MINIMUM_KG );
		}

		return (string) $minimum;
	}

	/**
	 * Add a quick settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function add_settings_link( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=cwcgfw-settings' ) ),
				esc_html__( 'Settings', 'cart-weight-checkout-gate-for-woocommerce' )
			)
		);

		return $links;
	}

	/** Render the settings page. */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'cart-weight-checkout-gate-for-woocommerce' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cart Weight Checkout Gate', 'cart-weight-checkout-gate-for-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'Require the combined weight of shippable items to reach a minimum before customers can checkout or pay.', 'cart-weight-checkout-gate-for-woocommerce' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enforcement', 'cart-weight-checkout-gate-for-woocommerce' ); ?></th>
						<td>
							<label for="cwcgfw-enabled">
								<input id="cwcgfw-enabled" name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" type="checkbox" value="yes" <?php checked( $this->is_enabled() ); ?>>
								<?php esc_html_e( 'Require the minimum cart weight', 'cart-weight-checkout-gate-for-woocommerce' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Turn this off to deactivate all progress messages and checkout restrictions without disabling the plugin.', 'cart-weight-checkout-gate-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cwcgfw-minimum-kg"><?php esc_html_e( 'Minimum weight', 'cart-weight-checkout-gate-for-woocommerce' ); ?></label></th>
						<td>
							<input id="cwcgfw-minimum-kg" name="<?php echo esc_attr( self::OPTION_MINIMUM_KG ); ?>" type="number" min="0.001" max="<?php echo esc_attr( self::MAXIMUM_MINIMUM_KG ); ?>" step="0.001" value="<?php echo esc_attr( $this->get_minimum_kg() ); ?>" class="small-text">
							<span><?php esc_html_e( 'kg', 'cart-weight-checkout-gate-for-woocommerce' ); ?></span>
							<p class="description"><?php esc_html_e( 'The plugin converts product weights from the WooCommerce store unit to kilograms automatically.', 'cart-weight-checkout-gate-for-woocommerce' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

