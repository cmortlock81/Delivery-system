<?php
/**
 * Main plugin bootstrap.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class SCD_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var SCD_Plugin|null
	 */
	private static ?SCD_Plugin $instance = null;

	/**
	 * Admin controller.
	 *
	 * @var SCD_Admin
	 */
	private SCD_Admin $admin;

	/**
	 * Public controller.
	 *
	 * @var SCD_Public
	 */
	private SCD_Public $public;

	/**
	 * Get singleton instance.
	 *
	 * @return SCD_Plugin
	 */
	public static function instance(): SCD_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->admin  = new SCD_Admin();
		$this->public = new SCD_Public();

		add_action( 'init', array( $this, 'load' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load runtime components.
	 *
	 * @return void
	 */
	public function load(): void {
		SCD_Job_Repository::register();
		$this->admin->register();
		$this->public->register();
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'speedy-couriers-dispatch', false, dirname( SCD_PLUGIN_BASENAME ) . '/languages' );
	}
}
