<?php

/**
 * WP Product Feed Manager Performance Prioritizer.
 *
 * Computes product performance metrics (revenue, orders, tier) from WooCommerce Analytics
 * lookup tables and persists them to the feedmanager_product_performance table.
 *
 * @package WP Product Feed Manager/Application/Classes
 * @since 3.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Performance_Prioritizer' ) ) :

	/**
	 * Performance Prioritizer service class.
	 */
	class WPPFM_Performance_Prioritizer {

		/**
		 * @var WPPFM_Queries
		 */
		private $_queries;

		/**
		 * @var WPPFM_Data
		 */
		private $_data;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->_queries = new WPPFM_Queries();
			$this->_data   = new WPPFM_Data();
		}

		/**
		 * Runs the performance computation for a feed and writes results to the database.
		 *
		 * @param int      $feed_id        Feed ID.
		 * @param int|null $period_days    Override period (optional). Clamped to 7–365.
		 * @param int|null $high_percentage Override high tier percentage (optional). Clamped to 1–100.
		 *
		 * @return array{ success: bool, message?: string, updated_gmt?: string, analyzed_count?: int }
		 */
		public function update_performance_data( $feed_id, $period_days = null, $high_percentage = null ) {
			$feed_id = (int) $feed_id;
			if ( $feed_id <= 0 ) {
				return array( 'success' => false, 'message' => __( 'Invalid feed ID.', 'wp-product-feed-manager' ) );
			}

			$feed_data = $this->_data->get_feed_data( $feed_id );
			if ( ! $feed_data ) {
				return array( 'success' => false, 'message' => __( 'Could not load feed data.', 'wp-product-feed-manager' ) );
			}

			$meta = $this->_queries->get_feed_performance_meta( $feed_id );

			// Resolve period and high percentage (from args or feed meta). Meta-derived values are clamped
			// to supported ranges to ensure deterministic behavior regardless of source.
			if ( null !== $period_days ) {
				$period_days = $this->clamp_period_days( (int) $period_days );
				$this->_queries->update_feed_performance_meta( $feed_id, 'wppfm_performance_period_days', (string) $period_days );
			} else {
				$period_days = $this->clamp_period_days( (int) ( $meta['wppfm_performance_period_days'] ?? 30 ) );
			}

			if ( null !== $high_percentage ) {
				$high_percentage = $this->clamp_high_percentage( (int) $high_percentage );
				$this->_queries->update_feed_performance_meta( $feed_id, 'wppfm_performance_high_percentage', (string) $high_percentage );
			} else {
				$high_percentage = $this->clamp_high_percentage( (int) ( $meta['wppfm_performance_high_percentage'] ?? 20 ) );
			}

			$product_ids = $this->get_product_ids_for_feed( $feed_data );
			if ( empty( $product_ids ) ) {
				$updated_gmt = gmdate( 'Y-m-d H:i:s' );
				$this->_queries->delete_performance_rows_for_feed( $feed_id, $period_days );
				$this->_queries->update_feed_performance_meta( $feed_id, 'wppfm_performance_last_update_gmt', $updated_gmt );
				$this->_queries->update_feed_performance_meta( $feed_id, 'wppfm_performance_last_analyzed_count', '0' );
				return array(
					'success'        => true,
					'updated_gmt'    => $updated_gmt,
					'analyzed_count' => 0,
				);
			}

			if ( ! $this->woocommerce_lookup_tables_exist() ) {
				$msg = __( 'WooCommerce Analytics lookup tables are missing. Performance data cannot be computed.', 'wp-product-feed-manager' );
				do_action( 'wppfm_feed_generation_message', $feed_id, $msg, 'ERROR' );
				return array( 'success' => false, 'message' => $msg );
			}

			$include_variations = '1' === ( $feed_data->includeVariations ?? '0' );

			try {
				$metrics = $this->fetch_metrics_from_analytics( $product_ids, $period_days, $include_variations );
				$rows    = $this->classify_and_build_rows( $product_ids, $metrics, $period_days, $high_percentage );

				$this->write_performance_data_atomically( $feed_id, $period_days, $rows, count( $product_ids ) );
			} catch ( \Throwable $e ) {
				do_action( 'wppfm_feed_generation_message', $feed_id, $e->getMessage(), 'ERROR' );
				return array( 'success' => false, 'message' => $e->getMessage() );
			}

			$updated_gmt = gmdate( 'Y-m-d H:i:s' );

			return array(
				'success'        => true,
				'updated_gmt'    => $updated_gmt,
				'analyzed_count' => count( $product_ids ),
			);
		}

		/**
		 * Gets product IDs that would be queued for the feed (same logic as Feed Master).
		 *
		 * @param object $feed_data Feed object with categoryMapping and includeVariations.
		 *
		 * @return int[]
		 */
		private function get_product_ids_for_feed( $feed_data ) {
			$category_string = $this->make_category_selection_string( $feed_data->categoryMapping ?? '' );
			$category_string = apply_filters( 'wppfm_selected_categories', $category_string, $feed_data->feedId ?? 0 );

			$include_variations = '1' === ( $feed_data->includeVariations ?? '0' );

			$product_ids = $this->_queries->get_all_post_ids_for_categories( $category_string, $include_variations, $feed_data->feedId ?? 0 );
			$product_ids = array_filter( array_unique( array_map( 'absint', $product_ids ) ) );

			return apply_filters( 'wppfm_products_in_feed_queue', array_values( $product_ids ), $feed_data->feedId ?? 0 );
		}

		/**
		 * Builds comma-separated category IDs from category mapping JSON.
		 *
		 * @param string $category_mapping JSON category mapping.
		 *
		 * @return string
		 */
		private function make_category_selection_string( $category_mapping ) {
			$category_selection_string = '';
			$category_mapping          = json_decode( $category_mapping );

			if ( ! empty( $category_mapping ) ) {
				foreach ( $category_mapping as $category ) {
					if ( isset( $category->shopCategoryId ) ) {
						$category_selection_string .= (int) $category->shopCategoryId . ', ';
					}
				}
			}

			return $category_selection_string ? substr( $category_selection_string, 0, -2 ) : '';
		}

		/**
		 * Checks if WooCommerce Analytics lookup tables exist.
		 *
		 * @return bool
		 */
		private function woocommerce_lookup_tables_exist() {
			global $wpdb;
			$product_table = $wpdb->prefix . 'wc_order_product_lookup';
			$stats_table   = $wpdb->prefix . 'wc_order_stats';

			$product_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $product_table ) ) === $product_table;
			$stats_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $stats_table ) ) === $stats_table;

			return $product_exists && $stats_exists;
		}

		/**
		 * Fetches revenue and orders from WooCommerce Analytics for the product set.
		 * Uses TEMPORARY TABLE when supported to avoid giant IN(...) lists; falls back
		 * to chunked analytics queries on hosts that block TEMPORARY TABLEs.
		 *
		 * @param int[] $product_ids        Product post IDs (feed queue).
		 * @param int   $period_days        Analysis period in days.
		 * @param bool  $include_variations Whether feed includes variations (affects ID mapping).
		 *
		 * @return array<int, array{ revenue: float, orders_count: int }> Keyed by product_id.
		 */
		private function fetch_metrics_from_analytics( $product_ids, $period_days, $include_variations ) {
			$force_fallback = (bool) apply_filters( 'wppfm_performance_force_chunked_analytics_fallback', false );

			if ( $force_fallback ) {
				return $this->fetch_metrics_via_chunked_analytics( $product_ids, $period_days, $include_variations );
			}

			$metrics = $this->fetch_metrics_via_temp_table( $product_ids, $period_days, $include_variations );

			if ( null === $metrics ) {
				return $this->fetch_metrics_via_chunked_analytics( $product_ids, $period_days, $include_variations );
			}

			return $metrics;
		}

		/**
		 * Attempts to fetch metrics using a MySQL TEMPORARY TABLE for the product ID set.
		 * Returns null if TEMPORARY TABLE creation fails (fallback to chunked queries).
		 *
		 * @param int[] $product_ids        Product post IDs.
		 * @param int   $period_days        Analysis period in days.
		 * @param bool  $include_variations Whether feed includes variations.
		 *
		 * @return array<int, array{ revenue: float, orders_count: int }>|null Keyed by product_id, or null on failure.
		 */
		private function fetch_metrics_via_temp_table( $product_ids, $period_days, $include_variations ) {
			global $wpdb;

			$temp_table = $wpdb->prefix . 'wppfm_perf_ids_' . wp_rand( 10000, 99999 );
			$create_sql = "CREATE TEMPORARY TABLE $temp_table ( product_id BIGINT UNSIGNED PRIMARY KEY )";

			if ( false === $wpdb->query( $create_sql ) ) {
				return null;
			}

			$insert_chunk_size = (int) apply_filters( 'wppfm_performance_temp_table_insert_chunk_size', 5000 );
			$insert_chunk_size = max( 1, min( 10000, $insert_chunk_size ) );

			$chunks = array_chunk( array_map( 'absint', $product_ids ), $insert_chunk_size );

			foreach ( $chunks as $chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '(%d)' ) );
				$insert_sql = "INSERT IGNORE INTO $temp_table (product_id) VALUES $placeholders";
				$wpdb->query( $wpdb->prepare( $insert_sql, $chunk ) );
			}

			$lookup_table  = $wpdb->prefix . 'wc_order_product_lookup';
			$stats_table   = $wpdb->prefix . 'wc_order_stats';
			$order_statuses = apply_filters( 'wppfm_performance_order_statuses', array( 'wc-processing', 'wc-completed' ) );
			$revenue_col   = apply_filters( 'wppfm_performance_revenue_column', 'product_net_revenue' );

			$allowed_cols = array( 'product_net_revenue', 'product_gross_revenue' );
			if ( ! in_array( $revenue_col, $allowed_cols, true ) ) {
				$revenue_col = 'product_net_revenue';
			}

			$start_gmt = gmdate( 'Y-m-d H:i:s', strtotime( "-{$period_days} days" ) );
			$placeholders = implode( ',', array_fill( 0, count( $order_statuses ), '%s' ) );

			$id_expr = $include_variations
				? "CASE WHEN opl.variation_id > 0 THEN opl.variation_id ELSE opl.product_id END"
				: 'opl.product_id';

			// Join with temp table: match either product_id or variation_id for feed products.
			$join_cond = $include_variations
				? "( t.product_id = opl.product_id OR t.product_id = opl.variation_id )"
				: "t.product_id = opl.product_id";

			$sql = $wpdb->prepare(
				"SELECT $id_expr AS feed_product_id,
					SUM(opl.{$revenue_col}) AS revenue,
					COUNT(DISTINCT opl.order_id) AS orders_count
				FROM {$lookup_table} opl
				INNER JOIN {$stats_table} os ON opl.order_id = os.order_id
				INNER JOIN $temp_table t ON $join_cond
				WHERE os.date_created_gmt >= %s
				AND os.status IN ($placeholders)
				GROUP BY feed_product_id",
				array_merge( array( $start_gmt ), $order_statuses )
			);

			$results = $wpdb->get_results( $sql );

			$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS $temp_table" );

			$metrics = array();
			if ( $results ) {
				foreach ( $results as $row ) {
					$pid = (int) $row->feed_product_id;
					$metrics[ $pid ] = array(
						'revenue'      => (float) $row->revenue,
						'orders_count' => (int) $row->orders_count,
					);
				}
			}

			return $metrics;
		}

		/**
		 * Fetches metrics via chunked analytics queries with bounded IN(...) lists.
		 * Fallback when TEMPORARY TABLE is not available.
		 *
		 * @param int[] $product_ids        Product post IDs.
		 * @param int   $period_days        Analysis period in days.
		 * @param bool  $include_variations Whether feed includes variations.
		 *
		 * @return array<int, array{ revenue: float, orders_count: int }> Keyed by product_id.
		 */
		private function fetch_metrics_via_chunked_analytics( $product_ids, $period_days, $include_variations ) {
			global $wpdb;

			$chunk_size = (int) apply_filters( 'wppfm_performance_analytics_id_chunk_size', 2000 );
			$chunk_size = max( 1, min( 5000, $chunk_size ) );

			$lookup_table  = $wpdb->prefix . 'wc_order_product_lookup';
			$stats_table   = $wpdb->prefix . 'wc_order_stats';
			$order_statuses = apply_filters( 'wppfm_performance_order_statuses', array( 'wc-processing', 'wc-completed' ) );
			$revenue_col   = apply_filters( 'wppfm_performance_revenue_column', 'product_net_revenue' );

			$allowed_cols = array( 'product_net_revenue', 'product_gross_revenue' );
			if ( ! in_array( $revenue_col, $allowed_cols, true ) ) {
				$revenue_col = 'product_net_revenue';
			}

			$start_gmt = gmdate( 'Y-m-d H:i:s', strtotime( "-{$period_days} days" ) );
			$status_placeholders = implode( ',', array_fill( 0, count( $order_statuses ), '%s' ) );

			$id_expr = $include_variations
				? "CASE WHEN opl.variation_id > 0 THEN opl.variation_id ELSE opl.product_id END"
				: 'opl.product_id';

			$metrics = array();
			$product_ids_safe = array_map( 'absint', $product_ids );
			$chunks = array_chunk( $product_ids_safe, $chunk_size );

			foreach ( $chunks as $chunk ) {
				$id_placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				$query_args = array_merge( array( $start_gmt ), $order_statuses, $chunk, $chunk );

				$sql = $wpdb->prepare(
					"SELECT $id_expr AS feed_product_id,
						SUM(opl.{$revenue_col}) AS revenue,
						COUNT(DISTINCT opl.order_id) AS orders_count
					FROM {$lookup_table} opl
					INNER JOIN {$stats_table} os ON opl.order_id = os.order_id
					WHERE os.date_created_gmt >= %s
					AND os.status IN ($status_placeholders)
					AND (opl.product_id IN ($id_placeholders) OR opl.variation_id IN ($id_placeholders))
					GROUP BY feed_product_id",
					$query_args
				);

				$results = $wpdb->get_results( $sql );
				if ( $results ) {
					foreach ( $results as $row ) {
						$pid = (int) $row->feed_product_id;
						if ( ! isset( $metrics[ $pid ] ) ) {
							$metrics[ $pid ] = array( 'revenue' => 0.0, 'orders_count' => 0 );
						}
						$metrics[ $pid ]['revenue']      += (float) $row->revenue;
						$metrics[ $pid ]['orders_count'] += (int) $row->orders_count;
					}
				}
			}

			return $metrics;
		}

		/**
		 * Classifies products into tiers and builds rows for the performance table.
		 * Only returns rows for products with orders_count > 0. Zero-sales products
		 * default to low/0/0 during feed injection and do not require storage.
		 *
		 * Tier rules:
		 * - low: orders_count = 0 (not stored; represented by missing row).
		 * - high: top X% by revenue (among products with orders > 0), tie-breaker orders desc, product_id asc.
		 * - mid: remaining with orders > 0.
		 *
		 * @param int[] $product_ids     Full list of product IDs in feed.
		 * @param array $metrics        revenue/orders_count keyed by product_id.
		 * @param int   $period_days    Period in days.
		 * @param int   $high_percentage High tier percentage (1–100).
		 *
		 * @return array[] Rows with product_id, orders_count, revenue, performance_tier (only sold products).
		 */
		private function classify_and_build_rows( $product_ids, $metrics, $period_days, $high_percentage ) {
			$high_percentage = (int) apply_filters( 'wppfm_performance_high_percentage', $high_percentage );

			$with_sales = array();

			foreach ( $product_ids as $pid ) {
				$revenue = (float) ( $metrics[ $pid ]['revenue'] ?? 0 );
				$orders  = (int) ( $metrics[ $pid ]['orders_count'] ?? 0 );

				if ( $orders > 0 ) {
					$with_sales[] = array(
						'product_id'   => $pid,
						'orders_count' => $orders,
						'revenue'      => $revenue,
					);
				}
			}

			// Sort with_sales: revenue desc, orders desc, product_id asc.
			usort( $with_sales, function ( $a, $b ) {
				if ( $b['revenue'] !== $a['revenue'] ) {
					return $b['revenue'] <=> $a['revenue'];
				}
				if ( $b['orders_count'] !== $a['orders_count'] ) {
					return $b['orders_count'] <=> $a['orders_count'];
				}
				return $a['product_id'] <=> $b['product_id'];
			} );

			$n_with_sales = count( $with_sales );
			$n_high       = $n_with_sales > 0 ? max( 1, (int) ceil( $n_with_sales * $high_percentage / 100 ) ) : 0;

			$rows = array();

			foreach ( $with_sales as $i => $item ) {
				$tier = $i < $n_high ? 'high' : 'mid';
				$rows[] = array(
					'product_id'       => $item['product_id'],
					'orders_count'     => $item['orders_count'],
					'revenue'          => $item['revenue'],
					'performance_tier' => $tier,
				);
			}

			return $rows;
		}

		/**
		 * Writes performance rows atomically (transaction when supported) or with
		 * conservative fallback. Meta keys are updated only on successful completion.
		 *
		 * @param int   $feed_id        Feed ID.
		 * @param int   $period_days    Period in days.
		 * @param array $rows           Rows to write (only sold products).
		 * @param int   $analyzed_count Total products analyzed (for meta).
		 */
		private function write_performance_data_atomically( $feed_id, $period_days, $rows, $analyzed_count ) {
			global $wpdb;

			$updated_gmt = gmdate( 'Y-m-d H:i:s' );

			// Best-effort transaction for atomic delete + insert + meta.
			//
			// IMPORTANT: `$wpdb->query()` returns `0` for many successful statements (including START TRANSACTION),
			// so we must only treat `false` as failure. Treating `0` as failure would leave an open transaction
			// and can make subsequent option writes (like the background queue state) invisible to the async request.
			$transaction_start_result = $wpdb->query( 'START TRANSACTION' );
			$use_transaction          = ( false !== $transaction_start_result );

			if ( $use_transaction ) {
				if ( apply_filters( 'wppfm_enable_feed_state_logging', false ) ) {
					do_action(
						'wppfm_feed_generation_message',
						$feed_id,
						sprintf(
							'Performance prioritizer started a DB transaction (start_result=%s).',
							is_scalar( $transaction_start_result ) ? strval( $transaction_start_result ) : gettype( $transaction_start_result )
						)
					);
				}

				try {
					$delete_result = $this->_queries->delete_performance_rows_for_feed( $feed_id, $period_days );
					if ( false === $delete_result ) {
						throw new \RuntimeException( 'Failed to delete old performance rows before insert.' );
					}

					$insert_result = $this->_queries->insert_performance_rows( $feed_id, $period_days, $rows );
					if ( false === $insert_result ) {
						throw new \RuntimeException( 'Failed to insert performance rows.' );
					}

					$meta_1 = $this->_queries->update_feed_performance_meta( $feed_id, 'wppfm_performance_last_update_gmt', $updated_gmt );
					$meta_2 = $this->_queries->update_feed_performance_meta( $feed_id, 'wppfm_performance_last_analyzed_count', (string) $analyzed_count );
					if ( false === $meta_1 || false === $meta_2 ) {
						throw new \RuntimeException( 'Failed to update performance meta after insert.' );
					}

					$commit_result = $wpdb->query( 'COMMIT' );
					if ( false === $commit_result ) {
						throw new \RuntimeException( 'Failed to commit performance transaction.' );
					}

					return;
				} catch ( \Throwable $e ) {
					$wpdb->query( 'ROLLBACK' );
					throw $e;
				}
			}

			// Fallback: insert first (upsert), then remove stale rows.
			$this->_queries->insert_performance_rows( $feed_id, $period_days, $rows );

			$new_product_ids = array_column( $rows, 'product_id' );
			$new_product_ids = array_map( 'absint', $new_product_ids );

			if ( ! empty( $new_product_ids ) ) {
				$chunk_size = (int) apply_filters( 'wppfm_performance_insert_chunk_size', 1000 );
				$chunk_size = max( 1, min( 5000, $chunk_size ) );

				$existing = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT product_id FROM {$wpdb->prefix}feedmanager_product_performance WHERE product_feed_id = %d AND period_days = %d",
						$feed_id,
						$period_days
					)
				);
				$existing = array_map( 'intval', (array) $existing );
				$to_delete = array_diff( $existing, $new_product_ids );

				if ( ! empty( $to_delete ) ) {
					$delete_chunks = array_chunk( array_values( $to_delete ), $chunk_size );
					$table = $wpdb->prefix . 'feedmanager_product_performance';
					foreach ( $delete_chunks as $chunk ) {
						$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
						$wpdb->query(
							$wpdb->prepare(
								"DELETE FROM $table WHERE product_feed_id = %d AND period_days = %d AND product_id IN ($placeholders)",
								array_merge( array( $feed_id, $period_days ), $chunk )
							)
						);
					}
				}
			} else {
				$this->_queries->delete_performance_rows_for_feed( $feed_id, $period_days );
			}

			$this->_queries->update_feed_performance_meta( $feed_id, 'wppfm_performance_last_update_gmt', $updated_gmt );
			$this->_queries->update_feed_performance_meta( $feed_id, 'wppfm_performance_last_analyzed_count', (string) $analyzed_count );
		}

		/**
		 * Clamps period days to valid range.
		 *
		 * @param int $days Raw value.
		 *
		 * @return int Clamped to 7–365.
		 */
		private function clamp_period_days( $days ) {
			return max( 7, min( 365, (int) $days ) );
		}

		/**
		 * Clamps high percentage to valid range.
		 *
		 * @param int $pct Raw value.
		 *
		 * @return int Clamped to 1–100.
		 */
		private function clamp_high_percentage( $pct ) {
			return max( 1, min( 100, (int) $pct ) );
		}
	}

endif;

/**
 * Runs performance data update before feed generation when enabled for the feed.
 *
 * Hooked to wppfm_feed_process_prepared. If the feed has performance prioritizing
 * enabled, updates the product performance metrics so mapping/filtering can use them.
 *
 * @param int  $feed_id Feed ID.
 * @param bool $silent  Whether the process runs silently (no UI output).
 *
 * @since 3.21.0
 */
function wppfm_maybe_update_performance_before_feed_generation( $feed_id, $silent ) {
	if ( ! $feed_id || $feed_id <= 0 ) {
		return;
	}

	$queries = new WPPFM_Queries();
	$meta    = $queries->get_feed_performance_meta( $feed_id, array( 'wppfm_performance_enabled' ) );

	if ( 'true' !== ( $meta['wppfm_performance_enabled'] ?? 'false' ) ) {
		return;
	}

	$prioritizer = new WPPFM_Performance_Prioritizer();
	$prioritizer->update_performance_data( $feed_id, null, null );
}

add_action( 'wppfm_feed_process_prepared', 'wppfm_maybe_update_performance_before_feed_generation', 5, 2 );

add_action( 'wppfm_feed_processing_batch_loaded', 'wppfm_prefetch_performance_for_batch', 10, 4 );

/**
 * Gets or sets the per-request performance cache.
 * Null means cache not initialized; array means prefetch completed (product_id => row).
 *
 * @param array<int, object>|null $cache Optional. Cache to set. Omit to read.
 *
 * @return array<int, object>|null Current cache when reading.
 */
function wppfm_performance_cache( $cache = null ) {
	static $store = null;

	if ( func_num_args() > 0 ) {
		$store = $cache;
	}

	return $store;
}

/**
 * Prefetches performance rows for the current batch and stores in per-request cache.
 * Reduces N+1 queries during feed processing to ~1 query per batch.
 *
 * @param int    $feed_id     Feed ID.
 * @param int[]  $product_ids Product IDs in the batch.
 * @param object $feed_data   Feed data object.
 * @param array  $pre_data    Pre-data (column_names, etc.).
 */
function wppfm_prefetch_performance_for_batch( $feed_id, $product_ids, $feed_data, $pre_data ) {
	$perf_fields   = array( 'wppfm_performance_tier', 'wppfm_performance_revenue', 'wppfm_performance_orders' );
	$column_names  = isset( $pre_data['column_names'] ) ? (array) $pre_data['column_names'] : array();
	$needs_performance = ! empty( array_intersect( $perf_fields, $column_names ) );

	if ( ! $needs_performance || empty( $product_ids ) || ! $feed_id ) {
		wppfm_performance_cache( array() );
		return;
	}

	$queries     = new WPPFM_Queries();
	$meta        = $queries->get_feed_performance_meta( $feed_id, array( 'wppfm_performance_enabled', 'wppfm_performance_period_days' ) );

	// Do not prefetch (and therefore do not expose) performance data when prioritizing is disabled for this feed.
	// This prevents "stale" performance rows from leaking into the generated feed when users disable the feature.
	if ( 'true' !== ( $meta['wppfm_performance_enabled'] ?? 'false' ) ) {
		wppfm_performance_cache( array() );
		return;
	}

	$period_days = (int) ( $meta['wppfm_performance_period_days'] ?? 30 );
	$period_days = max( 7, min( 365, $period_days ) );

	wppfm_performance_cache( $queries->get_performance_for_products( $feed_id, $product_ids, $period_days ) );
}
