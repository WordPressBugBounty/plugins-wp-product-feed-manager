<?php

/**
 * Initiates the Cron functions required for the automatic feed updates.
 *
 * @package WP Product Feed Manager/Application/Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activates the feed update schedules using Cron Jobs.
 */
function wppfm_update_feeds() {
	/*
	 * This callback runs from the wppfm_feed_update_schedule action after WordPress (and this plugin)
	 * are loaded.
	 */
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	// Include all product feed manager files.
	require_once __DIR__ . '/../wppfm-wpincludes.php';
	require_once __DIR__ . '/../data/wppfm-admin-functions.php';
	require_once __DIR__ . '/../user-interface/wppfm-messaging-functions.php';
	require_once __DIR__ . '/../user-interface/wppfm-url-functions.php';
	require_once __DIR__ . '/../application/wppfm-feed-processing-support.php';
	require_once __DIR__ . '/../application/wppfm-feed-product-counter.php';
	require_once __DIR__ . '/../application/wppfm-feed-processor-functions.php';

	// WooCommerce needs to be installed and active.
	if ( ! wppfm_wc_installed_and_active() ) {
		wppfm_write_log_file( 'Tried to start the auto update process but failed because WooCommerce is not installed.' );
		exit;
	}

	// Feed Manager requires at least WooCommerce version 3.0.0.
	if ( ! wppfm_wc_min_version_required() ) {
		wppfm_write_log_file( sprintf( 'Tried to start the auto update process but failed because WooCommerce is older than version %s', WPPFM_MIN_REQUIRED_WC_VERSION ) );
		exit;
	}

	// Include the files required for the Google Review Feed Manager.
	wppfm_include_files_for_review_feed_package();

	// Include the files required for the Google Merchant Promotions Feed Manager.
	//wppfm_include_files_for_merchant_promotions_feed_package();

	WC_Post_types::register_taxonomies(); // Make sure the WooCommerce taxonomies are loaded.
	WC_Post_types::register_post_types(); // Make sure the WooCommerce post types are loaded.

	// Include all required classes.
	wppfm_include_classes();
	wppfm_include_channels();

	do_action( 'wppfm_automatic_feed_processing_triggered' );

	// Update the database if required.
	wppfm_check_db_version();

	// Start updating the active feeds.
	$wppfm_schedules = new WPPFM_Schedules();
	$wppfm_schedules->update_active_feeds();
}

/**
 * Includes the files required for automatic feed updates for Google Review Feeds.
 *
 * @since 2.33.0.
 * @since 2.39.1 Corrected the paths to the include files.
 * @since 2.39.2 Added the wp-product-review-feed-manager.php file.
 */
function wppfm_include_files_for_review_feed_package() {
	require_once __DIR__ . '/../packages/review-feed-manager/wp-product-review-feed-manager.php';
	require_once __DIR__ . '/../packages/review-feed-manager/wpprfm-review-feed-form-functions.php';
	require_once __DIR__ . '/../packages/review-feed-manager/wpprfm-setup-feed-manager.php';
	require_once __DIR__ . '/../packages/review-feed-manager/wpprfm-include-classes-functions.php';
	require_once __DIR__ . '/../packages/review-feed-manager/wpprfm-feed-generation-functions.php';

	// Include the traits.
	require_once __DIR__ . '/../packages/review-feed-manager/traits/wpprfm-processing-support.php';
	require_once __DIR__ . '/../packages/review-feed-manager/traits/wpprfm-xml-element-functions.php';

	// Include the required classes.
	wppfm_rf_include_classes();
}

/**
 * Register the feed update cron schedule.
 *
 * @param array $schedules Current cron schedules.
 *
 * @return array
 * @since 3.24.0 Introduced a dedicated (default 5-minute) feed update interval.
 */
function wppfm_register_feed_update_schedule( $schedules ) {
	if ( isset( $schedules['wppfm_feed_update_interval'] ) ) {
		return $schedules;
	}

	$interval_minutes = apply_filters( 'wppfm_feed_update_interval_minutes', 5 );
	$interval_minutes = max( 1, intval( $interval_minutes ) );

	$schedules['wppfm_feed_update_interval'] = array(
		'interval' => $interval_minutes * MINUTE_IN_SECONDS,
		'display'  => sprintf(
			/* translators: %d: Number of minutes */
			_n(
				'Every %d minute (WPPFM Feed Updates)',
				'Every %d minutes (WPPFM Feed Updates)',
				$interval_minutes,
				'wp-product-feed-manager'
			),
			$interval_minutes
		),
	);

	return $schedules;
}

/**
 * Ensure the feed update cron event exists (and uses the expected interval).
 *
 * @since 3.24.0 Reschedules older hourly events to the dedicated (default 5-minute) interval.
 */
function wppfm_schedule_feed_update_event() {
	$desired_schedule = 'wppfm_feed_update_interval';
	$hook             = 'wppfm_feed_update_schedule';

	$event = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( $hook ) : false;

	if ( $event && isset( $event->schedule ) && $desired_schedule !== $event->schedule ) {
		// Keep the event, but normalize the recurrence when upgrading from older plugin versions.
		wp_clear_scheduled_hook( $hook );
		$event = false;
	}

	if ( ! $event && ! wp_next_scheduled( $hook ) ) {
		// Start on a short delay to avoid overlap during activation or heavy admin requests.
		wp_schedule_event( time() + MINUTE_IN_SECONDS, $desired_schedule, $hook );
	}
}

/**
 * Register the feed watchdog cron schedule.
 *
 * @param array $schedules Current cron schedules.
 *
 * @return array
 * @since 3.18.0 Introduced the watchdog interval that monitors stuck background processes.
 */
function wppfm_register_feed_watchdog_schedule( $schedules ) {
	$enabled = apply_filters( 'wppfm_enable_feed_watchdog', true );

	if ( $enabled && ! isset( $schedules['wppfm_feed_watchdog_interval'] ) ) {
		$interval_minutes = apply_filters( 'wppfm_feed_watchdog_interval_minutes', 5 );
		$interval_minutes = max( 1, intval( $interval_minutes ) );

		$schedules['wppfm_feed_watchdog_interval'] = array(
			'interval' => $interval_minutes * MINUTE_IN_SECONDS,
			'display'  => sprintf(
				/* translators: %d: Number of minutes */
				_n(
					'Every %d minute (WPPFM Feed Watchdog)',
					'Every %d minutes (WPPFM Feed Watchdog)',
					$interval_minutes,
					'wp-product-feed-manager'
				),
				$interval_minutes
			),
		);
	}

	return $schedules;
}

/**
 * Ensure the feed watchdog cron event exists.
 *
 * @since 3.18.0 Schedules the watchdog event that reboots the queue when required.
 */
function wppfm_schedule_feed_watchdog_event() {
	if ( ! apply_filters( 'wppfm_enable_feed_watchdog', true ) ) {
		return;
	}

	if ( ! wp_next_scheduled( 'wppfm_feed_watchdog_cron' ) ) {
		wp_schedule_event(
			time() + MINUTE_IN_SECONDS,
			'wppfm_feed_watchdog_interval',
			'wppfm_feed_watchdog_cron'
		);
	}
}

/**
 * Handle the feed watchdog cron execution.
 *
 * @since 3.18.0 Evaluates queue state, clears stale flags, and restarts feeds when needed.
 */
function wppfm_handle_feed_watchdog_cron() {
	if ( ! apply_filters( 'wppfm_enable_feed_watchdog', true ) ) {
		return;
	}

	if ( ! function_exists( 'wppfm_include_classes' ) ) {
		require_once __DIR__ . '/../wppfm-wpincludes.php';
	}

	wppfm_include_classes();

	$lock_value   = get_site_transient( 'wppfm_feed_generation_process_process_lock' );
	$lock_exists  = ! empty( $lock_value );
	$is_processing = WPPFM_Feed_Controller::feed_is_processing();
	$queue_empty   = WPPFM_Feed_Controller::feed_queue_is_empty();
	$queue         = get_site_option( 'wppfm_feed_queue', array() );
	$queue_count   = is_array( $queue ) ? count( $queue ) : 0;
	$active_key    = get_site_option( 'wppfm_background_process_key' );
	$heartbeat_fresh = method_exists( 'WPPFM_Feed_Controller', 'background_process_heartbeat_is_fresh' )
		? WPPFM_Feed_Controller::background_process_heartbeat_is_fresh()
		: false;
	$handoff_grace_active = method_exists( 'WPPFM_Feed_Controller', 'background_process_handoff_grace_is_active' )
		? WPPFM_Feed_Controller::background_process_handoff_grace_is_active()
		: false;

	$lock_missing_since_key = 'wppfm_watchdog_lock_missing_since';
	$lock_timeout           = apply_filters( 'wppfm_feed_watchdog_lock_timeout', 5 * MINUTE_IN_SECONDS );
	$lock_timeout           = max( 60, intval( $lock_timeout ) ); // Minimum 1 minute.

	if ( ! $queue_empty && ! $is_processing && ! $lock_exists ) {
		if ( $handoff_grace_active ) {
			do_action(
				'wppfm_feed_generation_message',
				'unknown',
				sprintf(
					'Feed watchdog detected an unlocked queue during batch handoff grace; skipping restart to avoid duplicate feed startup. (queue_count=%d, active_key=%s)',
					intval( $queue_count ),
					$active_key ? $active_key : 'none'
				),
				'WARNING'
			);

			delete_site_transient( $lock_missing_since_key );
			return;
		}

		$resume_feed_id = WPPFM_Feed_Controller::get_next_id_from_feed_queue();

		if ( $resume_feed_id && wppfm_watchdog_settle_completed_feed_state( $resume_feed_id, $lock_missing_since_key ) ) {
			return;
		}

		if ( $resume_feed_id && function_exists( 'wppfm_should_resume_existing_batch' ) && wppfm_should_resume_existing_batch( $resume_feed_id, true ) ) {
			delete_site_transient( $lock_missing_since_key );

			if ( function_exists( 'wppfm_resume_background_feed_batch' ) && wppfm_resume_background_feed_batch( $resume_feed_id, 'watchdog_queue_idle' ) ) {
				return;
			}
		}

		do_action(
			'wppfm_feed_generation_message',
			'unknown',
			sprintf(
				'Feed watchdog detected queued feeds without an active process lock. Starting next feed. (lock_exists=%s, is_processing=%s, queue_empty=%s, heartbeat_fresh=%s, queue_count=%d, active_key=%s)',
				$lock_exists ? 'true' : 'false',
				$is_processing ? 'true' : 'false',
				$queue_empty ? 'true' : 'false',
				$heartbeat_fresh ? 'true' : 'false',
				intval( $queue_count ),
				$active_key ? $active_key : 'none'
			),
			'WARNING'
		);

		delete_site_transient( $lock_missing_since_key );
		wppfm_watchdog_start_next_feed( 'queue_idle' );

		return;
	}

	if ( $is_processing && ! $lock_exists ) {
		// When the durable heartbeat is fresh, treat the system as healthy and avoid aggressive restarts.
		// This prevents watchdog-induced overlap when transient storage is unreliable on a host.
		if ( $heartbeat_fresh ) {
			delete_site_transient( $lock_missing_since_key );

			do_action(
				'wppfm_feed_generation_message',
				'unknown',
				sprintf(
					'Feed watchdog detected missing lock transient but heartbeat is fresh; skipping recovery to avoid overlap. (queue_count=%d, active_key=%s)',
					intval( $queue_count ),
					$active_key ? $active_key : 'none'
				),
				'WARNING'
			);

			return;
		}

		$missing_since = get_site_transient( $lock_missing_since_key );

		if ( false === $missing_since ) {
			$ttl = apply_filters( 'wppfm_feed_watchdog_lock_missing_ttl', HOUR_IN_SECONDS );
			set_site_transient( $lock_missing_since_key, time(), max( MINUTE_IN_SECONDS, intval( $ttl ) ) );

			return;
		}

		if ( time() - intval( $missing_since ) >= $lock_timeout ) {
			$resume_feed_id = WPPFM_Feed_Controller::get_next_id_from_feed_queue();

			if ( ! $resume_feed_id ) {
				$resume_feed_id = WPPFM_Feed_Controller::get_active_batch_feed_id();
			}

			if ( wppfm_watchdog_settle_completed_feed_state( $resume_feed_id, $lock_missing_since_key ) ) {
				return;
			}

			do_action(
				'wppfm_feed_generation_message',
				'unknown',
				sprintf(
					'Feed watchdog cleared stale processing flag after lock timeout. (lock_timeout=%ds, missing_since=%s)',
					intval( $lock_timeout ),
					intval( $missing_since )
				),
				'WARNING'
			);

			WPPFM_Feed_Controller::set_feed_processing_flag();
			delete_transient( 'wppfm_feed_file_size' );
			delete_site_transient( $lock_missing_since_key );

			if ( $resume_feed_id && wppfm_should_resume_existing_batch( $resume_feed_id, true ) && wppfm_resume_background_feed_batch( $resume_feed_id, 'watchdog_stale_processing_flag' ) ) {
				return;
			}

			wppfm_watchdog_start_next_feed( 'stale_processing_flag' );
		}

		return;
	}

	// Reset the tracker when everything looks healthy or the queue is idle.
	WPPFM_Feed_Controller::invalidate_feed_process_state_object_cache();

	if ( $lock_exists || ( ! WPPFM_Feed_Controller::feed_is_processing() && WPPFM_Feed_Controller::feed_queue_is_empty() ) ) {
		delete_site_transient( $lock_missing_since_key );
	}

	// Hook point for follow-up maintenance steps (e.g. orphaned batch cleanup) after the watchdog health check.
	do_action( 'wppfm_feed_watchdog_after_health_check', $lock_exists, $is_processing, ! $queue_empty );
}

/**
 * Clears stale watchdog markers when authoritative state shows the feed already finished.
 *
 * @param string $feed_id                  Feed id candidate for resume, may be empty.
 * @param string $lock_missing_since_key   Transient key tracking missing process locks.
 *
 * @return bool True when recovery must not continue.
 */
function wppfm_watchdog_settle_completed_feed_state( $feed_id, $lock_missing_since_key ) {
	if ( ! class_exists( 'WPPFM_Feed_Controller' ) ) {
		return false;
	}

	WPPFM_Feed_Controller::invalidate_feed_process_state_object_cache();

	$feed_id    = (string) absint( $feed_id );
	$batch_gone = ! WPPFM_Feed_Controller::background_batch_rows_exist();

	if ( '' !== $feed_id && WPPFM_Feed_Controller::feed_has_ok_status( $feed_id ) ) {
		WPPFM_Feed_Controller::set_feed_processing_flag();
		delete_site_transient( $lock_missing_since_key );
		delete_transient( 'wppfm_feed_file_size' );

		do_action(
			'wppfm_feed_generation_message',
			$feed_id,
			'Feed watchdog settled stale in-process state because the feed status is already OK.',
			'WARNING'
		);

		return true;
	}

	if ( $batch_gone ) {
		WPPFM_Feed_Controller::set_feed_processing_flag();
		delete_site_transient( $lock_missing_since_key );
		delete_transient( 'wppfm_feed_file_size' );

		do_action(
			'wppfm_feed_generation_message',
			'' !== $feed_id ? $feed_id : 'unknown',
			'Feed watchdog settled stale in-process state because no background batch rows remain.',
			'WARNING'
		);

		return true;
	}

	return false;
}

/**
 * Returns true when an in-flight batch for the feed can be resumed via handle().
 *
 * @param string $feed_id              Feed id to inspect.
 * @param bool   $bypass_object_cache When true, bypasses cached queue/batch options before reading.
 *
 * @return bool
 */
function wppfm_should_resume_existing_batch( $feed_id, $bypass_object_cache = false ) {
	if ( ! class_exists( 'WPPFM_Feed_Controller' ) ) {
		return false;
	}

	return WPPFM_Feed_Controller::should_resume_existing_batch( (string) $feed_id, (bool) $bypass_object_cache );
}

/**
 * Resumes the existing background batch without re-preparing the feed queue.
 *
 * @param string $feed_id Feed id to resume.
 * @param string $context Optional context for logging.
 *
 * @return bool True when batch processing was resumed.
 */
function wppfm_resume_background_feed_batch( $feed_id, $context = 'cron_recovery' ) {
	$feed_id = (string) $feed_id;

	if ( '' === $feed_id || ! wppfm_should_resume_existing_batch( $feed_id ) ) {
		return false;
	}

	if ( ! function_exists( 'wppfm_include_classes' ) ) {
		require_once __DIR__ . '/../wppfm-wpincludes.php';
	}

	wppfm_include_classes();

	if ( ! class_exists( 'WPPFM_Feed_Processor' ) ) {
		return false;
	}

	$background_process = new WPPFM_Feed_Processor();

	if ( $background_process->is_process_running() ) {
		return false;
	}

	do_action( 'wppfm_batch_resume_via_handle', $feed_id, $context );

	$background_process->resume_batch_processing();

	return true;
}

/**
 * Start the next feed in the queue for the watchdog.
 *
 * @param string $context Optional context for logging purposes.
 *
 * @since 3.18.0 Centralised helper used by the watchdog to resume processing.
 */
function wppfm_watchdog_start_next_feed( $context = 'unknown' ) {
	$next_feed_id = WPPFM_Feed_Controller::get_next_id_from_feed_queue();

	if ( ! $next_feed_id ) {
		return;
	}

	do_action( 'wppfm_feed_watchdog_starting_feed', $next_feed_id, $context );

	$message = sprintf(
		'Feed watchdog (%s) activating feed %s.',
		$context,
		$next_feed_id
	);

	do_action( 'wppfm_feed_generation_message', $next_feed_id, $message, 'WARNING' );

	$feed_master_class = new WPPFM_Feed_Master_Class( $next_feed_id );
	$feed_master_class->initiate_update_next_feed_in_queue();
}

add_filter( 'cron_schedules', 'wppfm_register_feed_watchdog_schedule' );
add_action( 'init', 'wppfm_schedule_feed_watchdog_event' );
add_action( 'wppfm_feed_watchdog_cron', 'wppfm_handle_feed_watchdog_cron' );

/**
 * Refreshes cached feed-process state after dispatch recovery is skipped for a completed feed.
 *
 * @param string $feed_id        Feed id.
 * @param string $classification Dispatch outcome token.
 * @param string $reason         Skip reason from dispatch recovery.
 *
 * @return void
 */
function wppfm_invalidate_feed_process_state_after_dispatch_skip( $feed_id, $classification, $reason ) {
	unset( $feed_id, $classification );

	if ( 'feed_status_ok' !== (string) $reason || ! class_exists( 'WPPFM_Feed_Controller' ) ) {
		return;
	}

	WPPFM_Feed_Controller::invalidate_feed_process_state_object_cache();
}

add_action( 'wppfm_dispatch_recovery_skipped', 'wppfm_invalidate_feed_process_state_after_dispatch_skip', 10, 3 );

add_filter( 'cron_schedules', 'wppfm_register_feed_update_schedule' ); // phpcs:disable WordPress.WP.CronInterval.ChangeDetected
add_action( 'init', 'wppfm_schedule_feed_update_event' );

/**
 * Transient key storing the Unix time a watchdog failure was first observed for a feed.
 *
 * @param string $feed_id Feed ID.
 *
 * @return string
 */
function wppfm_deferred_feed_failure_transient_key( $feed_id ) {
	return 'wppfm_deferred_failure_detected_' . sanitize_key( (string) $feed_id );
}

/**
 * Schedules a delayed failure notice so email is only sent if the feed is still failed
 * after a quiet period (avoids mail when generation later completes successfully).
 *
 * @param string $feed_id     Feed ID.
 * @param int    $detected_at Unix timestamp when the stall was detected.
 */
function wppfm_schedule_deferred_feed_failure_notice( $feed_id, $detected_at ) {
	$feed_id = (string) $feed_id;

	if ( '' === $feed_id ) {
		return;
	}

	$key = wppfm_deferred_feed_failure_transient_key( $feed_id );
	set_transient( $key, max( 1, (int) $detected_at ), DAY_IN_SECONDS );

	$hook = 'wppfm_send_deferred_feed_failure_notice';
	wp_clear_scheduled_hook( $hook, array( $feed_id ) );

	$delay = apply_filters( 'wppfm_feed_failure_notice_delay_seconds', 10 * MINUTE_IN_SECONDS, $feed_id );
	$delay = max( 120, intval( $delay ) );

	wp_schedule_single_event( time() + $delay, $hook, array( $feed_id ) );
}

/**
 * Clears a pending deferred failure notice (e.g. after a successful completion or an immediate terminal failure email).
 *
 * @param string $feed_id Feed ID.
 */
function wppfm_cancel_deferred_feed_failure_notice( $feed_id ) {
	$feed_id = (string) $feed_id;

	if ( '' === $feed_id ) {
		return;
	}

	delete_transient( wppfm_deferred_feed_failure_transient_key( $feed_id ) );
	wp_clear_scheduled_hook( 'wppfm_send_deferred_feed_failure_notice', array( $feed_id ) );
}

/**
 * Sends the deferred watchdog failure email only when the failure is still the final state.
 *
 * @param string $feed_id Feed ID.
 */
function wppfm_send_deferred_feed_failure_notice_cb( $feed_id ) {
	$feed_id = (string) $feed_id;

	if ( '' === $feed_id || ! class_exists( 'WPPFM_Data' ) || ! class_exists( 'WPPFM_Email' ) || ! class_exists( 'WPPFM_Feed_Controller' ) ) {
		return;
	}

	$key         = wppfm_deferred_feed_failure_transient_key( $feed_id );
	$detected_at = (int) get_transient( $key );

	if ( $detected_at <= 0 ) {
		$detected_at = time();
	}

	$data_class = new WPPFM_Data();
	$status     = $data_class->get_feed_status( $feed_id );

	// Status 6 = failed processing; anything else means the run recovered.
	if ( null === $status || '6' !== (string) $status ) {
		delete_transient( $key );
		return;
	}

	// Worker still active — do not send a terminal failure notice yet.
	if ( WPPFM_Feed_Controller::background_process_heartbeat_is_fresh() || WPPFM_Feed_Controller::feed_is_processing() ) {
		delete_transient( $key );
		return;
	}

	delete_transient( $key );

	WPPFM_Email::send_feed_failed_message(
		$feed_id,
		array(
			'detected_at' => $detected_at,
			'source'      => 'watchdog_stall',
		)
	);
}

add_action( 'wppfm_send_deferred_feed_failure_notice', 'wppfm_send_deferred_feed_failure_notice_cb', 10, 1 );
