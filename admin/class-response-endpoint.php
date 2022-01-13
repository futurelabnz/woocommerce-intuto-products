<?php
/**
 * Creates the redirect endpoint.
 *
 * @package Intuto
 */

namespace Intuto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Response_Endpoint
 *
 * @package Intuto
 */
class Response_Endpoint {

	/**
	 * Class instance.
	 *
	 * @var Response_Endpoint instance
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
		$this->add_endpoint();
	}

	/**
	 * The endpoint which handles the returning code when OAuth token is requested.
	 */
	private function add_endpoint() {

		if ( isset( $_GET['intuto-redirect-page'] ) ) {

			if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {

				$nonce = sanitize_text_field( wp_unslash( $_GET['state'] ) );

				if ( ! wp_verify_nonce( $nonce, 'intuto-link' ) ) {
					die( esc_html( __( 'Security check', 'woocommerce-intuto-products' ) ) );
				}

				$intuto_code = sanitize_text_field( wp_unslash( $_GET['code'] ) );

				if ( ! empty( $intuto_code ) && false !== $intuto_code ) {

					$result = ( new Workflow() )->get_access_token( $intuto_code );

					if ( true === $result ) {

						include plugin_dir_path( __DIR__ ) . '/templates/site-authorized.php';
					} else {

						include plugin_dir_path( __DIR__ ) . '/templates/site-unauthorized.php';
					}
					exit();
				}
			}
		}
	}
}

add_action( 'init', array( 'Intuto\Response_Endpoint', 'get_instance' ) );
