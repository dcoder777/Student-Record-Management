<?php
/**
 * Admin class.
 *
 * @package StudentCertificateVerification
 */

namespace StudentCertificateVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage admin page for student records.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_scv_add_record', array( $this, 'handle_add_record' ) );
		add_action( 'admin_post_scv_delete_record', array( $this, 'handle_delete_record' ) );
		add_action( 'admin_notices', array( $this, 'render_tutor_lms_notice' ) );
	}

	/**
	 * Check whether Tutor LMS is active and courses post type exists.
	 *
	 * @return bool
	 */
	private function is_tutor_lms_active() {
		return post_type_exists( 'courses' );
	}

	/**
	 * Register plugin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Student Records', 'student-certificate-verification' ),
			__( 'Student Records', 'student-certificate-verification' ),
			'manage_options',
			'scv-student-records',
			array( $this, 'render_admin_page' ),
			'dashicons-id',
			26
		);
	}

	/**
	 * Handle add record form submission.
	 *
	 * @return void
	 */
	public function handle_add_record() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'student-certificate-verification' ) );
		}

		check_admin_referer( 'scv_add_record_action', 'scv_add_record_nonce' );

		if ( ! $this->is_tutor_lms_active() ) {
			$this->redirect_with_message(
				'error',
				__( 'Tutor LMS must be active to add student records.', 'student-certificate-verification' )
			);
		}

		global $wpdb;
		$table_name = Database::get_table_name();

		$student_name       = isset( $_POST['student_name'] ) ? sanitize_text_field( wp_unslash( $_POST['student_name'] ) ) : '';
		$course_id          = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$certificate_number = isset( $_POST['certificate_number'] ) ? sanitize_text_field( wp_unslash( $_POST['certificate_number'] ) ) : '';

		if ( '' === $student_name || 0 === $course_id || '' === $certificate_number ) {
			$this->redirect_with_message(
				'error',
				__( 'All fields are required.', 'student-certificate-verification' )
			);
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, student_name, course_id, certificate_number, created_at FROM {$table_name} WHERE certificate_number = %s",
				$certificate_number
			)
		);

		if ( $existing ) {
			$course_name = get_the_title( (int) $existing->course_id );
			$message     = sprintf(
				/* translators: 1: student name, 2: course name, 3: certificate number, 4: created date. */
				__( 'Duplicate certificate number found. Existing record: Student: %1$s, Course: %2$s, Certificate: %3$s, Created: %4$s', 'student-certificate-verification' ),
				$existing->student_name,
				$course_name ? $course_name : __( 'Unknown Course', 'student-certificate-verification' ),
				$existing->certificate_number,
				$existing->created_at
			);

			$this->redirect_with_message( 'error', $message );
		}

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'student_name'        => $student_name,
				'course_id'           => $course_id,
				'certificate_number'  => $certificate_number,
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->redirect_with_message( 'error', __( 'Failed to save record.', 'student-certificate-verification' ) );
		}

		$this->redirect_with_message( 'success', __( 'Record added successfully.', 'student-certificate-verification' ) );
	}

	/**
	 * Handle delete action.
	 *
	 * @return void
	 */
	public function handle_delete_record() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'student-certificate-verification' ) );
		}

		$record_id = isset( $_GET['record_id'] ) ? absint( $_GET['record_id'] ) : 0;
		$nonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $record_id || ! wp_verify_nonce( $nonce, 'scv_delete_record_' . $record_id ) ) {
			$this->redirect_with_message( 'error', __( 'Invalid request.', 'student-certificate-verification' ) );
		}

		global $wpdb;
		$table_name = Database::get_table_name();
		$deleted    = $wpdb->delete( $table_name, array( 'id' => $record_id ), array( '%d' ) );

		if ( false === $deleted ) {
			$this->redirect_with_message( 'error', __( 'Failed to delete record.', 'student-certificate-verification' ) );
		}

		$this->redirect_with_message( 'success', __( 'Record deleted successfully.', 'student-certificate-verification' ) );
	}

	/**
	 * Redirect back to admin page with message.
	 *
	 * @param string $type Message type.
	 * @param string $text Message text.
	 * @return void
	 */
	private function redirect_with_message( $type, $text ) {
		$url = add_query_arg(
			array(
				'page'     => 'scv-student-records',
				'msg_type' => rawurlencode( $type ),
				'msg_text' => rawurlencode( $text ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->render_notice();
		$tutor_lms_active = $this->is_tutor_lms_active();
		$courses          = $this->get_course_posts();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Student Records', 'student-certificate-verification' ); ?></h1>

			<?php $this->render_shortcode_notice(); ?>

			<h2><?php esc_html_e( 'Add Student Record', 'student-certificate-verification' ); ?></h2>
			<?php $this->render_add_record_form( $courses, $tutor_lms_active ); ?>

			<hr />
			<h2><?php esc_html_e( 'All Records', 'student-certificate-verification' ); ?></h2>
			<?php $this->render_records_table(); ?>
		</div>
		<?php
	}

	/**
	 * Render shortcode helper notice.
	 *
	 * @return void
	 */
	private function render_shortcode_notice() {
		?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'Use this shortcode on any page to let users verify certificates:', 'student-certificate-verification' ); ?>
				<strong><code>[certificate_verify]</code></strong>
			</p>
		</div>
		<?php
	}

	/**
	 * Render add record form or Tutor LMS warning.
	 *
	 * @param array<int,\WP_Post> $courses Course posts.
	 * @param bool                  $tutor_lms_active Tutor LMS status.
	 * @return void
	 */
	private function render_add_record_form( $courses, $tutor_lms_active ) {
		if ( ! $tutor_lms_active ) {
			?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Tutor LMS is required for this plugin. Please install and activate Tutor LMS to load course records from the courses post type.', 'student-certificate-verification' ); ?></p></div>
			<?php
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="scv_add_record" />
			<?php wp_nonce_field( 'scv_add_record_action', 'scv_add_record_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="student_name"><?php esc_html_e( 'Student Name', 'student-certificate-verification' ); ?></label></th>
					<td><input type="text" id="student_name" name="student_name" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="course_id"><?php esc_html_e( 'Course Name', 'student-certificate-verification' ); ?></label></th>
					<td>
						<select id="course_id" name="course_id" required>
							<option value=""><?php esc_html_e( 'Select Course', 'student-certificate-verification' ); ?></option>
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( $course->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="certificate_number"><?php esc_html_e( 'Certificate Number', 'student-certificate-verification' ); ?></label></th>
					<td><input type="text" id="certificate_number" name="certificate_number" class="regular-text" required /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Add Record', 'student-certificate-verification' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render global notice when Tutor LMS is not active.
	 *
	 * @return void
	 */
	public function render_tutor_lms_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin() || $this->is_tutor_lms_active() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'toplevel_page_scv-student-records' !== $screen->id ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Student Certificate Verification requires Tutor LMS. Please install and activate Tutor LMS to use the courses dropdown.', 'student-certificate-verification' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render notice from query args.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( ! isset( $_GET['msg_text'], $_GET['msg_type'] ) ) {
			return;
		}

		$type = sanitize_text_field( wp_unslash( $_GET['msg_type'] ) );
		$text = sanitize_text_field( wp_unslash( $_GET['msg_text'] ) );

		$class = 'notice notice-success is-dismissible';
		if ( 'error' === $type ) {
			$class = 'notice notice-error is-dismissible';
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( rawurldecode( $text ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Get course posts for dropdown.
	 *
	 * @return array<int,\WP_Post>
	 */
	private function get_course_posts() {
		$query = new \WP_Query(
			array(
				'post_type'      => 'courses',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return $query->posts;
	}

	/**
	 * Render records table with search and pagination.
	 *
	 * @return void
	 */
	private function render_records_table() {
		global $wpdb;

		$table_name   = Database::get_table_name();
		$per_page     = 10;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$where_sql = '1=1';
		$params    = array();

		if ( '' !== $search ) {
			$where_sql = '(student_name LIKE %s OR certificate_number LIKE %s)';
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$params[]  = $like;
			$params[]  = $like;
		}

		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
		if ( ! empty( $params ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $params );
		}
		$total_items = (int) $wpdb->get_var( $count_sql );

		$list_sql = "SELECT id, student_name, course_id, certificate_number, created_at FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		if ( ! empty( $params ) ) {
			$params[] = $per_page;
			$params[] = $offset;
			$list_sql = $wpdb->prepare( $list_sql, $params );
		} else {
			$list_sql = $wpdb->prepare( $list_sql, $per_page, $offset );
		}
		$records = $wpdb->get_results( $list_sql );

		$total_pages = (int) ceil( $total_items / $per_page );
		?>
		<form method="get">
			<input type="hidden" name="page" value="scv-student-records" />
			<p class="search-box">
				<label class="screen-reader-text" for="record-search-input"><?php esc_html_e( 'Search Records', 'student-certificate-verification' ); ?></label>
				<input type="search" id="record-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" />
				<?php submit_button( __( 'Search Records', 'student-certificate-verification' ), '', '', false ); ?>
			</p>
		</form>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student Name', 'student-certificate-verification' ); ?></th>
					<th><?php esc_html_e( 'Course Name', 'student-certificate-verification' ); ?></th>
					<th><?php esc_html_e( 'Certificate Number', 'student-certificate-verification' ); ?></th>
					<th><?php esc_html_e( 'Created Date', 'student-certificate-verification' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'student-certificate-verification' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $records ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No records found.', 'student-certificate-verification' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $records as $record ) : ?>
						<tr>
							<td><?php echo esc_html( $record->student_name ); ?></td>
							<td><?php echo esc_html( get_the_title( (int) $record->course_id ) ); ?></td>
							<td><?php echo esc_html( $record->certificate_number ); ?></td>
							<td><?php echo esc_html( $record->created_at ); ?></td>
							<td>
								<?php
								$delete_url = wp_nonce_url(
									add_query_arg(
										array(
											'action'    => 'scv_delete_record',
											'record_id' => (int) $record->id,
										),
										admin_url( 'admin-post.php' )
									),
									'scv_delete_record_' . (int) $record->id
								);
								?>
								<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this record?', 'student-certificate-verification' ) ); ?>');">
									<?php esc_html_e( 'Delete', 'student-certificate-verification' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => __( '&laquo;', 'student-certificate-verification' ),
						'next_text' => __( '&raquo;', 'student-certificate-verification' ),
						'total'     => $total_pages,
						'current'   => $current_page,
					)
				)
			);
			echo '</div></div>';
		}
	}
}
