<?php
/**
 * Shared helpers.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper methods.
 */
class SCD_Helpers {
	/**
	 * Recursively sanitize form values.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public static function sanitize_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_deep' ), $value );
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( wp_unslash( $value ) );
		}

		return $value;
	}

	/**
	 * Sanitize textarea content.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function sanitize_textarea( string $value ): string {
		return sanitize_textarea_field( wp_unslash( $value ) );
	}

	/**
	 * Fetch a request value safely.
	 *
	 * @param string $key Key name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function request( string $key, $default = '' ) {
		return isset( $_REQUEST[ $key ] ) ? wp_unslash( $_REQUEST[ $key ] ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Format plugin admin URL.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Query args.
	 * @return string
	 */
	public static function admin_url( string $page, array $args = array() ): string {
		$args['page'] = $page;
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Format a date/time in site timezone.
	 *
	 * @param string $mysql_datetime MySQL datetime string.
	 * @return string
	 */
	public static function format_datetime( string $mysql_datetime ): string {
		if ( empty( $mysql_datetime ) ) {
			return '';
		}

		$timestamp = mysql2date( 'U', $mysql_datetime );
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Validate a phone-ish string.
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function normalize_phone( string $phone ): string {
		return preg_replace( '/[^0-9+\-()\s]/', '', $phone );
	}

	/**
	 * Safe redirect and exit.
	 *
	 * @param string $url Redirect target.
	 * @return void
	 */
	public static function redirect( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
