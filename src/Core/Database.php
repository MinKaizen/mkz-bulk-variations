<?php
/**
 * Database Schema Manager
 *
 * @package BulkVariations\Core
 */

namespace BulkVariations\Core;

/**
 * Database class handles table creation and schema management
 */
class Database {

	/**
	 * Get the logs table name
	 *
	 * @return string
	 */
	public static function get_logs_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'bulk_variations_logs';
	}

	/**
	 * Create database tables on plugin activation
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::get_logs_table_name();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			input_data longtext NOT NULL,
			output_data longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a log entry
	 *
	 * @param int    $product_id The product ID.
	 * @param string $status The status (pending, success, error).
	 * @param array  $input_data The input data array.
	 * @param array  $output_data The output data array.
	 * @return int|false The inserted row ID or false on error.
	 */
	public static function insert_log( $product_id, $status, $input_data, $output_data = array() ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::get_logs_table_name(),
			array(
				'product_id'  => $product_id,
				'status'      => $status,
				'input_data'  => wp_json_encode( $input_data ),
				'output_data' => wp_json_encode( $output_data ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a log entry
	 *
	 * @param int    $log_id The log entry ID.
	 * @param string $status The status.
	 * @param array  $output_data The output data array.
	 * @return bool True on success, false on failure.
	 */
	public static function update_log( $log_id, $status, $output_data = array() ) {
		global $wpdb;

		$result = $wpdb->update(
			self::get_logs_table_name(),
			array(
				'status'      => $status,
				'output_data' => wp_json_encode( $output_data ),
			),
			array( 'id' => $log_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get log entries for a product
	 *
	 * @param int $product_id The product ID.
	 * @param int $limit Maximum number of entries to retrieve.
	 * @return array Array of log entries.
	 */
	public static function get_logs_by_product( $product_id, $limit = 10 ) {
		global $wpdb;

		$table_name = self::get_logs_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE product_id = %d ORDER BY created_at DESC LIMIT %d",
				$product_id,
				$limit
			),
			ARRAY_A
		);

		// Decode JSON fields.
		foreach ( $results as &$result ) {
			$result['input_data']  = json_decode( $result['input_data'], true );
			$result['output_data'] = json_decode( $result['output_data'], true );
		}

		return $results;
	}
}
