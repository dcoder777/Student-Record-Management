<?php
/**
 * AJAX class.
 *
 * @package StudentCertificateVerification
 */

namespace StudentCertificateVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX verification requests.
 */
class Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_scv_verify_certificate', array( $this, 'verify_certificate' ) );
		add_action( 'wp_ajax_nopriv_scv_verify_certificate', array( $this, 'verify_certificate' ) );
	}

	/**
	 * Verify a certificate number.
	 *
	 * @return void
	 */
	public function verify_certificate() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'scv_verify_certificate_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid request.', 'student-certificate-verification' ),
				),
				403
			);
		}

		$certificate_number = isset( $_POST['certificate_number'] ) ? sanitize_text_field( wp_unslash( $_POST['certificate_number'] ) ) : '';

		if ( '' === $certificate_number ) {
			wp_send_json_error(
				array(
					'message' => __( 'Certificate not found', 'student-certificate-verification' ),
				),
				404
			);
		}

		global $wpdb;
		$table_name = Database::get_table_name();

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT student_name, course_id, certificate_number FROM {$table_name} WHERE certificate_number = %s",
				$certificate_number
			)
		);

		if ( ! $record ) {
			wp_send_json_error(
				array(
					'message' => __( 'Certificate not found', 'student-certificate-verification' ),
				),
				404
			);
		}

		wp_send_json_success(
			array(
				'message'            => __( 'Certificate Verified', 'student-certificate-verification' ),
				'student_name'       => esc_html( $record->student_name ),
				'course_name'        => esc_html( get_the_title( (int) $record->course_id ) ),
				'certificate_number' => esc_html( $record->certificate_number ),
			)
		);
	}
}
