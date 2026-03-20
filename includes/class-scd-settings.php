<?php
/**
 * Settings helper.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings wrapper.
 */
class SCD_Settings {
	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'scd_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'company_name'          => 'Speedy Couriers',
			'default_priority'      => 'normal',
			'order_prefix'          => 'SC',
			'enable_frontend_form'  => 'yes',
			'enable_tracking'       => 'yes',
		);
	}

	/**
	 * Get a setting or all settings.
	 *
	 * @param string|null $key Key.
	 * @return mixed
	 */
	public static function get( ?string $key = null ) {
		$settings = wp_parse_args( get_option( self::OPTION_NAME, array() ), self::defaults() );
		return null === $key ? $settings : ( $settings[ $key ] ?? null );
	}

	/**
	 * Update settings.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	public static function update( array $settings ): void {
		$clean = array(
			'company_name'         => sanitize_text_field( $settings['company_name'] ?? self::defaults()['company_name'] ),
			'default_priority'     => in_array( $settings['default_priority'] ?? 'normal', array( 'normal', 'urgent' ), true ) ? $settings['default_priority'] : 'normal',
			'order_prefix'         => strtoupper( sanitize_key( $settings['order_prefix'] ?? self::defaults()['order_prefix'] ) ),
			'enable_frontend_form' => ( isset( $settings['enable_frontend_form'] ) && 'yes' === $settings['enable_frontend_form'] ) ? 'yes' : 'no',
			'enable_tracking'      => ( isset( $settings['enable_tracking'] ) && 'yes' === $settings['enable_tracking'] ) ? 'yes' : 'no',
		);

		update_option( self::OPTION_NAME, $clean );
	}
}
