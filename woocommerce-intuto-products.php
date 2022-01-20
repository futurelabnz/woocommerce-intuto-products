<?php
/**
 * Plugin Name:     Woocommerce Intuto Products
 * Plugin URI:      https://github.com/futurelabnz/woocommerce-intuto-products
 * Description:     Link your Intuto Account to your WooCommerce store to sell Intuto Collections
 * Author:          FutureLab
 * Author URI:      https://www.futurelab.digital/
 * Text Domain:     woocommerce-intuto-products
 * Domain Path:     /languages
 * Version:         0.2.0
 *
 * @package         Woocommerce_Intuto_Products
 */

namespace Intuto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'inc/class-workflow.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-members.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-collections.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-purchase.php';

require_once plugin_dir_path( __FILE__ ) . 'admin/class-wc-intuto-settings-tab.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/class-wc-intuto-product-tab.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/class-response-endpoint.php';

/**
 * Class Woocommerce_Intuto_Products
 *
 * @package Intuto
 */
class Woocommerce_Intuto_Products {

	/**
	 * Class instance.
	 *
	 * @var Woocommerce_Intuto_Products instance
	 */
	protected static $instance = null;

	/**
	 * Get class instance
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Check dependencies on activation
	 */
	public static function activate() {

		if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
			if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				die( esc_html( __( 'This plugin requires WooCommerce to be active. Please add WooCommerce to your site first.', 'woocommerce-intuto-products' ) ) );
			}
		}
		// Scheduling token refresh.
		if ( ! wp_next_scheduled( 'api_token_refresh_action' ) ) {
			wp_schedule_event( time(), 'daily', 'api_token_refresh_action' );
		}

	}

	/**
	 * Deactivation removed the stored API data
	 */
	public static function deactivate() {

		$option_names = array(
			'intuto_products_client_id',
			'intuto_products_client_secret',
			'intuto_products_collections',
			'intuto_access_token',
			'intuto_refresh_token',
		);

		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}
	}

}

add_action( 'init', array( 'Intuto\WooCommerce_Intuto_Products', 'get_instance' ) );
register_deactivation_hook( __FILE__, array( 'Intuto\WooCommerce_Intuto_Products', 'deactivate' ) );
register_activation_hook( __FILE__, array( 'Intuto\WooCommerce_Intuto_Products', 'activate' ) );
