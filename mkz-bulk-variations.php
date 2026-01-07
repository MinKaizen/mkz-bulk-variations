<?php
/**
 * Plugin Name:     Bulk Variations
 * Plugin URI:      https://github.com/minkaizen/mkz-bulk-variations
 * Description:     High-performance tool for WooCommerce store managers to mass-create product variations via CSV upload or clipboard pasting
 * Author:          MinKaizen
 * Author URI:      https://minkaizen.com
 * Text Domain:     mkz-bulk-variations
 * Domain Path:     /languages
 * Version:         0.1.0
 * Requires PHP:    8.0
 * Requires at least: 5.8
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package         Mkz_Bulk_Variations
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'MKZ_BULK_VARIATIONS_VERSION', '0.1.0' );
define( 'MKZ_BULK_VARIATIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MKZ_BULK_VARIATIONS_URL', plugin_dir_url( __FILE__ ) );
define( 'MKZ_BULK_VARIATIONS_BASENAME', plugin_basename( __FILE__ ) );

// Require Composer autoloader.
if ( file_exists( MKZ_BULK_VARIATIONS_PATH . 'vendor/autoload.php' ) ) {
	require_once MKZ_BULK_VARIATIONS_PATH . 'vendor/autoload.php';
}

/**
 * Check if WooCommerce is active
 */
function mkz_bulk_variations_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'mkz_bulk_variations_woocommerce_notice' );
		return false;
	}
	return true;
}

/**
 * Display admin notice if WooCommerce is not active
 */
function mkz_bulk_variations_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'Bulk Variations requires WooCommerce to be installed and active.', 'mkz-bulk-variations' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin
 */
function mkz_bulk_variations_init() {
	if ( ! mkz_bulk_variations_check_woocommerce() ) {
		return;
	}

	// Initialize database schema on activation.
	register_activation_hook( __FILE__, array( 'BulkVariations\Core\Database', 'create_tables' ) );

	// Initialize admin functionality.
	if ( is_admin() ) {
		$admin = new \BulkVariations\Admin\Admin();
		$admin->init();
	}
}

add_action( 'plugins_loaded', 'mkz_bulk_variations_init' );

/**
 * Load plugin textdomain for translations
 */
function mkz_bulk_variations_load_textdomain() {
	load_plugin_textdomain(
		'mkz-bulk-variations',
		false,
		dirname( MKZ_BULK_VARIATIONS_BASENAME ) . '/languages'
	);
}

add_action( 'init', 'mkz_bulk_variations_load_textdomain' );
