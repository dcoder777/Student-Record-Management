<?php
/**
 * Plugin Name: Student Certificate Verification
 * Description: Verify student certificates via shortcode and manage student certificate records from admin.
 * Version: 1.0.0
 * Author: Codex
 * Text Domain: student-certificate-verification
 * Domain Path: /languages
 *
 * @package StudentCertificateVerification
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCV_PLUGIN_FILE', __FILE__ );
define( 'SCV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCV_PLUGIN_VERSION', '1.0.0' );

require_once SCV_PLUGIN_DIR . 'includes/class-database.php';
require_once SCV_PLUGIN_DIR . 'includes/class-admin.php';
require_once SCV_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once SCV_PLUGIN_DIR . 'includes/class-ajax.php';

use StudentCertificateVerification\Database;
use StudentCertificateVerification\Admin;
use StudentCertificateVerification\Shortcode;
use StudentCertificateVerification\Ajax;

/**
 * Bootstrap plugin classes.
 *
 * @return void
 */
function scv_bootstrap() {
	if ( is_admin() ) {
		new Admin();
	}

	new Shortcode();
	new Ajax();
}

add_action( 'plugins_loaded', 'scv_bootstrap' );
register_activation_hook( SCV_PLUGIN_FILE, array( Database::class, 'activate' ) );
