<?php
/**
 * Public shortcodes.
 *
 * @package SpeedyCouriersDispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public controller.
 */
class SCD_Public {
	/**
	 * Register public hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'speedy_couriers_order_form', array( $this, 'render_order_form_shortcode' ) );
		add_shortcode( 'speedy_couriers_track_order', array( $this, 'render_track_order_shortcode' ) );
		add_action( 'admin_post_nopriv_scd_frontend_order_submit', array( $this, 'handle_frontend_order_submit' ) );
		add_action( 'admin_post_scd_frontend_order_submit', array( $this, 'handle_frontend_order_submit' ) );
	}

	/**
	 * Frontend form shortcode.
	 *
	 * @return string
	 */
	public function render_order_form_shortcode(): string {
		if ( 'yes' !== SCD_Settings::get( 'enable_frontend_form' ) ) {
			return '<p>' . esc_html__( 'Online order submission is currently disabled.', 'speedy-couriers-dispatch' ) . '</p>';
		}

		ob_start();
		$notice = sanitize_text_field( (string) SCD_Helpers::request( 'scd_notice', '' ) );
		$type   = sanitize_key( (string) SCD_Helpers::request( 'scd_notice_type', '' ) );
		?>
		<div class="scd-public-wrap">
			<?php if ( $notice ) : ?>
				<div class="scd-message <?php echo 'success' === $type ? 'success' : 'error'; ?>"><?php echo esc_html( $notice ); ?></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scd-public-form">
				<?php wp_nonce_field( 'scd_frontend_order_submit' ); ?>
				<input type="hidden" name="action" value="scd_frontend_order_submit" />
				<p><label><?php esc_html_e( 'Customer Name', 'speedy-couriers-dispatch' ); ?><input type="text" name="customer_name" required /></label></p>
				<p><label><?php esc_html_e( 'Customer Phone', 'speedy-couriers-dispatch' ); ?><input type="text" name="customer_phone" required /></label></p>
				<p><label><?php esc_html_e( 'Recipient Name', 'speedy-couriers-dispatch' ); ?><input type="text" name="recipient_name" required /></label></p>
				<p><label><?php esc_html_e( 'Recipient Phone', 'speedy-couriers-dispatch' ); ?><input type="text" name="recipient_phone" required /></label></p>
				<p><label><?php esc_html_e( 'Delivery Address', 'speedy-couriers-dispatch' ); ?><textarea name="delivery_address" rows="4" required></textarea></label></p>
				<p><label><?php esc_html_e( 'Delivery Notes', 'speedy-couriers-dispatch' ); ?><textarea name="delivery_notes" rows="3"></textarea></label></p>
				<p>
					<label><?php esc_html_e( 'Priority', 'speedy-couriers-dispatch' ); ?>
						<select name="priority">
							<option value="normal"><?php esc_html_e( 'Normal', 'speedy-couriers-dispatch' ); ?></option>
							<option value="urgent"><?php esc_html_e( 'Urgent', 'speedy-couriers-dispatch' ); ?></option>
						</select>
					</label>
				</p>
				<p><button type="submit"><?php esc_html_e( 'Submit Delivery Request', 'speedy-couriers-dispatch' ); ?></button></p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Tracking shortcode.
	 *
	 * @return string
	 */
	public function render_track_order_shortcode(): string {
		if ( 'yes' !== SCD_Settings::get( 'enable_tracking' ) ) {
			return '<p>' . esc_html__( 'Order tracking is currently disabled.', 'speedy-couriers-dispatch' ) . '</p>';
		}

		$order_number = sanitize_text_field( (string) SCD_Helpers::request( 'order_number', '' ) );
		$phone        = sanitize_text_field( (string) SCD_Helpers::request( 'phone', '' ) );
		$job          = null;

		if ( $order_number && $phone ) {
			$result = SCD_Job_Repository::query_jobs(
				array(
					'posts_per_page' => -1,
					'search'         => $order_number,
				)
			);

			foreach ( $result['jobs'] as $candidate ) {
				if ( $candidate['order_number'] === $order_number && ( $candidate['customer_phone'] === $phone || $candidate['recipient_phone'] === $phone ) ) {
					$job = $candidate;
					break;
				}
			}
		}

		ob_start();
		?>
		<div class="scd-public-wrap">
			<form method="get" class="scd-public-form">
				<p><label><?php esc_html_e( 'Order Number', 'speedy-couriers-dispatch' ); ?><input type="text" name="order_number" value="<?php echo esc_attr( $order_number ); ?>" required /></label></p>
				<p><label><?php esc_html_e( 'Phone Number', 'speedy-couriers-dispatch' ); ?><input type="text" name="phone" value="<?php echo esc_attr( $phone ); ?>" required /></label></p>
				<p><button type="submit"><?php esc_html_e( 'Track Order', 'speedy-couriers-dispatch' ); ?></button></p>
			</form>
			<?php if ( $order_number || $phone ) : ?>
				<?php if ( $job ) : ?>
					<div class="scd-track-result">
						<p><strong><?php esc_html_e( 'Order', 'speedy-couriers-dispatch' ); ?>:</strong> <?php echo esc_html( $job['order_number'] ); ?></p>
						<p><strong><?php esc_html_e( 'Status', 'speedy-couriers-dispatch' ); ?>:</strong> <?php echo esc_html( SCD_Statuses::label( $job['status'] ) ); ?></p>
						<p><strong><?php esc_html_e( 'Recipient', 'speedy-couriers-dispatch' ); ?>:</strong> <?php echo esc_html( $job['recipient_name'] ); ?></p>
						<p><strong><?php esc_html_e( 'Delivery Address', 'speedy-couriers-dispatch' ); ?>:</strong> <?php echo esc_html( $job['delivery_address'] ); ?></p>
						<p><strong><?php esc_html_e( 'Last Updated', 'speedy-couriers-dispatch' ); ?>:</strong> <?php echo esc_html( SCD_Helpers::format_datetime( $job['post_modified'] ) ); ?></p>
					</div>
				<?php else : ?>
					<div class="scd-message error"><?php esc_html_e( 'No matching order was found.', 'speedy-couriers-dispatch' ); ?></div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle public order submission.
	 *
	 * @return void
	 */
	public function handle_frontend_order_submit(): void {
		if ( 'yes' !== SCD_Settings::get( 'enable_frontend_form' ) ) {
			wp_die( esc_html__( 'Frontend order form is disabled.', 'speedy-couriers-dispatch' ) );
		}

		check_admin_referer( 'scd_frontend_order_submit' );

		$data = array(
			'source'             => 'web',
			'customer_name'      => sanitize_text_field( (string) SCD_Helpers::request( 'customer_name', '' ) ),
			'customer_phone'     => sanitize_text_field( (string) SCD_Helpers::request( 'customer_phone', '' ) ),
			'recipient_name'     => sanitize_text_field( (string) SCD_Helpers::request( 'recipient_name', '' ) ),
			'recipient_phone'    => sanitize_text_field( (string) SCD_Helpers::request( 'recipient_phone', '' ) ),
			'delivery_address'   => SCD_Helpers::sanitize_textarea( (string) SCD_Helpers::request( 'delivery_address', '' ) ),
			'delivery_notes'     => SCD_Helpers::sanitize_textarea( (string) SCD_Helpers::request( 'delivery_notes', '' ) ),
			'priority'           => sanitize_text_field( (string) SCD_Helpers::request( 'priority', SCD_Settings::get( 'default_priority' ) ) ),
			'status'             => 'new',
			'created_by_user_id' => get_current_user_id(),
		);

		$url  = wp_get_referer() ?: home_url( '/' );
		$args = array();

		if ( empty( $data['customer_name'] ) || empty( $data['customer_phone'] ) || empty( $data['recipient_name'] ) || empty( $data['recipient_phone'] ) || empty( $data['delivery_address'] ) ) {
			$args['scd_notice_type'] = 'error';
			$args['scd_notice']      = __( 'Please complete all required fields.', 'speedy-couriers-dispatch' );
			SCD_Helpers::redirect( add_query_arg( $args, $url ) );
		}

		$result = SCD_Job_Repository::create_job( $data );

		if ( is_wp_error( $result ) ) {
			$args['scd_notice_type'] = 'error';
			$args['scd_notice']      = $result->get_error_message();
		} else {
			$args['scd_notice_type'] = 'success';
			$args['scd_notice']      = __( 'Your delivery request has been submitted.', 'speedy-couriers-dispatch' );
		}

		SCD_Helpers::redirect( add_query_arg( $args, $url ) );
	}
}
