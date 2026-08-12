<?php

/**
 * Per-feed products-added counter for background feed generation.
 *
 * Counter architecture (single authoritative store):
 *
 * 1. Authoritative — option `wppfm_feed_products_added_{feed_id}`.
 *    Written only at batch boundaries via wppfm_set_feed_products_added_counter()
 *    using an absolute, monotonic total (idempotent on retry). Reset when a feed
 *    run starts. Read at completion via wppfm_get_authoritative_feed_products_added_count().
 *
 * 2. Progress mirror — transient `wppfm_feed_run_products_added_{feed_id}`.
 *    Ephemeral in-run UI value updated during batch processing. Never used for
 *    completion; cleared when the feed run resets or completes.
 *
 * 3. Legacy UI transient — `wppfm_nr_of_processed_products` (global).
 *    Updated only while this feed is the active feed. Synced from the authoritative
 *    option and progress mirror; kept for older UI/diagnostic callers.
 *
 * @package WP Product Feed Manager/Application/Functions
 * @since 3.23.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the option / site-meta key for a feed's products-added counter.
 *
 * @param int $feed_id Feed id.
 *
 * @return string
 */
function wppfm_feed_products_added_counter_option_name( $feed_id ) {
	return 'wppfm_feed_products_added_' . absint( $feed_id );
}

/**
 * Returns the transient key for a feed's in-run progress mirror.
 *
 * @param int $feed_id Feed id.
 *
 * @return string
 */
function wppfm_feed_run_products_added_transient_name( $feed_id ) {
	return 'wppfm_feed_run_products_added_' . absint( $feed_id );
}

/**
 * Returns the transient TTL used for feed progress mirrors.
 *
 * @return int
 */
function wppfm_feed_products_progress_transient_ttl() {
	return defined( 'WPPFM_TRANSIENT_LIVE' ) ? WPPFM_TRANSIENT_LIVE : HOUR_IN_SECONDS;
}

/**
 * Resets the authoritative products-added counter and progress mirrors for a feed.
 *
 * @param int $feed_id Feed id.
 *
 * @return void
 */
function wppfm_reset_feed_products_added_counter( $feed_id ) {
	$feed_id = absint( $feed_id );

	if ( ! $feed_id ) {
		return;
	}

	$option_name = wppfm_feed_products_added_counter_option_name( $feed_id );

	if ( is_multisite() ) {
		delete_site_option( $option_name );
	} else {
		delete_option( $option_name );
	}

	wppfm_invalidate_feed_products_added_counter_cache( $feed_id );
	wppfm_clear_feed_run_products_added_count( $feed_id );
}

/**
 * Returns the per-feed in-run progress mirror value.
 *
 * @param int $feed_id Feed id.
 *
 * @return int
 */
function wppfm_get_feed_run_products_added_count( $feed_id ) {
	$feed_id = absint( $feed_id );

	if ( ! $feed_id ) {
		return 0;
	}

	$run_count = get_transient( wppfm_feed_run_products_added_transient_name( $feed_id ) );

	return false === $run_count ? 0 : max( 0, intval( $run_count ) );
}

/**
 * Clears the per-feed in-run progress mirror.
 *
 * @param int $feed_id Feed id.
 *
 * @return void
 */
function wppfm_clear_feed_run_products_added_count( $feed_id ) {
	$feed_id = absint( $feed_id );

	if ( ! $feed_id ) {
		return;
	}

	delete_transient( wppfm_feed_run_products_added_transient_name( $feed_id ) );
}

/**
 * Clears WordPress object-cache entries for a feed counter option.
 *
 * Direct SQL writes bypass update_option(), so cached reads must be
 * invalidated explicitly or later get_option() calls return stale totals.
 *
 * @param int $feed_id Feed id.
 *
 * @return void
 */
function wppfm_invalidate_feed_products_added_counter_cache( $feed_id ) {
	$feed_id = absint( $feed_id );

	if ( ! $feed_id ) {
		return;
	}

	$option_name = wppfm_feed_products_added_counter_option_name( $feed_id );

	if ( is_multisite() ) {
		global $wpdb;

		wp_cache_delete( $wpdb->siteid . ':' . $option_name, 'site-options' );
	} else {
		wp_cache_delete( $option_name, 'options' );
	}
}

/**
 * Returns the authoritative products-added counter for a feed.
 *
 * @param int  $feed_id       Feed id.
 * @param bool $bypass_cache  When true, bypasses object-cache before reading.
 *
 * @return int
 */
function wppfm_get_feed_products_added_counter( $feed_id, $bypass_cache = false ) {
	$feed_id = absint( $feed_id );

	if ( ! $feed_id ) {
		return 0;
	}

	if ( $bypass_cache ) {
		wppfm_invalidate_feed_products_added_counter_cache( $feed_id );
	}

	$option_name = wppfm_feed_products_added_counter_option_name( $feed_id );
	$value       = is_multisite() ? get_site_option( $option_name, 0 ) : get_option( $option_name, 0 );

	return max( 0, intval( $value ) );
}

/**
 * Sets the authoritative products-added counter to an absolute total.
 *
 * The value is monotonic: repeated calls with the same or lower total are no-ops,
 * making batch-boundary commits idempotent when a slice is retried.
 *
 * @param int $feed_id Feed id.
 * @param int $count   Absolute products-added total for the feed run so far.
 *
 * @return int Persisted counter value.
 */
function wppfm_set_feed_products_added_counter( $feed_id, $count ) {
	$feed_id = absint( $feed_id );
	$count   = max( 0, absint( $count ) );

	if ( ! $feed_id ) {
		return 0;
	}

	$current = wppfm_get_feed_products_added_counter( $feed_id, true );

	if ( $count <= $current ) {
		return $current;
	}

	$option_name = wppfm_feed_products_added_counter_option_name( $feed_id );

	if ( is_multisite() ) {
		update_site_option( $option_name, (string) $count );
	} else {
		update_option( $option_name, (string) $count, false );
	}

	wppfm_invalidate_feed_products_added_counter_cache( $feed_id );

	return wppfm_get_feed_products_added_counter( $feed_id, true );
}

/**
 * Atomically increments the products-added counter for a feed.
 *
 * Prefer wppfm_set_feed_products_added_counter() at batch boundaries; increment
 * remains available for exceptional additive adjustments.
 *
 * @param int $feed_id Feed id.
 * @param int $count   Number of products to add.
 *
 * @return int Updated counter value.
 */
function wppfm_increment_feed_products_added_counter( $feed_id, $count ) {
	global $wpdb;

	$feed_id = absint( $feed_id );
	$count   = absint( $count );

	if ( ! $feed_id || $count < 1 ) {
		return wppfm_get_feed_products_added_counter( $feed_id, true );
	}

	$option_name = wppfm_feed_products_added_counter_option_name( $feed_id );
	$count_value = (string) $count;

	if ( is_multisite() ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic increment requires a single SQL statement.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)
				ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + %d",
				$wpdb->siteid,
				$option_name,
				$count_value,
				$count
			)
		);
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic increment requires a single SQL statement.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
				ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + %d",
				$option_name,
				$count_value,
				$count
			)
		);
	}

	wppfm_invalidate_feed_products_added_counter_cache( $feed_id );

	return wppfm_get_feed_products_added_counter( $feed_id, true );
}

/**
 * Returns the legacy global progress transient used by the Feed Editor bar.
 *
 * @return int
 */
function wppfm_get_legacy_feed_products_processed_transient_count() {
	$legacy_count = get_transient( 'wppfm_nr_of_processed_products' );

	return false === $legacy_count ? 0 : max( 0, intval( $legacy_count ) );
}

/**
 * Returns true when the given feed is the active feed for progress UI updates.
 *
 * @param int $feed_id Feed id.
 *
 * @return bool
 */
function wppfm_is_active_feed_for_products_progress( $feed_id ) {
	$feed_id    = absint( $feed_id );
	$active_feed = get_transient( 'wppfm_active_feed_id' );

	return $feed_id > 0 && absint( $active_feed ) === $feed_id;
}

/**
 * Updates ephemeral progress mirrors for the Feed Editor progress bar.
 *
 * Mirrors are monotonic and scoped per feed. The legacy global transient is only
 * touched when this feed is the active feed so concurrent queue workers cannot
 * overwrite each other's totals.
 *
 * @param int $feed_id Feed id.
 * @param int $count   Absolute products-added total for the feed run so far.
 *
 * @return void
 */
function wppfm_update_feed_products_added_progress_mirrors( $feed_id, $count ) {
	$feed_id = absint( $feed_id );
	$count   = max( 0, absint( $count ) );

	if ( ! $feed_id || $count < 1 ) {
		return;
	}

	$ttl          = wppfm_feed_products_progress_transient_ttl();
	$current_run  = wppfm_get_feed_run_products_added_count( $feed_id );
	$target_count = max( $count, $current_run );

	if ( $target_count > $current_run ) {
		set_transient(
			wppfm_feed_run_products_added_transient_name( $feed_id ),
			$target_count,
			$ttl
		);
	}

	if ( wppfm_is_active_feed_for_products_progress( $feed_id ) ) {
		$legacy_count = wppfm_get_legacy_feed_products_processed_transient_count();

		if ( $target_count > $legacy_count ) {
			set_transient(
				'wppfm_nr_of_processed_products',
				$target_count,
				$ttl
			);
		}
	}
}

/**
 * Mirrors the authoritative counter into legacy UI transients for the active feed.
 *
 * Never lowers mirrored values so the progress bar cannot jump backwards.
 *
 * @param int $feed_id Feed id.
 *
 * @return void
 */
function wppfm_sync_feed_products_added_progress_transient( $feed_id ) {
	$feed_id = absint( $feed_id );

	if ( ! $feed_id ) {
		return;
	}

	$sync_count = max(
		wppfm_get_feed_products_added_counter( $feed_id, true ),
		wppfm_get_feed_run_products_added_count( $feed_id )
	);

	wppfm_update_feed_products_added_progress_mirrors( $feed_id, $sync_count );
}

/**
 * Ensures the authoritative counter matches an expected running total.
 *
 * @param int $feed_id        Feed id.
 * @param int $expected_total Expected products-added total for the feed.
 *
 * @return int Persisted counter value after reconciliation.
 */
function wppfm_reconcile_feed_products_added_counter( $feed_id, $expected_total ) {
	$feed_id        = absint( $feed_id );
	$expected_total = max( 0, absint( $expected_total ) );

	if ( ! $feed_id ) {
		return 0;
	}

	$persisted_total = wppfm_set_feed_products_added_counter( $feed_id, $expected_total );

	wppfm_sync_feed_products_added_progress_transient( $feed_id );

	return max( $persisted_total, $expected_total );
}

/**
 * Persists the latest in-batch products-added total into progress mirrors.
 *
 * @param int      $total_handled_products Running products-added total for the active feed.
 * @param int|null $feed_id                Feed id for per-feed progress mirrors.
 *
 * @return void
 */
function wppfm_persist_feed_products_added_progress_count( $total_handled_products, $feed_id = null ) {
	$feed_id = absint( $feed_id );

	if ( ! $feed_id ) {
		return;
	}

	wppfm_update_feed_products_added_progress_mirrors( $feed_id, $total_handled_products );
}

/**
 * Clears the legacy global progress transient used by the Feed Editor bar.
 *
 * @return void
 */
function wppfm_clear_feed_products_progress_transient() {
	delete_transient( 'wppfm_nr_of_processed_products' );
}

/**
 * Returns the authoritative products-added count for feed completion and persistence.
 *
 * @param int $feed_id Feed id.
 *
 * @return int
 */
function wppfm_get_authoritative_feed_products_added_count( $feed_id ) {
	return wppfm_get_feed_products_added_counter( absint( $feed_id ), true );
}

/**
 * Resolves the best available products-added count for progress / diagnostics.
 *
 * During an active run the progress mirror can be ahead of the authoritative option
 * until the next batch commit. Use the higher per-feed value so the progress bar moves
 * smoothly without consulting the shared legacy transient (which is not feed-scoped).
 *
 * @param int|null $feed_id       Optional feed id. Falls back to the active feed transient.
 * @param bool     $bypass_cache  When true, bypasses object-cache for the authoritative read.
 *
 * @return int
 */
function wppfm_resolve_feed_products_added_count( $feed_id = null, $bypass_cache = false ) {
	if ( null === $feed_id ) {
		$feed_id = get_transient( 'wppfm_active_feed_id' );
	}

	$feed_id = absint( $feed_id );

	if ( $feed_id ) {
		return max(
			wppfm_get_feed_products_added_counter( $feed_id, $bypass_cache ),
			wppfm_get_feed_run_products_added_count( $feed_id )
		);
	}

	return wppfm_get_legacy_feed_products_processed_transient_count();
}
