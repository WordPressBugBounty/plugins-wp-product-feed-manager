<?php

/**
 * WP Product Feed Controller Class.
 *
 * @package WP Product Feed Manager/Application/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Feed_Controller' ) ) :

	/**
	 * Feed Controller Class.
	 *
	 * @since 1.10.0
	 */
	class WPPFM_Feed_Controller {

		/**
		 * Removes a feed id from the feed queue.
		 *
		 * @param string $feed_id the id of the feed to remove.
		 */
		public static function remove_id_from_feed_queue( $feed_id ) {
			$feed_queue = self::get_feed_queue();
			$key        = array_search( $feed_id, $feed_queue, true );

			if ( false !== $key ) {
				unset( $feed_queue[ $key ] );
				$feed_queue = array_values( $feed_queue ); // resort after unset
				update_site_option( 'wppfm_feed_queue', $feed_queue );

				if ( self::feed_queue_is_empty() ) {
					wppfm_clear_feed_process_data();
				}
			}
		}

		/**
		 * Adds a feed id to the feed queue.
		 *
		 * @param string $feed_id the id of the feed to add.
		 */
		public static function add_id_to_feed_queue( $feed_id ) {
			$feed_queue = self::get_feed_queue();

			if ( ! in_array( $feed_id, $feed_queue, true ) ) {
				$feed_queue[] = $feed_id;
				update_site_option( 'wppfm_feed_queue', $feed_queue );
			}
		}

		/**
		 * Gets the next feed id from the feed queue.
		 *
		 * @return string with the next feed id in the feed queue. False if no id is found.
		 */
		public static function get_next_id_from_feed_queue() {
			$feed_queue = self::get_feed_queue();

			return count( $feed_queue ) > 0 ? $feed_queue[0] : false;
		}

		/**
		 * Empties the feed queue.
		 */
		public static function clear_feed_queue() {
			delete_option( 'wppfm_feed_queue' );
			update_site_option( 'wppfm_feed_queue', array() );
		}

		/**
		 * Checks if the feed queue is empty.
		 *
		 * @return bool true if the feed is empty.
		 */
		public static function feed_queue_is_empty() {
			$queue = self::get_feed_queue();

			return count( $queue ) < 1;
		}

		/**
		 * Returns the number of product ids that are still in the product queue.
		 *
		 * @since 2.3.0
		 * @since 3.13.0 - Added a check if the $ids_in_product_queue is set.
		 * @return int number of product ids still in the product queue.
		 */
		public static function nr_ids_remaining_in_product_queue() {
			$key = get_site_option( 'wppfm_background_process_key' );
			$ids_in_product_queue = get_site_option( $key );

			return $ids_in_product_queue ? count( $ids_in_product_queue ) - 1 : 0; // The last line in the product queue is the feed closure line, so it needs to be subtracted from the count.
		}

		/**
		 * Sets the background_process_is_running option.
		 *
		 * @since 3.11.0 switched from using an option to using a transient to store the process status.
		 * @since 3.18.0 records the process start timestamp to bridge the lock acquisition grace period.
		 * @param bool $set required setting. Default false.
		 */
		public static function set_feed_processing_flag( $set = false ) {
			$status = false !== $set ? 'true' : 'false';
			set_site_transient( 'wppfm_background_process_is_active', $status, DAY_IN_SECONDS );

			if ( 'true' === $status ) {
				// @since 3.18.0 recorded process start time to bridge the lock acquisition grace period.
				set_site_transient( 'wppfm_background_process_started_at', time(), DAY_IN_SECONDS );
			} else {
				// @since 3.18.0 ensure any previously recorded start timestamp is cleared when processing stops.
				delete_site_transient( 'wppfm_background_process_started_at' );
			}
		}

		/**
		 * Get the background_process_is_active status option.
		 *
		 * @since 3.11.0 switched from using an option to using a transient to store the process status.
		 * @since 3.18.0 requires a valid background-process lock or a short grace period after startup.
		 * @return bool true if the process is still running.
		 */
		public static function feed_is_processing() {
			$status   = get_site_transient( 'wppfm_background_process_is_active' );
			$use_lock = apply_filters( 'wppfm_use_lock_for_processing_flag', true );

			if ( 'true' !== $status ) {
				return false;
			}

			if ( ! $use_lock ) {
				return true;
			}

			$process_lock = get_site_transient( 'wppfm_feed_generation_process_process_lock' );

			if ( $process_lock ) {
				return true;
			}

			$start_timestamp = intval( get_site_transient( 'wppfm_background_process_started_at' ) );
			$grace_period    = apply_filters( 'wppfm_feed_processing_lock_grace_seconds', MINUTE_IN_SECONDS );

			if ( $start_timestamp > 0 && ( time() - $start_timestamp ) <= max( 10, intval( $grace_period ) ) ) {
				// @since 3.18.0 apply a grace period after start to allow the background worker to obtain the lock.
				return true;
			}

			// @since 3.18.0+ Heartbeat fallback for environments where transients may be evicted early.
			// This helps prevent overlapping workers and watchdog restarts when a process is still active but the lock transient vanished.
			if ( self::background_process_heartbeat_is_fresh() ) {
				return true;
			}

			return false;
		}

		/**
		 * Returns the durable background-process heartbeat payload (if present).
		 *
		 * @since 3.18.0
		 *
		 * @return array|null
		 */
		public static function get_background_process_heartbeat() {
			$heartbeat = get_site_option( 'wppfm_feed_generation_process_process_heartbeat' );

			return is_array( $heartbeat ) ? $heartbeat : null;
		}

		/**
		 * Returns true when the durable heartbeat indicates a process is likely still active.
		 *
		 * @since 3.18.0
		 *
		 * @return bool
		 */
		public static function background_process_heartbeat_is_fresh() {
			$heartbeat = self::get_background_process_heartbeat();

			if ( ! $heartbeat || empty( $heartbeat['ts'] ) ) {
				return false;
			}

			$ttl = apply_filters( 'wppfm_feed_generation_process_heartbeat_ttl', 10 * MINUTE_IN_SECONDS );
			$ttl = max( 60, intval( $ttl ) );

			return ( time() - intval( $heartbeat['ts'] ) ) <= $ttl;
		}

		/**
		 * Checks if a running feed size is still growing, in order to identify a failing feed process.
		 *
		 * @since 2.2.0.
		 *
		 * @param   string $feed_file String with the full path and name of the feed file.
		 *
		 * @return  boolean false if the feed still grows, true if it stopped growing for a certain time.
		 */
		public static function feed_processing_failed( $feed_file ) {

			if ( '' === $feed_file ) {
				return null;
			}

			$monitor_data = self::get_feed_growth_monitor_data( $feed_file );
			$prev_feed_size = $monitor_data['size'];
			$prev_feed_time_stamp = $monitor_data['timestamp'];
			$prev_processed_products = $monitor_data['processed'];
			$bonus_delay = $monitor_data['bonus_delay'];

			$curr_feed_size = file_exists( $feed_file ) ? filesize( $feed_file ) : false;

			// If the file does not exist, return true.
			if ( false === $curr_feed_size ) {
				delete_transient( 'wppfm_feed_file_size' ); // Reset the counter.
				return true;
			}

			$current_processed_products = self::get_processed_products_counter();

			$feed_grew     = $curr_feed_size > $prev_feed_size;
			$products_grew = $current_processed_products > $prev_processed_products;

			if ( $feed_grew || $products_grew ) {
				$bonus = $products_grew ? apply_filters( 'wppfm_failed_detection_alpha', 60, $feed_file ) : 0;

				self::persist_feed_growth_monitor_data(
					array(
						'size'        => $curr_feed_size,
						'timestamp'   => time(),
						'file'        => $feed_file,
						'processed'   => $current_processed_products,
						'bonus_delay' => $bonus,
					)
				);

				return false;
			}

			$base_delay = apply_filters( 'wppfm_failed_detection_base_delay', WPPFM_DELAY_FAILED_LABEL, $feed_file );
			$base_delay = max( 0, intval( $base_delay ) );
			$bonus_delay = max( 0, intval( $bonus_delay ) );
			$delay = $base_delay + $bonus_delay;

			// And the delay time has passed.
			if ( (int) $prev_feed_time_stamp + $delay < time() ) {
				delete_transient( 'wppfm_feed_file_size' ); // Reset the counter.
				return true;
			}

			return false;
		}

		/**
		 * Updates the timer that is used as reference to monitor if a file is growing during the feed production process.
		 *
		 * @since 2.11.0
		 */
		public static function update_file_grow_monitoring_timer() {
			// Get the current monitor data.
			$grow_monitor_array = get_transient( 'wppfm_feed_file_size' );

			if ( ! $grow_monitor_array ) { // The wppfm_feed_file_size is not set in the non-background mode.
				return;
			}

			$grow_monitor_data = explode( '|', $grow_monitor_array );
			$prev_size         = isset( $grow_monitor_data[0] ) ? intval( $grow_monitor_data[0] ) : 0;
			$tracked_file      = $grow_monitor_data[2] ?? '';
			$prev_processed    = isset( $grow_monitor_data[3] ) ? intval( $grow_monitor_data[3] ) : self::get_processed_products_counter();
			$bonus_delay       = isset( $grow_monitor_data[4] ) ? intval( $grow_monitor_data[4] ) : 0;

			// Reset the timer part of the monitor while keeping the other data intact.
			self::persist_feed_growth_monitor_data(
				array(
					'size'        => $prev_size,
					'timestamp'   => time(),
					'file'        => $tracked_file,
					'processed'   => $prev_processed,
					'bonus_delay' => $bonus_delay,
				)
			);
		}

		/**
		 * Returns the current feed queue.
		 *
		 * @return array with feed ids in the queue or an empty array.
		 */
		protected static function get_feed_queue() {
			return get_site_option( 'wppfm_feed_queue', array() );
		}

		/**
		 * Returns the processed products counter from the transient store.
		 *
		 * @since 3.18.0
		 * @return int
		 */
		private static function get_processed_products_counter() {
			$processed_products = get_transient( 'wppfm_nr_of_processed_products' );

			return false === $processed_products ? 0 : intval( $processed_products );
		}

		/**
		 * Retrieves or initializes the feed growth monitor data.
		 *
		 * @since 3.18.0
		 *
		 * @param string $feed_file The feed file currently being processed.
		 *
		 * @return array
		 */
		private static function get_feed_growth_monitor_data( $feed_file ) {
			$transient_value = get_transient( 'wppfm_feed_file_size' );

			if ( false === $transient_value ) {
				$data = array(
					'size'        => 0,
					'timestamp'   => time(),
					'file'        => $feed_file,
					'processed'   => self::get_processed_products_counter(),
					'bonus_delay' => 0,
				);
				self::persist_feed_growth_monitor_data( $data );

				return $data;
			}

			$stored = explode( '|', $transient_value );

			// Prepare a normalized dataset (handles both legacy 3-part payloads and the new 5-part payloads).
			$data = array(
				'size'        => isset( $stored[0] ) ? intval( $stored[0] ) : 0,
				'timestamp'   => isset( $stored[1] ) ? intval( $stored[1] ) : time(),
				'file'        => $stored[2] ?? $feed_file,
				'processed'   => isset( $stored[3] ) ? intval( $stored[3] ) : self::get_processed_products_counter(),
				'bonus_delay' => isset( $stored[4] ) ? intval( $stored[4] ) : 0,
			);

			if ( $feed_file !== $data['file'] && '' !== $feed_file ) {
				$data['size']        = 0;
				$data['timestamp']   = time();
				$data['file']        = $feed_file;
				$data['processed']   = self::get_processed_products_counter();
				$data['bonus_delay'] = 0;
				self::persist_feed_growth_monitor_data( $data );
			}

			return $data;
		}

		/**
		 * Persists the feed growth monitor data structure into the transient store.
		 *
		 * @since 3.18.0
		 *
		 * @param array $data Normalised data set.
		 */
		private static function persist_feed_growth_monitor_data( $data ) {
			$payload = sprintf(
				'%d|%d|%s|%d|%d',
				max( 0, intval( $data['size'] ?? 0 ) ),
				max( 0, intval( $data['timestamp'] ?? time() ) ),
				$data['file'] ?? '',
				max( 0, intval( $data['processed'] ?? 0 ) ),
				max( 0, intval( $data['bonus_delay'] ?? 0 ) )
			);

			set_transient( 'wppfm_feed_file_size', $payload, WPPFM_TRANSIENT_LIVE );
		}
	}

endif;
