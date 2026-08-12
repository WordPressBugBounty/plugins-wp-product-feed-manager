<?php
/**
 * Read-only helpers for feed processing log diagnostics (no processing side effects).
 *
 * @package Logger
 * @since 3.23.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and formats stall/recovery diagnostic context for feed processing logs.
 */
class WPPFM_Feed_Processing_Diagnostics {

	/**
	 * Returns true when verbose handoff/batch diagnostic logging is enabled.
	 *
	 * @return bool
	 */
	public static function verbose_logging_enabled() {
		return (bool) apply_filters( 'wppfm_feed_processing_log_diagnostics', false );
	}

	/**
	 * Stores the latest dispatch classification for support correlation.
	 *
	 * @param string $feed_id        Feed id.
	 * @param string $classification Dispatch outcome token.
	 *
	 * @return void
	 */
	public static function record_dispatch_outcome( $feed_id, $classification ) {
		$feed_id = (string) $feed_id;

		if ( '' === $feed_id || '' === (string) $classification ) {
			return;
		}

		$ttl = max( MINUTE_IN_SECONDS, intval( apply_filters( 'wppfm_dispatch_outcome_log_ttl', 15 * MINUTE_IN_SECONDS, $feed_id ) ) );

		set_site_transient(
			'wppfm_last_dispatch_outcome_' . sanitize_key( $feed_id ),
			array(
				'classification' => (string) $classification,
				'ts'             => time(),
			),
			$ttl
		);
	}

	/**
	 * Returns the last recorded dispatch outcome for a feed.
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return array{classification:string,ts:int}
	 */
	public static function get_last_dispatch_outcome( $feed_id ) {
		$feed_id = (string) $feed_id;
		$empty   = array(
			'classification' => '',
			'ts'             => 0,
		);

		if ( '' === $feed_id ) {
			return $empty;
		}

		$payload = get_site_transient( 'wppfm_last_dispatch_outcome_' . sanitize_key( $feed_id ) );

		if ( ! is_array( $payload ) ) {
			return $empty;
		}

		return array(
			'classification' => isset( $payload['classification'] ) ? (string) $payload['classification'] : '',
			'ts'             => isset( $payload['ts'] ) ? intval( $payload['ts'] ) : 0,
		);
	}

	/**
	 * Builds a diagnostic snapshot for stall analysis (read-only).
	 *
	 * @param string $feed_id       Feed id.
	 * @param string $watchdog_path Path monitored for growth.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_stall_snapshot( $feed_id, $watchdog_path = '' ) {
		$feed_id       = (string) $feed_id;
		$watchdog_path = (string) $watchdog_path;
		$snapshot      = array(
			'feed_id'        => $feed_id,
			'watchdog_path'  => $watchdog_path,
			'timestamp'      => time(),
		);

		if ( class_exists( 'WPPFM_Feed_Controller' ) ) {
			$snapshot['queue_remaining']        = WPPFM_Feed_Controller::nr_ids_remaining_in_product_queue();
			$snapshot['handoff_grace_active']   = WPPFM_Feed_Controller::is_feed_handoff_grace_active( $feed_id );
			$snapshot['handoff_marker_active']  = WPPFM_Feed_Controller::feed_handoff_marker_is_active_for_feed( $feed_id );
			$snapshot['handoff_age_seconds']    = WPPFM_Feed_Controller::get_feed_handoff_age_seconds( $feed_id );
			$snapshot['should_resume_batch']    = WPPFM_Feed_Controller::should_resume_existing_batch( $feed_id );
			$snapshot['suppress_failure']       = WPPFM_Feed_Controller::should_suppress_feed_processing_failure( $feed_id );
			$snapshot['suppression_reason']     = self::get_failure_suppression_reason( $feed_id );
			$snapshot['feed_is_processing']     = WPPFM_Feed_Controller::feed_is_processing();
			$snapshot['active_batch_feed_id']   = WPPFM_Feed_Controller::get_active_batch_feed_id();
			$snapshot['background_process_key'] = get_site_option( 'wppfm_background_process_key', '' );
		}

		$snapshot['process_lock_present'] = ! empty( get_site_transient( 'wppfm_feed_generation_process_process_lock' ) );
		$snapshot['heartbeat_fresh']      = class_exists( 'WPPFM_Feed_Controller' ) && WPPFM_Feed_Controller::background_process_heartbeat_is_fresh();
		$snapshot['pending_dispatch']     = self::has_pending_dispatch( $feed_id );
		$snapshot['last_dispatch']        = self::get_last_dispatch_outcome( $feed_id );
		$snapshot['recovery_cron_next']   = wp_next_scheduled( 'wppfm_feed_generation_process_cron_recovery' );
		$snapshot['handled_items']        = intval( get_transient( 'wppfm_nr_of_handled_items' ) );
		$snapshot['processed_products']   = function_exists( 'wppfm_resolve_feed_products_added_count' )
			? wppfm_resolve_feed_products_added_count( $feed_id )
			: intval( get_transient( 'wppfm_nr_of_processed_products' ) );

		$monitor = self::parse_growth_monitor_transient();
		$snapshot['monitor_prev_size']      = $monitor['size'];
		$snapshot['monitor_prev_timestamp'] = $monitor['timestamp'];
		$snapshot['monitor_prev_handled']   = $monitor['processed'];
		$snapshot['monitor_bonus_delay']    = $monitor['bonus_delay'];
		$snapshot['monitor_tracked_file']   = $monitor['file'];

		if ( '' !== $watchdog_path && file_exists( $watchdog_path ) ) {
			$snapshot['watchdog_file_size'] = filesize( $watchdog_path );
		} else {
			$snapshot['watchdog_file_size'] = false === file_exists( $watchdog_path ) ? null : 0;
		}

		$snapshot['stall_delay_seconds'] = self::estimate_stall_delay_seconds( $feed_id, $watchdog_path );
		$snapshot['likely_cause']        = self::infer_likely_cause( $snapshot );

		return apply_filters( 'wppfm_feed_stall_diagnostic_snapshot', $snapshot, $feed_id, $watchdog_path );
	}

	/**
	 * Human-readable reason while UI stall failure is suppressed.
	 *
	 * @param string $feed_id Feed id.
	 *
	 * @return string Empty when not suppressed.
	 */
	public static function get_failure_suppression_reason( $feed_id ) {
		if ( ! class_exists( 'WPPFM_Feed_Controller' ) ) {
			return '';
		}

		$feed_id = (string) $feed_id;

		if ( '' === $feed_id || ! WPPFM_Feed_Controller::should_suppress_feed_processing_failure( $feed_id ) ) {
			return '';
		}

		if ( WPPFM_Feed_Controller::is_feed_handoff_grace_active( $feed_id ) ) {
			$age = WPPFM_Feed_Controller::get_feed_handoff_age_seconds( $feed_id );

			return sprintf(
				'handoff_grace (%ds elapsed, grace=%ds)',
				null !== $age ? intval( $age ) : 0,
				WPPFM_Feed_Controller::get_feed_handoff_grace_seconds( $feed_id )
			);
		}

		if ( WPPFM_Feed_Controller::should_resume_existing_batch( $feed_id ) ) {
			return 'recovery_plausible (batch metadata present, no lock/heartbeat)';
		}

		return 'suppressed';
	}

	/**
	 * Logs a throttled notice when stall failure is suppressed during status polling.
	 *
	 * @param string $feed_id       Feed id.
	 * @param string $watchdog_path Monitored file path.
	 *
	 * @return void
	 */
	public static function maybe_log_suppressed_stall_notice( $feed_id, $watchdog_path = '' ) {
		if ( ! function_exists( 'wppfm_process_logger_is_active' ) || ! wppfm_process_logger_is_active() ) {
			return;
		}

		$reason = self::get_failure_suppression_reason( $feed_id );

		if ( '' === $reason ) {
			return;
		}

		$interval = max( 30, intval( apply_filters( 'wppfm_stall_suppression_log_interval_seconds', 60, $feed_id ) ) );
		$key      = 'wppfm_stall_suppress_logged_' . sanitize_key( (string) $feed_id );

		if ( get_transient( $key ) ) {
			return;
		}

		set_transient( $key, 1, $interval );

		$message = sprintf(
			'Stall failure suppressed while waiting for recovery (%s). Queue remaining: %d.',
			$reason,
			class_exists( 'WPPFM_Feed_Controller' ) ? WPPFM_Feed_Controller::nr_ids_remaining_in_product_queue() : 0
		);

		do_action( 'wppfm_feed_generation_message', $feed_id, $message, 'WARNING' );

		if ( self::verbose_logging_enabled() ) {
			do_action( 'wppfm_feed_generation_message', $feed_id, self::format_diagnostic_block( self::build_stall_snapshot( $feed_id, $watchdog_path ) ), 'WARNING' );
		}
	}

	/**
	 * Formats a diagnostic snapshot for the processing log file.
	 *
	 * @param array<string, mixed> $snapshot Diagnostic data.
	 *
	 * @return string
	 */
	public static function format_diagnostic_block( $snapshot ) {
		if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
			return 'Processing diagnostics: (no data)';
		}

		$lines   = array( '--- Feed processing diagnostics ---' );
		$lines[] = 'likely_cause=' . ( isset( $snapshot['likely_cause'] ) ? $snapshot['likely_cause'] : 'unknown' );

		foreach ( $snapshot as $key => $value ) {
			if ( 'likely_cause' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( null === $value ) {
				$value = 'null';
			}

			$lines[] = $key . '=' . $value;
		}

		$lines[] = '--- End diagnostics ---';

		return implode( "\r\n", $lines );
	}

	/**
	 * Heuristic label for support-facing logs.
	 *
	 * @param array<string, mixed> $snapshot Diagnostic snapshot.
	 *
	 * @return string
	 */
	public static function infer_likely_cause( $snapshot ) {
		$classification = isset( $snapshot['last_dispatch']['classification'] ) ? (string) $snapshot['last_dispatch']['classification'] : '';
		$queue_remaining  = isset( $snapshot['queue_remaining'] ) ? intval( $snapshot['queue_remaining'] ) : 0;
		$pending          = ! empty( $snapshot['pending_dispatch'] );
		$resume           = ! empty( $snapshot['should_resume_batch'] );
		$grace            = ! empty( $snapshot['handoff_grace_active'] );
		$lock             = ! empty( $snapshot['process_lock_present'] );
		$heartbeat        = ! empty( $snapshot['heartbeat_fresh'] );

		if ( $grace && $queue_remaining > 0 ) {
			return 'handoff_waiting';
		}

		if ( $pending || ( '' !== $classification && 'success' !== $classification ) ) {
			if ( false !== strpos( $classification, '403' ) || false !== strpos( $classification, 'wp_error' ) ) {
				return 'loopback_blocked';
			}

			if ( $resume && ! $lock && ! $heartbeat ) {
				return 'loopback_failed_awaiting_recovery';
			}
		}

		if ( $resume && ! $lock && ! $heartbeat && $queue_remaining > 0 ) {
			$recovery_next = isset( $snapshot['recovery_cron_next'] ) ? intval( $snapshot['recovery_cron_next'] ) : 0;

			if ( $recovery_next > 0 && $recovery_next < time() ) {
				return 'cron_recovery_overdue';
			}

			if ( 0 === $recovery_next && ! $pending ) {
				return 'cron_not_scheduled';
			}
		}

		return 'genuine_stall';
	}

	/**
	 * Logs a verbose snapshot when diagnostics mode is enabled.
	 *
	 * @param string $feed_id Feed id.
	 * @param string $context Short context label.
	 *
	 * @return void
	 */
	public static function maybe_log_verbose_snapshot( $feed_id, $context = '' ) {
		if ( ! self::verbose_logging_enabled() || ! function_exists( 'wppfm_process_logger_is_active' ) || ! wppfm_process_logger_is_active() ) {
			return;
		}

		$path = '';

		if ( class_exists( 'WPPFM_Feed_Controller' ) && '' !== (string) $feed_id ) {
			$path = WPPFM_Feed_Controller::resolve_active_feed_generation_file_path_from_batch_metadata( $feed_id );
		}

		$snapshot = self::build_stall_snapshot( $feed_id, $path );
		$prefix   = '' !== (string) $context ? $context . ': ' : '';

		do_action( 'wppfm_feed_generation_message', $feed_id, $prefix . self::format_diagnostic_block( $snapshot ), 'MESSAGE' );
	}

	/**
	 * @param string $feed_id Feed id.
	 *
	 * @return bool
	 */
	private static function has_pending_dispatch( $feed_id ) {
		$feed_id = (string) $feed_id;

		if ( '' === $feed_id ) {
			return false;
		}

		$payload = get_site_transient( 'wppfm_pending_dispatch_' . sanitize_key( $feed_id ) );

		return is_array( $payload ) && ! empty( $payload['feed_id'] );
	}

	/**
	 * @return array{size:int,timestamp:int,processed:int,bonus_delay:int,file:string}
	 */
	private static function parse_growth_monitor_transient() {
		$defaults = array(
			'size'        => 0,
			'timestamp'   => 0,
			'processed'   => 0,
			'bonus_delay' => 0,
			'file'        => '',
		);

		$raw = get_transient( 'wppfm_feed_file_size' );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return $defaults;
		}

		$parts = explode( '|', $raw );

		return array(
			'size'        => isset( $parts[0] ) ? intval( $parts[0] ) : 0,
			'timestamp'   => isset( $parts[1] ) ? intval( $parts[1] ) : 0,
			'processed'   => isset( $parts[3] ) ? intval( $parts[3] ) : 0,
			'bonus_delay' => isset( $parts[4] ) ? intval( $parts[4] ) : 0,
			'file'        => isset( $parts[2] ) ? (string) $parts[2] : '',
		);
	}

	/**
	 * @param string $feed_id       Feed id.
	 * @param string $watchdog_path Watchdog path.
	 *
	 * @return int
	 */
	private static function estimate_stall_delay_seconds( $feed_id, $watchdog_path ) {
		if ( ! defined( 'WPPFM_DELAY_FAILED_LABEL' ) ) {
			return 0;
		}

		$monitor = self::parse_growth_monitor_transient();
		$base    = apply_filters( 'wppfm_failed_detection_base_delay', WPPFM_DELAY_FAILED_LABEL, $watchdog_path );
		$delay   = max( 0, intval( $base ) ) + max( 0, intval( $monitor['bonus_delay'] ) );

		if ( class_exists( 'WPPFM_Feed_Controller' ) && '' !== (string) $feed_id ) {
			$delay += WPPFM_Feed_Controller::get_feed_stall_detection_extra_delay( $feed_id );
		}

		return $delay;
	}
}
