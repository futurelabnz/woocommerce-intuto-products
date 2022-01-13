<?php
/**
 * WC_Intuto_Product_Tab
 * Handles all presentation and saves of custom fields on products
 *
 * @package Intuto
 */

namespace Intuto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Intuto_Product_Tab.
 *
 * @package Intuto
 */
class WC_Intuto_Product_Tab {

	/**
	 * Class instance.
	 *
	 * @var WC_Intuto_Product_Tab instance
	 */
	protected static $instance = null;

	/**
	 * Get class instance.
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
		/* Enqueue scripts required for autocomplete field */
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		/* Adds product tab and product tab fields */
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'get_product_tab' ), 10, 1 );
		add_action( 'woocommerce_product_data_panels', array( $this, 'get_product_tab_fields' ) );
		/* Saves product tab data */
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_tab_data' ), 10, 1 );

	}


	/**
	 * We require jQuery for the jQuery Autocomplete component in
	 * the collections field. Uses WordPress Core scripts.
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		/**
		 * Our JS
		 */
		wp_enqueue_script(
			'intuto',
			plugin_dir_url( __DIR__ ) . '/assets/js/intuto.js',
			array( 'jquery-ui-core', 'jquery-ui-autocomplete' ),
			filemtime( plugin_dir_path( __DIR__ ) . 'assets/js/intuto.js' ),
			true
		);

		/**
		 * Variables for AJAX calls
		 */
		wp_localize_script(
			'intuto',
			'intuto_vars',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'ajax_nonce'  => wp_create_nonce( 'intuto' ),
				'collections' => array_values( $this->get_collection_options() ),
			)
		);

	}


	/**
	 * Adds the Intuto Products tab to the product data tabs
	 *
	 * @param array $product_data_tabs From WC. Array of existing product data tabs.
	 *
	 * @return mixed
	 */
	public function get_product_tab( $product_data_tabs ) {

		$product_data_tabs['intuto_product'] = array(
			'label'  => __( 'Intuto Products', 'woocommerce-intuto-products' ),
			'target' => 'intuto_product_data',
		);

		return $product_data_tabs;

	}

	/**
	 * Adds the Intuto custom meta fields to the Intuto Product Tab.
	 */
	public function get_product_tab_fields() {

		/**
		 * Get the collection data for the autocomplete data source.
		 */

		?>

		<div id="intuto_product_data" class="panel woocommerce_options_panel">

			<?php

			$collection_options = $this->get_collection_options();

			/**
			 * If the $collection_options fetch returned an error, it will be a string, not an
			 * array, and will present an admin notice warning.
			 */
			if ( is_string( $collection_options ) ) {

				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $collection_options ) . '</p></div>';

			} else {

				/**
				 * This is a text field driven by jQuery autocomplete, which allows us to handle collections which
				 * are too large for a standard dropdown.
				 */

				woocommerce_wp_select( 
					array( // Text Field type
						'id'          => '_intuto_collection_title',
						'label'       => __( 'Linked Intuto Course', 'woocommerce-intuto-products' ),
						'description' => __( 'Select the course linked to this product.', 'woocommerce-intuto-products' ),
						'desc_tip'    => true,
						'options'     => $collection_options
					)
				);


				/**
				 * This is the hidden input where the ID of the collection is stored
				 * when a select event is triggered in the autocomplete dropdown menu.
				 */
				woocommerce_wp_hidden_input(
					array(
						'id'       => '_intuto_collection_id',
						'default'  => '0',
						'desc_tip' => false,
					)
				);
			}

			/**
			 * Jquery to drive the autocomplete search.
			 */
			?>

			<h4><?php esc_html_e( 'Missing something?', 'woocommerce-intuto-products' ); ?></h4>
			<p>
				<?php esc_html_e( 'Synchronize your WooCommerce store and your Intuto Account.', 'woocommerce-intuto-products' ); ?>
			</p>
			<p>
				<button type="button" class="button refresh-intuto-products-list">
					<span class="wp-media-buttons-icon"></span>
					<?php esc_html_e( 'Refresh collections list', 'woocommerce-intuto-products' ); ?>
				</button>
			</p>
			<p class="intuto-products-list-message">
				<?php
				echo wp_kses(
					( new Collections() )->get_collection_count_message(),
					array(
						'p' => array( 'class' ),
						'br',
					)
				);
				?>
			</p>

		</div>

		<script>
			//var intuto_collections = <?php echo wp_json_encode( array_values( $collection_options ) ); ?>;
		</script>

		<?php

	}

	/**
	 * Returns an array of label and value for the name and id of
	 * the Intuto Collections listing. Returns a string error message on error.
	 *
	 * @return array|string|void
	 */
	private function get_collection_options() {

		$output = array();

		$collections = get_option( 'intuto_product_collections' );

		if ( 0 === count( $collections ) ) {
			return __( 'You have no collections in your Intuto account. You need to create a collection first.', 'woocommerce-intuto-products' );
		}

		foreach ( $collections as $collection ) {
			$output[ $collection->CollectionId ] = $collection->CollectionName;
		}

		return $output;
	}

	/**
	 * Save the Intuto Product custom meta data.
	 *
	 * @param string|int $post_id Passed by WC. Post ID of the product.
	 */
	public function save_product_tab_data(
		$post_id
	) {
		if ( isset( $_POST['_intuto_collection_title'] ) ) {
			update_post_meta( $post_id, '_intuto_collection_title', sanitize_text_field( wp_unslash( $_POST['_intuto_collection_title'] ) ) );
		}
		if ( isset( $_POST['_intuto_collection_id'] ) ) {
			update_post_meta( $post_id, '_intuto_collection_id', sanitize_text_field( wp_unslash( $_POST['_intuto_collection_id'] ) ) );
		}

	}

}

add_action( 'admin_menu', array( 'Intuto\WC_Intuto_Product_Tab', 'get_instance' ) );
