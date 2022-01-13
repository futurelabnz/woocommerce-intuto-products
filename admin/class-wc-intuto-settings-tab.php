<?php
/**
 * Handles presentation and functionality for Inuto Product settings tab in
 * WooCommerce Settings. This is where API keys are stored and authorization is requested.
 *
 * @package Intuto
 */

namespace Intuto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Intuto_Settings_Tab
 *
 * @package Intuto
 */
class WC_Intuto_Settings_Tab {

	/**
	 * Class instance.
	 *
	 * @var WC_Intuto_Settings_Tab instance
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
		/**
		 * Adds the tab and the tab contents
		 */
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50, 1 );
		add_action( 'woocommerce_settings_tabs_intuto_products', array( $this, 'settings_tab' ) );

		/**
		 * Saves the tab content
		 */
		add_action( 'woocommerce_settings_save_intuto_products', array( $this, 'save_tab_settings' ), 10, 1 );

		/**
		 * Adds the custom link button, if secret and key are both stored.
		 */
		add_action( 'woocommerce_admin_field_button', array( $this, 'add_authorize_button' ), 10, 1 );

		/**
		 * Adds the refresh local store of collections button, if a refresh token is present
		 */
		add_action( 'woocommerce_admin_field_refresh_button', array( $this, 'add_refresh_button' ), 10, 1 );

		/**
		 * Add the settings link to the plugin on the plugins list table
		 */
		add_filter( 'plugin_action_links_woocommerce-intuto-products', array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add the settings tab
	 *
	 * @param array $settings_tabs An array of existing settings tabs.
	 *
	 * @return mixed
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['intuto_products'] = __( 'Intuto Products', 'woocommerce-intuto-products' );

		return $settings_tabs;
	}


	/**
	 * Generate settings tab HTML.
	 */
	public function settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Save Settings tab data.
	 *
	 * @param array $data Passed from WC.
	 */
	public function save_tab_settings( $data ) {
		woocommerce_update_options( $this->get_settings() );
	}

	/**
	 * Returns the settings tab fields array for use with WC Settings API.
	 *
	 * @return mixed|void
	 */
	private function get_settings() {

		$settings = array(
			'section_title' => array(
				'name' => __( 'Intuto API details', 'woocommerce-intuto-products' ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'intuto_products_section_title',
			),
			'title'         => array(
				'name' => __( 'Client secret', 'woocommerce-intuto-products' ),
				'type' => 'text',
				'desc' => __( 'Your client secret from Intuto. Available under the "settings" section of your Intuto Account.', 'woocommerce-intuto-products' ),
				'id'   => 'intuto_products_client_secret',
			),
			'description'   => array(
				'name' => __( 'Client ID', 'woocommerce-intuto-products' ),
				'type' => 'text',
				'desc' => __( 'Your client ID from Intuto. Available under the "settings" section of your Intuto Account.', 'woocommerce-intuto-products' ),
				'id'   => 'intuto_products_client_id',
			),
			'sandbox'   => array(
				'name' => __( 'Use sandbox', 'woocommerce-intuto-products' ),
				'type' => 'checkbox',
				'desc' => __( 'To use the sandbox settings, please contact Intuto support for details.', 'woocommerce-intuto-products' ),
				'id'   => 'intuto_products_sandbox',
			),
		);

		if ( ! empty( get_option( 'intuto_products_client_secret' ) ) && ! empty( get_option( 'intuto_products_client_id' ) ) ) {

			$settings['button'] = array(
				'id'          => '_authorize_app',
				'desc_tip'    => false,
				'description' => __( 'With your Client Secret and Client ID now defined, you can link your WooCommerce store to your Intuto account.', 'woocommerce-intuto-products' ),
				'title'       => __( 'Link to Intuto', 'woocommerce-intuto-products' ),
				'type'        => 'button',
			);
		}

		if ( ! empty( get_option( 'intuto_refresh_token' ) ) && ! empty( get_option( 'intuto_products_client_id' ) ) ) {

			$settings['refresh_button'] = array(
				'id'          => '_refresh_collections',
				'desc_tip'    => false,
				'description' => __( 'Synchronize your WooCommerce store and your Intuto Account.', 'woocommerce-intuto-products' ),
				'title'       => __( 'Refresh collections', 'woocommerce-intuto-products' ),
				'type'        => 'refresh_button',
			);
		}

		$settings['section_end'] = array(
			'type' => 'sectionend',
			'id'   => 'intuto_products_section_end',
		);

		return apply_filters( 'intuto_products_settings', $settings );
	}


	/**
	 * Generates a custom link for
	 *
	 * @param array $value The array of values set in the tab fields array for this field type.
	 */
	public function add_authorize_button( $value ) {

		$uri = ( new Workflow() )->get_authorization_uri();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>">
					<?php echo wp_kses_post( $value['title'] ); ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $value['title'] ); ?></span>
					</legend>
					<a href="<?php echo esc_url( $uri ); ?>" target="_blank" style="<?php echo esc_attr( $value['css'] ); ?>">
						<?php esc_html_e( 'Link your WooCommerce store to your Intuto Account', 'woocommerce-intuto-products' ); ?>
					</a>
				</fieldset>
				<p class="description">
					<?php echo esc_html( $value['description'] ); ?>
				</p>
			</td>
		</tr>
		<?php

	}

	/**
	 * Adds the button used to refresh the local store of collections
	 *
	 * @param array $value Provided by WC Settings API. Array of values used to build field html.
	 */
	public function add_refresh_button( $value ) {

		?>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>">
					<?php echo wp_kses_post( $value['title'] ); ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $value['title'] ); ?></span>
					</legend>
					<p>
						<button type="button" class="button refresh-intuto-products-list">
							<span class="wp-media-buttons-icon"></span>
							Refresh collections list
						</button>
					</p>
				</fieldset>
				<p class="description">
					<?php echo esc_html( $value['description'] ); ?>
				</p>
			</td>
		</tr>

		<?php

	}


	public function add_settings_link( $links ) {
		$settings_link = '&lt;a href=&quot;admin.php?page=wc-settings&tab=intuto_products&quot;&gt;' . __( 'Settings' ) . '&lt;/a&gt;';
		array_push( $links, $settings_link );
		error_log( $links );

		return $links;
	}

}

add_action( 'init', array( 'Intuto\WC_Intuto_Settings_Tab', 'get_instance' ) );
