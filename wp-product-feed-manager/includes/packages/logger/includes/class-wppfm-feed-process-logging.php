<?php

/* * ******************************************************************
 * Version 1.0
 * Package: Logger
 * Modified: 18-10-2019
 * Copyright 2019 Accentio. All rights reserved.
 * License: None
 * By: Michel Jongbloed
 * ****************************************************************** */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPPFM_Feed_Process_Logging Class
 */
class WPPFM_Feed_Process_Logging {

	/**
	 * Transient key for the feed id currently writing to the process log.
	 */
	const ACTIVE_PROCESS_LOG_FEED_TRANSIENT = 'wppfm_active_process_log_feed_id';

	/**
	 * Returns the transient key that stores the last feed-start timestamp for a feed id.
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return string
	 */
	private static function get_last_started_transient_key( $feed_id ) {
		return 'wppfm_feed_log_last_started_' . sanitize_key( (string) $feed_id );
	}

	/**
	 * Returns the append window in seconds for reusing an existing log file for the same feed.
	 *
	 * @return int
	 */
	private static function get_log_append_window_seconds() {
		return max( MINUTE_IN_SECONDS, intval( apply_filters( 'wppfm_feed_log_append_window_seconds', HOUR_IN_SECONDS ) ) );
	}

	/**
	 * Returns true when a new start should append to the current log instead of clearing it.
	 *
	 * We only append when the same feed restarts within a recent time window so mid-run restarts
	 * preserve their earlier evidence, while older unrelated runs still get a fresh log file.
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return bool
	 */
	private static function should_append_to_existing_log( $feed_id ) {
		$last_started = get_transient( self::get_last_started_transient_key( $feed_id ) );

		if ( false === $last_started ) {
			return false;
		}

		return ( time() - intval( $last_started ) ) <= self::get_log_append_window_seconds();
	}

	/**
	 * Stores the current feed-start timestamp for restart-aware log initialization.
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return void
	 */
	private static function record_feed_log_started_at( $feed_id ) {
		set_transient(
			self::get_last_started_transient_key( $feed_id ),
			time(),
			self::get_log_append_window_seconds()
		);
	}

	/**
	 * Returns the active client request id for a feed (if any).
	 *
	 * The feed editor can send a `client_request_id` that we store in a transient when a generation
	 * starts. We use it here to prefix log messages so browser console logs and server logs can be
	 * cross-referenced reliably.
	 *
	 * @since 3.21.0
	 *
	 * @param string $feed_id
	 *
	 * @return string
	 */
	private static function get_client_request_id_for_feed( $feed_id ) {
		$req = get_transient( 'wppfm_client_request_id_' . $feed_id );
		return is_string( $req ) ? $req : '';
	}

	/**
	 * Remembers which feed id owns the current process log session.
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return void
	 */
	public static function set_active_process_log_feed_id( $feed_id ) {
		$feed_id = (string) absint( $feed_id );

		if ( '' === $feed_id || '0' === $feed_id ) {
			return;
		}

		set_transient( self::ACTIVE_PROCESS_LOG_FEED_TRANSIENT, $feed_id, HOUR_IN_SECONDS );
	}

	/**
	 * Clears the active process-log feed id when a run ends.
	 *
	 * @return void
	 */
	public static function clear_active_process_log_feed_id() {
		delete_transient( self::ACTIVE_PROCESS_LOG_FEED_TRANSIENT );
	}

	/**
	 * Returns the feed id for the active process log session, if any.
	 *
	 * @return string
	 */
	public static function get_active_process_log_feed_id() {
		$feed_id = get_transient( self::ACTIVE_PROCESS_LOG_FEED_TRANSIENT );

		return is_string( $feed_id ) || is_numeric( $feed_id ) ? (string) absint( $feed_id ) : '';
	}

	/**
	 * Resolves a feed id for routing log lines to feed-{id}-processing.log.
	 *
	 * Avoids the feed-unknown-processing.log bucket unless no feed can be determined.
	 *
	 * @param string $feed_id Raw feed id from the caller (may be empty or "unknown").
	 *
	 * @return string Numeric feed id, or empty when unknown.
	 */
	public static function resolve_log_feed_id( $feed_id ) {
		$feed_id = trim( (string) $feed_id );

		if ( '' !== $feed_id && 'unknown' !== strtolower( $feed_id ) ) {
			$normalized = (string) absint( $feed_id );

			if ( '' !== $normalized && '0' !== $normalized ) {
				return $normalized;
			}
		}

		$active_log_feed = self::get_active_process_log_feed_id();

		if ( '' !== $active_log_feed ) {
			return $active_log_feed;
		}

		if ( class_exists( 'WPPFM_Feed_Controller' ) ) {
			$batch_feed = WPPFM_Feed_Controller::get_active_batch_feed_id();

			if ( '' !== $batch_feed ) {
				return (string) absint( $batch_feed );
			}
		}

		$pending = get_site_option( 'wppfm_pending_dispatch_feeds', array() );

		if ( is_array( $pending ) && ! empty( $pending ) ) {
			$first_pending = reset( $pending );

			if ( is_string( $first_pending ) || is_numeric( $first_pending ) ) {
				$normalized_pending = (string) absint( $first_pending );

				if ( '' !== $normalized_pending && '0' !== $normalized_pending ) {
					return $normalized_pending;
				}
			}
		}

		return '';
	}

	/**
	 * Builds the plugin identity line written at the top of each log session.
	 *
	 * @return string
	 */
	private static function get_plugin_log_identity_line() {
		$plugin_type = defined( 'WPPFM_PLUGIN_VERSION_ID' ) ? (string) WPPFM_PLUGIN_VERSION_ID : 'unknown';
		$version     = defined( 'WPPFM_VERSION_NUM' ) ? (string) WPPFM_VERSION_NUM : 'unknown';

		if ( 'unknown' === $version && defined( 'WPPFM_PLUGIN_DIR' ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_data = get_plugin_data( WPPFM_PLUGIN_DIR . 'wp-product-feed-manager.php', false, false );
			$version     = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : 'unknown';
		}

		return sprintf(
			'%sWooCommerce Product Feed Manager — plugin_type=%s, plugin_version=%s',
			self::generate_log_tag( 'MESSAGE' ),
			sanitize_key( $plugin_type ),
			sanitize_text_field( $version )
		);
	}

	/**
	 * Initiates the logging of a feed process and writes a header to the logging file
	 *
	 * @since 2.7.0
	 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
	 * @param string $feed_id
	 * @param bool   $silent identifies if the feed process has been started manually or automatically
	 */
	public static function initiate_feed_process_logging( $feed_id, $silent ) {
		$wp_filesystem = wppfm_get_wp_filesystem();

		WPPFM_Logging_Folders::make_logs_folder();

		$log_file_name         = self::generate_log_file_name( $feed_id );
		$background_processing = 'true' === get_option( 'wppfm_disabled_background_mode', 'false' ) ? 'foreground' : 'background';
		$starter               = $silent ? 'through a cron' : 'manually';
		$client_request_id     = self::get_client_request_id_for_feed( $feed_id );
		$client_request_tag    = '' !== $client_request_id ? sprintf( ' (client_request_id=%s)', $client_request_id ) : '';
		$log_header            = sprintf(
			'%sGenerating feed %s %s initiated in %s mode.%s',
			self::generate_log_tag( 'MESSAGE' ),
			$feed_id,
			$starter,
			$background_processing,
			$client_request_tag
		);
		$file_path             = WPPFM_LOGGINGS_DIR . '/' . $log_file_name;
		$append_to_existing    = self::should_append_to_existing_log( $feed_id );

		$plugin_identity_line = self::get_plugin_log_identity_line();

		if ( $append_to_existing ) {
			$restart_notice = sprintf(
				'%sAppending to the existing feed log because feed %s restarted within the last %d seconds.',
				self::generate_log_tag( 'WARNING' ),
				$feed_id,
				self::get_log_append_window_seconds()
			);

			wppfm_append_line_to_file( $file_path, '', true );
			wppfm_append_line_to_file( $file_path, $restart_notice, true );
			wppfm_append_line_to_file( $file_path, $plugin_identity_line, true );
			wppfm_append_line_to_file( $file_path, $log_header, true );
		} else {
			$wp_filesystem->put_contents( $file_path, '', FS_CHMOD_FILE ); // start with a clear log
			$wp_filesystem->put_contents(
				$file_path,
				$plugin_identity_line . "\r\n" . $log_header . "\r\n",
				FS_CHMOD_FILE
			);
		}

		self::record_feed_log_started_at( $feed_id );
		self::set_active_process_log_feed_id( $feed_id );
	}

	/**
	 * Adds a message to the logging file
	 *
	 * @since 2.7.0
	 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
	 * @param string $feed_id the id of the feed.
	 * @param string $message the message to be logged.
	 * @param string $tag     the tag to be used in the log entry.
	 */
	public static function add_to_feed_process_logging( $feed_id, $message, $tag = 'MESSAGE' ) {
		$resolved_feed_id = self::resolve_log_feed_id( $feed_id );

		if ( '' === $resolved_feed_id ) {
			return;
		}

		$log_file_name = self::generate_log_file_name( $resolved_feed_id );
		$client_request_id = self::get_client_request_id_for_feed( $feed_id );
		$client_prefix     = '' !== $client_request_id ? sprintf( '[client_request_id=%s] ', $client_request_id ) : '';
		$log_message        = self::generate_log_tag( $tag ) . $client_prefix . $message;
		$file_path     = WPPFM_LOGGINGS_DIR . '/' . $log_file_name;

		wppfm_append_line_to_file( $file_path, $log_message, true );
	}

	/**
	 * Closes the logging of a feed process and writes a footer to the logging file
	 *
	 * @since 2.7.0
	 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
	 * @param string $feed_id the id of the feed.
	 * @param string $status  the status of the feed process.
	 */
	public static function close_feed_process_logging( $feed_id, $status = 'ok' ) {
		$resolved_feed_id = self::resolve_log_feed_id( $feed_id );
		$log_file_name    = self::generate_log_file_name( $resolved_feed_id ? $resolved_feed_id : $feed_id );
		$file_path        = WPPFM_LOGGINGS_DIR . '/' . $log_file_name;

		$message = 'ok' === $status ? 'Feed processing ended' : $status;
		$level   = 'ok' === $status ? 'MESSAGE' : 'ERROR';

		$log_message = self::generate_log_tag( $level ) . $message;

		wppfm_append_line_to_file( $file_path, $log_message, true );

		if ( '' !== $resolved_feed_id && $resolved_feed_id === self::get_active_process_log_feed_id() ) {
			self::clear_active_process_log_feed_id();
		}
	}

	/**
	 * Makes a file name for the logging file
	 *
	 * @since 2.7.0
	 * @param  string $feed_id
	 * @return string with the name of the logging file
	 */
	private static function generate_log_file_name( $feed_id ) {
		return 'feed-' . $feed_id . '-processing.log';
	}

	/**
	 * Generates the prefix for every log entry
	 *
	 * @since 2.7.0
	 * @param  string $level options are MESSAGE, ERROR or WARNING
	 * @return string
	 */
	private static function generate_log_tag( $level ) {
		return sprintf( '[%s]-[%s]=', gmdate( 'Y-m-d H:i:s', time() ), $level );
	}
}

// End of WPPFM_Feed_Process_Logging Class
