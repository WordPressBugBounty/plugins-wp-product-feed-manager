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
	 * Indicates whether maybe_handle() was invoked internally (not by an external AJAX request).
	 *
	 * @var bool
	 */
	protected $internal_dispatch_context = false;

	/**
	 * Initiate new async request
	 */
	public function __construct() {
		$this->identifier = $this->prefix . '_' . $this->action;

		add_action( 'wp_ajax_' . $this->identifier, array( $this, 'maybe_handle' ) );

		// System cron and some hosts trigger dispatch without WordPress auth cookies, so admin-ajax
		// runs as a guest. nopriv is required for the loopback to reach maybe_handle(); access is
		// still gated by the one-time transient + nonce pair issued in get_query_args().
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
	 * @param string $feed_id Feed id.
	 */
	public function dispatch( $feed_id ) {
		if ( get_option( 'wppfm_disabled_background_mode', 'false' ) === 'false' ) {
			if ( $this->should_clear_feed_data_on_dispatch( $feed_id ) ) {
				delete_site_option( 'wppfm_feed_data' );
			}

			// Set the feed_id in the data array
			$this->data['feed_id'] = $feed_id;

			$dispatch = $this->prepare_loopback_dispatch( $feed_id );
			$url      = $dispatch['url'];
			$args     = $dispatch['args'];

			do_action( 'wppfm_register_remote_post_args', $feed_id, $url, $args );

			$this->log_dispatch_message(
				$feed_id,
				sprintf(
					'Dispatching async request via wp_remote_post (blocking=%s, timeout=%ds).',
					isset( $args['blocking'] ) && false === $args['blocking'] ? 'false' : 'true',
					isset( $args['timeout'] ) ? intval( $args['timeout'] ) : 0
				)
			);

			$response         = wp_remote_post( esc_url_raw( $url ), $args );
			$classification   = $this->classify_dispatch_response( $response, $args );
			$classification   = $this->refine_nonblocking_dispatch_classification( $feed_id, $classification, $args );

			$this->log_dispatch_outcome( $feed_id, $classification, $response, $args );

			if ( $this->dispatch_outcome_requires_recovery( $classification ) ) {
				$classification = $this->resolve_dispatch_recovery( $feed_id, $classification, $url, $args, $response );
			}

			if ( class_exists( 'WPPFM_Feed_Processing_Diagnostics' ) ) {
				WPPFM_Feed_Processing_Diagnostics::record_dispatch_outcome( $feed_id, $classification );
			}

			do_action( 'wppfm_wp_remote_post_response', $feed_id, $response );
			do_action( 'wppfm_dispatch_completed', $feed_id, $classification, $response, $url, $args );
		} else {
			// Foreground mode runs in the same authenticated request context.
			$this->internal_dispatch_context = true;
			$this->maybe_handle();
			$this->internal_dispatch_context = false;
		}
	}

	/**
	 * Indicates whether the current maybe_handle() run is an internal foreground dispatch.
	 *
	 * @return bool
	 */
	protected function is_internal_dispatch_context() {
		return true === $this->internal_dispatch_context;
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
	 * Builds the loopback URL and wp_remote_post() args for a dispatch or probe.
	 *
	 * Query parameters remain on the URL for backward compatibility; the same values
	 * are duplicated in the POST body when enabled (standard admin-ajax shape).
	 *
	 * @param string $feed_id        Feed id.
	 * @param array  $extra_query    Optional extra query/body parameters.
	 * @param array  $args_overrides Optional overrides for wp_remote_post() args.
	 *
	 * @return array{url:string,args:array,query:array}
	 */
	protected function prepare_loopback_dispatch( $feed_id, $extra_query = array(), $args_overrides = array() ) {
		$query_args = $this->get_query_args( $feed_id );

		if ( ! empty( $extra_query ) ) {
			$query_args = array_merge( $query_args, $extra_query );
		}

		$url = add_query_arg( $query_args, $this->get_query_url() );
		$url = apply_filters( 'wppfm_loopback_dispatch_url', $url, $feed_id, $query_args );

		$args = $this->get_post_args( $url, $query_args, $feed_id );

		if ( ! empty( $args_overrides ) ) {
			$args = array_merge( $args, $args_overrides );
		}

		$args = apply_filters( 'wppfm_loopback_request_args', $args, $url, $query_args, $feed_id );

		$dispatch_kind = ( isset( $extra_query['wppfm_dispatch_probe'] ) && '1' === (string) $extra_query['wppfm_dispatch_probe'] ) ? 'probe' : 'dispatch';
		$this->log_loopback_dispatch_profile( $feed_id, $url, $args, $query_args, $dispatch_kind );

		return array(
			'url'   => $url,
			'args'  => $args,
			'query' => $query_args,
		);
	}

	/**
	 * Returns a short label for the PHP runtime that initiated the loopback.
	 *
	 * @return string
	 */
	protected function get_loopback_runtime_context() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp-cli';
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return 'cron';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		if ( is_admin() ) {
			return 'admin';
		}

		return 'front';
	}

	/**
	 * Logs loopback request shape for support (process logger when active).
	 *
	 * @param string $feed_id       Feed id.
	 * @param string $url           Dispatch URL.
	 * @param array  $args          wp_remote_post() args.
	 * @param array  $query_args    Dispatch query/body parameters.
	 * @param string $dispatch_kind dispatch|probe.
	 *
	 * @return void
	 */
	protected function log_loopback_dispatch_profile( $feed_id, $url, $args, $query_args, $dispatch_kind = 'dispatch' ) {
		if ( ! apply_filters( 'wppfm_loopback_log_dispatch_profile', true, $feed_id, $dispatch_kind ) ) {
			return;
		}

		$headers     = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$host_header = isset( $headers['Host'] ) ? (string) $headers['Host'] : 'not_set';
		$parsed      = is_string( $url ) ? wp_parse_url( $url ) : array();
		$url_host    = isset( $parsed['host'] ) ? (string) $parsed['host'] : 'unknown';
		$url_scheme  = isset( $parsed['scheme'] ) ? (string) $parsed['scheme'] : 'unknown';
		$post_body   = ! empty( $args['body'] ) ? 'yes' : 'no';
		$cookies     = isset( $args['cookies'] ) && is_array( $args['cookies'] ) ? count( $args['cookies'] ) : 0;
		$sslverify   = isset( $args['sslverify'] ) && $args['sslverify'] ? 'true' : 'false';
		$blocking    = isset( $args['blocking'] ) && false === $args['blocking'] ? 'false' : 'true';
		$timeout     = isset( $args['timeout'] ) ? intval( $args['timeout'] ) : 0;
		$server_host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		$host_from_url = (bool) apply_filters( 'wppfm_loopback_host_from_dispatch_url', true, $feed_id, $url );
		$include_body  = (bool) apply_filters( 'wppfm_loopback_include_post_body', true, $feed_id, $url, $query_args );
		$forwarded_for = (bool) apply_filters( 'wppfm_loopback_include_forwarded_for', true, $feed_id, $url ) && isset( $headers['X-Forwarded-For'] );

		$message = sprintf(
			'Loopback dispatch profile (kind=%s, runtime=%s, url_host=%s, url_scheme=%s, loopback_host=%s, server_http_host=%s, post_body=%s, host_from_url_filter=%s, post_body_filter=%s, forwarded_for=%s, sslverify=%s, blocking=%s, timeout=%ds, cookies=%d).',
			sanitize_key( $dispatch_kind ),
			$this->get_loopback_runtime_context(),
			$url_host,
			$url_scheme,
			$host_header,
			'' !== $server_host ? $server_host : 'not_set',
			$post_body,
			$host_from_url ? 'yes' : 'no',
			$include_body ? 'yes' : 'no',
			$forwarded_for ? 'yes' : 'no',
			$sslverify,
			$blocking,
			$timeout,
			$cookies
		);

		$this->log_dispatch_message( $feed_id, $message );
	}

	/**
	 * Get post args for a loopback dispatch.
	 *
	 * @param string $url         Full dispatch URL (used to align the Host header).
	 * @param array  $query_args  Dispatch parameters (action, nonce, feed_id, …).
	 * @param string $feed_id     Feed id for filters.
	 *
	 * @return array
	 */
	protected function get_post_args( $url = '', $query_args = array(), $feed_id = '' ) {
		$headers = array(
			// Crucial for robustness: Disable 'Expect: 100-continue' which causes issues with some servers/proxies.
			'Expect' => '',

			// Identify the request as internal to WordPress. Might be useful for debugging or specific rules.
			'X-WordPress-Internal-Request' => 'true',

			// Standard header for AJAX requests. Some security rules might look for this on admin-ajax.php.
			'X-Requested-With' => 'XMLHttpRequest',

			// Prevent intermediate caches from interfering.
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Pragma'        => 'no-cache',
			'Expires'       => '0',

			'Accept' => 'application/json, text/javascript, */*; q=0.01',
		);

		$headers['User-Agent'] = apply_filters(
			'wppfm_loopback_user_agent',
			'WordPress/' . get_bloginfo( 'version' ) . '; WPPFM-Background-Feed',
			$feed_id,
			$url
		);

		$host = '';

		if ( (bool) apply_filters( 'wppfm_loopback_host_from_dispatch_url', true, $feed_id, $url ) && is_string( $url ) && '' !== $url ) {
			$parsed = wp_parse_url( $url );
			$host   = isset( $parsed['host'] ) ? (string) $parsed['host'] : '';
		}

		if ( '' === $host && isset( $_SERVER['HTTP_HOST'] ) ) {
			$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}

		// Never send an empty Host header — that alone can trigger HTTP 400 on nginx.
		if ( '' !== $host ) {
			$headers['Host'] = $host;
		}

		// Forward the original IP when available; disable via filter on strict WAF hosts.
		if ( (bool) apply_filters( 'wppfm_loopback_include_forwarded_for', true, $feed_id, $url ) && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$headers['X-Forwarded-For'] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$headers = apply_filters( 'wppfm_loopback_request_headers', $headers, $feed_id, $url, $query_args );

		$cookies = array();
		if ( ! empty( $_COOKIE ) ) {
			foreach ( $_COOKIE as $name => $value ) {
				if ( strpos( $name, 'wordpress_' ) === 0 || strpos( $name, 'wp-' ) === 0 ) {
					$cookies[ $name ] = is_string( $value ) ? wp_unslash( $value ) : $value; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}
			}
		}

		$body = null;

		if ( (bool) apply_filters( 'wppfm_loopback_include_post_body', true, $feed_id, $url, $query_args ) && ! empty( $query_args ) ) {
			$body = $query_args;
		}

		return array(
			'timeout'   => 30,
			'blocking'  => false,
			'headers'   => $headers,
			'cookies'   => $cookies,
			'sslverify' => apply_filters( 'wppfm_loopback_sslverify', false, $feed_id, $url ),
			'body'      => $body,
		);
	}

	/**
	 * Maybe handle
	 *
	 * Check for correct nonce and pass to handler.
	 */
	public function maybe_handle() {
		session_write_close();

		$feed_id = $this->get_async_request_feed_id();
		$nonce   = $this->get_async_request_nonce();

		$nonce_data = $this->verify_dispatch_nonce( $feed_id, $nonce );

		if ( false !== $nonce_data && $this->is_dispatch_probe_request() ) {
			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				sprintf(
					'Loopback probe accepted (request_id=%s, param_source=%s).',
					isset( $nonce_data['request_id'] ) ? strval( $nonce_data['request_id'] ) : 'n/a',
					$this->get_loopback_inbound_param_source()
				)
			);

			wp_send_json_success(
				array(
					'probe'   => true,
					'feed_id' => $feed_id,
				)
			);
			return;
		}

		if ( false === $nonce_data ) {
			do_action(
				'wppfm_feed_generation_message',
				$feed_id ? $feed_id : 'unknown',
				sprintf(
					'Async request rejected: invalid or expired dispatch nonce (param_source=%s).',
					$this->get_loopback_inbound_param_source()
				),
				'ERROR'
			);
			wp_send_json_error( 'Invalid or expired nonce' );
			return;
		}

		do_action(
			'wppfm_feed_generation_message',
			$feed_id,
			sprintf(
				'Async request accepted (request_id=%s, param_source=%s). Entering background handler.',
				isset( $nonce_data['request_id'] ) ? strval( $nonce_data['request_id'] ) : 'n/a',
				$this->get_loopback_inbound_param_source()
			)
		);

		// Process the request
		$this->handle();

		wp_die();
	}

	/**
	 * Reads the feed id from the loopback dispatch query string or POST body.
	 *
	 * @return string
	 */
	protected function get_async_request_feed_id() {
		$feed_id = filter_input( INPUT_GET, 'feed_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( is_string( $feed_id ) && '' !== $feed_id ) {
			return (string) absint( $feed_id );
		}

		if ( isset( $_POST['feed_id'] ) ) {
			return (string) absint( wp_unslash( $_POST['feed_id'] ) );
		}

		return '';
	}

	/**
	 * Reads the dispatch nonce from the loopback query string or POST body.
	 *
	 * @return string
	 */
	protected function get_async_request_nonce() {
		$nonce = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( is_string( $nonce ) && '' !== $nonce ) {
			return $nonce;
		}

		if ( isset( $_POST['nonce'] ) ) {
			return sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		}

		return '';
	}

	/**
	 * Indicates whether dispatch parameters arrived via the query string or POST body.
	 *
	 * @return string query|body|mixed|missing
	 */
	protected function get_loopback_inbound_param_source() {
		$has_get_nonce  = is_string( filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) )
			&& '' !== filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$has_post_nonce = isset( $_POST['nonce'] ) && '' !== sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( $has_get_nonce && $has_post_nonce ) {
			return 'mixed';
		}

		if ( $has_post_nonce ) {
			return 'body';
		}

		if ( $has_get_nonce ) {
			return 'query';
		}

		return 'missing';
	}

	/**
	 * Validates a server-issued one-time dispatch nonce (cron/loopback safe, no logged-in user required).
	 *
	 * @param string|int $feed_id Feed id from the request.
	 * @param string     $nonce   Nonce token from the request.
	 *
	 * @return array|false Nonce payload when valid; false when rejected.
	 */
	protected function verify_dispatch_nonce( $feed_id, $nonce ) {
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}

		$feed_id = (string) absint( $feed_id );

		if ( '' === $feed_id ) {
			return false;
		}

		$this->cleanup_old_transients();

		$nonce_data = get_transient( 'wppfm_async_nonce_' . $nonce );

		if ( ! is_array( $nonce_data ) || empty( $nonce_data['nonce_key'] ) || ! wp_verify_nonce( $nonce, $nonce_data['nonce_key'] ) ) {
			return false;
		}

		if (
			time() - intval( $nonce_data['created'] ) > HOUR_IN_SECONDS
			|| (string) absint( $nonce_data['feed_id'] ) !== $feed_id
			|| ( isset( $nonce_data['identifier'] ) ? (string) $nonce_data['identifier'] : '' ) !== (string) $this->identifier
		) {
			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				sprintf(
					'Async request rejected: dispatch validation failed (age=%ds, expected_feed_id=%s, got_feed_id=%s, expected_identifier=%s, got_identifier=%s).',
					intval( time() - intval( $nonce_data['created'] ) ),
					isset( $nonce_data['feed_id'] ) ? strval( $nonce_data['feed_id'] ) : 'n/a',
					$feed_id,
					isset( $nonce_data['identifier'] ) ? strval( $nonce_data['identifier'] ) : 'n/a',
					(string) $this->identifier
				),
				'ERROR'
			);

			return false;
		}

		delete_transient( 'wppfm_async_nonce_' . $nonce );

		return $nonce_data;
	}

	/**
	 * Clean up expired nonce transients to prevent accumulation.
	 *
	 * WordPress stores transients in two option rows per key:
	 *   _transient_<name>           — the serialized PHP value
	 *   _transient_timeout_<name>   — a plain Unix expiry timestamp
	 *
	 * The previous implementation compared option_value (the serialized array)
	 * against a Unix timestamp. MySQL converts a non-numeric serialized string to 0,
	 * so `0 < (now - 1 hour)` was always TRUE, meaning every nonce transient was
	 * deleted the moment verify_dispatch_nonce() ran. That broke the transient-based
	 * auth path and forced all loopback requests to fall back to the admin-cookie
	 * fallback — which succeeds for browser-initiated manual requests (cookies present)
	 * but silently rejects cron-initiated loopbacks (no cookies, no logged-in user).
	 *
	 * The fix queries _transient_timeout_ entries, which do store plain timestamps.
	 */
	protected function cleanup_old_transients() {
		global $wpdb;

		// Find expired timeout records; CAST is safe because these option_values are plain integer timestamps.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot cleanup query; caching the result would be counterproductive.
		$expired_timeout_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
				$wpdb->esc_like( '_transient_timeout_wppfm_async_nonce_' ) . '%',
				time()
			)
		);

		foreach ( $expired_timeout_keys as $timeout_key ) {
			// Convert '_transient_timeout_wppfm_async_nonce_X' → 'wppfm_async_nonce_X' so
			// delete_transient() removes both the value and timeout rows atomically.
			$transient_name = str_replace( '_transient_timeout_', '', $timeout_key );
			delete_transient( $transient_name );
		}
	}

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	abstract protected function handle();

	/**
	 * Returns true when this dispatch should clear legacy wppfm_feed_data storage.
	 *
	 * Batch continuations must retain site options needed for resume-via-handle().
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return bool
	 */
	protected function should_clear_feed_data_on_dispatch( $feed_id ) {
		$feed_id = (string) $feed_id;

		if ( class_exists( 'WPPFM_Feed_Controller' ) && method_exists( 'WPPFM_Feed_Controller', 'should_resume_existing_batch' ) ) {
			if ( WPPFM_Feed_Controller::should_resume_existing_batch( $feed_id ) ) {
				return false;
			}
		}

		$key = get_site_option( 'wppfm_background_process_key' );

		if ( $key && is_string( $key ) ) {
			$batch_metadata = get_site_option( 'wppfm_batch_metadata_' . $key, array() );

			if ( is_array( $batch_metadata ) && ! empty( $batch_metadata['feed_id'] ) && (string) $batch_metadata['feed_id'] === $feed_id ) {
				return false;
			}
		}

		return (bool) apply_filters( 'wppfm_clear_feed_data_on_dispatch', true, $feed_id );
	}

	/**
	 * Classifies the wp_remote_post() outcome for recovery decisions.
	 *
	 * @param array|WP_Error $response Remote post response.
	 * @param array          $args     Request args.
	 *
	 * @return string
	 */
	protected function classify_dispatch_response( $response, $args ) {
		if ( is_wp_error( $response ) ) {
			return 'wp_error';
		}

		$blocking = ! ( isset( $args['blocking'] ) && false === $args['blocking'] );
		$code     = function_exists( 'wp_remote_retrieve_response_code' ) ? intval( wp_remote_retrieve_response_code( $response ) ) : 0;

		if ( $blocking ) {
			if ( $code < 200 || $code >= 300 ) {
				return 'http_status_' . ( $code > 0 ? $code : 'empty' );
			}

			return 'success';
		}

		if ( $code >= 200 && $code < 300 ) {
			return 'success';
		}

		return 'suspect_nonblocking';
	}

	/**
	 * After a non-blocking dispatch, optionally wait briefly for the worker lock.
	 *
	 * @param string $feed_id        Feed id.
	 * @param string $classification Current classification.
	 * @param array  $args           Request args.
	 *
	 * @return string
	 */
	protected function refine_nonblocking_dispatch_classification( $feed_id, $classification, $args ) {
		if ( 'success' !== $classification && 'suspect_nonblocking' !== $classification ) {
			return $classification;
		}

		if ( isset( $args['blocking'] ) && false !== $args['blocking'] ) {
			return $classification;
		}

		if ( ! apply_filters( 'wppfm_dispatch_verify_worker_lock_after_nonblocking', true, $feed_id ) ) {
			return $classification;
		}

		$wait_seconds = max( 1, intval( apply_filters( 'wppfm_dispatch_lock_check_seconds', 2, $feed_id ) ) );
		$deadline     = microtime( true ) + (float) $wait_seconds;

		while ( microtime( true ) < $deadline ) {
			if ( $this->dispatch_process_lock_is_held() ) {
				return 'success';
			}

			usleep( 200000 );
		}

		return 'suspect_nonblocking';
	}

	/**
	 * Returns true when the background process lock transient is present.
	 *
	 * @return bool
	 */
	protected function dispatch_process_lock_is_held() {
		$lock_key = apply_filters( 'wppfm_dispatch_process_lock_key', 'wppfm_feed_generation_process_process_lock', $this->identifier );

		return ! empty( get_site_transient( $lock_key ) );
	}

	/**
	 * Returns true when recovery should be scheduled for this dispatch outcome.
	 *
	 * @param string $classification Outcome token.
	 *
	 * @return bool
	 */
	protected function dispatch_outcome_requires_recovery( $classification ) {
		return 'success' !== $classification;
	}

	/**
	 * Optional blocking probe after a suspect non-blocking dispatch.
	 *
	 * @param string $feed_id Feed id.
	 * @param string $url     Primary dispatch URL (unused; probe issues a fresh nonce).
	 * @param array  $args    Base post args.
	 *
	 * @return string|null Updated classification, or null when no probe ran.
	 */
	protected function maybe_retry_dispatch_with_blocking_probe( $feed_id, $url, $args ) {
		$enabled = (bool) apply_filters( 'wppfm_enable_blocking_loopback_dispatch_retry', false, $feed_id );
		$on_suspect = (bool) apply_filters( 'wppfm_enable_blocking_loopback_dispatch_retry_on_suspect', true, $feed_id );

		if ( ! $enabled && ! $on_suspect ) {
			return null;
		}

		$probe_delay = max( 0, intval( apply_filters( 'wppfm_loopback_probe_nonce_delay_seconds', 0, $feed_id ) ) );

		if ( $probe_delay > 0 ) {
			sleep( min( 5, $probe_delay ) );
		}

		$probe_dispatch = $this->prepare_loopback_dispatch(
			$feed_id,
			array( 'wppfm_dispatch_probe' => '1' ),
			array(
				'blocking' => true,
				'timeout'  => max( 5, intval( apply_filters( 'wppfm_blocking_loopback_dispatch_retry_timeout', 8, $feed_id ) ) ),
			)
		);
		$probe_url  = $probe_dispatch['url'];
		$probe_args = $probe_dispatch['args'];

		do_action( 'wppfm_register_remote_post_args', $feed_id, $probe_url, $probe_args );

		$this->log_dispatch_message(
			$feed_id,
			sprintf(
				'Running blocking loopback dispatch probe (timeout=%ds).',
				intval( $probe_args['timeout'] )
			)
		);

		$probe_response = wp_remote_post( esc_url_raw( $probe_url ), $probe_args );

		if ( is_wp_error( $probe_response ) ) {
			$this->log_dispatch_outcome( $feed_id, 'wp_error', $probe_response, $probe_args, true );
			return 'wp_error';
		}

		$code = function_exists( 'wp_remote_retrieve_response_code' ) ? intval( wp_remote_retrieve_response_code( $probe_response ) ) : 0;
		$body = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $probe_response ) : '';

		if ( $code >= 200 && $code < 300 && false !== strpos( $body, '"probe":true' ) ) {
			$this->log_dispatch_outcome( $feed_id, 'success', $probe_response, $probe_args, true );
			return 'success';
		}

		if ( $code < 200 || $code >= 300 ) {
			$outcome = 'http_status_' . ( $code > 0 ? $code : 'empty' );
			$this->log_dispatch_outcome( $feed_id, $outcome, $probe_response, $probe_args, true );
			return $outcome;
		}

		// HTTP 2xx without the probe JSON marker still proves loopback reached WordPress (common on strict hosts).
		if ( (bool) apply_filters( 'wppfm_loopback_probe_http_2xx_is_success', true, $feed_id, $probe_response ) ) {
			if ( '' !== $body && false === strpos( $body, '"probe":true' ) ) {
				$this->log_dispatch_message(
					$feed_id,
					sprintf(
						'Dispatch probe reached WordPress (HTTP %d) without probe JSON marker; treating as success.',
						$code
					)
				);
			}

			$this->log_dispatch_outcome( $feed_id, 'success', $probe_response, $probe_args, true );
			return 'success';
		}

		$this->log_dispatch_outcome( $feed_id, 'suspect_nonblocking', $probe_response, $probe_args, true );
		return 'suspect_nonblocking';
	}

	/**
	 * Runs probe retry and failure handling unless recovery is unnecessary for this feed.
	 *
	 * @param string              $feed_id        Feed id.
	 * @param string              $classification Current outcome token.
	 * @param string              $url            Dispatch URL.
	 * @param array               $args           Request args.
	 * @param array|WP_Error|null $response       Primary dispatch response.
	 *
	 * @return string Final classification after recovery attempts.
	 */
	protected function resolve_dispatch_recovery( $feed_id, $classification, $url, $args, $response ) {
		if ( $this->should_skip_dispatch_recovery_actions( $feed_id ) ) {
			$this->log_dispatch_recovery_skipped( $feed_id, $classification );
			return 'success';
		}

		$retry_classification = $this->maybe_retry_dispatch_with_blocking_probe( $feed_id, $url, $args );

		if ( null !== $retry_classification ) {
			$classification = $retry_classification;
		}

		if ( ! $this->dispatch_outcome_requires_recovery( $classification ) ) {
			return $classification;
		}

		if ( $this->should_skip_dispatch_recovery_actions( $feed_id ) ) {
			$this->log_dispatch_recovery_skipped( $feed_id, $classification );
			return 'success';
		}

		$this->handle_dispatch_failure( $feed_id, $classification, $response, $url, $args );

		return $classification;
	}

	/**
	 * Returns true when recovery markers and cron fallback should not run for this dispatch.
	 *
	 * Skips post-completion stray handoffs (feed OK or drained queue with more feeds queued).
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return bool
	 */
	protected function should_skip_dispatch_recovery_actions( $feed_id ) {
		$feed_id = (string) absint( $feed_id );

		if ( '' === $feed_id || '0' === $feed_id ) {
			return false;
		}

		$reason = $this->get_dispatch_recovery_skip_reason( $feed_id );
		$skip   = '' !== $reason;

		return (bool) apply_filters( 'wppfm_skip_dispatch_recovery_actions', $skip, $feed_id, $reason );
	}

	/**
	 * Explains why dispatch recovery should be skipped, or empty string when it should run.
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return string
	 */
	protected function get_dispatch_recovery_skip_reason( $feed_id ) {
		if ( $this->feed_has_ok_status( $feed_id ) ) {
			return 'feed_status_ok';
		}

		if ( ! class_exists( 'WPPFM_Feed_Controller' ) ) {
			return '';
		}

		if ( WPPFM_Feed_Controller::should_resume_existing_batch( $feed_id ) ) {
			return '';
		}

		if ( WPPFM_Feed_Controller::nr_ids_remaining_in_product_queue() > 0 ) {
			return '';
		}

		if ( ! WPPFM_Feed_Controller::feed_queue_is_empty() ) {
			return 'feed_queue_has_more_feeds';
		}

		if ( ! WPPFM_Feed_Controller::feed_is_processing() ) {
			return 'queue_idle_not_processing';
		}

		return 'queue_idle_no_resume';
	}

	/**
	 * Returns true when the feed status in the database is OK (status id 1).
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return bool
	 */
	protected function feed_has_ok_status( $feed_id ) {
		if ( ! class_exists( 'WPPFM_Data' ) ) {
			return false;
		}

		$data   = new WPPFM_Data();
		$status = $data->get_feed_status( $feed_id );

		return '1' === (string) $status;
	}

	/**
	 * Logs that recovery was intentionally skipped for a completed or idle feed handoff.
	 *
	 * @param string $feed_id        Feed id.
	 * @param string $classification Outcome that would have triggered recovery.
	 *
	 * @return void
	 */
	protected function log_dispatch_recovery_skipped( $feed_id, $classification ) {
		$feed_id = (string) absint( $feed_id );
		$reason  = $this->get_dispatch_recovery_skip_reason( $feed_id );

		$this->log_dispatch_message(
			$feed_id,
			sprintf(
				'Dispatch recovery skipped (outcome=%s, reason=%s).',
				$classification,
				'' !== $reason ? $reason : 'unknown'
			)
		);

		do_action( 'wppfm_dispatch_recovery_skipped', $feed_id, $classification, $reason );
	}

	/**
	 * Returns true when the inbound request is a lightweight loopback probe (no batch work).
	 *
	 * @return bool
	 */
	protected function is_dispatch_probe_request() {
		$probe = filter_input( INPUT_GET, 'wppfm_dispatch_probe', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( '1' === (string) $probe ) {
			return true;
		}

		if ( isset( $_POST['wppfm_dispatch_probe'] ) ) {
			return '1' === sanitize_text_field( wp_unslash( $_POST['wppfm_dispatch_probe'] ) );
		}

		return false;
	}

	/**
	 * Schedules recovery and records a pending-dispatch marker after a failed dispatch.
	 *
	 * @param string       $feed_id        Feed id.
	 * @param string       $classification Outcome token.
	 * @param array|WP_Error|null $response Remote response when available.
	 * @param string       $url            Dispatch URL.
	 * @param array        $args           Request args.
	 *
	 * @return void
	 */
	protected function handle_dispatch_failure( $feed_id, $classification, $response, $url, $args ) {
		$error_message  = is_wp_error( $response ) ? $response->get_error_message() : '';
		$recovery_hook  = apply_filters( 'wppfm_dispatch_healthcheck_fallback_hook', 'wppfm_feed_generation_process_cron', $this->identifier );
		$recovery_next  = wp_next_scheduled( $recovery_hook );
		$recovery_label = $recovery_next > 0 ? gmdate( 'Y-m-d H:i:s', $recovery_next ) . ' UTC (in ' . max( 0, $recovery_next - time() ) . 's)' : 'not_scheduled';

		do_action(
			'wppfm_feed_generation_message',
			$feed_id,
			sprintf(
				'Dispatch requires recovery (outcome=%s%s). Pending dispatch marker set. Next recovery cron (%s): %s.',
				$classification,
				'' !== $error_message ? ', error=' . $error_message : '',
				$recovery_hook,
				$recovery_label
			),
			'ERROR'
		);

		do_action( 'wppfm_dispatch_failed', $feed_id, $response, $url, $args, $classification );

		$this->flag_pending_dispatch( $feed_id );
		$this->schedule_health_check_fallback( $feed_id );
	}

	/**
	 * Logs dispatch intent/outcome; verbose success logging is opt-in.
	 *
	 * @param string $feed_id Feed id.
	 * @param string $message Log message.
	 * @param string $level   Log level.
	 *
	 * @return void
	 */
	protected function log_dispatch_message( $feed_id, $message, $level = '' ) {
		do_action( 'wppfm_feed_generation_message', $feed_id, $message, $level );
	}

	/**
	 * Logs the classified dispatch result.
	 *
	 * @param string              $feed_id        Feed id.
	 * @param string              $classification Outcome token.
	 * @param array|WP_Error|null $response       Remote response.
	 * @param array               $args           Request args.
	 * @param bool                $is_retry       Whether this log line is for a probe retry.
	 *
	 * @return void
	 */
	protected function log_dispatch_outcome( $feed_id, $classification, $response, $args, $is_retry = false ) {
		$log_success = (bool) apply_filters( 'wppfm_enable_feed_state_logging', false );

		if ( 'success' === $classification && ! $log_success && ! $is_retry ) {
			return;
		}

		$code    = is_wp_error( $response ) ? 'error' : ( function_exists( 'wp_remote_retrieve_response_code' ) ? wp_remote_retrieve_response_code( $response ) : 0 );
		$message = function_exists( 'wp_remote_retrieve_response_message' ) && ! is_wp_error( $response ) ? wp_remote_retrieve_response_message( $response ) : '';
		$prefix  = $is_retry ? 'Dispatch probe' : 'Dispatch';

		$level = $this->dispatch_outcome_requires_recovery( $classification ) ? 'ERROR' : '';

		$log_line = sprintf(
			'%s outcome=%s (code=%s, message=%s, blocking=%s).',
			$prefix,
			$classification,
			$code ? strval( $code ) : 'n/a',
			$message ? $message : 'n/a',
			isset( $args['blocking'] ) && false === $args['blocking'] ? 'false' : 'true'
		);

		if ( ! is_wp_error( $response ) && is_numeric( $code ) && intval( $code ) >= 400 && function_exists( 'wp_remote_retrieve_body' ) ) {
			$response_body = (string) wp_remote_retrieve_body( $response );

			if ( '' !== $response_body ) {
				$snippet  = wp_html_excerpt( wp_strip_all_tags( $response_body ), 200, '...' );
				$log_line .= ' response_snippet=' . $snippet;
			}
		}

		$this->log_dispatch_message( $feed_id, $log_line, $level );
	}

	/**
	 * Store a marker so the cron health check can escalate a failed dispatch.
	 *
	 * @param int $feed_id Feed identifier.
	 *
	 * @since 3.18.0
	 */
	protected function flag_pending_dispatch( $feed_id ) {
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
	 * Background_Process overrides this to use the dedicated recovery cron hook.
	 *
	 * @since 3.18.0
	 *
	 * @param string $feed_id Optional feed id for handoff-aware scheduling.
	 */
	protected function schedule_health_check_fallback( $feed_id = '' ) {
		$hook  = apply_filters( 'wppfm_dispatch_healthcheck_fallback_hook', 'wppfm_feed_generation_process_cron', $this->identifier );
		$delay = max( 0, intval( apply_filters( 'wppfm_pending_dispatch_healthcheck_delay', 0, $feed_id ) ) );

		wp_schedule_single_event( time() + $delay, $hook );
	}
}
