<?php
/**
 * Actions for members
 *
 * @package Intuto
 */

namespace Intuto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Members
 *
 * @package Intuto
 */
class Members extends Workflow {


	/**
	 * Class instance.
	 *
	 * @var Members instance
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
	}

	/**
	 * Create a new member in a company
	 *
	 * @param array $data Member data. Required data includes FirstName, LastName and Email.
	 * @param bool  $send_mails Whether Intuto should send the new user mails.
	 *
	 * @return array|boolean Array containing the response.
	 */
	public function create_member( $data, $send_mails = true ) {

		$endpoint = 'companymember';

		if ( true === $send_mails ) {
			$endpoint .= '?sendEmails=true';
		} else {
			$endpoint .= '?sendEmails=false';
		}

		$body = '[' . wp_json_encode( $data ) . ']';

		$response_body = $this->fetch_api( $endpoint, $body, 'POST' );

		$result = json_decode( $response_body );

		return $result;

	}

	/**
	 * List all users in a company. Limited to 100 items.
	 */
	public function list_members() {

		$endpoint = 'companymember';

		$data = array();
		$body = $data;

		$response_body = $this->fetch_api( $endpoint, $body, 'GET' );

		$result = json_decode( $response_body );

		return $result;

	}

	/**
	 * Add an existing user or users to a collection.
	 *
	 * @param string $collection_id The ID of the Intuto Collection the user should be added to.
	 * @param array  $member_ids An array of Intuto Member IDs to add to the collection.
	 * @param bool   $send_mails Whether Intuto should send mails to the newly added collection members.
	 *
	 * @return mixed
	 */
	public function add_to_collection( $collection_id = '', $member_ids = array(), $send_mails = true ) {

		$endpoint = 'collection/' . $collection_id . '/collectionmember';

		if ( true === $send_mails ) {
			$endpoint .= '?sendEmails=true';
		} else {
			$endpoint .= '?sendEmails=false';
		}

		$body = wp_json_encode( $member_ids );

		$response_body = $this->fetch_api( $endpoint, $body, 'POST' );

		$result = json_decode( $response_body );

		return $result;

	}

}

add_action( 'init', array( 'Intuto\Members', 'get_instance' ) );
