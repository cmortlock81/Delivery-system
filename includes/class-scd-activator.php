<?php
/**
 * Activation routines.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin activator.
 */
class SCD_Activator {
	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		SCD_Roles::add_roles();
		SCD_Job_Repository::register();
		SCD_Settings::update( SCD_Settings::get() );
		update_option( 'scd_version', SCD_VERSION );
		flush_rewrite_rules();
	}
}
