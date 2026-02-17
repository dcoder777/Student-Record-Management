<?php
/**
 * Database class.
 *
 * @package StudentCertificateVerification
 */

namespace StudentCertificateVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom table creation.
 */
class Database {

	/**
	 * Get certificate table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'student_certificates';
	}

	/**
	 * Create plugin table on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			student_name VARCHAR(255) NOT NULL,
			course_id BIGINT(20) UNSIGNED NOT NULL,
			certificate_number VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY certificate_number (certificate_number)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
