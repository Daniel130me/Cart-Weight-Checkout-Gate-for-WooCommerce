<?php
/**
 * Plugin composition root.
 *
 * @package CartQuotaMinimumWeight
 */

namespace CartQuotaMinimumWeight;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/** @var Plugin|null */
	private static $instance;

	/** @var bool */
	private $registered = false;

	/**
	 * Get the single plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register each independently testable plugin component.
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		$settings   = new Settings();
		$calculator = new Weight_Calculator( $settings );
		$validator  = new Checkout_Validator( $settings, $calculator );
		$presenter  = new Progress_Presenter( $settings, $calculator );

		$settings->register();
		$validator->register();
		$presenter->register();
	}

	/** Prevent direct construction. */
	private function __construct() {}
}
