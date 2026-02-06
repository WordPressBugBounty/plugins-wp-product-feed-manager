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

			// Log dispatch intent (high-signal: helps debug loopback/cron environments).
			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				sprintf(
					'Dispatching async request via wp_remote_post (blocking=%s, timeout=%ds, url=%s).',
					isset( $args['blocking'] ) && false === $args['blocking'] ? 'false' : 'true',
					isset( $args['timeout'] ) ? intval( $args['timeout'] ) : 0,
					$url
				)
			);

			$response = wp_remote_post( esc_url_raw( $url ), $args );

			if ( is_wp_error( $response ) ) {
				do_action( 'wppfm_feed_generation_message', $feed_id, 'wp_remote_post failed: ' . $response->get_error_message(), 'ERROR' );
				do_action( 'wppfm_dispatch_failed', $feed_id, $response, $url, $args );
				$this->flag_pending_dispatch( $feed_id );
				$this->schedule_health_check_fallback();
			} else {
				// Even with non-blocking requests, WordPress may provide a response structure; log what we can.
				$code    = function_exists( 'wp_remote_retrieve_response_code' ) ? wp_remote_retrieve_response_code( $response ) : 0;
				$message = function_exists( 'wp_remote_retrieve_response_message' ) ? wp_remote_retrieve_response_message( $response ) : '';

				do_action(
					'wppfm_feed_generation_message',
					$feed_id,
					sprintf(
						'wp_remote_post returned (code=%s, message=%s, blocking=%s).',
						$code ? strval( $code ) : 'n/a',
						$message ? $message : 'n/a',
						isset( $args['blocking'] ) && false === $args['blocking'] ? 'false' : 'true'
					)
				);
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

		// Log nonce issuance so we can correlate dispatch requests to later nonce/verification failures.
		do_action(
			'wppfm_feed_generation_message',
			$feed_id,
			sprintf(
				'Issued async nonce (request_id=%s, created=%d, identifier=%s).',
				$nonce_data['request_id'],
				intval( $nonce_data['created'] ),
				$nonce_data['identifier']
			)
		);

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
			'Host'         => isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '',

			// Prevent intermediate caches from interfering.
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Pragma'        => 'no-cache', // HTTP/1.0 backwards compatibility for caching
			'Expires'       => '0', // Proxies

			// Add accept header to indicate preference for response types (optional but good practice)
			'Accept' => 'application/json, text/javascript, */*; q=0.01'
		);

		// Add standard headers: Forward the original IP if available. Useful for logging/debugging on the server side.
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$headers['X-Forwarded-For'] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Get WordPress authentication cookies, potentially needed by admin-ajax.php. Check if $_COOKIE is available.
		$cookies = array();
		if ( ! empty( $_COOKIE ) ) {
			foreach ( $_COOKIE as $name => $value ) {
				// Capture standard WordPress login and test cookies.
				if ( strpos( $name, 'wordpress_' ) === 0 || strpos( $name, 'wp-' ) === 0 ) {
					// Do not sanitize cookie values as that may change them; unslash if needed.
					$cookies[ $name ] = is_string( $value ) ? wp_unslash( $value ) : $value; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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

		$feed_id = isset( $_POST['feed_id'] ) ? intval( wp_unslash( $_POST['feed_id'] ) ) : 0;
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		// Clean up old transients
		$this->cleanup_old_transients();

		// Get and verify nonce data
		$nonce_data = get_transient('wppfm_async_nonce_' . $nonce);
		if ( ! $nonce_data || ! wp_verify_nonce( $nonce, $nonce_data['nonce_key'] ) ) {
			do_action(
				'wppfm_feed_generation_message',
				$feed_id ? $feed_id : 'unknown',
				'Async request rejected: invalid or expired nonce (missing nonce_data or wp_verify_nonce failed).',
				'ERROR'
			);
			wp_send_json_error('Invalid or expired nonce');
			return;
		}

		// Check nonce age and feed ID
		if ( time() - $nonce_data['created'] > HOUR_IN_SECONDS ||
			$nonce_data['feed_id'] !== $feed_id ||
			$nonce_data['identifier'] !== $this->identifier ) {
			do_action(
				'wppfm_feed_generation_message',
				$feed_id ? $feed_id : 'unknown',
				sprintf(
					'Async request rejected: request validation failed (age=%ds, expected_feed_id=%s, got_feed_id=%s, expected_identifier=%s, got_identifier=%s).',
					intval( time() - intval( $nonce_data['created'] ) ),
					isset( $nonce_data['feed_id'] ) ? strval( $nonce_data['feed_id'] ) : 'n/a',
					strval( $feed_id ),
					isset( $nonce_data['identifier'] ) ? strval( $nonce_data['identifier'] ) : 'n/a',
					strval( $this->identifier )
				),
				'ERROR'
			);
			wp_send_json_error('Invalid request');
			return;
		}

		// Delete the nonce after verification
		delete_transient('wppfm_async_nonce_' . $nonce);

		do_action(
			'wppfm_feed_generation_message',
			$feed_id,
			sprintf(
				'Async request accepted (request_id=%s). Entering background handler.',
				isset( $nonce_data['request_id'] ) ? strval( $nonce_data['request_id'] ) : 'n/a'
			)
		);

		// Process the request
		$this->handle();

		wp_die();
	}

	/**
	 * Clean up old transients to prevent accumulation
	 */
	private function cleanup_old_transients() {
		global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup operation that needs to query directly. Caching transient cleanup doesn't make sense.
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

	/**
	 * Store a marker so the cron health check can escalate a failed dispatch.
	 *
	 * @param int $feed_id Feed identifier.
	 *
	 * @since 3.18.0
	 */
	private function flag_pending_dispatch( $feed_id ) {
		if ( ! $feed_id ) {
			return;
		}

		$ttl     = max( MINUTE_IN_SECONDS, apply_filters( 'wppfm_pending_dispatch_ttl', 3 * MINUTE_IN_SECONDS ) );
		$payload = array(
			'feed_id' => $feed_id,
			'created' => time(),
		);

		set_site_transient( 'wppfm_pending_dispatch_' . $feed_id, $payload, $ttl );

		$pending = get_site_option( 'wppfm_pending_dispatch_feeds', array() );
		if ( ! is_array( $pending ) ) {
			$pending = array();
		}

		$pending[ $feed_id ] = $payload['created'];
		update_site_option( 'wppfm_pending_dispatch_feeds', $pending );
	}

	/**
	 * Schedule a background-process health check soon after a failed dispatch.
	 *
	 * @since 3.18.0
	 */
	private function schedule_health_check_fallback() {
		$hook       = 'wppfm_feed_generation_process_cron';
		$delay      = max( 5, intval( apply_filters( 'wppfm_pending_dispatch_healthcheck_delay', 30 ) ) );
		$timestamp  = time() + $delay;

		// Allow multiple single events; they coalesce if same timestamp exists.
		wp_schedule_single_event( $timestamp, $hook );
	}
}
