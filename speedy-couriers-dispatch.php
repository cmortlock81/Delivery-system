<?php
/**
 * Plugin Name: Speedy Couriers Dispatch
 * Plugin URI:  https://example.com/speedy-couriers-dispatch
 * Description: Lightweight courier dispatch workflow for Speedy Couriers.
 * Version:     1.0.0
 * Author:      Mortify
 * Text Domain: speedy-couriers-dispatch
 * Requires PHP: 8.1
 * Requires at least: 6.4
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCD_VERSION', '1.0.0' );
define( 'SCD_PLUGIN_FILE', __FILE__ );
define( 'SCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SCD_PLUGIN_DIR . 'includes/class-scd-helpers.php';
require_once SCD_PLUGIN_DIR . 'includes/class-scd-statuses.php';
require_once SCD_PLUGIN_DIR . 'includes/class-scd-roles.php';
require_once SCD_PLUGIN_DIR . 'includes/class-scd-settings.php';
require_once SCD_PLUGIN_DIR . 'includes/class-scd-job-repository.php';
require_once SCD_PLUGIN_DIR . 'includes/class-scd-activator.php';
require_once SCD_PLUGIN_DIR . 'includes/class-scd-deactivator.php';
require_once SCD_PLUGIN_DIR . 'admin/class-scd-jobs-list-table.php';
require_once SCD_PLUGIN_DIR . 'admin/class-scd-admin.php';
require_once SCD_PLUGIN_DIR . 'public/class-scd-public.php';
require_once SCD_PLUGIN_DIR . 'includes/class-scd-plugin.php';

register_activation_hook( __FILE__, array( 'SCD_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SCD_Deactivator', 'deactivate' ) );

/**
 * Bootstrap the plugin.
 *
 * @return SCD_Plugin
 */
function speedy_couriers_dispatch() {
	return SCD_Plugin::instance();
}

speedy_couriers_dispatch();
