<?php
/**
 * Collections
 *
 * @package Intuto
 */

namespace Intuto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Collections
 *
 * @package Intuto
 */
class Collections extends Workflow {

	/**
	 * The limit for queries to the API. The default is set to 100 so
	 * this is just for use as a throttle.
	 *
	 * @var int
	 */
	private $api_query_limit = 30;

	/**
	 * Class instance.
	 *
	 * @var Collections instance
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
		parent::__construct();

		/**
		 * AJAX action for synchronize data button.
		 */
		add_action( 'wp_ajax_refresh_intuto_collections', array( $this, 'ajax_update_collections_store' ) );

		/**
		 * Cron job for refresh data.
		 */
		add_action( 'cron_refresh_intuto_collections', array( $this, 'cron_update_intuto_collections' ) );

		/**
		 * Add the cron Job.
		 */
		if ( ! wp_next_scheduled( 'cron_refresh_intuto_collections' ) ) {
			wp_schedule_event( time(), 'hourly', 'cron_refresh_intuto_collections' );
		}

	}

	/**
	 * List collections in a company. Limited to the first 100
	 *
	 * @param int $offset Pagination offset.
	 * @param int $limit Pagination limit. Hard limit of 100 set by API cannot be exceeded.
	 *
	 * @return mixed
	 */
	public function list_collections( $offset = 0, $limit = 100 ) {

		$endpoint  = 'collection';
		$endpoint .= '?limit=' . $limit . '&offset=' . $offset;

		$body   = array();
		$result = $this->fetch_api( $endpoint, $body, 'GET' );

		$output = json_decode( $result );

		return $output;
	}

	/**
	 * Search Collections for a string. Limited to the first 100 results.
	 *
	 * @param string $search_term The term to search for.
	 *
	 * @return mixed
	 */
	public function search_collections( $search_term ) {

		$endpoint = 'collection?search=' . $search_term;

		$body   = array();
		$result = $this->fetch_api( $endpoint, $body, 'GET' );

		$output = json_decode( $result );

		return $output;

	}

	/**
	 * Gets a single array of collection objects
	 *
	 * @return array
	 */
	public function get_all_collections() {

		$collections = array();

		$query = $this->list_collections( 0, $this->api_query_limit );

		$total = $query->Total; // phpcs:ignore

		if ( $total > 0 ) {

			$pages = $total / $this->api_query_limit;

			for ( $i = 0; $i < $pages; $i ++ ) {

				$offset = $i * $this->api_query_limit;
				$data   = $this->list_collections( $offset, $this->api_query_limit );

				foreach ( $data->Data as $datum ) { // phpcs:ignore
					$collections[] = $datum;
				}
			}
		}

		return $collections;
	}

	/**
	 * Database all collections in the intuto_product_collections option name in the options table.
	 * True on success, false on failure.
	 *
	 * @return bool
	 */
	public function store_collections_locally() {

		$result = false;

		$collections = $this->get_all_collections();

		if ( ! empty( $collections ) ) {
			$result = update_option( 'intuto_product_collections', $collections );
		}

		if ( ! empty( $collections ) && ! $result ) {
			$result = true;
		}

		return $result;
	}

	/**
	 * Returns a count of collections currently stored in the options table.
	 *
	 * @return int
	 */
	public function get_local_collections_count() {

		$collections = get_option( 'intuto_product_collections' );

		return count( $collections );
	}

	/**
	 * Returns a count of collections currently on the Intuto Account or false on failure.
	 *
	 * @return int|bool
	 */
	public function get_remote_collections_count() {

		$collections = $this->list_collections( 0, 10 );

		if ( isset( $collections->Total ) ) { // phpcs:ignore
			return $collections->Total; // phpcs:ignore
		} else {
			return false;
		}
	}


	/**
	 * Returns a human readable message comparing count of local and remote collections
	 *
	 * @return string
	 */
	public function get_collection_count_message() {

		$local_count  = $this->get_local_collections_count();
		$remote_count = $this->get_remote_collections_count();

		return sprintf(
		// Translators: %1$s is the local count of collections, %2$s the remote count.
			__( 'You currently have %1$s collections listed locally and %2$s in your Intuto account.<br>To update your local store, refresh now.', 'woocommerce-intuto-products' ),
			$local_count,
			$remote_count
		);
	}

	/**
	 * Exposes store_collections_locally to AJAX request to update the local collection store.
	 */
	public function ajax_update_collections_store() {

		check_ajax_referer( 'intuto', 'security' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			exit;
		}

		$result = $this->store_collections_locally();

		if ( $result ) {
			$message = __( 'Your local collection store has been updated.', 'woocommerce-intuto-products' );
		} else {
			$message = __( 'Your local collection is already up to date, or failed to update.', 'woocommerce-intuto-products' );
		}

		echo wp_json_encode( array( 'alert' => $message ) );
		exit;

	}

	/**
	 * Exposes store_collections_locally to the cron job synching Intuto Account and store.
	 */
	public function cron_refresh_intuto_collections() {

		$result = $this->store_collections_locally();
	}

}

add_action( 'init', array( 'Intuto\Collections', 'get_instance' ) );
