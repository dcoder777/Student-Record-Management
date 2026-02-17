<?php
/**
 * Shortcode class.
 *
 * @package StudentCertificateVerification
 */

namespace StudentCertificateVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles verification shortcode.
 */
class Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'certificate_verify', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'scv-frontend',
			SCV_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SCV_PLUGIN_VERSION
		);
	}

	/**
	 * Render shortcode form.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		wp_enqueue_style( 'scv-frontend' );

		$ajax_nonce = wp_create_nonce( 'scv_verify_certificate_nonce' );
		$ajax_url   = admin_url( 'admin-ajax.php' );

		ob_start();
		?>
		<div class="scv-verify-wrapper">
			<form id="scv-verify-form" class="scv-verify-form">
				<?php wp_nonce_field( 'scv_verify_certificate_nonce', 'scv_verify_nonce' ); ?>
				<label for="scv_certificate_number"><?php esc_html_e( 'Certificate Number', 'student-certificate-verification' ); ?></label>
				<input type="text" id="scv_certificate_number" name="certificate_number" required />
				<button type="submit"><?php esc_html_e( 'Verify', 'student-certificate-verification' ); ?></button>
			</form>
			<div id="scv-result" class="scv-result" aria-live="polite"></div>
		</div>
		<script>
			(function () {
				const form = document.getElementById('scv-verify-form');
				const result = document.getElementById('scv-result');
				if (!form || !result) {
					return;
				}

				form.addEventListener('submit', function (event) {
					event.preventDefault();
					result.textContent = '<?php echo esc_js( __( 'Verifying...', 'student-certificate-verification' ) ); ?>';
					result.className = 'scv-result';

					const formData = new FormData();
					formData.append('action', 'scv_verify_certificate');
					formData.append('nonce', '<?php echo esc_js( $ajax_nonce ); ?>');
					formData.append('certificate_number', document.getElementById('scv_certificate_number').value);

					fetch('<?php echo esc_url( $ajax_url ); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						body: formData
					})
						.then(function (response) {
							return response.json();
						})
						.then(function (data) {
							if (data.success) {
								result.classList.add('scv-success');
								result.innerHTML =
									'<h4><?php echo esc_js( __( 'Certificate Verified', 'student-certificate-verification' ) ); ?></h4>' +
									'<p><strong><?php echo esc_js( __( 'Student Name:', 'student-certificate-verification' ) ); ?></strong> ' + data.data.student_name + '</p>' +
									'<p><strong><?php echo esc_js( __( 'Course Name:', 'student-certificate-verification' ) ); ?></strong> ' + data.data.course_name + '</p>' +
									'<p><strong><?php echo esc_js( __( 'Certificate Number:', 'student-certificate-verification' ) ); ?></strong> ' + data.data.certificate_number + '</p>';
							} else {
								result.classList.add('scv-error');
								result.textContent = data.data && data.data.message
									? data.data.message
									: '<?php echo esc_js( __( 'Certificate not found', 'student-certificate-verification' ) ); ?>';
							}
						})
						.catch(function () {
							result.classList.add('scv-error');
							result.textContent = '<?php echo esc_js( __( 'An unexpected error occurred.', 'student-certificate-verification' ) ); ?>';
						});
				});
			})();
		</script>
		<?php
		return (string) ob_get_clean();
	}
}
