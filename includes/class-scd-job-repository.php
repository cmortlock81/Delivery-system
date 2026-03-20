<?php
/**
 * Delivery job repository.
 *
 * CPT is the best MVP fit here because it provides secure CRUD, query APIs,
 * native timestamps, metadata storage, and straightforward admin integration
 * without introducing a second persistence layer before dispatch scale demands it.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsulates job persistence.
 */
class SCD_Job_Repository {
	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'scd_job';

	/**
	 * Meta keys.
	 *
	 * @return string[]
	 */
	public static function meta_keys(): array {
		return array(
			'order_number',
			'source',
			'customer_name',
			'customer_phone',
			'recipient_name',
			'recipient_phone',
			'delivery_address',
			'delivery_notes',
			'priority',
			'status',
			'assigned_courier_id',
			'created_by_user_id',
			'failed_delivery_reason',
			'status_history',
		);
	}

	/**
	 * Register post type and meta.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Delivery Jobs', 'speedy-couriers-dispatch' ),
					'singular_name' => __( 'Delivery Job', 'speedy-couriers-dispatch' ),
				),
				'public'             => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'supports'           => array( 'title', 'author' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'exclude_from_search'=> true,
				'rewrite'            => false,
				'can_export'         => true,
			)
		);

		foreach ( self::meta_keys() as $meta_key ) {
			register_post_meta(
				self::POST_TYPE,
				'scd_' . $meta_key,
				array(
					'show_in_rest'      => false,
					'single'            => true,
					'type'              => in_array( $meta_key, array( 'assigned_courier_id', 'created_by_user_id' ), true ) ? 'integer' : 'string',
					'auth_callback'     => '__return_true',
					'sanitize_callback' => array( __CLASS__, 'sanitize_registered_meta' ),
				)
			);
		}
	}

	/**
	 * Sanitize meta by registration.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function sanitize_registered_meta( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return absint( $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Create a job.
	 *
	 * @param array $data Job data.
	 * @return int|WP_Error
	 */
	public static function create_job( array $data ) {
		$prepared = self::prepare_job_data( $data );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $prepared['recipient_name'] . ' - ' . $prepared['delivery_address'],
				'post_author' => $prepared['created_by_user_id'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$prepared['order_number'] = self::generate_order_number( $post_id );

		foreach ( $prepared as $key => $value ) {
			update_post_meta( $post_id, 'scd_' . $key, $value );
		}

		self::append_status_history(
			$post_id,
			'',
			$prepared['status'],
			$prepared['created_by_user_id'],
			__( 'Job created', 'speedy-couriers-dispatch' )
		);

		do_action( 'scd_job_created', $post_id, $prepared );

		if ( ! empty( $prepared['assigned_courier_id'] ) ) {
			do_action( 'scd_job_assigned', $post_id, (int) $prepared['assigned_courier_id'], $prepared );
		}

		return $post_id;
	}

	/**
	 * Update a job.
	 *
	 * @param int   $job_id Job id.
	 * @param array $data Changes.
	 * @param int   $changed_by User id.
	 * @return true|WP_Error
	 */
	public static function update_job( int $job_id, array $data, int $changed_by ) {
		$job = self::get_job( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'scd_job_missing', __( 'Delivery job not found.', 'speedy-couriers-dispatch' ) );
		}

		$prepared       = self::prepare_job_data( array_merge( $job, $data ), false );
		$current_status = $job['status'];
		$new_status     = $prepared['status'];
		$old_assignee   = (int) $job['assigned_courier_id'];
		$new_assignee   = (int) $prepared['assigned_courier_id'];

		wp_update_post(
			array(
				'ID'         => $job_id,
				'post_title' => $prepared['recipient_name'] . ' - ' . $prepared['delivery_address'],
			)
		);

		foreach ( $prepared as $key => $value ) {
			if ( 'order_number' === $key && empty( $value ) ) {
				continue;
			}
			update_post_meta( $job_id, 'scd_' . $key, $value );
		}

		if ( $current_status !== $new_status ) {
			self::append_status_history( $job_id, $current_status, $new_status, $changed_by, $prepared['failed_delivery_reason'] ?? '' );
			do_action( 'scd_job_status_changed', $job_id, $current_status, $new_status, $changed_by );
		}

		if ( $old_assignee !== $new_assignee && $new_assignee > 0 ) {
			do_action( 'scd_job_assigned', $job_id, $new_assignee, $prepared );
		}

		return true;
	}

	/**
	 * Update only job status.
	 *
	 * @param int    $job_id Job id.
	 * @param string $new_status Status.
	 * @param int    $changed_by User id.
	 * @param string $note Optional note.
	 * @return true|WP_Error
	 */
	public static function update_status( int $job_id, string $new_status, int $changed_by, string $note = '' ) {
		$job = self::get_job( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'scd_job_missing', __( 'Delivery job not found.', 'speedy-couriers-dispatch' ) );
		}

		update_post_meta( $job_id, 'scd_status', $new_status );
		update_post_meta( $job_id, 'scd_failed_delivery_reason', 'failed' === $new_status ? $note : '' );
		self::append_status_history( $job_id, $job['status'], $new_status, $changed_by, $note );
		do_action( 'scd_job_status_changed', $job_id, $job['status'], $new_status, $changed_by );

		return true;
	}

	/**
	 * Get a single job with meta.
	 *
	 * @param int $job_id Job id.
	 * @return array<string,mixed>|null
	 */
	public static function get_job( int $job_id ): ?array {
		$post = get_post( $job_id );
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$data = array(
			'ID'                 => $post->ID,
			'post_date'          => $post->post_date,
			'post_modified'      => $post->post_modified,
			'post_author'        => $post->post_author,
			'order_number'       => (string) get_post_meta( $job_id, 'scd_order_number', true ),
			'source'             => (string) get_post_meta( $job_id, 'scd_source', true ),
			'customer_name'      => (string) get_post_meta( $job_id, 'scd_customer_name', true ),
			'customer_phone'     => (string) get_post_meta( $job_id, 'scd_customer_phone', true ),
			'recipient_name'     => (string) get_post_meta( $job_id, 'scd_recipient_name', true ),
			'recipient_phone'    => (string) get_post_meta( $job_id, 'scd_recipient_phone', true ),
			'delivery_address'   => (string) get_post_meta( $job_id, 'scd_delivery_address', true ),
			'delivery_notes'     => (string) get_post_meta( $job_id, 'scd_delivery_notes', true ),
			'priority'           => (string) get_post_meta( $job_id, 'scd_priority', true ),
			'status'             => (string) get_post_meta( $job_id, 'scd_status', true ),
			'assigned_courier_id'=> (int) get_post_meta( $job_id, 'scd_assigned_courier_id', true ),
			'created_by_user_id' => (int) get_post_meta( $job_id, 'scd_created_by_user_id', true ),
			'failed_delivery_reason' => (string) get_post_meta( $job_id, 'scd_failed_delivery_reason', true ),
			'status_history'     => self::get_status_history( $job_id ),
		);

		return $data;
	}

	/**
	 * Query jobs.
	 *
	 * @param array $args Query args.
	 * @return array<string,mixed>
	 */
	public static function query_jobs( array $args = array() ): array {
		$defaults = array(
			'paged'            => 1,
			'posts_per_page'   => 20,
			'status'           => '',
			'assigned_courier' => 0,
			'search'           => '',
			'orderby'          => 'date',
			'order'            => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $args['posts_per_page'],
			'paged'          => max( 1, (int) $args['paged'] ),
			'orderby'        => 'date' === $args['orderby'] ? 'date' : 'meta_value',
			'order'          => 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC',
			'meta_key'       => 'date' === $args['orderby'] ? '' : 'scd_' . sanitize_key( $args['orderby'] ),
			'meta_query'     => array( 'relation' => 'AND' ),
		);

		if ( ! empty( $args['status'] ) ) {
			$query_args['meta_query'][] = array(
				'key'   => 'scd_status',
				'value' => sanitize_key( $args['status'] ),
			);
		}

		if ( ! empty( $args['assigned_courier'] ) ) {
			$query_args['meta_query'][] = array(
				'key'   => 'scd_assigned_courier_id',
				'value' => (int) $args['assigned_courier'],
				'type'  => 'NUMERIC',
			);
		}

		if ( ! empty( $args['search'] ) ) {
			$search = sanitize_text_field( $args['search'] );
			$query_args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => 'scd_order_number',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'scd_customer_name',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'scd_recipient_name',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'scd_customer_phone',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'scd_recipient_phone',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'scd_delivery_address',
					'value'   => $search,
					'compare' => 'LIKE',
				),
			);
		}

		$query = new WP_Query( $query_args );
		$jobs  = array_map( array( __CLASS__, 'get_job' ), $query->posts );

		return array(
			'jobs'        => array_filter( $jobs ),
			'total_items' => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Get status counts.
	 *
	 * @return array<string,int>
	 */
	public static function get_status_counts(): array {
		$counts = array_fill_keys( array_keys( SCD_Statuses::all() ), 0 );
		$all    = self::query_jobs( array( 'posts_per_page' => -1 ) );

		foreach ( $all['jobs'] as $job ) {
			if ( isset( $counts[ $job['status'] ] ) ) {
				++$counts[ $job['status'] ];
			}
		}

		return $counts;
	}

	/**
	 * Get delivered today count.
	 *
	 * @return int
	 */
	public static function get_delivered_today_count(): int {
		$all   = self::query_jobs( array( 'posts_per_page' => -1, 'status' => 'delivered' ) );
		$today = wp_date( 'Y-m-d' );
		$count = 0;

		foreach ( $all['jobs'] as $job ) {
			$history = array_reverse( $job['status_history'] );
			foreach ( $history as $entry ) {
				if ( 'delivered' === $entry['new_status'] && str_starts_with( $entry['changed_at'], $today ) ) {
					++$count;
					break;
				}
			}
		}

		return $count;
	}

	/**
	 * Get status history.
	 *
	 * @param int $job_id Job id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_status_history( int $job_id ): array {
		$history = get_post_meta( $job_id, 'scd_status_history', true );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Append a history row.
	 *
	 * @param int    $job_id Job id.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param int    $changed_by User id.
	 * @param string $note Optional note.
	 * @return void
	 */
	public static function append_status_history( int $job_id, string $old_status, string $new_status, int $changed_by, string $note = '' ): void {
		$history   = self::get_status_history( $job_id );
		$history[] = array(
			'old_status' => $old_status,
			'new_status' => $new_status,
			'changed_by' => $changed_by,
			'changed_at' => current_time( 'mysql' ),
			'note'       => $note,
		);

		update_post_meta( $job_id, 'scd_status_history', $history );
	}

	/**
	 * Create a new order number.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function generate_order_number( int $post_id ): string {
		$prefix   = SCD_Settings::get( 'order_prefix' );
		$sequence = (int) get_option( 'scd_order_sequence', 1000 ) + 1;
		$order    = sprintf( '%s-%d', $prefix ?: 'SC', $sequence );

		while ( self::order_number_exists( $order, $post_id ) ) {
			++$sequence;
			$order = sprintf( '%s-%d', $prefix ?: 'SC', $sequence );
		}

		update_option( 'scd_order_sequence', $sequence, false );
		return $order;
	}

	/**
	 * Check order number uniqueness.
	 *
	 * @param string $order_number Order number.
	 * @param int    $exclude_post Excluded post id.
	 * @return bool
	 */
	public static function order_number_exists( string $order_number, int $exclude_post = 0 ): bool {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'post__not_in'   => $exclude_post ? array( $exclude_post ) : array(),
				'meta_query'     => array(
					array(
						'key'   => 'scd_order_number',
						'value' => $order_number,
					),
				),
			)
		);

		return $query->have_posts();
	}

	/**
	 * Return courier users.
	 *
	 * @return array<int,WP_User>
	 */
	public static function get_couriers(): array {
		return get_users(
			array(
				'role'   => 'scd_courier',
				'orderby'=> 'display_name',
				'order'  => 'ASC',
			)
		);
	}

	/**
	 * Build validated job payload.
	 *
	 * @param array $data Job data.
	 * @param bool  $for_create Whether creating.
	 * @return array<string,mixed>
	 */
	public static function prepare_job_data( array $data, bool $for_create = true ): array {
		$defaults = array(
			'order_number'           => '',
			'source'                 => 'phone',
			'customer_name'          => '',
			'customer_phone'         => '',
			'recipient_name'         => '',
			'recipient_phone'        => '',
			'delivery_address'       => '',
			'delivery_notes'         => '',
			'priority'               => SCD_Settings::get( 'default_priority' ),
			'status'                 => 'new',
			'assigned_courier_id'    => 0,
			'created_by_user_id'     => get_current_user_id(),
			'failed_delivery_reason' => '',
			'status_history'         => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		$prepared = array(
			'order_number'           => sanitize_text_field( $data['order_number'] ),
			'source'                 => in_array( $data['source'], array( 'phone', 'web' ), true ) ? $data['source'] : 'phone',
			'customer_name'          => sanitize_text_field( $data['customer_name'] ),
			'customer_phone'         => SCD_Helpers::normalize_phone( (string) $data['customer_phone'] ),
			'recipient_name'         => sanitize_text_field( $data['recipient_name'] ),
			'recipient_phone'        => SCD_Helpers::normalize_phone( (string) $data['recipient_phone'] ),
			'delivery_address'       => sanitize_textarea_field( $data['delivery_address'] ),
			'delivery_notes'         => sanitize_textarea_field( $data['delivery_notes'] ),
			'priority'               => in_array( $data['priority'], array( 'normal', 'urgent' ), true ) ? $data['priority'] : 'normal',
			'status'                 => array_key_exists( $data['status'], SCD_Statuses::all() ) ? $data['status'] : 'new',
			'assigned_courier_id'    => absint( $data['assigned_courier_id'] ),
			'created_by_user_id'     => absint( $data['created_by_user_id'] ),
			'failed_delivery_reason' => sanitize_textarea_field( $data['failed_delivery_reason'] ),
			'status_history'         => is_array( $data['status_history'] ) ? $data['status_history'] : array(),
		);

		if ( $for_create && empty( $prepared['assigned_courier_id'] ) && 'assigned' === $prepared['status'] ) {
			$prepared['status'] = 'new';
		}

		if ( ! empty( $prepared['assigned_courier_id'] ) && 'new' === $prepared['status'] ) {
			$prepared['status'] = 'assigned';
		}

		return $prepared;
	}
}
