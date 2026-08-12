<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract WPPFM_Background_Process class, derived from https://github.com/A5hleyRich/wp-background-processing.
 *
 * @abstract
 * @package WPPFM-Background-Processing
 * @extends WPPFM_Async_Request
 */
abstract class WPPFM_Background_Process extends WPPFM_Async_Request {

	/**
	 * Action
	 *
	 * (default value: 'background_process')
	 * @var string
	 */
	protected $action = 'background_process';

	/**
	 * Start time of the current process.
	 *
	 * (default value: 0)
	 *
	 * @var int
	 */
	protected $start_time = 0;

	/**
	 * Maximum lock time of the queue.
	 * Override if applicable, but the duration should be greater than that defined in the time_exceeded() method.
	 *
	 * @var int
	 */
	protected $queue_lock_time = 600; // @since 3.18.0+ Longer lease time to survive throttled hosts and long-running batches.

	/**
	 * Cron_hook_identifier
	 *
	 * @var mixed
	 */
	protected $cron_hook_identifier;

	/**
	 * Cron_interval_identifier
	 *
	 * @var mixed
	 */
	protected $cron_interval_identifier;

	/**
	 * Hook name for the single-fire loopback-recovery cron event.
	 *
	 * Kept separate from $cron_hook_identifier so that scheduling it from
	 * inside dispatch() cannot race with other health-check writes on the
	 * shared cron options row.
	 *
	 * @var string
	 */
	protected $cron_recovery_hook_identifier;

	/**
	 * Keeps track of the number of products that where added to the feed
	 *
	 * @var int
	 */
	protected $processed_products;

	/**
	 * Keeps track of the number of products that where handled in a specific batch.
	 *
	 * @var int
	 */
	protected $products_handled_in_batch;

	/**
	 * The processing class.
	 *
	 * @var mixed
	 */
	protected $processing_class;

	/**
	 * Batch update interval for progress counter
	 *
	 * @var int
	 */
	protected $progress_update_interval = 50;

	/**
	 * Timestamp of last lock refresh
	 *
	 * @var int
	 */
	protected $last_lock_refresh = 0;

	/**
	 * Lock refresh interval in seconds
	 *
	 * @var int
	 */
	protected $lock_refresh_interval = 30;

	/**
	 * Cached heartbeat key for this background process identifier.
	 *
	 * @var string|null
	 */
	protected $heartbeat_key = null;

	/**
	 * Final published feed path when the batch writes to a temporary file first (regular product feeds).
	 *
	 * @var string
	 */
	protected $batch_final_feed_file_path = '';

	/**
	 * Temporary processing path paired with {@see WPPFM_Background_Process::$batch_final_feed_file_path}.
	 *
	 * @var string
	 */
	protected $batch_temporary_feed_file_path = '';

	/**
	 * Incremental queue runtime state persisted in batch metadata.
	 *
	 * @var array
	 */
	protected $incremental_state = array();

	/**
	 * Whether this batch uses a temporary feed file that maps to {@see WPPFM_Background_Process::$batch_temporary_feed_file_path}.
	 *
	 * @var bool
	 */
	protected $use_temporary_feed_file_for_batch = false;

	/**
	 * Cached process-lock key for this background process identifier.
	 *
	 * @var string|null
	 */
	protected $process_lock_key = null;

	/**
	 * Cached owner id for the currently running background process.
	 *
	 * IMPORTANT:
	 * - This must remain stable for the lifetime of a process lock.
	 * - It must NOT depend on `wppfm_background_process_key`, because that key is intentionally deleted
	 *   when clearing batch data (see `delete()` / feed processor completion). Tying ownership to it can
	 *   cause `unlock_process()` to become a "non-owner" mid-run, leaving the lock stuck.
	 *
	 * @var string|null
	 */
	protected $process_owner_id = null;

	/**
	 * Sanitized feed identifier captured from the validated background request.
	 *
	 * @var string
	 */
	protected $request_feed_id = '';

	/**
	 * Initiate a new background process
	 */
	public function __construct() {
		parent::__construct();

		$this->cron_hook_identifier          = $this->identifier . '_cron';
		$this->cron_interval_identifier      = $this->identifier . '_cron_interval';
		$this->cron_recovery_hook_identifier = $this->identifier . '_cron_recovery';
		// Keep this in-memory list empty; queue progression is the source of truth for processed items.
		$this->processed_products            = array();
		
		// Allow customization of progress update interval
		$this->progress_update_interval = apply_filters( 
			'wppfm_progress_update_interval', 
			50 
		);
		
		// Allow customization of lock refresh interval
		$this->lock_refresh_interval = apply_filters(
			'wppfm_lock_refresh_interval',
			30 // Default: 30 seconds
		);

		add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_health_check' ) );
		// Both the interval health-check and single-fire recovery hooks run the same handler.
		add_action( $this->cron_recovery_hook_identifier, array( $this, 'handle_cron_health_check' ) );
		add_filter( 'cron_schedules', array( $this, 'schedule_cron_health_check' ) ); // phpcs:disable WordPress.WP.CronInterval.ChangeDetected
		// Recover when legacy recurring events fail wp_reschedule_event() during concurrent cron writes.
		add_action( 'cron_reschedule_event_error', array( $this, 'handle_cron_reschedule_event_error' ), 10, 3 );
	}

	/**
	 * Returns the transient key used for the background process lock.
	 *
	 * @return string
	 */
	protected function get_process_lock_key() {
		if ( null === $this->process_lock_key ) {
			$this->process_lock_key = $this->identifier . '_process_lock';
		}

		return $this->process_lock_key;
	}

	/**
	 * Returns the site option key used to store the current process owner id.
	 *
	 * @return string
	 */
	protected function get_process_owner_option_key() {
		return $this->identifier . '_process_owner_id';
	}

	/**
	 * Returns the site option key used for a durable "heartbeat" of the current owner.
	 *
	 * This acts as a fallback signal when the transient storage is unreliable or the lock TTL is shorter than
	 * real-world request gaps (e.g. throttled hosting, cron overlap, slow I/O).
	 *
	 * @return string
	 */
	protected function get_process_heartbeat_key() {
		if ( null === $this->heartbeat_key ) {
			$this->heartbeat_key = $this->identifier . '_process_heartbeat';
		}

		return $this->heartbeat_key;
	}

	/**
	 * Returns the maximum age (in seconds) for which a heartbeat is considered "fresh".
	 *
	 * @return int
	 */
	protected function get_process_heartbeat_ttl() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is intentionally namespaced by this process identifier to allow per-process overrides.
		$ttl = apply_filters( $this->identifier . '_process_heartbeat_ttl', 10 * MINUTE_IN_SECONDS );

		return max( 60, intval( $ttl ) );
	}

	/**
	 * Returns the age (in seconds) after which a lock should be considered stale and eligible for cleanup.
	 *
	 * @return int
	 */
	protected function get_process_lock_stale_seconds() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is intentionally namespaced by this process identifier to allow per-process overrides.
		$stale = apply_filters( $this->identifier . '_process_lock_stale_seconds', 15 * MINUTE_IN_SECONDS );

		return max( 60, intval( $stale ) );
	}

	/**
	 * Writes/refreshes the durable heartbeat marker for the current owner.
	 *
	 * @param string $context Optional context for diagnostic logging.
	 *
	 * @return void
	 */
	protected function update_process_heartbeat( $context = '' ) {
		$payload = array(
			'ts'      => time(),
			'owner'   => $this->get_owner_id(),
			'context' => is_string( $context ) ? $context : '',
		);

		update_site_option( $this->get_process_heartbeat_key(), $payload );

		// Only log heartbeat writes when explicitly enabled to avoid flooding logs.
		if ( apply_filters( 'wppfm_enable_background_lock_logging', false ) ) {
			do_action(
				'wppfm_feed_generation_message',
				'unknown',
				sprintf(
					'Background process heartbeat updated (owner: %s, context: %s).',
					$payload['owner'],
					$payload['context']
				)
			);
		}
	}

	/**
	 * Returns true when a durable heartbeat indicates an active process is likely still running.
	 *
	 * @return bool
	 */
	protected function is_process_heartbeat_fresh() {
		$heartbeat = get_site_option( $this->get_process_heartbeat_key() );
		if ( ! is_array( $heartbeat ) || empty( $heartbeat['ts'] ) ) {
			return false;
		}

		return ( time() - intval( $heartbeat['ts'] ) ) <= $this->get_process_heartbeat_ttl();
	}

	/**
	 * Dispatch the feed generation process. Runs the parent dispatch method in the wppfm-async-request class.
	 *
	 * @param string $feed_id   The id of the feed.
	 */
	public function dispatch( $feed_id ) {
		// Schedule the cron health check (single event; no-op if already scheduled).
		$this->schedule_event();

		// Perform the remote post.
		parent::dispatch( $feed_id );

		// Schedule recovery after handoff grace when applicable so cron does not run during
		// the intentional lock gap and exit before loopback can acquire the worker lock.
		$this->schedule_recovery_cron_event( $feed_id );
	}

	/**
	 * Whether dispatch should nudge WP-Cron to run immediately after scheduling recovery.
	 *
	 * @param string $feed_id Feed id for filter context.
	 *
	 * @return bool
	 */
	protected function should_spawn_cron_after_dispatch( $feed_id ) {
		$default = ! ( defined( 'WP_CLI' ) && WP_CLI );

		return (bool) apply_filters( 'wppfm_spawn_cron_after_dispatch', $default, $feed_id );
	}

	/**
	 * Schedules recovery via the dedicated cron hook (not the recurring health hook).
	 *
	 * @since 3.18.0
	 *
	 * @param string $feed_id Optional feed id for handoff-aware scheduling.
	 */
	protected function schedule_health_check_fallback( $feed_id = '' ) {
		$this->schedule_recovery_cron_event( $feed_id );
	}

	/**
	 * Schedules the single-fire recovery cron, deferring until after handoff grace when marked.
	 *
	 * Avoids firing handle_cron_health_check() during the short handoff window where it would
	 * exit early and consume a cron slot while loopback may still arrive.
	 *
	 * @since 3.23.0
	 *
	 * @param string $feed_id Feed id for grace calculation.
	 *
	 * @return int Unix timestamp when recovery was scheduled.
	 */
	protected function schedule_recovery_cron_event( $feed_id = '' ) {
		$feed_id = (string) $feed_id;

		if ( '' === $feed_id && class_exists( 'WPPFM_Feed_Controller' ) ) {
			$feed_id = WPPFM_Feed_Controller::get_active_batch_feed_id();
		}

		$timestamp      = time();
		$spawn_cron_now = true;
		$after_handoff  = false;

		if ( '' !== $feed_id && class_exists( 'WPPFM_Feed_Controller' ) && WPPFM_Feed_Controller::feed_handoff_marker_is_active_for_feed( $feed_id ) ) {
			$grace     = WPPFM_Feed_Controller::get_feed_handoff_grace_seconds( $feed_id );
			$buffer    = max( 0, intval( apply_filters( 'wppfm_feed_recovery_cron_buffer_seconds', 5, $feed_id ) ) );
			$timestamp = time() + $grace + $buffer;
			$spawn_cron_now = (bool) apply_filters( 'wppfm_spawn_cron_after_handoff_dispatch', false, $feed_id );
			$after_handoff  = true;
		}

		$extra_delay = max( 0, intval( apply_filters( 'wppfm_pending_dispatch_healthcheck_delay', 0, $feed_id ) ) );

		if ( $extra_delay > 0 ) {
			$timestamp = max( $timestamp, time() + $extra_delay );
		}

		wp_clear_scheduled_hook( $this->cron_recovery_hook_identifier );
		wp_schedule_single_event( $timestamp, $this->cron_recovery_hook_identifier );

		if ( $after_handoff ) {
			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				sprintf(
					'Recovery cron scheduled after handoff grace (in %ds).',
					max( 0, $timestamp - time() )
				),
				'WARNING'
			);
		}

		if ( $spawn_cron_now && $this->should_spawn_cron_after_dispatch( $feed_id ) && function_exists( 'spawn_cron' ) ) {
			spawn_cron( time() );
		}

		return $timestamp;
	}

	/**
	 * Push to queue
	 *
	 * @param mixed $data Data.
	 *
	 * @return $this
	 */
	public function push_to_queue( $data ) {
		$this->data[] = $data;

		return $this;
	}

	public function nr_of_products_in_queue() {
		return count( $this->data ) - 2; // subtract the XML header and footer items as they are not products
	}

	/**
	 * Implements the wppfm_feed_ids_in_queue filter on the queue.
	 *
	 * @param   string $feed_id    Feed id to enable using the filter on a specific feed.
	 *
	 * @since 2.10.0.
	 */
	public function apply_filter_to_queue( $feed_id ) {
		// Remove the feed header from the queue.
		$feed_header = array_shift( $this->data );

		// Apply the filter.
		$ids = apply_filters( 'wppfm_feed_ids_in_queue', $this->data, $feed_id );

		// Add the feed header again.
		array_unshift( $ids, $feed_header );

		$this->data = $ids;
	}

	/**
	 * Clears the queue
	 *
	 * @return $this
	 */
	public function clear_the_queue() {
		$this->data = null;

		return $this;
	}

	/**
	 * Set the path to the feed file
	 *
	 * @param string $file_path     The path to the feed file.
	 *
	 * @return $this
	 */
	public function set_file_path( $file_path ) {
		$this->file_path = $file_path;

		return $this;
	}

	/**
	 * Stores final and temporary output paths for batch metadata (regular product feeds).
	 *
	 * @param string $final_path      Absolute path to the published feed file.
	 * @param string $temporary_path  Absolute path to the active processing file (same as file_path when using a temp artifact).
	 * @param bool   $use_temporary   True when generation writes to the temporary path first.
	 *
	 * @return $this
	 */
	public function set_temporary_feed_batch_paths( $final_path, $temporary_path, $use_temporary ) {
		$this->batch_final_feed_file_path      = (string) $final_path;
		$this->batch_temporary_feed_file_path  = (string) $temporary_path;
		$this->use_temporary_feed_file_for_batch = (bool) $use_temporary;

		return $this;
	}

	/**
	 * Set the language of the feed
	 *
	 * @param object $feed_data  The feed data.
	 *
	 * @return $this
	 */
	public function set_feed_data( $feed_data ) {
		$this->feed_data = $feed_data;

		return $this;
	}

	/**
	 * Set the feed pre-data
	 *
	 * @param array $pre_data   The pre-data to be stored.
	 *
	 * @return $this
	 */
	public function set_pre_data( $pre_data ) {
		$this->pre_data = $pre_data;

		return $this;
	}

	/**
	 * Set the channel-specific main category title and description title
	 *
	 * @param array $channel_details    The channel details to be set.
	 *
	 * @return $this
	 */
	public function set_channel_details( $channel_details ) {
		$this->channel_details = $channel_details;

		return $this;
	}

	/**
	 * Sets the relation table
	 *
	 * @param array $relations_table    The relation table to be set.
	 *
	 * @return $this
	 */
	public function set_relations_table( $relations_table ) {
		$this->relations_table = $relations_table;

		return $this;
	}

	/**
	 * Stores incremental queue runtime state for metadata persistence.
	 *
	 * @param array $incremental_state Runtime state used by incremental feed discovery.
	 *
	 * @return $this
	 */
	public function set_incremental_state( $incremental_state ) {
		$this->incremental_state = is_array( $incremental_state ) ? $incremental_state : array();

		return $this;
	}

	/**
	 * Save queue data.
	 *
	 * @param string $feed_id   The feed id.
	 *
	 * @return $this
	 */
	public function save( $feed_id ) {
		$key = $this->generate_key( $feed_id );

		if ( ! empty( $this->data ) ) {
			$previous_key = get_site_option( 'wppfm_background_process_key' );

			// Consolidate all batch metadata into a single option for better performance
			$batch_metadata = array(
				'version'         => 1, // For future migration support
				'feed_id'         => $feed_id,
				'created_at'      => time(),
				'feed_data'       => $this->feed_data,
				'file_path'       => $this->file_path,
				'pre_data'        => $this->pre_data,
				'channel_details' => $this->channel_details,
				'relations_table' => $this->relations_table,
			);

			if ( isset( $this->incremental_state ) && is_array( $this->incremental_state ) ) {
				$batch_metadata['incremental_state'] = $this->incremental_state;
			}

			// Regular product feeds: persist both paths so completion can promote the temp file without touching the live feed mid-run.
			$raw_feed_type_id = isset( $this->feed_data->feedTypeId ) ? (string) $this->feed_data->feedTypeId : '1';
			$normalized_type  = ( '' === $raw_feed_type_id ) ? '1' : $raw_feed_type_id;
			if ( '1' === $normalized_type && $this->use_temporary_feed_file_for_batch ) {
				$batch_metadata['final_file_path']      = $this->batch_final_feed_file_path;
				$batch_metadata['temporary_file_path']  = $this->batch_temporary_feed_file_path;
				$batch_metadata['use_temporary_file']   = true;
			}

			update_site_option( 'wppfm_background_process_key', $key );
			update_site_option( $key, $this->data );
			update_site_option( 'wppfm_batch_metadata_' . $key, $batch_metadata );

			// Log consolidation + key change (opt-in to avoid noisy logs on large queues).
			if ( apply_filters( 'wppfm_enable_feed_state_logging', false ) ) {
				do_action(
					'wppfm_feed_generation_message',
					$feed_id,
					sprintf(
						'Saved batch state (previous_key=%s, new_key=%s).',
						$previous_key ? $previous_key : 'none',
						$key
					)
				);
			}
		} else { // @since 2.35.0
			$message = sprintf( 'Got no data to store in the site option! Feed id = %s', $feed_id );
			do_action( 'wppfm_feed_generation_message', $feed_id, $message, 'ERROR' );
		}

		return $this;
	}

	/**
	 * Update queue
	 *
	 * @param string $key   Key.
	 * @param array  $data  Data.
	 */
	public function update( $key, $data ) {
		if ( ! empty( $data ) ) {
			$previous_key = get_site_option( 'wppfm_background_process_key' );
			update_site_option( 'wppfm_background_process_key', $key );
			update_site_option( $key, $data );

			if ( apply_filters( 'wppfm_enable_feed_state_logging', false ) ) {
				do_action(
					'wppfm_feed_generation_message',
					'unknown',
					sprintf(
						'Updated batch queue state (previous_key=%s, active_key=%s, remaining_items=%d).',
						$previous_key ? $previous_key : 'none',
						$key,
						is_array( $data ) ? count( $data ) : 0
					)
				);
			}
		}
	}

	/**
	 * Delete queue and properties stored in the options table
	 *
	 * When a regular feed uses a temporary artifact, batch metadata and the background key must stay
	 * available until {@see WPPFM_Feed_Processor::complete()} finishes promotion (watchdog + failure cleanup).
	 *
	 * @param string $key Key.
	 */
	public function delete( $key ) {
		$batch_metadata = get_site_option( 'wppfm_batch_metadata_' . $key, array() );
		$defer_cleanup  = is_array( $batch_metadata ) && ! empty( $batch_metadata['use_temporary_file'] );

		// Always drop the drained product queue option; metadata/key may be retained for the completion window.
		delete_site_option( $key );

		if ( ! $defer_cleanup ) {
			delete_site_option( 'wppfm_background_process_key' );
			delete_site_option( 'wppfm_batch_metadata_' . $key );
		}

		if ( apply_filters( 'wppfm_enable_feed_state_logging', false ) ) {
			do_action(
				'wppfm_feed_generation_message',
				'unknown',
				sprintf(
					'Deleted batch queue state (cleared_key=%s).',
					$key ? $key : 'none'
				)
			);
		}
	}

	/**
	 * Generate key
	 *
	 * Generates a unique key based on micro time. Queue items are
	 * given a unique key so that they can be merged upon save.
	 *
	 * @param string $feed_id   The feed id.
	 * @param int    $length    The length of the key.
	 *
	 * @return string
	 */
	protected function generate_key( $feed_id, $length = 64 ) {
		$unique  = md5( microtime() . wp_rand() );
		$prepend = $this->identifier . '_batch_' . $feed_id . '_';

		return substr( $prepend . $unique, 0, $length );
	}

	/**
	 * Maybe process queue. This method is activated by the dispatch method in the parent class.
	 *
	 * Check whether data exists within the queue and that the process is not yet running.
	 */
	public function maybe_handle() {
		// Don't lock up other requests while processing.
		session_write_close();

		$this->request_feed_id = $this->get_async_request_feed_id();
		$feed_id               = $this->request_feed_id;

		$background_mode_disabled = get_option( 'wppfm_disabled_background_mode', 'false' );

		if ( $this->is_queue_empty() ) {
			$message = 'Tried to start a new batch but the queue is empty!';
			do_action( 'wppfm_feed_generation_message', $feed_id, $message, 'ERROR' );
			// No data to process.
			wp_die();
		}

		// Foreground mode dispatches internally in the same request and therefore has no standalone nonce payload.
		if ( ! $this->is_internal_dispatch_context() ) {
			$nonce      = $this->get_async_request_nonce();
			$nonce_data = $this->verify_dispatch_nonce( $feed_id, $nonce );

			// Cron/loopback: authorize with the one-time transient issued in dispatch() (no logged-in user).
			// Logged-in admin fallback: capability + action nonce when the transient is unavailable.
			if ( false === $nonce_data ) {
				$authorized_as_admin = is_string( $nonce )
					&& '' !== $nonce
					&& wp_verify_nonce( $nonce, $this->identifier )
					&& current_user_can( 'edit_feeds' );

				if ( ! $authorized_as_admin ) {
					do_action(
						'wppfm_feed_generation_message',
						$feed_id ? $feed_id : 'unknown',
						'Unauthorized background-process request rejected (invalid dispatch nonce and no edit_feeds capability).',
						'ERROR'
					);
					wp_die( esc_html__( 'You are not allowed to do this.', 'wp-product-feed-manager' ) );
				}
			} else {
				do_action(
					'wppfm_feed_generation_message',
					$feed_id,
					sprintf(
						'Background dispatch authorized via loopback nonce (request_id=%s).',
						isset( $nonce_data['request_id'] ) ? strval( $nonce_data['request_id'] ) : 'n/a'
					)
				);
			}
		}

		// Acquire the existing process lock before entering handle() so two accepted
		// loopback requests cannot both pass the pre-flight checks and start processing.
		$this->lock_process();

		// The next worker successfully took over, so the previous batch handoff is no longer pending.
		if ( $feed_id && class_exists( 'WPPFM_Feed_Controller' ) ) {
			WPPFM_Feed_Controller::clear_feed_handoff_marker( $feed_id );
		}

		// Another request may have drained the queue while this request was waiting for
		// the lock, so validate again before we start reading batch state.
		if ( $this->is_queue_empty() ) {
			$message = 'Tried to start a new batch after acquiring the process lock, but the queue is empty!';
			do_action( 'wppfm_feed_generation_message', $this->request_feed_id, $message, 'WARNING' );
			$this->unlock_process();
			wp_die();
		}

		$this->handle();

		if ( 'true' === $background_mode_disabled ) {
			echo 'foreground_processing_complete';
		}

		wp_die();
	}

	/**
	 * Is the queue empty?
	 *
	 * @return bool
	 */
	protected function is_queue_empty() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		// phpcs:ignore
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore
				"SELECT COUNT(*) FROM $table WHERE $column LIKE %s",
				$key
			)
		);

		return ! ( ( $count > 0 ) );
	}

	/**
	 * Is process running
	 *
	 * Check whether the current process is already running
	 * in a background process. Kinsta-compatible version.
	 */
	public function is_process_running() {
		$lock = get_site_transient( $this->get_process_lock_key() );
		
		if ( ! $lock ) {
			// Fallback: a durable heartbeat helps prevent overlap when transient storage is flaky.
			if ( $this->is_process_heartbeat_fresh() ) {
				if ( apply_filters( 'wppfm_enable_background_lock_logging', false ) ) {
					do_action(
						'wppfm_feed_generation_message',
						'unknown',
						sprintf(
							'No lock transient found, but heartbeat is fresh. Treating process as running (identifier: %s).',
							$this->identifier
						),
						'WARNING'
					);
				}

				return true;
			}

			return false;
		}

		// Parse the lock value: timestamp_random_ownerid
		// Split into exactly 3 parts so owner id (which may contain underscores) stays intact
		$lock_parts = explode( '_', $lock, 3 );
		if ( count( $lock_parts ) >= 3 ) {
			$lock_timestamp = floatval( $lock_parts[0] );
			$lock_owner = $lock_parts[2];
			$current_owner = $this->get_owner_id();
			
			// If it's our owner id, we own the lock
			if ( $lock_owner === $current_owner ) {
				return true;
			}
			
			// Check if lock is stale (older than 5 minutes)
			$lock_age = microtime( true ) - $lock_timestamp;
			if ( $lock_age > $this->get_process_lock_stale_seconds() ) {
				do_action(
					'wppfm_feed_generation_message',
					'unknown',
					sprintf(
						'Detected stale process lock; clearing it (identifier: %s, lock_age=%.2fs, stale_after=%ds).',
						$this->identifier,
						$lock_age,
						intval( $this->get_process_lock_stale_seconds() )
					),
					'WARNING'
				);

				// Lock is stale, clear it
				delete_site_transient( $this->get_process_lock_key() );
				return false;
			}
			
			// Lock belongs to another owner and is not stale
			return true;
		}

		return true;
	}

	/**
	 * Returns a stable process owner id across requests.
	 * Prefer the batch properties key stored in site options; fallback to a persistent owner id option.
	 *
	 * @return string
	 */
	protected function get_owner_id() {
		// Use cached value when available to avoid extra DB reads.
		if ( null !== $this->process_owner_id ) {
			return $this->process_owner_id;
		}

		// Prefer the explicit "current process owner id" option.
		// This is set when a lock is acquired and cleared when the lock is released.
		$owner_id = get_site_option( $this->get_process_owner_option_key() );
		if ( $owner_id ) {
			$this->process_owner_id = $owner_id;
			return $owner_id;
		}

		// Fallback: generate a stable (but not per-run) id for logging/heartbeat when no process is active yet.
		// This MUST NOT be used as an ownership check for lock release in active runs.
		$owner_option_key = $this->identifier . '_owner_id';
		$owner_id         = get_site_option( $owner_option_key );

		if ( ! $owner_id ) {
			$owner_id = uniqid( 'wppfm_', true );
			update_site_option( $owner_option_key, $owner_id );
		}

		$this->process_owner_id = $owner_id;
		return $owner_id;
	}

	/**
	 * Sets a new per-run process owner id and persists it for cross-request continuity.
	 *
	 * @return string The new owner id.
	 */
	protected function set_new_process_owner_id() {
		$this->process_owner_id = uniqid( 'wppfm_', true );
		update_site_option( $this->get_process_owner_option_key(), $this->process_owner_id );

		return $this->process_owner_id;
	}

	/**
	 * Check if the current process still owns the lock
	 * Kinsta-compatible version using stable owner id
	 *
	 * @return bool
	 */
	protected function is_current_process_locked() {
		$lock = get_site_transient( $this->get_process_lock_key() );
		
		if ( ! $lock ) {
			return false;
		}

		// Split into exactly 3 parts so owner id (which may contain underscores) stays intact
		$lock_parts = explode( '_', $lock, 3 );
		if ( count( $lock_parts ) >= 3 ) {
			$lock_owner = $lock_parts[2];
			$current_owner = $this->get_owner_id();
			
			return $lock_owner === $current_owner;
		}

		return false;
	}

	/**
	 * Lock process
	 *
	 * Lock the process so that multiple instances can't run simultaneously.
	 * Kinsta-compatible version using session-based identification.
	 */
	protected function lock_process() {
		$this->start_time = time(); // Set start time of a current process.

		$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 120; // 2 minutes
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is intentionally namespaced by this process identifier to allow per-process lock TTL overrides.
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );
		$lock_duration = max( 60, intval( $lock_duration ) );

		// Use a more robust locking mechanism with retry logic
		$max_attempts = 3;
		$attempt = 0;
		$lock_acquired = false;

		while ( $attempt < $max_attempts && ! $lock_acquired ) {
			// Check if another process is already running
			if ( $this->is_process_running() ) {
				$attempt++;
				if ( $attempt < $max_attempts ) {
					// Wait a bit before retrying (with jitter to avoid thundering herd)
					usleep( ( 100000 + ( wp_rand( 0, 100000 ) ) ) ); // 100-200ms
					continue;
				}
				// If we can't acquire the lock after max attempts, exit
				/* translators: %d: number of attempts */
				wp_die( sprintf( esc_html__( 'Could not acquire process lock after %d attempts', 'wp-product-feed-manager' ), intval( $max_attempts ) ) );
			}

			// Create a new per-run owner id and use it for the lifetime of this lock.
			$owner_id   = $this->set_new_process_owner_id();
			$lock_value = microtime( true ) . '_' . wp_rand( 10000, 99999 ) . '_' . $owner_id;
			$lock_acquired = set_site_transient( $this->get_process_lock_key(), $lock_value, $lock_duration );
			

			if ( $lock_acquired ) {
				// Verify we still have the lock (double-check)
				$current_lock = get_site_transient( $this->get_process_lock_key() );
				if ( $current_lock !== $lock_value ) {
					// Someone else got the lock, try again
					$lock_acquired = false;
					$attempt++;
					continue;
				}

				// Write a durable heartbeat so other requests can detect a running process even if the transient is lost.
				$this->update_process_heartbeat( 'lock_acquired' );

				if ( apply_filters( 'wppfm_enable_background_lock_logging', false ) ) {
					do_action(
						'wppfm_feed_generation_message',
						'unknown',
						sprintf(
							'Acquired process lock (identifier: %s, owner: %s, ttl: %ds).',
							$this->identifier,
							$owner_id,
							$lock_duration
						)
					);
				}
			} else {
				$attempt++;
			}
		}

		if ( ! $lock_acquired ) {
			wp_die( esc_html__( 'Failed to acquire process lock', 'wp-product-feed-manager' ) );
		}
	}

	/**
	 * Unlock process
	 *
	 * Unlock the process so that other instances can spawn.
	 *
	 * @return $this
	 */
	protected function unlock_process() {
		$current_lock = get_site_transient( $this->get_process_lock_key() );

		// Only the owner should be allowed to clear the lock.
		if ( $current_lock && ! $this->is_current_process_locked() ) {
			do_action(
				'wppfm_feed_generation_message',
				'unknown',
				sprintf(
					'Unlock requested by non-owner; lock retained (identifier: %s, current_lock: %s, current_owner: %s).',
					$this->identifier,
					is_string( $current_lock ) ? $current_lock : 'non-string',
					$this->get_owner_id()
				),
				'WARNING'
			);

			return $this;
		}

		delete_site_transient( $this->get_process_lock_key() );

		// Clear the per-run owner id marker so the next lock acquisition gets a fresh owner.
		delete_site_option( $this->get_process_owner_option_key() );

		// Clear heartbeat once processing ends (best-effort), but only if it belongs to this owner.
		$heartbeat = get_site_option( $this->get_process_heartbeat_key() );
		if ( is_array( $heartbeat ) && ! empty( $heartbeat['owner'] ) && $heartbeat['owner'] === $this->get_owner_id() ) {
			delete_site_option( $this->get_process_heartbeat_key() );
		}

		return $this;
	}

	/**
	 * Refresh the process lock to prevent expiration during long processing
	 *
	 * @return bool
	 */
	protected function refresh_process_lock() {
		if ( ! $this->is_current_process_locked() ) {
			return false;
		}

		$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 120;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is intentionally namespaced by this process identifier to allow per-process lock TTL overrides.
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );
		$lock_duration = max( 60, intval( $lock_duration ) );

		$current_lock = get_site_transient( $this->get_process_lock_key() );
		if ( $current_lock ) {
			// Extend the lock duration and refresh the timestamp to prevent stale-age cleanup during long runs.
			$owner_id   = $this->get_owner_id();
			$lock_value = microtime( true ) . '_' . wp_rand( 10000, 99999 ) . '_' . $owner_id;
			$refreshed  = set_site_transient( $this->get_process_lock_key(), $lock_value, $lock_duration );
			if ( $refreshed ) {
				$this->update_process_heartbeat( 'lock_refresh' );

				if ( apply_filters( 'wppfm_enable_background_lock_logging', false ) ) {
					do_action(
						'wppfm_feed_generation_message',
						'unknown',
						sprintf(
							'Refreshed process lock timestamp (identifier: %s, owner: %s, ttl: %ds).',
							$this->identifier,
							$owner_id,
							$lock_duration
						)
					);
				}
			}

			return $refreshed;
		}

		return false;
	}

	/**
	 * Get batch
	 *
	 * @return  stdClass|bool   Return the first batch from the queue or false if it does not exist.
	 */
	protected function get_batch() {
		global $wpdb;

		$table        = $wpdb->options;
		$column       = 'option_name';
		$key_column   = 'option_id';
		$value_column = 'option_value';

		if ( is_multisite() ) {
			$table        = $wpdb->sitemeta;
			$column       = 'meta_key';
			$key_column   = 'meta_id';
			$value_column = 'meta_value';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		// phpcs:ignore
		$query = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore
				"	SELECT * FROM $table WHERE $column LIKE %s ORDER BY $key_column LIMIT 1",
				$key
			)
		);

		// @since 2.10.0 added an extra validation if the batch still exists.
		if ( $query && property_exists( $query, $column ) && property_exists( $query, $value_column ) ) {
			$batch       = new stdClass();
			$batch->key  = $query->$column;
			$batch->data = maybe_unserialize( $query->$value_column );
		} else {
			return false;
		}

		return $batch;
	}

	/**
	 * Persists the authoritative products-added counter at a batch boundary.
	 *
	 * Uses an absolute, monotonic total so repeated commits for the same slice are
	 * idempotent instead of additive.
	 *
	 * @param int|string $feed_id                Feed id.
	 * @param int        $running_progress_count Absolute products-added total for the feed run.
	 *
	 * @return void
	 */
	protected function commit_batch_products_added_counter( $feed_id, $running_progress_count = 0 ) {
		$feed_id                = absint( $feed_id );
		$running_progress_count = max( 0, absint( $running_progress_count ) );

		if ( ! $feed_id || $running_progress_count < 1 ) {
			$this->products_handled_in_batch = 0;
			return;
		}

		if ( function_exists( 'wppfm_set_feed_products_added_counter' ) ) {
			wppfm_set_feed_products_added_counter( $feed_id, $running_progress_count );
		}

		if ( function_exists( 'wppfm_sync_feed_products_added_progress_transient' ) ) {
			wppfm_sync_feed_products_added_progress_transient( $feed_id );
		}

		$this->products_handled_in_batch = 0;
	}

	/**
	 * Handle
	 *
	 * Pass each queue item to the task handler, while remaining
	 * within server memory and time limit constraints.
	 *
	 * @return   void|bool
	 */
	protected function handle() {
		// maybe_handle() acquires the process lock as early as possible for async requests.
		// Keep this fallback so direct/internal calls still use the same lock lifecycle.
		if ( ! $this->is_current_process_locked() ) {
			$this->lock_process();
		}

		$feed_id             = '';
		$handled_items_count = 0;

		do {
			// Validate that we still own the lock before processing each batch
			if ( ! $this->is_current_process_locked() ) {
				do_action( 'wppfm_feed_generation_message', 'unknown', 'Process lock was lost during processing', 'ERROR' );
				$this->unlock_process();
				return false;
			}

			// Refresh lock at the start of each batch
			$this->refresh_process_lock();
			$this->last_lock_refresh = time(); // Initialize timestamp

			$batch = $this->get_batch();

			if ( ! $batch ) { // @since 2.10.0
				$message = 'Could not get the next batch data!';
				do_action( 'wppfm_feed_generation_message', $this->request_feed_id, $message, 'ERROR' );
				$this->end_batch( 'unknown', 'failed' );
				return false;
			}

			// Single source of truth:
			// Always use the batch key we actually selected as the authoritative key for BOTH:
			// - batch queue data
			// - consolidated batch metadata (feed data, file path, etc.)
			//
			// Using the global `wppfm_background_process_key` here can lead to cross-feed contamination
			// when multiple feeds are queued and that pointer is updated by another request between reads.
			$properties_key = ( isset( $batch->key ) && is_string( $batch->key ) ) ? $batch->key : '';

			// Keep the global pointer aligned for legacy helpers that still consult it (best effort).
			$active_key = get_site_option( 'wppfm_background_process_key' );
			if ( $properties_key && $active_key !== $properties_key ) {
				update_site_option( 'wppfm_background_process_key', $properties_key );
			}

			// @since 2.10.0
			if ( ! $properties_key ) {
				$message = 'Tried to get the next batch but the batch key is empty.';
				do_action( 'wppfm_feed_generation_message', $this->request_feed_id, $message, 'ERROR' );
				$this->end_batch( 'unknown', 'failed' );
				return false;
			}

			// Load consolidated batch metadata
			$batch_metadata = get_site_option( 'wppfm_batch_metadata_' . $properties_key );

			if ( ! $batch_metadata || ! is_array( $batch_metadata ) ) {
				$message = sprintf( 'Could not load batch metadata for key: %s. Aborting feed processing.', $properties_key );
				do_action( 'wppfm_feed_generation_message', 'unknown', $message, 'ERROR' );

				$feed_id_from_request = $this->request_feed_id;
				$resolved_feed_id     = $feed_id_from_request ? $feed_id_from_request : get_transient( 'wppfm_active_feed_id' );

				$this->end_batch( $resolved_feed_id ? $resolved_feed_id : 'unknown', 'failed' );
				return false;
			}

			// Extract metadata from consolidated array
			$feed_data       = isset( $batch_metadata['feed_data'] ) ? $batch_metadata['feed_data'] : null;
			$feed_file_path  = isset( $batch_metadata['file_path'] ) ? $batch_metadata['file_path'] : null;
			$pre_data        = isset( $batch_metadata['pre_data'] ) ? $batch_metadata['pre_data'] : null;
			$channel_details = isset( $batch_metadata['channel_details'] ) ? $batch_metadata['channel_details'] : null;
			$relations_table = isset( $batch_metadata['relations_table'] ) ? $batch_metadata['relations_table'] : null;

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( '3' === $feed_data->feedTypeId ) {
				$batch->data = array(
					'product_id' => '0',
				);
			}

			// @since 2.34.0
			if ( ! empty( $feed_data ) && property_exists( $feed_data, 'feedId' ) ) {
				// phpcs:ignore
				$feed_id = $feed_data->feedId;
				// phpcs:ignore
				do_action( 'wppfm_feed_generation_message', $feed_data->feedId, 'Feed handle has been started. Async request has been passed through.' ); // @since 2.40.0

				// @since 3.18.0 clear pending dispatch markers once the background process picks up the batch.
				$this->clear_pending_dispatch_flag( $feed_id );
			} else {
				$message = sprintf( 'Tried to get the next batch but the feed data could not be loaded correctly. Used property key: %s', $properties_key );
				do_action( 'wppfm_feed_generation_message', 'unknown', $message, 'ERROR' );
				$this->end_batch( 'unknown', 'failed' );
				return false;
			}

			// Committed products-added total before this batch slice. Always bypass object-cache:
			// direct SQL increments invalidate cache, but a stale cached read here would inflate
			// progress totals and reconcile would persist the wrong feed product count.
			$batch_committed_baseline = 0;

			if ( function_exists( 'wppfm_get_feed_products_added_counter' ) ) {
				$batch_committed_baseline = wppfm_get_feed_products_added_counter( $feed_id, true );
			} else {
				$legacy_baseline = get_transient( 'wppfm_nr_of_processed_products' );

				if ( false === $legacy_baseline ) {
					$legacy_baseline = 0;
					set_transient( 'wppfm_nr_of_processed_products', $legacy_baseline );
				}

				$batch_committed_baseline = intval( $legacy_baseline );
			}

			// Initialise a separate transient that tracks all handled items (added or filtered).
			// This counter is used by the stalled-feed watchdog logic so that heavy filtering
			// does not look like a stalled feed when the file itself is no longer growing.
			$handled_items_count = get_transient( 'wppfm_nr_of_handled_items' );

			if ( false === $handled_items_count ) {
				$handled_items_count = 0;
				set_transient(
					'wppfm_nr_of_handled_items',
					$handled_items_count
				);
			}

			// Incremental mode keeps only runtime state in metadata and loads product slices on demand.
			$batch = $this->maybe_prepare_incremental_batch_data( $batch, $batch_metadata, $properties_key, $feed_data );

			// @since 2.12.0
			$this->products_handled_in_batch = 0;

			// @since 2.12.0
			update_option( 'wppfm_batch_counter', get_option( 'wppfm_batch_counter', 0 ) + 1 );

			// When in foreground mode, increase the set time limit to enable larger feeds.
			// @since 2.11.0.
			if ( 'true' === get_option( 'wppfm_disabled_background_mode', 'false' ) && function_exists( 'wc_set_time_limit' ) ) {
				wc_set_time_limit( 30 * MINUTE_IN_SECONDS );
			}

			$initial_memory = function_exists( 'ini_get' ) ? ini_get( 'memory_limit' ) : 'unknown';

			do_action( 'wppfm_feed_processing_batch_activated', $feed_id, $initial_memory, count( $batch->data ) );

			// Expose batch product IDs for performance prefetch (avoids N+1 per-product lookups).
			$batch_product_ids = $this->extract_product_ids_from_batch_data( $batch->data );
			do_action( 'wppfm_feed_processing_batch_loaded', $feed_id, $batch_product_ids, $feed_data, $pre_data );

			foreach ( $batch->data as $key => $value ) {
				// Validate lock ownership before processing each item
				if ( ! $this->is_current_process_locked() ) {
					$this->commit_batch_products_added_counter(
						$feed_id,
						$batch_committed_baseline + $this->products_handled_in_batch
					);
					do_action( 'wppfm_feed_generation_message', $feed_id, 'Process lock was lost during item processing', 'ERROR' );
					$this->unlock_process();
					return false;
				}

				// Refresh lock based on time instead of count
				$current_time = time();
				if ( $current_time - $this->last_lock_refresh >= $this->lock_refresh_interval ) {
					$this->refresh_process_lock();
					$this->last_lock_refresh = $current_time;
				}

				// Only product queue items are counted as handled work so remaining-work totals
				// stay aligned with product-only discovery totals.
				if ( $this->is_product_queue_item( $value ) ) {
					$handled_items_count++;
				}

				// Persist the handled-items counter periodically to reduce database writes.
				if ( $handled_items_count % $this->progress_update_interval === 0 ) {
					set_transient( 'wppfm_nr_of_handled_items', $handled_items_count );
				}

				// If it's not an array, then it's a product id.
				if ( ! is_array( $value ) ) {
					$value = array( 'product_id' => $value );
				}

				// Run the task.
				$task = $this->task( $value, $feed_data, $feed_file_path, $pre_data, $channel_details, $relations_table );

				// If the product was added successfully, increment feed progress counters.
				if ( 'product added' === $task && array_key_exists( 'product_id', $value ) ) {
					$this->products_handled_in_batch++;
					$running_progress_count = $batch_committed_baseline + $this->products_handled_in_batch;

					// Update progress mirrors only every N products to reduce DB writes.
					if ( $running_progress_count % $this->progress_update_interval === 0 && function_exists( 'wppfm_update_feed_products_added_progress_mirrors' ) ) {
						wppfm_update_feed_products_added_progress_mirrors( $feed_id, $running_progress_count );
					}
				}

				unset( $batch->data[ $key ] ); // Remove this product from the queue.

				// Flush buffer periodically during batch processing to prevent data loss
				// and reduce memory usage (every 25 products as recommended in Step 5)
				if ( $this->products_handled_in_batch > 0 && $this->products_handled_in_batch % 25 === 0 ) {
					// Call flush on the processor if method exists
					if ( method_exists( $this, 'flush_file_buffer' ) ) {
						$this->flush_file_buffer();
					}
				}

				if ( $this->time_exceeded( $feed_id ) || $this->memory_exceeded( $feed_id ) ) {
					// Batch limits reached - flush buffer before breaking
					if ( method_exists( $this, 'flush_file_buffer' ) ) {
						$this->flush_file_buffer();
					}
					break;
				}
			}

			// Persist the final in-batch total before atomic commit so completion/progress
			// still see the full count when the last slice is not on an interval boundary.
			$running_progress_count = $batch_committed_baseline + $this->products_handled_in_batch;

			if ( $running_progress_count > 0 && function_exists( 'wppfm_persist_feed_products_added_progress_count' ) ) {
				wppfm_persist_feed_products_added_progress_count( $running_progress_count, $feed_id );
			}

			// Commit the authoritative per-feed counter once per batch slice.
			$this->commit_batch_products_added_counter( $feed_id, $running_progress_count );

			// Update or delete current batch.
			if ( ! empty( $batch->data ) ) {
				$message = sprintf( 'Updated the batch data in the site options store for the next batch. Using key %s', $batch->key );
				do_action( 'wppfm_feed_generation_message', $feed_id, $message ); // @since 2.35.0
				$this->update( $batch->key, $batch->data );
			} elseif ( $this->can_finalize_incremental_batch( $batch_metadata ) ) {
				// Queue is about to be cleared, preserve feed context so complete() can restore it even if a loopback fails.
				if ( method_exists( $this, 'preserve_feed_context_for_completion' ) ) {
					$this->preserve_feed_context_for_completion( $feed_id, $batch->key );
					do_action( 'wppfm_feed_generation_message', $feed_id, sprintf( 'Preserved feed context for completion (properties key: %s)', $batch->key ) );
				}

				$message = sprintf( 'No more products in the batch, so we can clear the batch data from the site options. Used key = %s', $batch->key );
				do_action( 'wppfm_feed_generation_message', $feed_id, $message ); // @since 2.35.0
				$this->delete( $batch->key );
				// Defer remove_id_from_feed_queue until complete() or terminal failure handlers so wppfm_clear_feed_process_data()
				// does not wipe batch metadata before temporary-file promotion runs.
			} else {
				// Keep incremental batches alive until discovery and footer processing are fully complete.
				$this->update( $batch->key, array( array( 'load_next_slice' => true ) ) );
			}
		} while ( ! $this->time_exceeded( $feed_id, true ) && ! $this->memory_exceeded( $feed_id ) && ! $this->is_queue_empty() );

		// Ensure buffer is flushed before ending batch to prevent data loss
		if ( method_exists( $this, 'flush_file_buffer' ) ) {
			$this->flush_file_buffer();
		}

		// Mirror the authoritative atomic counter into the legacy progress transient.
		if ( $feed_id && function_exists( 'wppfm_sync_feed_products_added_progress_transient' ) ) {
			wppfm_sync_feed_products_added_progress_transient( $feed_id );
		}

		// If the queue is not empty, restart the process.
		if ( ! $this->is_queue_empty() ) {
			update_option( 'wppfm_processed_products', implode( ',', $this->processed_products ) );
			
			// Ensure progress counters are up to date even if not on interval boundary.
			if ( $feed_id && function_exists( 'wppfm_sync_feed_products_added_progress_transient' ) ) {
				wppfm_sync_feed_products_added_progress_transient( $feed_id );
			}

			set_transient( 'wppfm_nr_of_handled_items', $handled_items_count );


			// @since 2.11.0
			// The feed process is still running so update the file grow monitor to prevent it from initiating a failed feed.
			WPPFM_Feed_Controller::update_file_grow_monitoring_timer();

			// Mark the intentional lock gap so startup and watchdog logic do not treat this handoff as an idle queue.
			WPPFM_Feed_Controller::mark_feed_handoff_pending( $feed_id );

			// Release lock before dispatch so the next async request can acquire it immediately.
			$this->unlock_process();
			$this->dispatch( $feed_id );
		} else {
			// Queue is empty - finalize the feed.
			do_action( 'wppfm_feed_generation_message', $feed_id, 'Queue is empty, preparing to finalize feed completion' );

			// Ensure feed context is available before calling end_batch.
			if ( method_exists( $this, 'ensure_feed_context_before_completion' ) ) {
				$this->ensure_feed_context_before_completion( $feed_id );
			} else {
				do_action( 'wppfm_feed_generation_message', $feed_id, 'Warning: ensure_feed_context_before_completion method not available', 'WARNING' );
			}

			$this->end_batch( $feed_id );
		}
	}

	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @param string $feed_id   The feed id.
	 *
	 * @return bool
	 */
	protected function memory_exceeded( $feed_id ) {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			do_action( 'wppfm_batch_memory_limit_exceeded', $feed_id, $current_memory, $memory_limit, $this->products_handled_in_batch );
			$return = true;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is intentionally namespaced by this process identifier to allow per-process memory checks.
		return apply_filters( $this->identifier . '_memory_exceeded', $return );
	}

	/**
	 * Get memory limit
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || - 1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return intval( $memory_limit ) * 1024 * 1024;
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30 seconds is common on shared hosting.
	 *
	 * @param string $feed_id   The feed id.
	 * @param bool   $report    Set to true if you want to report the time exceeded in the feed processing logging file. Default false.
	 *
	 * @since 3.9.0 added the $report parameter to prevent a double line in the feed processing logging.
	 * @return bool
	 */
	protected function time_exceeded( $feed_id, $report = false ) {
		$finish = $this->start_time + apply_filters( 'wppfm_default_time_limit', 30 );
		$return = false;

		if ( time() >= $finish ) {
			if ( $report ) {
				do_action( 'wppfm_batch_time_limit_exceeded', $feed_id, apply_filters( 'wppfm_default_time_limit', 30 ), $this->products_handled_in_batch );
			}

			$return = true;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is intentionally namespaced by this process identifier to allow per-process timeout checks.
		return apply_filters( $this->identifier . '_time_exceeded', $return );
	}

	/**
	 * Ends the current batch. Clean up the batch data and start a new feed if there is one in the feed queue.
	 *
	 * @since 2.10.0.
	 *
	 * @param   string $feed_id    The feed id.
	 * @param   string $status     Use "failed" for failing batches. The Default status is ready.
	 */
	protected function end_batch( $feed_id, $status = 'ready' ) {
		$this->clear_the_queue();

		if ( $feed_id && class_exists( 'WPPFM_Feed_Controller' ) ) {
			WPPFM_Feed_Controller::clear_feed_handoff_marker( $feed_id );
		}

		// Check for silent mode before any cleanup (used for failure email below).
		$was_running_silent = (bool) get_transient( 'wppfm_running_silent' );

		$this->complete();

		if ( 'failed' === $status && $feed_id ) {
			// Set the feed status to fail (6).
			$data_class = new WPPFM_Data();
			$data_class->update_feed_status( $feed_id, 6 );

			// Log the failure.
			$message = 'Batch ended prematurely.';
			do_action( 'wppfm_feed_generation_message', $feed_id, $message, 'ERROR' );

			// Terminal failure: notify immediately when running in automatic/silent mode.
			if ( $was_running_silent && class_exists( 'WPPFM_Email' ) ) {
				$notice_feed_id = ( 'unknown' !== (string) $feed_id && '' !== (string) $feed_id ) ? (string) $feed_id : '';
				if ( '' !== $notice_feed_id && function_exists( 'wppfm_cancel_deferred_feed_failure_notice' ) ) {
					wppfm_cancel_deferred_feed_failure_notice( $notice_feed_id );
				}
				WPPFM_Email::send_feed_failed_message(
					$notice_feed_id,
					array(
						'detected_at' => time(),
						'source'      => 'batch_aborted',
					)
				);
			}
		}

		// Release the processing lock only after completion/failure handling has settled queue metadata.
		$this->unlock_process();

		if ( ! WPPFM_Feed_Controller::feed_queue_is_empty() ) {
			do_action( 'wppfm_activated_next_feed', WPPFM_Feed_Controller::get_next_id_from_feed_queue() );

			$this->dispatch( WPPFM_Feed_Controller::get_next_id_from_feed_queue() ); // Start with the next feed in the queue.
		} else {
			// Queue is empty: automatic run is fully complete. Clear the silent flag.
			delete_transient( 'wppfm_running_silent' );
		}
	}

	/**
	 * Complete.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	public function complete() {
		delete_option( 'wppfm_processed_products' );
		delete_transient( 'wppfm_nr_of_processed_products' );
		delete_transient( 'wppfm_nr_of_handled_items' );

		// Note: wppfm_running_silent is cleared in end_batch() when the feed queue is empty,
		// so it persists across multiple feeds in one automatic run and allows failure emails.

		// Unscheduled the cron health check.
		$this->clear_scheduled_event();
		$this->unlock_process();
	}

	/**
	 * Schedule cron health check
	 *
	 * @access public
	 *
	 * @param mixed $schedules Schedules.
	 *
	 * @return mixed
	 */
	public function schedule_cron_health_check( $schedules ) {
		$interval = $this->get_cron_health_check_interval_minutes();

		// Adds every 5 minutes to the existing schedules.
		$schedules[ $this->identifier . '_cron_interval' ] = array(
			'interval' => MINUTE_IN_SECONDS * $interval,

			'display'  => sprintf(
				/* translators: %d: Cron check interval */
				_n(
					'Every %d minute',
					'Every %d minutes',
					$interval,
					'wp-product-feed-manager'
				),
				$interval
			),
		);

		return $schedules;
	}

	/**
	 * Logs why a cron health check exited without starting handle() (logging only).
	 *
	 * @param string $reason  Machine-readable skip reason.
	 * @param string $feed_id Optional feed id for context.
	 *
	 * @return void
	 */
	protected function log_cron_health_check_skip( $reason, $feed_id = '' ) {
		$feed_id = (string) $feed_id;

		if ( '' === $feed_id && class_exists( 'WPPFM_Feed_Controller' ) ) {
			$feed_id = WPPFM_Feed_Controller::get_active_batch_feed_id();
		}

		do_action(
			'wppfm_cron_health_check_skipped',
			$reason,
			$feed_id,
			$this->cron_recovery_hook_identifier
		);
	}

	/**
	 * Handle cron health check
	 *
	 * Restart the background process if not already running
	 * and data exists in the queue.
	 */
	public function handle_cron_health_check() {
		$pending_feed_ids = $this->get_pending_dispatch_feed_ids();
		$has_pending      = ! empty( $pending_feed_ids );

		// Background process already running — nothing to do.
		if ( $this->is_process_running() ) {
			$this->log_cron_health_check_skip( 'process_already_running' );
			$this->schedule_next_cron_health_check();
			exit;
		}

		// Defer only during the short handoff grace so loopback can acquire the lock first.
		// After grace expires, fall through and resume via handle() when batch data still exists.
		if ( class_exists( 'WPPFM_Feed_Controller' ) && WPPFM_Feed_Controller::background_process_handoff_grace_is_active() ) {
			$this->log_cron_health_check_skip( 'handoff_grace_active' );
			$this->schedule_next_cron_health_check();
			exit;
		}

		// No active process and no in-flight loopback — check whether there is work left.
		if ( $this->is_queue_empty() && ! $has_pending ) {
			// Nothing left to process; remove any pending health-check events.
			$this->clear_scheduled_event();
			$this->log_cron_health_check_skip( 'queue_empty_no_pending_dispatch' );
			exit;
		}

		// Add a small delay to avoid race conditions with other health checks
		usleep( wp_rand( 100000, 500000 ) ); // 100-500ms

		// Final check before starting
		if ( $this->is_process_running() ) {
			$this->log_cron_health_check_skip( 'process_running_after_delay' );
			$this->schedule_next_cron_health_check();
			exit;
		}

		if ( $this->is_queue_empty() ) {
			if ( $has_pending ) {
				foreach ( $pending_feed_ids as $feed_id ) {
					$this->clear_pending_dispatch_flag( $feed_id );
					do_action(
						'wppfm_feed_generation_message',
						$feed_id,
						'Pending dispatch marker cleared because no batch data was found.',
						'WARNING'
					);
				}
			}
			$this->clear_scheduled_event();
			$this->log_cron_health_check_skip( 'queue_empty_with_pending_dispatch_cleared' );
			exit;
		}

		if ( $has_pending ) {
			$feed_id = reset( $pending_feed_ids );
			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				'Pending dispatch detected. Cron health check is starting the background process.',
				'WARNING'
			);
		} elseif ( class_exists( 'WPPFM_Feed_Controller' ) ) {
			$resume_feed_id = WPPFM_Feed_Controller::get_active_batch_feed_id();

			if ( $resume_feed_id && WPPFM_Feed_Controller::should_resume_existing_batch( $resume_feed_id ) ) {
				do_action(
					'wppfm_feed_generation_message',
					$resume_feed_id,
					'Cron health check resuming existing batch after handoff grace via handle().',
					'WARNING'
				);
			}
		}

		$this->handle();

		// Keep a fallback health check queued while batches run in case loopback dispatch fails.
		$this->schedule_next_cron_health_check();

		exit;
	}

	/**
	 * Returns the health-check interval in minutes for this background process.
	 *
	 * @return int
	 */
	protected function get_cron_health_check_interval_minutes() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is intentionally namespaced by this process identifier to allow per-process cron interval overrides.
		$interval = apply_filters( $this->identifier . '_cron_interval', 5 );

		if ( property_exists( $this, 'cron_interval' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Dynamic hook is intentionally namespaced by this process identifier to allow per-process cron interval overrides.
			$interval = apply_filters( $this->identifier . '_cron_interval', $this->cron_interval_identifier );
		}

		return max( 1, intval( $interval ) );
	}

	/**
	 * Returns the health-check interval in seconds for this background process.
	 *
	 * @return int
	 */
	protected function get_cron_health_check_interval_seconds() {
		return $this->get_cron_health_check_interval_minutes() * MINUTE_IN_SECONDS;
	}

	/**
	 * Converts legacy recurring health-check cron entries to single events.
	 *
	 * Recurring events are rescheduled by WordPress before the hook runs, which can
	 * race with dispatch()/spawn_cron() and trigger "could_not_set" debug.log noise.
	 *
	 * @return void
	 */
	protected function maybe_convert_recurring_health_check_cron() {
		if ( ! function_exists( 'wp_get_scheduled_event' ) ) {
			return;
		}

		$event = wp_get_scheduled_event( $this->cron_hook_identifier );

		if ( ! $event || empty( $event->schedule ) ) {
			return;
		}

		$next_timestamp = max( time(), intval( $event->timestamp ) );

		wp_clear_scheduled_hook( $this->cron_hook_identifier );
		wp_schedule_single_event( $next_timestamp, $this->cron_hook_identifier );
	}

	/**
	 * Schedules the next single-fire health-check cron event when monitoring is still required.
	 *
	 * @return bool True when an event was scheduled or one is already pending.
	 */
	protected function schedule_next_cron_health_check() {
		if ( wp_next_scheduled( $this->cron_hook_identifier ) ) {
			return true;
		}

		$timestamp = time() + $this->get_cron_health_check_interval_seconds();

		return (bool) wp_schedule_single_event( $timestamp, $this->cron_hook_identifier );
	}

	/**
	 * Ensures a health-check cron exists after legacy recurring reschedule failures.
	 *
	 * @param WP_Error $result     Reschedule error from WordPress core.
	 * @param string   $hook       Hook that failed to reschedule.
	 * @param array    $event_data Stored cron event data.
	 *
	 * @return void
	 */
	public function handle_cron_reschedule_event_error( $result, $hook, $event_data ) {
		if ( $hook !== $this->cron_hook_identifier ) {
			return;
		}

		unset( $result, $event_data );

		$this->maybe_convert_recurring_health_check_cron();
		$this->schedule_next_cron_health_check();
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		$this->maybe_convert_recurring_health_check_cron();

		if ( wp_next_scheduled( $this->cron_hook_identifier ) ) {
			return;
		}

		// Single events avoid wp_reschedule_event() races on the shared cron option row.
		if ( ! wp_schedule_single_event( time(), $this->cron_hook_identifier ) ) {
			wppfm_show_wp_error( __( 'Could not schedule the cron event required to start the feed process. Please check if your wp cron is configured correctly and is running.', 'wp-product-feed-manager' ) );
		}
	}

	/**
	 * Clear scheduled event
	 */
	protected function clear_scheduled_event() {
		wp_clear_scheduled_hook( $this->cron_hook_identifier );

		// Also cancel any pending single-fire recovery event that may have been
		// queued by dispatch() but is no longer needed now the queue is empty.
		wp_clear_scheduled_hook( $this->cron_recovery_hook_identifier );
	}

	/**
	 * Retrieve feed IDs that have pending dispatch markers.
	 *
	 * @since 3.18.0
	 *
	 * @return array
	 */
	protected function get_pending_dispatch_feed_ids() {
		$pending = get_site_option( 'wppfm_pending_dispatch_feeds', array() );

		if ( ! is_array( $pending ) || empty( $pending ) ) {
			return array();
		}

		$ttl     = max( MINUTE_IN_SECONDS, apply_filters( 'wppfm_pending_dispatch_ttl', 3 * MINUTE_IN_SECONDS ) );
		$expiry  = time() - $ttl;
		$changed = false;

		foreach ( $pending as $feed_id => $created_at ) {
			$created_at = intval( $created_at );
			$transient  = get_site_transient( 'wppfm_pending_dispatch_' . $feed_id );

			if ( ! $transient || $created_at < $expiry ) {
				unset( $pending[ $feed_id ] );
				delete_site_transient( 'wppfm_pending_dispatch_' . $feed_id );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_site_option( 'wppfm_pending_dispatch_feeds', $pending );
		}

		return array_keys( $pending );
	}

	/**
	 * Clear the pending dispatch marker for the provided feed.
	 *
	 * @param int|string $feed_id Feed identifier.
	 *
	 * @since 3.18.0
	 */
	protected function clear_pending_dispatch_flag( $feed_id ) {
		if ( ! $feed_id ) {
			return;
		}

		delete_site_transient( 'wppfm_pending_dispatch_' . $feed_id );

		$pending = get_site_option( 'wppfm_pending_dispatch_feeds', array() );

		if ( is_array( $pending ) && isset( $pending[ $feed_id ] ) ) {
			unset( $pending[ $feed_id ] );
			update_site_option( 'wppfm_pending_dispatch_feeds', $pending );
		}
	}

	/**
	 * Extracts product IDs from batch queue data for prefetching.
	 * Skips non-product items (e.g. file_format_line, error_message).
	 *
	 * @param array $batch_data Batch data array from the queue.
	 *
	 * @return int[] Product post IDs.
	 */
	protected function extract_product_ids_from_batch_data( $batch_data ) {
		$product_ids = array();

		if ( ! is_array( $batch_data ) ) {
			return $product_ids;
		}

		foreach ( $batch_data as $item ) {
			$value = $item;
			if ( ! is_array( $value ) ) {
				$value = array( 'product_id' => $value );
			}
			if ( array_key_exists( 'product_id', $value ) && is_numeric( $value['product_id'] ) ) {
				$product_ids[] = (int) $value['product_id'];
			}
		}

		return array_values( array_unique( array_filter( $product_ids ) ) );
	}

	/**
	 * Loads active queue data from incremental metadata when the queue only has control items.
	 *
	 * @param stdClass $batch          Current batch object.
	 * @param array    $batch_metadata Batch metadata array.
	 * @param string   $properties_key Active batch option key.
	 * @param object   $feed_data      Feed data object.
	 *
	 * @return stdClass
	 */
	protected function maybe_prepare_incremental_batch_data( $batch, &$batch_metadata, $properties_key, $feed_data ) {
		if ( ! is_array( $batch_metadata ) || empty( $batch_metadata['incremental_state'] ) || ! is_array( $batch_metadata['incremental_state'] ) ) {
			return $batch;
		}

		$state = $batch_metadata['incremental_state'];
		if ( ! isset( $state['mode'] ) || 'incremental' !== $state['mode'] ) {
			return $batch;
		}

		$discovery_complete = ! empty( $state['discovery_complete'] );
		$should_load_slice  = $this->batch_needs_incremental_slice( $batch->data );

		if ( $discovery_complete && ! $should_load_slice ) {
			return $batch;
		}

		if ( ! $should_load_slice && ! $discovery_complete ) {
			return $batch;
		}

		$queries_class         = new WPPFM_Queries();
		$selected_categories   = apply_filters( 'wppfm_selected_categories', $this->get_category_selection_string_from_feed_data( $feed_data ), $feed_data->feedId );
		$include_variations    = isset( $feed_data->includeVariations ) && '1' === (string) $feed_data->includeVariations;
		$last_main_product_id  = isset( $state['last_main_product_id'] ) ? intval( $state['last_main_product_id'] ) : -1;
		$slice_limit           = isset( $state['slice_limit'] ) ? max( 1, absint( $state['slice_limit'] ) ) : max( 1, absint( apply_filters( 'wppfm_product_query_limit', 1000 ) ) );
		$discovery_result      = $queries_class->discover_post_ids_by_cursor( $selected_categories, $last_main_product_id, $include_variations, $slice_limit );
		$slice_item_ids        = isset( $discovery_result['item_ids'] ) && is_array( $discovery_result['item_ids'] ) ? $discovery_result['item_ids'] : array();
		$slice_item_ids        = array_values( array_unique( array_filter( array_map( 'absint', $slice_item_ids ) ) ) );
		$next_last_main_id     = isset( $discovery_result['last_main_product_id'] ) ? intval( $discovery_result['last_main_product_id'] ) : $last_main_product_id;
		$discovery_is_complete = ! empty( $discovery_result['discovery_complete'] );

		$slice_item_ids = apply_filters( 'wppfm_products_in_feed_queue', $slice_item_ids, $feed_data->feedId );
		if ( ! is_array( $slice_item_ids ) ) {
			$slice_item_ids = array();
		}
		$slice_item_ids = array_values( array_unique( array_filter( array_map( 'absint', $slice_item_ids ) ) ) );

		$reordered_slice_item_ids = apply_filters( 'wppfm_feed_ids_in_queue', $slice_item_ids, $feed_data->feedId );
		if ( is_array( $reordered_slice_item_ids ) ) {
			if ( count( $reordered_slice_item_ids ) === count( $slice_item_ids ) ) {
				// Keep this hook limited to product IDs so control lines cannot be reordered into invalid positions.
				$slice_item_ids = array_values( array_unique( array_filter( array_map( 'absint', $reordered_slice_item_ids ) ) ) );
			} else {
				do_action(
					'wppfm_feed_generation_message',
					$feed_data->feedId,
					'The wppfm_feed_ids_in_queue filter changed the active slice item count. The mutated slice was ignored to keep progress totals exact.',
					'WARNING'
				);
			}
		}

		$queue_items = array();
		$header_written = ! empty( $state['header_written'] );

		if ( ! $header_written && ( ! empty( $slice_item_ids ) || $discovery_is_complete ) ) {
			$header_line = isset( $state['header_string'] ) ? (string) $state['header_string'] : '';
			if ( '' !== $header_line ) {
				$queue_items[] = array( 'file_format_line' => $header_line );
			}
			$state['header_written'] = true;
			$header_written          = true;
		}

		foreach ( $slice_item_ids as $product_id ) {
			$queue_items[] = array( 'product_id' => $product_id );
		}

		$file_extension = isset( $state['file_extension'] ) ? $state['file_extension'] : ( function_exists( 'wppfm_get_file_type' ) ? wppfm_get_file_type( $feed_data->channel ) : 'xml' );
		// Ensure XML footer is appended in the same final slice when discovery completes.
		if ( $discovery_is_complete && $header_written && 'xml' === $file_extension && empty( $state['footer_written'] ) ) {
			$footer_line = isset( $state['footer_string'] ) ? (string) $state['footer_string'] : '';
			if ( '' !== $footer_line ) {
				$queue_items[] = array( 'file_format_line' => $footer_line );
				$state['footer_written'] = true;
			}
		}

		if ( empty( $queue_items ) && ! $discovery_is_complete ) {
			$queue_items[] = array( 'load_next_slice' => true );
		}

		$state['last_main_product_id'] = $next_last_main_id;
		$state['discovery_complete']   = $discovery_is_complete;
		$state['active_slice_loaded']  = ! empty( $slice_item_ids );
		$batch_metadata['incremental_state'] = $state;
		$batch->data = $queue_items;

		$this->update_batch_incremental_metadata( $properties_key, $batch_metadata );
		update_site_option( $properties_key, $batch->data );

		return $batch;
	}

	/**
	 * Persists updated batch metadata after incremental-state changes.
	 *
	 * @param string $properties_key Active batch option key.
	 * @param array  $batch_metadata Consolidated batch metadata payload.
	 *
	 * @return void
	 */
	private function update_batch_incremental_metadata( $properties_key, $batch_metadata ) {
		if ( ! is_string( $properties_key ) || '' === $properties_key ) {
			return;
		}

		if ( ! is_array( $batch_metadata ) ) {
			return;
		}

		update_site_option( 'wppfm_batch_metadata_' . $properties_key, $batch_metadata );
	}

	/**
	 * Determines whether an incremental batch can be safely finalized.
	 *
	 * @param array $batch_metadata Consolidated batch metadata payload.
	 *
	 * @return bool
	 */
	private function can_finalize_incremental_batch( $batch_metadata ) {
		if ( ! is_array( $batch_metadata ) || empty( $batch_metadata['incremental_state'] ) || ! is_array( $batch_metadata['incremental_state'] ) ) {
			return true;
		}

		$state = $batch_metadata['incremental_state'];
		if ( empty( $state['mode'] ) || 'incremental' !== $state['mode'] ) {
			return true;
		}

		$discovery_complete = ! empty( $state['discovery_complete'] );
		if ( ! $discovery_complete ) {
			return false;
		}

		$file_extension = isset( $state['file_extension'] ) ? strtolower( (string) $state['file_extension'] ) : 'xml';
		if ( 'xml' === $file_extension && empty( $state['footer_written'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines if the current batch contains only incremental control items.
	 *
	 * @param mixed $batch_data Current queue payload.
	 *
	 * @return bool
	 */
	protected function batch_needs_incremental_slice( $batch_data ) {
		if ( ! is_array( $batch_data ) || empty( $batch_data ) ) {
			return true;
		}

		if ( 1 === count( $batch_data ) && is_array( $batch_data[0] ) && ! empty( $batch_data[0]['load_next_slice'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines if a queue item represents product work (not control lines).
	 *
	 * @param mixed $item Queue entry to evaluate.
	 *
	 * @return bool
	 */
	private function is_product_queue_item( $item ) {
		if ( is_array( $item ) ) {
			return array_key_exists( 'product_id', $item ) && is_numeric( $item['product_id'] );
		}

		return is_numeric( $item );
	}

	/**
	 * Builds category selection string from feed category mapping.
	 *
	 * @param object $feed_data Feed data object.
	 *
	 * @return string
	 */
	protected function get_category_selection_string_from_feed_data( $feed_data ) {
		if ( ! isset( $feed_data->categoryMapping ) || empty( $feed_data->categoryMapping ) ) {
			return '';
		}

		$category_mapping = json_decode( $feed_data->categoryMapping );
		if ( empty( $category_mapping ) || ! is_array( $category_mapping ) ) {
			return '';
		}

		$category_ids = array();
		foreach ( $category_mapping as $category ) {
			if ( isset( $category->shopCategoryId ) ) {
				$category_ids[] = absint( $category->shopCategoryId );
			}
		}

		$category_ids = array_values( array_unique( array_filter( $category_ids ) ) );

		return implode( ', ', $category_ids );
	}


	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param   mixed  $item                Queue item to iterate over.
	 * @param   array  $feed_data           The feed data.
	 * @param   string $feed_file_path      The path to the feed file.
	 * @param   array  $pre_data            All required pre-data.
	 * @param   array  $channel_details     The channel details.
	 * @param   array  $relation_table      The relation table.
	 *
	 * @return mixed
	 */
	abstract protected function task( $item, $feed_data, $feed_file_path, $pre_data, $channel_details, $relation_table );

}
