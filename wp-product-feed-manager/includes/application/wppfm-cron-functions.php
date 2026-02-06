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
	// Include the required WordPress files.
	defined( 'WP_CLI' ) || require_once ABSPATH . 'wp-load.php'; // @since 3.13.0 - Added defined( 'WP_CLI' ) || to prevent a reloading wp-config.php.
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php'; // Required for using the file system.
	require_once ABSPATH . 'wp-admin/includes/plugin.php'; // Required to prevent a fatal error about not finding the is_plugin_active function.

	// Include all product feed manager files.
	require_once __DIR__ . '/../wppfm-wpincludes.php';
	require_once __DIR__ . '/../data/wppfm-admin-functions.php';
	require_once __DIR__ . '/../user-interface/wppfm-messaging-functions.php';
	require_once __DIR__ . '/../user-interface/wppfm-url-functions.php';
	require_once __DIR__ . '/../application/wppfm-feed-processing-support.php';
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
	include_classes();
	include_channels();

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

	if ( ! function_exists( 'include_classes' ) ) {
		require_once __DIR__ . '/../wppfm-wpincludes.php';
	}

	include_classes();

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

	$lock_missing_since_key = 'wppfm_watchdog_lock_missing_since';
	$lock_timeout           = apply_filters( 'wppfm_feed_watchdog_lock_timeout', 5 * MINUTE_IN_SECONDS );
	$lock_timeout           = max( 60, intval( $lock_timeout ) ); // Minimum 1 minute.

	if ( ! $queue_empty && ! $is_processing && ! $lock_exists ) {
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

			wppfm_watchdog_start_next_feed( 'stale_processing_flag' );
		}

		return;
	}

	// Reset the tracker when everything looks healthy.
	if ( $lock_exists ) {
		delete_site_transient( $lock_missing_since_key );
	}

	// Hook point for follow-up maintenance steps (e.g. orphaned batch cleanup) after the watchdog health check.
	do_action( 'wppfm_feed_watchdog_after_health_check', $lock_exists, $is_processing, ! $queue_empty );
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
