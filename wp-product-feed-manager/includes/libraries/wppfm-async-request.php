<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract WPPFM_Async_Request class derived from https://github.com/A5hleyRich/wp-background-processing.
 *
 * @package WPPFM-Background-Processing
 * @abstract
 */
abstract class WPPFM_Async_Request {

	/**
	 * Prefix
	 *
	 * @var string
	 */
	protected $prefix = 'wppfm';

	/**
	 * Action
	 *
	 * @var string
	 */
	protected $action = 'async_request';

	/**
	 * Identifier
	 *
	 * @var mixed
	 */
	protected $identifier;

	/**
	 * Data
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * File Path
	 *
	 * @var string
	 */
	protected $file_path = '';

	/**
	 * Contains the general data of the feed
	 *
	 * @var string
	 */
	protected $feed_data = '';

	/**
	 * Contains general pre feed production data
	 *
	 * @var array
	 */
	protected $pre_data;

	/**
	 * Contains the channels category title and description title
	 *
	 * @var array
	 */
	protected $channel_details;

	/**
	 * Contains the relations between the WooCommerce and channel fields
	 *
	 * @var array
	 */
	protected $relations_table;

	/**
	 * Initiate new async request
	 */
	public function __construct() {
		$this->identifier = $this->prefix . '_' . $this->action;

		add_action( 'wp_ajax_' . $this->identifier, array( $this, 'maybe_handle' ) );
		add_action( 'wp_ajax_nopriv_' . $this->identifier, array( $this, 'maybe_handle' ) );
	}

	/**
	 * Set data used during the request
	 *
	 * @param array $data Data.
	 *
	 * @return $this
	 */
	public function data( $data ) {
		$this->data = $data;
		return $this;
	}

	/**
	 * Dispatch the async request to trigger the feed process with a remote post.
	 *
	 * @param string $feed_id
	 */
	public function dispatch( $feed_id ) {
		if ( get_option( 'wppfm_disabled_background_mode', 'false' ) === 'false' ) {
			// Clean up any existing feed data before starting
			delete_site_option('wppfm_feed_data');

			// Set the feed_id in the data array
			$this->data['feed_id'] = $feed_id;

			$url  = add_query_arg( $this->get_query_args( $feed_id ), $this->get_query_url() );
			$args = $this->get_post_args();

			do_action( 'wppfm_register_remote_post_args', $feed_id, $url, $args );

			$response = wp_remote_post( esc_url_raw( $url ), $args );

			if (is_wp_error($response)) {
				do_action('wppfm_feed_generation_message', $feed_id, 'wp_remote_post failed: ' . $response->get_error_message(), 'ERROR');
			}

			do_action( 'wppfm_wp_remote_post_response', $feed_id, $response );
		} else {
			$this->maybe_handle();
		}
	}

	/**
	 * Get query args
	 *
	 * @param int $feed_id Feed ID.
	 *
	 * @return array
	 */
	protected function get_query_args( $feed_id ) {
		$nonce_key = 'wppfm_feed_generation_process';
		$nonce = wp_create_nonce($nonce_key);

		$nonce_data = array(
			'created' => time(),
			'feed_id' => $feed_id,
			'identifier' => $this->identifier,
			'request_id' => uniqid('req_', true),
			'nonce_key' => $nonce_key
		);

		set_transient('wppfm_async_nonce_' . $nonce, $nonce_data, HOUR_IN_SECONDS);

		return array(
			'action'  => $this->identifier,
			'nonce'   => $nonce,
			'feed_id' => $feed_id,
		);
	}

	/**
	 * Get query URL
	 *
	 * @return string
	 */
	protected function get_query_url() {
		return admin_url( 'admin-ajax.php' );
	}

	/**
	 * Get post args.
	 *
	 * @return array
	 */
	protected function get_post_args() {
		// Build headers for maximum robustness
		$headers = array(
			// Crucial for robustness: Disable 'Expect: 100-continue' which causes issues with some servers/proxies.
			'Expect'       => '',

			// Identify the request as internal to WordPress. Might be useful for debugging or specific rules.
			'X-WordPress-Internal-Request' => 'true',

			// Standard header for AJAX requests. Some security rules might look for this on admin-ajax.php.
			'X-Requested-With' => 'XMLHttpRequest',

			// Explicitly set the Host header. Check if $_SERVER['HTTP_HOST'] is set (e.g., might not be in CLI).
			'Host'         => isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '',

			// Prevent intermediate caches from interfering.
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Pragma'        => 'no-cache', // HTTP/1.0 backwards compatibility for caching
			'Expires'       => '0', // Proxies

			// Add accept header to indicate preference for response types (optional but good practice)
			'Accept' => 'application/json, text/javascript, */*; q=0.01'
		);

		// Add standard headers: Forward the original IP if available. Useful for logging/debugging on the server side.
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$headers['X-Forwarded-For'] = $_SERVER['REMOTE_ADDR'];
		}

		// Get WordPress authentication cookies, potentially needed by admin-ajax.php. Check if $_COOKIE is available.
		$cookies = array();
		if ( ! empty( $_COOKIE ) ) {
			foreach ($_COOKIE as $name => $value) {
				// Capture standard WordPress login and test cookies.
				if (strpos($name, 'wordpress_') === 0 || strpos($name, 'wp-') === 0) {
					$cookies[$name] = $value;
				}
			}
		}

		return array(
			'timeout'   => 30,    // Reasonable timeout for an async trigger.
			'blocking'  => false, // Set to false for a true non-blocking async trigger.
			'headers'   => $headers,
			'cookies'   => $cookies,
			'sslverify' => false, // Often needed for loopback requests.
			'body'      => null   // Explicitly set body to null if no data is being sent.
		);
	}

	/**
	 * Maybe handle
	 *
	 * Check for correct nonce and pass to handler.
	 */
	public function maybe_handle() {
		session_write_close();

		$feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
		$nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

		// Clean up old transients
		$this->cleanup_old_transients();

		// Get and verify nonce data
		$nonce_data = get_transient('wppfm_async_nonce_' . $nonce);
		if (!$nonce_data || !wp_verify_nonce($nonce, $nonce_data['nonce_key'])) {
			wp_send_json_error('Invalid or expired nonce');
			return;
		}

		// Check nonce age and feed ID
		if (time() - $nonce_data['created'] > HOUR_IN_SECONDS ||
			$nonce_data['feed_id'] !== $feed_id ||
			$nonce_data['identifier'] !== $this->identifier) {
			wp_send_json_error('Invalid request');
			return;
		}

		// Delete the nonce after verification
		delete_transient('wppfm_async_nonce_' . $nonce);

		// Process the request
		$this->handle();

		wp_die();
	}

	/**
	 * Clean up old transients to prevent accumulation
	 */
	private function cleanup_old_transients() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				'_transient_wppfm_async_nonce_%',
				time() - HOUR_IN_SECONDS
			)
		);
	}

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	abstract protected function handle();
}
