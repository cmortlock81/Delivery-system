<?php
/**
 * Jobs list table.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Delivery jobs table.
 */
class SCD_Jobs_List_Table extends WP_List_Table {
	/**
	 * Current query args.
	 *
	 * @var array<string,mixed>
	 */
	private array $query_args = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'scd_job',
				'plural'   => 'scd_jobs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->query_args = array(
			'paged'          => $this->get_pagenum(),
			'posts_per_page' => 20,
			'status'         => sanitize_key( (string) SCD_Helpers::request( 'status_filter', '' ) ),
			'search'         => sanitize_text_field( (string) SCD_Helpers::request( 's', '' ) ),
			'orderby'        => sanitize_key( (string) SCD_Helpers::request( 'orderby', 'date' ) ),
			'order'          => sanitize_text_field( (string) SCD_Helpers::request( 'order', 'DESC' ) ),
		);

		$result       = SCD_Job_Repository::query_jobs( $this->query_args );
		$this->items  = $result['jobs'];
		$columns      = $this->get_columns();
		$hidden       = array();
		$sortable     = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total_items'],
				'per_page'    => 20,
				'total_pages' => $result['total_pages'],
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'order'      => __( 'Order', 'speedy-couriers-dispatch' ),
			'customer'   => __( 'Customer', 'speedy-couriers-dispatch' ),
			'recipient'  => __( 'Recipient', 'speedy-couriers-dispatch' ),
			'address'    => __( 'Address', 'speedy-couriers-dispatch' ),
			'priority'   => __( 'Priority', 'speedy-couriers-dispatch' ),
			'status'     => __( 'Status', 'speedy-couriers-dispatch' ),
			'courier'    => __( 'Courier', 'speedy-couriers-dispatch' ),
			'updated'    => __( 'Updated', 'speedy-couriers-dispatch' ),
			'quick_edit' => __( 'Quick Actions', 'speedy-couriers-dispatch' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array<int|string>>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'order'    => array( 'order_number', false ),
			'customer' => array( 'customer_name', false ),
			'recipient'=> array( 'recipient_name', false ),
			'priority' => array( 'priority', false ),
			'status'   => array( 'status', false ),
			'updated'  => array( 'date', true ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="job_ids[]" value="%d" />', (int) $item['ID'] );
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item Job row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'order':
				$edit_url = SCD_Helpers::admin_url( 'speedy-couriers-dispatch-job', array( 'job_id' => $item['ID'] ) );
				return sprintf( '<strong><a href="%s">%s</a></strong><br><small>%s</small>', esc_url( $edit_url ), esc_html( $item['order_number'] ), esc_html( strtoupper( $item['source'] ) ) );
			case 'customer':
				return esc_html( $item['customer_name'] . ' / ' . $item['customer_phone'] );
			case 'recipient':
				return esc_html( $item['recipient_name'] . ' / ' . $item['recipient_phone'] );
			case 'address':
				return esc_html( $item['delivery_address'] );
			case 'priority':
				return esc_html( ucfirst( $item['priority'] ) );
			case 'status':
				return esc_html( SCD_Statuses::label( $item['status'] ) );
			case 'courier':
				$user = $item['assigned_courier_id'] ? get_user_by( 'id', $item['assigned_courier_id'] ) : null;
				return $user instanceof WP_User ? esc_html( $user->display_name ) : '&mdash;';
			case 'updated':
				return esc_html( SCD_Helpers::format_datetime( $item['post_modified'] ) );
			case 'quick_edit':
				return $this->quick_actions_markup( $item );
		}

		return '';
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'bulk_assign' => __( 'Assign Courier', 'speedy-couriers-dispatch' ),
			'bulk_status' => __( 'Change Status', 'speedy-couriers-dispatch' ),
		);
	}

	/**
	 * Render extra nav.
	 *
	 * @param string $which Which area.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$status_filter = $this->query_args['status'] ?? '';
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="status_filter"><?php esc_html_e( 'Filter by status', 'speedy-couriers-dispatch' ); ?></label>
			<select name="status_filter" id="status_filter">
				<option value=""><?php esc_html_e( 'All statuses', 'speedy-couriers-dispatch' ); ?></option>
				<?php foreach ( SCD_Statuses::all() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'speedy-couriers-dispatch' ), 'secondary', '', false ); ?>
		</div>
		<?php
	}

	/**
	 * Quick actions per row.
	 *
	 * @param array $item Job.
	 * @return string
	 */
	private function quick_actions_markup( array $item ): string {
		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scd-quick-actions">
			<?php wp_nonce_field( 'scd_quick_update_job_' . $item['ID'] ); ?>
			<input type="hidden" name="action" value="scd_quick_update_job" />
			<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $item['ID'] ); ?>" />
			<select name="assigned_courier_id">
				<option value="0"><?php esc_html_e( 'Unassigned', 'speedy-couriers-dispatch' ); ?></option>
				<?php foreach ( SCD_Job_Repository::get_couriers() as $courier ) : ?>
					<option value="<?php echo esc_attr( (string) $courier->ID ); ?>" <?php selected( (int) $item['assigned_courier_id'], $courier->ID ); ?>><?php echo esc_html( $courier->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<?php foreach ( SCD_Statuses::all() as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $item['status'], $status ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-small"><?php esc_html_e( 'Save', 'speedy-couriers-dispatch' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}
