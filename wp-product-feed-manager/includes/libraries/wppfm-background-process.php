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
	protected $queue_lock_time = 120; // Increased from 60 to 120 seconds to ensure it covers batch processing time

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
	 * Initiate a new background process
	 */
	public function __construct() {
		parent::__construct();

		$this->cron_hook_identifier     = $this->identifier . '_cron';
		$this->cron_interval_identifier = $this->identifier . '_cron_interval';
		$processed_products_option      = get_option( 'wppfm_processed_products' );
		$this->processed_products       = $processed_products_option ? explode( ',', $processed_products_option ) : array();

		add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_health_check' ) );
		add_filter( 'cron_schedules', array( $this, 'schedule_cron_health_check' ) ); // phpcs:disable WordPress.WP.CronInterval.ChangeDetected
	}

	/**
	 * Dispatch the feed generation process. Runs the parent dispatch method in the wppfm-async-request class.
	 *
	 * @param string $feed_id   The id of the feed.
	 */
	public function dispatch( $feed_id ) {
		// Schedule the cron health check.
		$this->schedule_event();

		// Perform the remote post.
		parent::dispatch( $feed_id );
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
	 * Save queue data.
	 *
	 * @param string $feed_id   The feed id.
	 *
	 * @return $this
	 */
	public function save( $feed_id ) {
		$key = $this->generate_key( $feed_id );

		if ( ! empty( $this->data ) ) {
			update_site_option( 'wppfm_background_process_key', $key );
			update_site_option( $key, $this->data );
			update_site_option( 'feed_data_' . $key, $this->feed_data );
			update_site_option( 'file_path_' . $key, $this->file_path );
			update_site_option( 'pre_data_' . $key, $this->pre_data );
			update_site_option( 'channel_details_' . $key, $this->channel_details );
			update_site_option( 'relations_table_' . $key, $this->relations_table );
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
			update_site_option( 'wppfm_background_process_key', $key );
			update_site_option( $key, $data );
		}
	}

	/**
	 * Delete queue and properties stored in the options table
	 *
	 * @param string $key Key.
	 */
	public function delete( $key ) {
		delete_site_option( 'wppfm_background_process_key' );
		delete_site_option( $key );
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

		$background_mode_disabled = get_option( 'wppfm_disabled_background_mode', 'false' );

		if ( 'false' === $background_mode_disabled && $this->is_process_running() ) {
			// Background process already running.
			wp_die();
		}

		if ( $this->is_queue_empty() ) {
			$feed_id = filter_input( INPUT_GET, 'feed_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$message = 'Tried to start a new batch but the queue is empty!';
			do_action( 'wppfm_feed_generation_message', $feed_id, $message, 'ERROR' );
			// No data to process.
			wp_die();
		}

		if ( 'false' === $background_mode_disabled ) {
			check_ajax_referer( $this->identifier, 'nonce' );
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
	 * in a background process.
	 */
	public function is_process_running() {
		$lock = get_site_transient( $this->identifier . '_process_lock' );
		
		if ( ! $lock ) {
			return false;
		}

		// Parse the lock value to get process info
		$lock_parts = explode( '_', $lock );
		if ( count( $lock_parts ) >= 3 ) {
			$lock_pid = intval( $lock_parts[1] );
			$current_pid = getmypid();
			
			// If the lock PID doesn't match current PID, check if that process is still alive
			if ( $lock_pid !== $current_pid ) {
				// On Linux/Unix systems, check if the process is still running
				if ( function_exists( 'posix_kill' ) ) {
					if ( ! posix_kill( $lock_pid, 0 ) ) {
						// Process is dead, clear the stale lock
						delete_site_transient( $this->identifier . '_process_lock' );
						return false;
					}
				}
				
				// If we can't verify process status, assume it's running
				return true;
			}
		}

		return true;
	}

	/**
	 * Check if the current process still owns the lock
	 *
	 * @return bool
	 */
	protected function is_current_process_locked() {
		$lock = get_site_transient( $this->identifier . '_process_lock' );
		
		if ( ! $lock ) {
			return false;
		}

		$lock_parts = explode( '_', $lock );
		if ( count( $lock_parts ) >= 3 ) {
			$lock_pid = intval( $lock_parts[1] );
			$current_pid = getmypid();
			
			return $lock_pid === $current_pid;
		}

		return false;
	}

	/**
	 * Lock process
	 *
	 * Lock the process so that multiple instances can't run simultaneously.
	 * Override if applicable, but the duration should be greater than that
	 * defined in the time_exceeded() method.
	 */
	protected function lock_process() {
		$this->start_time = time(); // Set start time of a current process.

		$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 120; // 2 minutes
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

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
				wp_die( 'Could not acquire process lock after ' . $max_attempts . ' attempts' );
			}

			// Try to acquire the lock
			$lock_value = microtime() . '_' . getmypid() . '_' . wp_rand( 1000, 9999 );
			$lock_acquired = set_site_transient( $this->identifier . '_process_lock', $lock_value, $lock_duration );

			if ( $lock_acquired ) {
				// Verify we still have the lock (double-check)
				$current_lock = get_site_transient( $this->identifier . '_process_lock' );
				if ( $current_lock !== $lock_value ) {
					// Someone else got the lock, try again
					$lock_acquired = false;
					$attempt++;
					continue;
				}
			} else {
				$attempt++;
			}
		}

		if ( ! $lock_acquired ) {
			wp_die( 'Failed to acquire process lock' );
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
		delete_site_transient( $this->identifier . '_process_lock' );

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
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

		$current_lock = get_site_transient( $this->identifier . '_process_lock' );
		if ( $current_lock ) {
			// Extend the lock duration
			return set_site_transient( $this->identifier . '_process_lock', $current_lock, $lock_duration );
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
	 * Handle
	 *
	 * Pass each queue item to the task handler, while remaining
	 * within server memory and time limit constraints.
	 *
	 * @return   void|bool
	 */
	protected function handle() {
		$this->lock_process();

		do {
			// Validate that we still own the lock before processing each batch
			if ( ! $this->is_current_process_locked() ) {
				do_action( 'wppfm_feed_generation_message', 'unknown', 'Process lock was lost during processing', 'ERROR' );
				$this->unlock_process();
				return false;
			}

			// Refresh lock at the start of each batch
			$this->refresh_process_lock();

			$batch = $this->get_batch();

			if ( ! $batch ) { // @since 2.10.0
				$feed_id = filter_input( INPUT_GET, 'feed_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$message = 'Could not get the next batch data!';
				do_action( 'wppfm_feed_generation_message', $feed_id, $message, 'ERROR' );
				$this->end_batch( 'unknown', 'failed' );
				return false;
			}

			$properties_key         = get_site_option( 'wppfm_background_process_key' );
			$total_handled_products = get_transient( 'wppfm_nr_of_processed_products' );

			if ( false === $total_handled_products ) {
				$total_handled_products = 0;
				set_transient( 'wppfm_nr_of_processed_products', $total_handled_products );
			}

			// @since 2.10.0
			if ( ! $properties_key ) {
				$feed_id = filter_input( INPUT_GET, 'feed_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$message = 'Tried to get the next batch but the wppfm_background_process_key is empty.';
				do_action( 'wppfm_feed_generation_message', $feed_id, $message, 'ERROR' );
				$this->end_batch( 'unknown', 'failed' );
				return false;
			} else {
				$feed_data = get_site_option( 'feed_data_' . $properties_key );
				// phpcs:ignore
				do_action( 'wppfm_feed_generation_message', $feed_data->feedId, 'Feed handle has been started. Async request has been passed through.' ); // @since 2.40.0
			}

			// If it's a Merchant Promotions Feed, refill the batch data with a dummy product id.
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( '3' === $feed_data->feedTypeId ) {
				$batch->data = array(
					'product_id' => '0',
				);
			}

			$feed_file_path  = get_site_option( 'file_path_' . $properties_key );
			$pre_data        = get_site_option( 'pre_data_' . $properties_key );
			$channel_details = get_site_option( 'channel_details_' . $properties_key );
			$relations_table = get_site_option( 'relations_table_' . $properties_key );

			// @since 2.34.0
			if ( ! empty( $feed_data ) && property_exists( $feed_data, 'feedId' ) ) {
				// phpcs:ignore
				$feed_id = $feed_data->feedId;
			} else {
				$message = sprintf( 'Tried to get the next batch the feed data could not be loaded correctly. Used property key: %s', $properties_key );
				do_action( 'wppfm_feed_generation_message', 'unknown', $message, 'ERROR' );
				$this->end_batch( 'unknown', 'failed' );
				return false;
			}

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

			foreach ( $batch->data as $key => $value ) {
				// Validate lock ownership before processing each item
				if ( ! $this->is_current_process_locked() ) {
					do_action( 'wppfm_feed_generation_message', $feed_id, 'Process lock was lost during item processing', 'ERROR' );
					$this->unlock_process();
					return false;
				}

				// Refresh lock every 10 items to prevent expiration
				if ( $this->products_handled_in_batch > 0 && $this->products_handled_in_batch % 10 === 0 ) {
					$this->refresh_process_lock();
				}

				// If it's not an array, then it's a product id.
				if ( ! is_array( $value ) ) {
					$value = array( 'product_id' => $value );
				}

				// Prevent doubles in the feed.
				// phpcs:disable WordPress.PHP.StrictInArray.MissingTrueStrict
				if ( array_key_exists( 'product_id', $value ) && in_array( $value['product_id'], $this->processed_products ) ) {
					unset( $batch->data[ $key ] ); // Remove this product from the queue.
					continue;
				}

				// Run the task.
				$task = $this->task( $value, $feed_data, $feed_file_path, $pre_data, $channel_details, $relations_table );

				// If there was no failure and the id is known, add the product id to the list of processed products.
				if ( 'product added' === $task && array_key_exists( 'product_id', $value ) ) {
					$this->products_handled_in_batch++;
					$total_handled_products++;
					set_transient( 'wppfm_nr_of_processed_products', $total_handled_products );
					$this->processed_products[] = $value['product_id'];
				}

				unset( $batch->data[ $key ] ); // Remove this product from the queue.

				if ( $this->time_exceeded( $feed_id ) || $this->memory_exceeded( $feed_id ) ) {
					// Batch limits reached.
					$this->delete( $batch->key );
					break;
				}
			}

			// Update or delete current batch.
			if ( ! empty( $batch->data ) ) {
				$message = sprintf( 'Updated the batch data in the site options store for the next batch. Using key %s', $batch->key );
				do_action( 'wppfm_feed_generation_message', $feed_id, $message ); // @since 2.35.0
				$this->update( $batch->key, $batch->data );
			} else {
				$message = sprintf( 'No more products in the batch, so we can clear the batch data from the site options. Used key = %s', $batch->key );
				do_action( 'wppfm_feed_generation_message', $feed_id, $message ); // @since 2.35.0
				$this->delete( $batch->key );
				WPPFM_Feed_Controller::remove_id_from_feed_queue( $feed_id );
			}
		} while ( ! $this->time_exceeded( $feed_id, true ) && ! $this->memory_exceeded( $feed_id ) && ! $this->is_queue_empty() );

		$this->unlock_process();

		// If the queue is not empty, restart the process.
		if ( ! $this->is_queue_empty() ) {
			update_option( 'wppfm_processed_products', implode( ',', $this->processed_products ) );

			// @since 2.3.0
			do_action( 'wppfm_activated_next_batch', $feed_id );

			// @since 2.11.0
			// The feed process is still running so update the file grow monitor to prevent it from initiating a failed feed.
			WPPFM_Feed_Controller::update_file_grow_monitoring_timer();

			$this->dispatch( $feed_id );
		} else {

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

		$this->complete(); // Complete processing this feed.

		if ( 'failed' === $status && $feed_id ) {
			// Set the feed status to fail (6).
			$data_class = new WPPFM_Data();
			$data_class->update_feed_status( $feed_id, 6 );

			// Log the failure.
			$message = 'Batch ended prematurely.';
			do_action( 'wppfm_feed_generation_message', $feed_id, $message, 'ERROR' );
		}

		if ( ! WPPFM_Feed_Controller::feed_queue_is_empty() ) {
			do_action( 'wppfm_activated_next_feed', WPPFM_Feed_Controller::get_next_id_from_feed_queue() );

			$this->dispatch( WPPFM_Feed_Controller::get_next_id_from_feed_queue() ); // Start with the next feed in the queue.
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
		$interval = apply_filters( $this->identifier . '_cron_interval', 5 );

		if ( property_exists( $this, 'cron_interval' ) ) {
			$interval = apply_filters( $this->identifier . '_cron_interval', $this->cron_interval_identifier );
		}

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
	 * Handle cron health check
	 *
	 * Restart the background process if not already running
	 * and data exists in the queue.
	 */
	public function handle_cron_health_check() {
		// Be more conservative about spawning new processes
		if ( $this->is_process_running() ) {
			// Background process already running.
			exit;
		}

		// Double-check the queue isn't empty before starting
		if ( $this->is_queue_empty() ) {
			// No data to process.
			$this->clear_scheduled_event();
			exit;
		}

		// Add a small delay to avoid race conditions with other health checks
		usleep( wp_rand( 100000, 500000 ) ); // 100-500ms

		// Final check before starting
		if ( $this->is_process_running() || $this->is_queue_empty() ) {
			exit;
		}

		$this->handle();

		exit;
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			if ( ! wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier ) ) {
				wppfm_show_wp_error( __( 'Could not schedule the cron event required to start the feed process. Please check if your wp cron is configured correctly and is running.', 'wp-product-feed-manager' ) );
			}
		}
	}

	/**
	 * Clear scheduled event
	 */
	protected function clear_scheduled_event() {
		$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
		}
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
