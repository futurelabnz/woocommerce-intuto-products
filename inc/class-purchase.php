<?php
/**
 * Actions triggered by a completed purchase
 *
 * @package Intuto
 */

namespace Intuto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Purchase
 *
 * @package Intuto
 */
class Purchase extends Workflow {

	/**
	 * Class instance.
	 *
	 * @var Purchase instance
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
		 * Each time payment is completed
		 */
		add_action( 'woocommerce_order_status_processing', array( $this, 'intuto_product_purchase' ) );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'intuto_product_purchase' ) );
	}


	/**
	 * Each time a payment is completed, the items purchased in that order are examined. If
	 * they are products linked to an Intuto Collection, the order billing details are used to create the
	 * new user in the Intuto Company, and that user is then added to the specified collection.
	 * If the user exists, the existing user is added to the specified collection
	 *
	 * @param string|int $order_id The ID of the WooCommerce Order that payment just completed on.
	 */
	public function intuto_product_purchase( $order_id ) {

		$order = wc_get_order( $order_id );
		$items = $order->get_items();

		/**
		 * Loop through the order items
		 */
		foreach ( $items as $item ) {

			$product_id        = $item->get_product_id();
			$intuto_product_id = $this->get_intuto_product_id( $product_id );

			/*
			 * If this is an Intuto product
			 */
			if ( $intuto_product_id > 0 ) {

				$members = new Members();

				$member_data['FirstName'] = $order->get_billing_first_name();
				$member_data['LastName']  = $order->get_billing_last_name();
				$member_data['Email']     = $order->get_billing_email();

				/**
				 * Test all required user data before sending
				 */
				foreach ( $member_data as $key => $value ) {
					if ( empty( $member_data[ $key ] ) ) {
						$message = 'Intuto Product user creation failed due to insufficient data: Order ID: ' . $order_id;
						$this->log_error( $message, $member_data );
					}
				}

				/**
				 * Set optional data
				 */
				$member_data['Phone'] = $order->get_billing_phone();

				/**
				 * Create the user
				 */
				$intuto_user = $members->create_member( $member_data );

				/**
				 * If the user already existed, the UserId is returned in the same format as
				 * if the user is a new user.
				 */
				if ( is_array( $intuto_user ) && is_object( $intuto_user[0]->Member ) ) {

					if ( $intuto_user[0]->Member->UserId > 0 ) {

						/**
						 * Add the user to the collection specified in the WooCommerce Product Meta
						 */
						$result[] = $members->add_to_collection( $intuto_product_id, array( $intuto_user[0]->Member->UserId ), true );

						$to =  WC()->mailer()->get_emails()['WC_Email_New_Order']->recipient;
						$subject = 'New Intuto Subscription has been created';
						$body = '<p>A new user ' . $member_data['FirstName'] . ' ' . $member_data['LastName'] .' has been added to Intuto Collection ' . $intuto_product_id . '</p>';
						$headers = array('Content-Type: text/html; charset=UTF-8');

						wp_mail( $to, $subject, $body, $headers, $export_url );


					}
				}
			}
		}
	}

	/**
	 * Return the Intuto Collection ID stored against this product.
	 *
	 * @param string|int $product_id A WooCommerce product ID.
	 *
	 * @return mixed
	 */
	public function get_intuto_product_id( $product_id ) {
		return get_post_meta( $product_id, '_intuto_collection_id', true );
	}

}

add_action( 'init', array( 'Intuto\Purchase', 'get_instance' ) );
