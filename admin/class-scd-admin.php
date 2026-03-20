<?php
/**
 * Admin controllers.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens and handlers.
 */
class SCD_Admin {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_scd_save_job', array( $this, 'handle_save_job' ) );
		add_action( 'admin_post_scd_quick_update_job', array( $this, 'handle_quick_update' ) );
		add_action( 'admin_post_scd_bulk_update_jobs', array( $this, 'handle_bulk_update' ) );
		add_action( 'admin_post_scd_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_scd_courier_status_update', array( $this, 'handle_courier_status_update' ) );
	}

	/**
	 * Register menus.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Speedy Dispatch', 'speedy-couriers-dispatch' ),
			__( 'Speedy Dispatch', 'speedy-couriers-dispatch' ),
			'scd_view_dispatch_dashboard',
			'speedy-couriers-dispatch',
			array( $this, 'render_dashboard_page' ),
			'dashicons-location-alt',
			56
		);

		add_submenu_page(
			'speedy-couriers-dispatch',
			__( 'Dashboard', 'speedy-couriers-dispatch' ),
			__( 'Dashboard', 'speedy-couriers-dispatch' ),
			'scd_view_dispatch_dashboard',
			'speedy-couriers-dispatch',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'speedy-couriers-dispatch',
			__( 'Add New Job', 'speedy-couriers-dispatch' ),
			__( 'Add New Job', 'speedy-couriers-dispatch' ),
			'scd_create_jobs',
			'speedy-couriers-dispatch-add-job',
			array( $this, 'render_add_job_page' )
		);

		add_submenu_page(
			'speedy-couriers-dispatch',
			__( 'All Jobs', 'speedy-couriers-dispatch' ),
			__( 'All Jobs', 'speedy-couriers-dispatch' ),
			'scd_view_all_jobs',
			'speedy-couriers-dispatch-jobs',
			array( $this, 'render_jobs_page' )
		);

		add_submenu_page(
			null,
			__( 'Job Detail', 'speedy-couriers-dispatch' ),
			__( 'Job Detail', 'speedy-couriers-dispatch' ),
			'scd_edit_jobs',
			'speedy-couriers-dispatch-job',
			array( $this, 'render_job_detail_page' )
		);

		add_submenu_page(
			'speedy-couriers-dispatch',
			__( 'My Deliveries', 'speedy-couriers-dispatch' ),
			__( 'My Deliveries', 'speedy-couriers-dispatch' ),
			'scd_view_assigned_jobs',
			'speedy-couriers-dispatch-courier',
			array( $this, 'render_courier_page' )
		);

		add_submenu_page(
			'speedy-couriers-dispatch',
			__( 'Settings', 'speedy-couriers-dispatch' ),
			__( 'Settings', 'speedy-couriers-dispatch' ),
			'scd_manage_settings',
			'speedy-couriers-dispatch-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue CSS.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'speedy-couriers-dispatch' ) ) {
			return;
		}

		wp_enqueue_style( 'scd-admin', SCD_PLUGIN_URL . 'assets/css/admin.css', array(), SCD_VERSION );
	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'scd_view_dispatch_dashboard' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'speedy-couriers-dispatch' ) );
		}

		$counts    = SCD_Job_Repository::get_status_counts();
		$delivered = SCD_Job_Repository::get_delivered_today_count();
		$result    = SCD_Job_Repository::query_jobs(
			array(
				'posts_per_page' => 10,
				'search'         => sanitize_text_field( (string) SCD_Helpers::request( 's', '' ) ),
				'status'         => sanitize_key( (string) SCD_Helpers::request( 'status_filter', '' ) ),
			)
		);
		?>
		<div class="wrap scd-wrap">
			<h1><?php esc_html_e( 'Dispatch Dashboard', 'speedy-couriers-dispatch' ); ?></h1>
			<?php $this->render_admin_notices(); ?>
			<div class="scd-card-grid">
				<?php $this->summary_card( __( 'New Jobs', 'speedy-couriers-dispatch' ), $counts['new'] ?? 0, 'new' ); ?>
				<?php $this->summary_card( __( 'Assigned', 'speedy-couriers-dispatch' ), $counts['assigned'] ?? 0, 'assigned' ); ?>
				<?php $this->summary_card( __( 'Out for Delivery', 'speedy-couriers-dispatch' ), $counts['out_for_delivery'] ?? 0, 'out_for_delivery' ); ?>
				<?php $this->summary_card( __( 'Delivered Today', 'speedy-couriers-dispatch' ), $delivered, 'delivered' ); ?>
				<?php $this->summary_card( __( 'Failed', 'speedy-couriers-dispatch' ), $counts['failed'] ?? 0, 'failed' ); ?>
			</div>

			<form method="get" class="scd-filters">
				<input type="hidden" name="page" value="speedy-couriers-dispatch" />
				<input type="search" name="s" value="<?php echo esc_attr( (string) SCD_Helpers::request( 's', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Search order, customer, recipient, phone, address', 'speedy-couriers-dispatch' ); ?>" />
				<select name="status_filter">
					<option value=""><?php esc_html_e( 'All statuses', 'speedy-couriers-dispatch' ); ?></option>
					<?php foreach ( SCD_Statuses::all() as $status => $label ) : ?>
						<option value="<?php echo esc_attr( $status ); ?>" <?php selected( (string) SCD_Helpers::request( 'status_filter', '' ), $status ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Apply', 'speedy-couriers-dispatch' ); ?></button>
			</form>

			<div class="scd-panel">
				<h2><?php esc_html_e( 'Recent Jobs', 'speedy-couriers-dispatch' ); ?></h2>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'speedy-couriers-dispatch' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'speedy-couriers-dispatch' ); ?></th>
							<th><?php esc_html_e( 'Recipient', 'speedy-couriers-dispatch' ); ?></th>
							<th><?php esc_html_e( 'Address', 'speedy-couriers-dispatch' ); ?></th>
							<th><?php esc_html_e( 'Status', 'speedy-couriers-dispatch' ); ?></th>
							<th><?php esc_html_e( 'Courier', 'speedy-couriers-dispatch' ); ?></th>
							<th><?php esc_html_e( 'Updated', 'speedy-couriers-dispatch' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $result['jobs'] ) ) : ?>
							<?php foreach ( $result['jobs'] as $job ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( SCD_Helpers::admin_url( 'speedy-couriers-dispatch-job', array( 'job_id' => $job['ID'] ) ) ); ?>"><?php echo esc_html( $job['order_number'] ); ?></a></td>
									<td><?php echo esc_html( $job['customer_name'] ); ?><br><small><?php echo esc_html( $job['customer_phone'] ); ?></small></td>
									<td><?php echo esc_html( $job['recipient_name'] ); ?><br><small><?php echo esc_html( $job['recipient_phone'] ); ?></small></td>
									<td><?php echo esc_html( $job['delivery_address'] ); ?></td>
									<td><span class="scd-status scd-status-<?php echo esc_attr( $job['status'] ); ?>"><?php echo esc_html( SCD_Statuses::label( $job['status'] ) ); ?></span></td>
									<td><?php echo esc_html( $this->courier_name( (int) $job['assigned_courier_id'] ) ); ?></td>
									<td><?php echo esc_html( SCD_Helpers::format_datetime( $job['post_modified'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="7"><?php esc_html_e( 'No jobs found.', 'speedy-couriers-dispatch' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render add page.
	 *
	 * @return void
	 */
	public function render_add_job_page(): void {
		if ( ! current_user_can( 'scd_create_jobs' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'speedy-couriers-dispatch' ) );
		}

		$defaults = SCD_Job_Repository::prepare_job_data( array(), false );
		?>
		<div class="wrap scd-wrap">
			<h1><?php esc_html_e( 'Add New Delivery Job', 'speedy-couriers-dispatch' ); ?></h1>
			<?php $this->render_admin_notices(); ?>
			<?php $this->render_job_form( $defaults, true ); ?>
		</div>
		<?php
	}

	/**
	 * Render jobs list.
	 *
	 * @return void
	 */
	public function render_jobs_page(): void {
		if ( ! current_user_can( 'scd_view_all_jobs' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'speedy-couriers-dispatch' ) );
		}

		$table = new SCD_Jobs_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap scd-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'All Jobs', 'speedy-couriers-dispatch' ); ?></h1>
			<a href="<?php echo esc_url( SCD_Helpers::admin_url( 'speedy-couriers-dispatch-add-job' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'speedy-couriers-dispatch' ); ?></a>
			<?php $this->render_admin_notices(); ?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="scd-bulk-actions">
				<?php wp_nonce_field( 'scd_bulk_update_jobs' ); ?>
				<input type="hidden" name="page" value="speedy-couriers-dispatch-jobs" />
				<div class="scd-bulk-bar">
					<select name="bulk_action">
						<option value=""><?php esc_html_e( 'Bulk action', 'speedy-couriers-dispatch' ); ?></option>
						<option value="bulk_assign"><?php esc_html_e( 'Assign Courier', 'speedy-couriers-dispatch' ); ?></option>
						<option value="bulk_status"><?php esc_html_e( 'Change Status', 'speedy-couriers-dispatch' ); ?></option>
					</select>
					<select name="bulk_courier_id">
						<option value="0"><?php esc_html_e( 'Select courier', 'speedy-couriers-dispatch' ); ?></option>
						<?php foreach ( SCD_Job_Repository::get_couriers() as $courier ) : ?>
							<option value="<?php echo esc_attr( (string) $courier->ID ); ?>"><?php echo esc_html( $courier->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="bulk_status_value">
						<option value=""><?php esc_html_e( 'Select status', 'speedy-couriers-dispatch' ); ?></option>
						<?php foreach ( SCD_Statuses::all() as $status => $label ) : ?>
							<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" name="action" value="scd_bulk_update_jobs" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post" class="button button-secondary"><?php esc_html_e( 'Apply to Selected', 'speedy-couriers-dispatch' ); ?></button>
				</div>
				<?php $table->search_box( __( 'Search Jobs', 'speedy-couriers-dispatch' ), 'scd-jobs' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render detail page.
	 *
	 * @return void
	 */
	public function render_job_detail_page(): void {
		if ( ! current_user_can( 'scd_edit_jobs' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'speedy-couriers-dispatch' ) );
		}

		$job_id = absint( SCD_Helpers::request( 'job_id' ) );
		$job    = SCD_Job_Repository::get_job( $job_id );

		if ( ! $job ) {
			wp_die( esc_html__( 'Delivery job not found.', 'speedy-couriers-dispatch' ) );
		}
		?>
		<div class="wrap scd-wrap">
			<h1><?php echo esc_html( sprintf( __( 'Edit Job %s', 'speedy-couriers-dispatch' ), $job['order_number'] ) ); ?></h1>
			<?php $this->render_admin_notices(); ?>
			<div class="scd-two-column">
				<div>
					<?php $this->render_job_form( $job, false ); ?>
				</div>
				<div class="scd-panel">
					<h2><?php esc_html_e( 'Status History', 'speedy-couriers-dispatch' ); ?></h2>
					<ul class="scd-history">
						<?php foreach ( array_reverse( $job['status_history'] ) as $entry ) : ?>
							<li>
								<strong><?php echo esc_html( SCD_Statuses::label( $entry['new_status'] ) ); ?></strong>
								<?php if ( ! empty( $entry['old_status'] ) ) : ?>
									<span><?php echo esc_html( sprintf( __( 'from %s', 'speedy-couriers-dispatch' ), SCD_Statuses::label( $entry['old_status'] ) ) ); ?></span>
								<?php endif; ?>
								<br>
								<small>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: user name, 2: date */
											__( 'By %1$s on %2$s', 'speedy-couriers-dispatch' ),
											$this->user_name( (int) $entry['changed_by'] ),
											SCD_Helpers::format_datetime( $entry['changed_at'] )
										)
									);
									?>
								</small>
								<?php if ( ! empty( $entry['note'] ) ) : ?>
									<p><?php echo esc_html( $entry['note'] ); ?></p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<h2><?php esc_html_e( 'Audit Info', 'speedy-couriers-dispatch' ); ?></h2>
					<p><?php echo esc_html( sprintf( __( 'Created by %1$s on %2$s', 'speedy-couriers-dispatch' ), $this->user_name( (int) $job['created_by_user_id'] ), SCD_Helpers::format_datetime( $job['post_date'] ) ) ); ?></p>
					<p><?php echo esc_html( sprintf( __( 'Last updated %s', 'speedy-couriers-dispatch' ), SCD_Helpers::format_datetime( $job['post_modified'] ) ) ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render courier page.
	 *
	 * @return void
	 */
	public function render_courier_page(): void {
		if ( ! current_user_can( 'scd_view_assigned_jobs' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'speedy-couriers-dispatch' ) );
		}

		$result = SCD_Job_Repository::query_jobs(
			array(
				'posts_per_page'   => 50,
				'assigned_courier' => get_current_user_id(),
			)
		);
		?>
		<div class="wrap scd-wrap">
			<h1><?php esc_html_e( 'My Deliveries', 'speedy-couriers-dispatch' ); ?></h1>
			<?php $this->render_admin_notices(); ?>
			<div class="scd-courier-grid">
				<?php if ( ! empty( $result['jobs'] ) ) : ?>
					<?php foreach ( $result['jobs'] as $job ) : ?>
						<div class="scd-panel scd-courier-card priority-<?php echo esc_attr( $job['priority'] ); ?>">
							<h2><?php echo esc_html( $job['order_number'] ); ?></h2>
							<p><strong><?php esc_html_e( 'Recipient:', 'speedy-couriers-dispatch' ); ?></strong> <?php echo esc_html( $job['recipient_name'] ); ?></p>
							<p><strong><?php esc_html_e( 'Phone:', 'speedy-couriers-dispatch' ); ?></strong> <?php echo esc_html( $job['recipient_phone'] ); ?></p>
							<p><strong><?php esc_html_e( 'Address:', 'speedy-couriers-dispatch' ); ?></strong> <?php echo esc_html( $job['delivery_address'] ); ?></p>
							<p><strong><?php esc_html_e( 'Notes:', 'speedy-couriers-dispatch' ); ?></strong> <?php echo esc_html( $job['delivery_notes'] ?: __( 'None', 'speedy-couriers-dispatch' ) ); ?></p>
							<p><strong><?php esc_html_e( 'Status:', 'speedy-couriers-dispatch' ); ?></strong> <?php echo esc_html( SCD_Statuses::label( $job['status'] ) ); ?></p>
							<p><strong><?php esc_html_e( 'Priority:', 'speedy-couriers-dispatch' ); ?></strong> <?php echo esc_html( ucfirst( $job['priority'] ) ); ?></p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scd-courier-actions">
								<?php wp_nonce_field( 'scd_courier_status_update_' . $job['ID'] ); ?>
								<input type="hidden" name="action" value="scd_courier_status_update" />
								<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $job['ID'] ); ?>" />
								<div class="scd-action-buttons">
									<?php foreach ( SCD_Statuses::courier_statuses() as $status => $label ) : ?>
										<?php if ( SCD_Statuses::can_transition( $job['status'], $status, 'courier' ) ) : ?>
											<button type="submit" name="status" value="<?php echo esc_attr( $status ); ?>" class="button <?php echo 'delivered' === $status ? 'button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></button>
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
								<p>
									<label for="failed_reason_<?php echo esc_attr( (string) $job['ID'] ); ?>"><?php esc_html_e( 'Failed reason', 'speedy-couriers-dispatch' ); ?></label>
									<textarea id="failed_reason_<?php echo esc_attr( (string) $job['ID'] ); ?>" name="failed_reason" rows="2" placeholder="<?php esc_attr_e( 'Required only if marking failed', 'speedy-couriers-dispatch' ); ?>"></textarea>
								</p>
							</form>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="scd-panel"><p><?php esc_html_e( 'You have no assigned jobs right now.', 'speedy-couriers-dispatch' ); ?></p></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'scd_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'speedy-couriers-dispatch' ) );
		}

		$settings = SCD_Settings::get();
		?>
		<div class="wrap scd-wrap">
			<h1><?php esc_html_e( 'Dispatch Settings', 'speedy-couriers-dispatch' ); ?></h1>
			<?php $this->render_admin_notices(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scd-panel scd-form">
				<?php wp_nonce_field( 'scd_save_settings' ); ?>
				<input type="hidden" name="action" value="scd_save_settings" />
				<p>
					<label for="company_name"><?php esc_html_e( 'Company Name', 'speedy-couriers-dispatch' ); ?></label>
					<input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( $settings['company_name'] ); ?>" class="regular-text" />
				</p>
				<p>
					<label for="default_priority"><?php esc_html_e( 'Default Priority', 'speedy-couriers-dispatch' ); ?></label>
					<select id="default_priority" name="default_priority">
						<option value="normal" <?php selected( $settings['default_priority'], 'normal' ); ?>><?php esc_html_e( 'Normal', 'speedy-couriers-dispatch' ); ?></option>
						<option value="urgent" <?php selected( $settings['default_priority'], 'urgent' ); ?>><?php esc_html_e( 'Urgent', 'speedy-couriers-dispatch' ); ?></option>
					</select>
				</p>
				<p>
					<label for="order_prefix"><?php esc_html_e( 'Order Prefix', 'speedy-couriers-dispatch' ); ?></label>
					<input type="text" id="order_prefix" name="order_prefix" value="<?php echo esc_attr( $settings['order_prefix'] ); ?>" class="small-text" maxlength="5" />
				</p>
				<p>
					<label><input type="checkbox" name="enable_frontend_form" value="yes" <?php checked( $settings['enable_frontend_form'], 'yes' ); ?> /> <?php esc_html_e( 'Enable frontend order form', 'speedy-couriers-dispatch' ); ?></label>
				</p>
				<p>
					<label><input type="checkbox" name="enable_tracking" value="yes" <?php checked( $settings['enable_tracking'], 'yes' ); ?> /> <?php esc_html_e( 'Enable tracking shortcode', 'speedy-couriers-dispatch' ); ?></label>
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'speedy-couriers-dispatch' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Save add/edit form.
	 *
	 * @return void
	 */
	public function handle_save_job(): void {
		$job_id = absint( SCD_Helpers::request( 'job_id', 0 ) );

		if ( ( $job_id && ! current_user_can( 'scd_edit_jobs' ) ) || ( ! $job_id && ! current_user_can( 'scd_create_jobs' ) ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'speedy-couriers-dispatch' ) );
		}

		check_admin_referer( 'scd_save_job_' . $job_id );

		$data = array(
			'source'                 => sanitize_text_field( (string) SCD_Helpers::request( 'source', 'phone' ) ),
			'customer_name'          => sanitize_text_field( (string) SCD_Helpers::request( 'customer_name', '' ) ),
			'customer_phone'         => (string) SCD_Helpers::request( 'customer_phone', '' ),
			'recipient_name'         => sanitize_text_field( (string) SCD_Helpers::request( 'recipient_name', '' ) ),
			'recipient_phone'        => (string) SCD_Helpers::request( 'recipient_phone', '' ),
			'delivery_address'       => SCD_Helpers::sanitize_textarea( (string) SCD_Helpers::request( 'delivery_address', '' ) ),
			'delivery_notes'         => SCD_Helpers::sanitize_textarea( (string) SCD_Helpers::request( 'delivery_notes', '' ) ),
			'priority'               => sanitize_text_field( (string) SCD_Helpers::request( 'priority', 'normal' ) ),
			'status'                 => sanitize_text_field( (string) SCD_Helpers::request( 'status', 'new' ) ),
			'assigned_courier_id'    => absint( SCD_Helpers::request( 'assigned_courier_id', 0 ) ),
			'failed_delivery_reason' => SCD_Helpers::sanitize_textarea( (string) SCD_Helpers::request( 'failed_delivery_reason', '' ) ),
			'created_by_user_id'     => $job_id ? (int) ( SCD_Job_Repository::get_job( $job_id )['created_by_user_id'] ?? get_current_user_id() ) : get_current_user_id(),
		);

		if ( empty( $data['customer_name'] ) || empty( $data['customer_phone'] ) || empty( $data['recipient_name'] ) || empty( $data['recipient_phone'] ) || empty( $data['delivery_address'] ) ) {
			$this->redirect_with_notice( 'error', __( 'Please complete all required fields.', 'speedy-couriers-dispatch' ), $job_id, $job_id ? 'speedy-couriers-dispatch-job' : 'speedy-couriers-dispatch-add-job' );
		}

		if ( 'failed' === $data['status'] && empty( $data['failed_delivery_reason'] ) ) {
			$this->redirect_with_notice( 'error', __( 'A failed delivery reason is required when marking a job as failed.', 'speedy-couriers-dispatch' ), $job_id, $job_id ? 'speedy-couriers-dispatch-job' : 'speedy-couriers-dispatch-add-job' );
		}

		if ( $job_id ) {
			$current_job = SCD_Job_Repository::get_job( $job_id );
			if ( ! $current_job ) {
				$this->redirect_with_notice( 'error', __( 'Job not found.', 'speedy-couriers-dispatch' ), $job_id, 'speedy-couriers-dispatch-jobs' );
			}

			if ( ! SCD_Statuses::can_transition( $current_job['status'], $data['status'], 'controller' ) ) {
				$this->redirect_with_notice( 'error', __( 'That status transition is not allowed.', 'speedy-couriers-dispatch' ), $job_id, 'speedy-couriers-dispatch-job' );
			}

			$result = SCD_Job_Repository::update_job( $job_id, $data, get_current_user_id() );
		} else {
			$result = SCD_Job_Repository::create_job( $data );
			$job_id = is_wp_error( $result ) ? 0 : (int) $result;
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message(), $job_id, $job_id ? 'speedy-couriers-dispatch-job' : 'speedy-couriers-dispatch-add-job' );
		}

		$this->redirect_with_notice( 'success', __( 'Delivery job saved.', 'speedy-couriers-dispatch' ), $job_id, 'speedy-couriers-dispatch-job' );
	}

	/**
	 * Save quick row changes.
	 *
	 * @return void
	 */
	public function handle_quick_update(): void {
		if ( ! current_user_can( 'scd_assign_jobs' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'speedy-couriers-dispatch' ) );
		}

		$job_id = absint( SCD_Helpers::request( 'job_id', 0 ) );
		check_admin_referer( 'scd_quick_update_job_' . $job_id );

		$job = SCD_Job_Repository::get_job( $job_id );
		if ( ! $job ) {
			$this->redirect_with_notice( 'error', __( 'Job not found.', 'speedy-couriers-dispatch' ) );
		}

		$status  = sanitize_text_field( (string) SCD_Helpers::request( 'status', $job['status'] ) );
		$courier = absint( SCD_Helpers::request( 'assigned_courier_id', $job['assigned_courier_id'] ) );

		if ( ! SCD_Statuses::can_transition( $job['status'], $status, 'controller' ) ) {
			$status = $job['status'];
		}

		SCD_Job_Repository::update_job(
			$job_id,
			array(
				'status'              => $status,
				'assigned_courier_id' => $courier,
			),
			get_current_user_id()
		);

		$this->redirect_with_notice( 'success', __( 'Job updated.', 'speedy-couriers-dispatch' ) );
	}

	/**
	 * Save bulk list changes.
	 *
	 * @return void
	 */
	public function handle_bulk_update(): void {
		if ( ! current_user_can( 'scd_assign_jobs' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'speedy-couriers-dispatch' ) );
		}

		check_admin_referer( 'scd_bulk_update_jobs' );
		$job_ids      = array_map( 'absint', (array) SCD_Helpers::request( 'job_ids', array() ) );
		$bulk_action  = sanitize_text_field( (string) SCD_Helpers::request( 'bulk_action', '' ) );
		$courier_id   = absint( SCD_Helpers::request( 'bulk_courier_id', 0 ) );
		$status_value = sanitize_text_field( (string) SCD_Helpers::request( 'bulk_status_value', '' ) );

		if ( empty( $job_ids ) || empty( $bulk_action ) ) {
			$this->redirect_with_notice( 'error', __( 'Select at least one job and a valid bulk action.', 'speedy-couriers-dispatch' ) );
		}

		foreach ( $job_ids as $job_id ) {
			$job = SCD_Job_Repository::get_job( $job_id );
			if ( ! $job ) {
				continue;
			}

			if ( 'bulk_assign' === $bulk_action && $courier_id > 0 ) {
				SCD_Job_Repository::update_job(
					$job_id,
					array(
						'assigned_courier_id' => $courier_id,
						'status'              => 'new' === $job['status'] ? 'assigned' : $job['status'],
					),
					get_current_user_id()
				);
			}

			if ( 'bulk_status' === $bulk_action && array_key_exists( $status_value, SCD_Statuses::all() ) && SCD_Statuses::can_transition( $job['status'], $status_value, 'controller' ) ) {
				SCD_Job_Repository::update_status( $job_id, $status_value, get_current_user_id() );
			}
		}

		$this->redirect_with_notice( 'success', __( 'Bulk update completed.', 'speedy-couriers-dispatch' ) );
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( 'scd_manage_settings' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'speedy-couriers-dispatch' ) );
		}

		check_admin_referer( 'scd_save_settings' );
		SCD_Settings::update(
			array(
				'company_name'         => SCD_Helpers::request( 'company_name' ),
				'default_priority'     => SCD_Helpers::request( 'default_priority' ),
				'order_prefix'         => SCD_Helpers::request( 'order_prefix' ),
				'enable_frontend_form' => SCD_Helpers::request( 'enable_frontend_form' ),
				'enable_tracking'      => SCD_Helpers::request( 'enable_tracking' ),
			)
		);

		$this->redirect_with_notice( 'success', __( 'Settings saved.', 'speedy-couriers-dispatch' ), 0, 'speedy-couriers-dispatch-settings' );
	}

	/**
	 * Courier status update handler.
	 *
	 * @return void
	 */
	public function handle_courier_status_update(): void {
		if ( ! current_user_can( 'scd_update_assigned_jobs' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'speedy-couriers-dispatch' ) );
		}

		$job_id = absint( SCD_Helpers::request( 'job_id', 0 ) );
		check_admin_referer( 'scd_courier_status_update_' . $job_id );
		$job = SCD_Job_Repository::get_job( $job_id );

		if ( ! $job || get_current_user_id() !== (int) $job['assigned_courier_id'] ) {
			$this->redirect_with_notice( 'error', __( 'You can update only your assigned jobs.', 'speedy-couriers-dispatch' ), 0, 'speedy-couriers-dispatch-courier' );
		}

		$status = sanitize_text_field( (string) SCD_Helpers::request( 'status', '' ) );
		$reason = SCD_Helpers::sanitize_textarea( (string) SCD_Helpers::request( 'failed_reason', '' ) );

		if ( ! SCD_Statuses::can_transition( $job['status'], $status, 'courier' ) ) {
			$this->redirect_with_notice( 'error', __( 'That action is not available for this job.', 'speedy-couriers-dispatch' ), 0, 'speedy-couriers-dispatch-courier' );
		}

		if ( 'failed' === $status && empty( $reason ) ) {
			$this->redirect_with_notice( 'error', __( 'Please provide a failed delivery reason.', 'speedy-couriers-dispatch' ), 0, 'speedy-couriers-dispatch-courier' );
		}

		SCD_Job_Repository::update_status( $job_id, $status, get_current_user_id(), $reason );
		$this->redirect_with_notice( 'success', __( 'Job status updated.', 'speedy-couriers-dispatch' ), 0, 'speedy-couriers-dispatch-courier' );
	}

	/**
	 * Render shared job form.
	 *
	 * @param array $job Job data.
	 * @param bool  $is_new Whether new.
	 * @return void
	 */
	private function render_job_form( array $job, bool $is_new ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scd-panel scd-form">
			<?php wp_nonce_field( 'scd_save_job_' . (int) ( $job['ID'] ?? 0 ) ); ?>
			<input type="hidden" name="action" value="scd_save_job" />
			<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) ( $job['ID'] ?? 0 ) ); ?>" />
			<div class="scd-form-grid">
				<p>
					<label for="source"><?php esc_html_e( 'Source', 'speedy-couriers-dispatch' ); ?></label>
					<select id="source" name="source">
						<option value="phone" <?php selected( $job['source'], 'phone' ); ?>><?php esc_html_e( 'Phone', 'speedy-couriers-dispatch' ); ?></option>
						<option value="web" <?php selected( $job['source'], 'web' ); ?>><?php esc_html_e( 'Web', 'speedy-couriers-dispatch' ); ?></option>
					</select>
				</p>
				<p>
					<label for="customer_name"><?php esc_html_e( 'Customer Name', 'speedy-couriers-dispatch' ); ?></label>
					<input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr( $job['customer_name'] ); ?>" required />
				</p>
				<p>
					<label for="customer_phone"><?php esc_html_e( 'Customer Phone', 'speedy-couriers-dispatch' ); ?></label>
					<input type="text" id="customer_phone" name="customer_phone" value="<?php echo esc_attr( $job['customer_phone'] ); ?>" required />
				</p>
				<p>
					<label for="recipient_name"><?php esc_html_e( 'Recipient Name', 'speedy-couriers-dispatch' ); ?></label>
					<input type="text" id="recipient_name" name="recipient_name" value="<?php echo esc_attr( $job['recipient_name'] ); ?>" required />
				</p>
				<p>
					<label for="recipient_phone"><?php esc_html_e( 'Recipient Phone', 'speedy-couriers-dispatch' ); ?></label>
					<input type="text" id="recipient_phone" name="recipient_phone" value="<?php echo esc_attr( $job['recipient_phone'] ); ?>" required />
				</p>
				<p class="scd-span-2">
					<label for="delivery_address"><?php esc_html_e( 'Delivery Address', 'speedy-couriers-dispatch' ); ?></label>
					<textarea id="delivery_address" name="delivery_address" rows="3" required><?php echo esc_textarea( $job['delivery_address'] ); ?></textarea>
				</p>
				<p class="scd-span-2">
					<label for="delivery_notes"><?php esc_html_e( 'Delivery Notes', 'speedy-couriers-dispatch' ); ?></label>
					<textarea id="delivery_notes" name="delivery_notes" rows="3"><?php echo esc_textarea( $job['delivery_notes'] ); ?></textarea>
				</p>
				<p>
					<label for="priority"><?php esc_html_e( 'Priority', 'speedy-couriers-dispatch' ); ?></label>
					<select id="priority" name="priority">
						<option value="normal" <?php selected( $job['priority'], 'normal' ); ?>><?php esc_html_e( 'Normal', 'speedy-couriers-dispatch' ); ?></option>
						<option value="urgent" <?php selected( $job['priority'], 'urgent' ); ?>><?php esc_html_e( 'Urgent', 'speedy-couriers-dispatch' ); ?></option>
					</select>
				</p>
				<p>
					<label for="assigned_courier_id"><?php esc_html_e( 'Assign Courier', 'speedy-couriers-dispatch' ); ?></label>
					<select id="assigned_courier_id" name="assigned_courier_id">
						<option value="0"><?php esc_html_e( 'Unassigned', 'speedy-couriers-dispatch' ); ?></option>
						<?php foreach ( SCD_Job_Repository::get_couriers() as $courier ) : ?>
							<option value="<?php echo esc_attr( (string) $courier->ID ); ?>" <?php selected( (int) $job['assigned_courier_id'], $courier->ID ); ?>><?php echo esc_html( $courier->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="status"><?php esc_html_e( 'Status', 'speedy-couriers-dispatch' ); ?></label>
					<select id="status" name="status">
						<?php foreach ( SCD_Statuses::all() as $status => $label ) : ?>
							<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $job['status'], $status ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="scd-span-2">
					<label for="failed_delivery_reason"><?php esc_html_e( 'Failed Delivery Reason', 'speedy-couriers-dispatch' ); ?></label>
					<textarea id="failed_delivery_reason" name="failed_delivery_reason" rows="2"><?php echo esc_textarea( $job['failed_delivery_reason'] ); ?></textarea>
				</p>
			</div>
			<p>
				<button type="submit" class="button button-primary"><?php echo esc_html( $is_new ? __( 'Create Job', 'speedy-couriers-dispatch' ) : __( 'Update Job', 'speedy-couriers-dispatch' ) ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Render summary card.
	 *
	 * @param string $label Card label.
	 * @param int    $value Card value.
	 * @param string $status Status filter.
	 * @return void
	 */
	private function summary_card( string $label, int $value, string $status ): void {
		$url = SCD_Helpers::admin_url( 'speedy-couriers-dispatch-jobs', array( 'status_filter' => $status ) );
		?>
		<a class="scd-card" href="<?php echo esc_url( $url ); ?>">
			<span class="scd-card-label"><?php echo esc_html( $label ); ?></span>
			<strong class="scd-card-value"><?php echo esc_html( (string) $value ); ?></strong>
		</a>
		<?php
	}

	/**
	 * Notice renderer.
	 *
	 * @return void
	 */
	private function render_admin_notices(): void {
		$notice = sanitize_text_field( (string) SCD_Helpers::request( 'scd_notice', '' ) );
		$type   = sanitize_key( (string) SCD_Helpers::request( 'scd_notice_type', '' ) );

		if ( empty( $notice ) ) {
			return;
		}
		?>
		<div class="notice <?php echo 'success' === $type ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php
	}

	/**
	 * Redirect helper with notice.
	 *
	 * @param string $type success|error.
	 * @param string $message Notice text.
	 * @param int    $job_id Optional job id.
	 * @param string $page Target page.
	 * @return void
	 */
	private function redirect_with_notice( string $type, string $message, int $job_id = 0, string $page = 'speedy-couriers-dispatch-jobs' ): void {
		$args = array(
			'scd_notice_type' => $type,
			'scd_notice'      => $message,
		);


		if ( $job_id > 0 ) {
			$args['job_id'] = $job_id;
		}

		SCD_Helpers::redirect( SCD_Helpers::admin_url( $page, $args ) );
	}

	/**
	 * Resolve courier display name.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	private function courier_name( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return __( 'Unassigned', 'speedy-couriers-dispatch' );
		}

		$user = get_user_by( 'id', $user_id );
		return $user instanceof WP_User ? $user->display_name : __( 'Unknown', 'speedy-couriers-dispatch' );
	}

	/**
	 * Resolve user name.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	private function user_name( int $user_id ): string {
		$user = get_user_by( 'id', $user_id );
		return $user instanceof WP_User ? $user->display_name : __( 'System', 'speedy-couriers-dispatch' );
	}
}
