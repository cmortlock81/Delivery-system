<?php
/**
 * Status registry and transition rules.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delivery status helper.
 */
class SCD_Statuses {
	/**
	 * Return known statuses.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		return array(
			'new'              => __( 'New', 'speedy-couriers-dispatch' ),
			'assigned'         => __( 'Assigned', 'speedy-couriers-dispatch' ),
			'out_for_delivery' => __( 'Out for Delivery', 'speedy-couriers-dispatch' ),
			'delivered'        => __( 'Delivered', 'speedy-couriers-dispatch' ),
			'failed'           => __( 'Failed', 'speedy-couriers-dispatch' ),
			'cancelled'        => __( 'Cancelled', 'speedy-couriers-dispatch' ),
		);
	}

	/**
	 * Statuses couriers can set directly.
	 *
	 * @return array<string,string>
	 */
	public static function courier_statuses(): array {
		return array_intersect_key(
			self::all(),
			array_flip( array( 'out_for_delivery', 'delivered', 'failed' ) )
		);
	}

	/**
	 * Human label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function label( string $status ): string {
		$statuses = self::all();
		return $statuses[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Allowed transitions.
	 *
	 * @param string $from Current status.
	 * @param string $to Requested status.
	 * @param string $context controller|courier.
	 * @return bool
	 */
	public static function can_transition( string $from, string $to, string $context = 'controller' ): bool {
		$controller_transitions = array(
			'new'              => array( 'assigned', 'cancelled', 'new' ),
			'assigned'         => array( 'assigned', 'out_for_delivery', 'cancelled', 'failed', 'delivered' ),
			'out_for_delivery' => array( 'out_for_delivery', 'delivered', 'failed', 'cancelled' ),
			'delivered'        => array( 'delivered' ),
			'failed'           => array( 'failed', 'assigned', 'cancelled' ),
			'cancelled'        => array( 'cancelled' ),
		);

		$courier_transitions = array(
			'assigned'         => array( 'out_for_delivery', 'failed' ),
			'out_for_delivery' => array( 'delivered', 'failed' ),
			'failed'           => array(),
			'delivered'        => array(),
			'new'              => array(),
			'cancelled'        => array(),
		);

		$map = 'courier' === $context ? $courier_transitions : $controller_transitions;

		return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
	}
}
