<?php
/**
 * Workflow for queries to and from the API.
 *
 * @package Intuto
 */

namespace Intuto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Workflow
 *
 * @package Intuto
 */
class Workflow {


	/**
	 * URI of the Authorization request.
	 *
	 * @var string
	 */
	private $authorize_uri = 'https://identity.intuto.com/connect/authorize?';

	/**
	 * SANDBOX RI of the Authorization request.
	 *
	 * @var string
	 */
	private $sandbox_authorize_uri = 'https://identity-sandbox.intuto.com/connect/authorize?';

	/**
	 * URI for the Authorization code request.
	 *
	 * @var string
	 */
	private $token_uri = 'https://identity.intuto.com/connect/token';

	/**
	 * SANDBOX URI of the Authorization request.
	 *
	 * @var string
	 */
	private $sandbox_token_uri = 'https://identity-sandbox.intuto.com/connect/token';

	/**
	 * Base URI for API requests
	 *
	 * @var string
	 */
	public $api_base = 'https://api.intuto.com/v2/';

	/**
	 * SANDBOX URI of the Authorization request.
	 *
	 * @var string
	 */
	public $sandbox_api_base = 'https://api-sandbox.intuto.com/v2/';

	/**
	 * The scope of the access requested.
	 *
	 * @var string
	 */
	private $scope = 'offline_access apiv2';

	/**
	 * Response type expected.
	 *
	 * @var string
	 */
	private $response_type = 'code';

	/**
	 * The redirect URI.
	 *
	 * @var string
	 */
	private $redirect_uri = '';

	/**
	 * State
	 *
	 * @var string
	 */
	private $state = '';

	/**
	 * Class instance.
	 *
	 * @var Workflow instance
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
		$this->set_mode();
		add_action( 'api_token_refresh_action', [ $this, 'get_refresh_token' ] );
	}


	/**
	 * Sets the plugin to use Sandbox endpoints if selected in
	 * the WC Intuto Products Settings tab
	 */
	private function set_mode(){

		$sandbox = get_option( 'intuto_products_sandbox');

		if( 'yes' === $sandbox ){
				$this->token_uri     = $this->sandbox_token_uri;
				$this->api_base      = $this->sandbox_api_base;
				$this->authorize_uri = $this->sandbox_authorize_uri;
		}
	}


	/**
	 * Fetches the access token after successful response from Auth server.
	 *
	 * @param string $intuto_code The authorization code returned after successful authorization.
	 *
	 * @return bool
	 */
	public function get_access_token( $intuto_code ) {

		$url = $this->token_uri;

		$headers = array(
			'authorization' => 'Basic ' . $this->get_basic_authorization(),
			'Content-Type'  => 'application/x-www-form-urlencoded',
		);

		$body = 'grant_type=authorization_code&code=' . $intuto_code . '&redirect_uri=' . $this->get_redirect_uri();

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $this->get_timeout(),
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = $this->check_response( $response );

		if ( 200 !== $response_code ) {
			$this->log_error( 'API call to ' . $url . ' failed with error code: ' . $response_code, null );
		}

		// todo is if valid.
		return $this->set_tokens( $response );

	}


	/**
	 * Fetches the refresh token when the access token has expired.
	 *
	 * @return bool
	 */
	private function get_refresh_token() {

		$refresh_token = get_option( 'intuto_refresh_token', false );

		if ( false === $refresh_token ) {
			$this->log_error( 'FutureLab error: Intuto token is empty', null );
			return false;
		}

		$url  = $this->token_uri;
		$body = 'grant_type=refresh_token&refresh_token=' . $refresh_token;

		$args = array(
			'timeout'     => $this->get_timeout(),
			'body'        => $body,
			'headers'     => array(
				'Authorization' => 'Basic ' . $this->get_basic_authorization(),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'data_format' => 'body',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'FutureLab error: Intuto tokenfailed with response: ' . print_r( $response ), null );
			return false;
		}

		$response_code = $this->check_response( $response );

		if ( 200 !== $response_code ) {
			$this->log_error( 'API call to ' . $url . ' failed with error code: ' . $response_code, null );
		}

		// todo if is valid.
		return $this->set_tokens( $response );

	}

	/**
	 * Set the tokens in the database using options and transients
	 *
	 * @param array|\WP_Error object $response Response from token or token refresh.
	 *
	 * @return bool
	 */
	private function set_tokens( $response ) {

		$result = true;

		if ( is_wp_error( $response ) ) {
			$message = 'Error fetching refresh token';
			$this->log_error( $message, $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( isset( $data->access_token ) && ! empty( $data->access_token ) ) {

			$expires = time() + $data->expires_in;

			$access_token_data = array(
				'access_token' => $data->access_token,
				'expires'      => $expires,
			);

			update_option( 'intuto_access_token', $access_token_data );
		} else {
			// todo error here.
			$result = false;
		}

		if ( isset( $data->refresh_token ) && ! empty( $data->refresh_token ) ) {

			update_option( 'intuto_refresh_token', $data->refresh_token );
		} else {
			// todo error here.
			$result = false;
		}

		return $result;

	}


	/**
	 * Used to fetch data from the Intuto API
	 *
	 * @param string $endpoint Required API endpoint to access.
	 * @param array  $body Optional Body of the request.
	 * @param string $action Optional. Accepted POST or GET. Defaults to GET.
	 *
	 * @return array|bool|string
	 */
	public function fetch_api( $endpoint = '', $body = array(), $action = 'GET' ) {

		if ( empty( $endpoint ) ) {
			return false;
		}

		$token = $this->get_token();

		if ( empty( $token ) || false === $token ) {
			$this->get_refresh_token();
			$token = $this->get_token();
		}

		$url = $this->api_base . $endpoint;

		$args = array(
			'timeout'     => $this->get_timeout(),
			'body'        => $body,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
			'data_format' => 'body',
		);

		switch ( $action ) {
			case 'POST':
				$response = wp_remote_post( $url, $args );
				break;
			default:
				$response = wp_remote_get( $url, $args );
				break;
		}

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$response_code = $this->check_response( $response );

		if ( 200 !== $response_code ) {
			$this->log_error( 'API call to ' . $url . ' failed with error code: ' . $response_code, $response );
		}

		$body = wp_remote_retrieve_body( $response );

		return $body;

	}


	/**
	 * Gets the access token stored in the database. Returns false or an array containing token plus
	 * token expiry data.
	 *
	 * @return array|bool
	 */
	private function get_token() {

		$tokens = get_option( 'intuto_access_token', false );

		if ( false === $tokens ) {
			return false;
		}

		$now = time();

		if ( $now < $tokens['expires'] ) {

			return $tokens['access_token'];

		} else {

			$this->get_refresh_token();

			$tokens = get_option( 'intuto_access_token', false );

			if ( $now < $tokens['expires'] ) {
				return $tokens['access_token'];
			}
		}

		return false;
	}


	/**
	 * Returns the response code to an API call. To be used later for expanded logging if
	 * required, and additional checks.
	 *
	 * @param array $response The response returned by an API call.
	 *
	 * @return bool|int|string
	 */
	private function check_response( $response ) {

		$response_code = wp_remote_retrieve_response_code( $response );

		return $response_code;

	}


	/**
	 *  The full authorization URI.
	 *
	 * @return string
	 */
	public function get_authorization_uri() {
		/*
		 * This code should only execute for administrators.
		 */
		if ( ! user_can( wp_get_current_user(), 'manage_options' ) ) {
			return false;
		}

		/*
		 * State is a nonce used to check the data returned with the authorization code.
		 * If it fails to match on return, further steps will be aborted.
		 */
		$authorization_uri  = $this->authorize_uri;
		$authorization_uri .= 'response_type=' . $this->response_type;
		$authorization_uri .= '&client_id=' . $this->get_client_id();
		$authorization_uri .= '&redirect_uri=' . $this->get_redirect_uri();
		$authorization_uri .= '&scope=' . $this->scope;
		$authorization_uri .= '&state=' . wp_create_nonce( 'intuto-link' );

		return $authorization_uri;

	}


	/**
	 * The Client ID
	 *
	 * @return string
	 */
	private function get_client_id() {
		return get_option( 'intuto_products_client_id' );
	}

	/**
	 * The redirect URI which the authorization request will send the code
	 * and state to.
	 *
	 * @return string
	 */
	private function get_redirect_uri() {
		$this->redirect_uri = get_bloginfo( 'url' ) . '/wp-admin/?intuto-redirect-page';

		return $this->redirect_uri;
	}


	/**
	 * The base64 encoded Authorization header for token requests.
	 *
	 * @return string
	 */
	private function get_basic_authorization() {

		$client_id     = $this->get_client_id();
		$client_secret = get_option( 'intuto_products_client_secret', false );

		return base64_encode( $client_id . ':' . $client_secret );
	}


	/**
	 * A helper function used to set timeouts.
	 *
	 * @return int
	 */
	private function get_timeout() {
		return 45;
	}


	/**
	 * Logging via WC_Logger class allows access to logs via the WC tools dashboard.
	 *
	 * @param string       $message The error message to log.
	 * @param object|array $error Additional error data.
	 */
	protected function log_error( $message, $error ) {

		$log       = new \WC_Logger();
		$log_entry = $message;
		$log->add( 'woocommerce-intuto-products-log', $log_entry );

	}

}

add_action( 'init', array( 'Intuto\Workflow', 'get_instance' ) );
