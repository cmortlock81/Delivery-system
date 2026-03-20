<?php
/**
 * Deactivation routines.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin deactivator.
 */
class SCD_Deactivator {
	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
