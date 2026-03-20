<?php
/**
 * Roles and capabilities.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage custom roles and capabilities.
 */
class SCD_Roles {
	/**
	 * Capability map.
	 *
	 * @return string[]
	 */
	public static function capabilities(): array {
		return array(
			'scd_manage_settings',
			'scd_view_dispatch_dashboard',
			'scd_create_jobs',
			'scd_edit_jobs',
			'scd_view_all_jobs',
			'scd_assign_jobs',
			'scd_change_job_statuses',
			'scd_view_assigned_jobs',
			'scd_update_assigned_jobs',
			'scd_submit_frontend_jobs',
		);
	}

	/**
	 * Register or update roles.
	 *
	 * @return void
	 */
	public static function add_roles(): void {
		$admin_caps = array_fill_keys( self::capabilities(), true );
		$admin_caps['read'] = true;

		$controller_caps = array(
			'read'                       => true,
			'scd_view_dispatch_dashboard' => true,
			'scd_create_jobs'            => true,
			'scd_edit_jobs'              => true,
			'scd_view_all_jobs'          => true,
			'scd_assign_jobs'            => true,
			'scd_change_job_statuses'    => true,
		);

		$courier_caps = array(
			'read'                   => true,
			'scd_view_assigned_jobs' => true,
			'scd_update_assigned_jobs' => true,
		);

		add_role( 'scd_controller', __( 'Controller', 'speedy-couriers-dispatch' ), $controller_caps );
		add_role( 'scd_courier', __( 'Courier', 'speedy-couriers-dispatch' ), $courier_caps );

		$administrator = get_role( 'administrator' );
		if ( $administrator instanceof WP_Role ) {
			foreach ( $admin_caps as $capability => $grant ) {
				$administrator->add_cap( $capability, $grant );
			}
		}
	}

	/**
	 * Remove custom roles only.
	 *
	 * @return void
	 */
	public static function remove_roles(): void {
		remove_role( 'scd_controller' );
		remove_role( 'scd_courier' );
	}

	/**
	 * Whether current user can fully manage jobs.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_jobs(): bool {
		return current_user_can( 'scd_view_all_jobs' ) || current_user_can( 'manage_options' );
	}
}
