<?php
/**
 * Log Entry Model
 *
 * @package BulkVariations\Models
 */

namespace BulkVariations\Models;

/**
 * Log_Entry represents a single import log entry
 */
class Log_Entry {

	/**
	 * Log entry ID
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Product ID
	 *
	 * @var int
	 */
	public $product_id;

	/**
	 * Import status
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Input data
	 *
	 * @var array
	 */
	public $input_data;

	/**
	 * Output data
	 *
	 * @var array
	 */
	public $output_data;

	/**
	 * Created timestamp
	 *
	 * @var string
	 */
	public $created_at;

	/**
	 * Constructor
	 *
	 * @param array $data Log entry data from database.
	 */
	public function __construct( array $data ) {
		$this->id          = isset( $data['id'] ) ? intval( $data['id'] ) : 0;
		$this->product_id  = isset( $data['product_id'] ) ? intval( $data['product_id'] ) : 0;
		$this->status      = isset( $data['status'] ) ? $data['status'] : 'pending';
		$this->input_data  = isset( $data['input_data'] ) ? $data['input_data'] : array();
		$this->output_data = isset( $data['output_data'] ) ? $data['output_data'] : array();
		$this->created_at  = isset( $data['created_at'] ) ? $data['created_at'] : '';
	}

	/**
	 * Get status label
	 *
	 * @return string
	 */
	public function get_status_label() {
		$labels = array(
			'pending' => __( 'Pending', 'mkz-bulk-variations' ),
			'success' => __( 'Success', 'mkz-bulk-variations' ),
			'error'   => __( 'Error', 'mkz-bulk-variations' ),
		);

		return isset( $labels[ $this->status ] ) ? $labels[ $this->status ] : $this->status;
	}

	/**
	 * Convert to array representation
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'          => $this->id,
			'product_id'  => $this->product_id,
			'status'      => $this->status,
			'input_data'  => $this->input_data,
			'output_data' => $this->output_data,
			'created_at'  => $this->created_at,
		);
	}
}
