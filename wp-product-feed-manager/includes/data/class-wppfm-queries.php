<?php

/**
 * WP Queries Class.
 *
 * @package WP Product Feed Manager/Data/Classes
 * @version 4.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Queries' ) ) :

	/**
	 * WP Queries Class
	 */
	class WPPFM_Queries {

		private $_wpdb;

		/**
		 * @var string placeholder containing the wp table prefix
		 */
		private $_table_prefix;

		/**
		 * WPPFM_Queries Constructor
		 */
		public function __construct() {
			// get global WordPress database functions
			global $wpdb;

			// assign the global wpdb to a variable
			$this->_wpdb = &$wpdb;

			// assign the wp table prefix to a variable
			$this->_table_prefix = $this->_wpdb->prefix;
		}

		public function get_feeds_list() {
			return $this->_wpdb->get_results(
				"SELECT p.product_feed_id, p.title, p.url, p.updated, p.feed_type_id, p.products, p.channel_id, s.status AS status, s.color AS color FROM {$this->_table_prefix}feedmanager_product_feed AS p
				INNER JOIN {$this->_table_prefix}feedmanager_feed_status AS s on p.status_id = s.status_id"
			);
		}

		public function get_all_feed_names() {
			return $this->_wpdb->get_results( "SELECT title FROM {$this->_table_prefix}feedmanager_product_feed" );
		}

		/**
		 * Request-level cache for feed rows to avoid duplicate queries when the same
		 * feed is read from multiple places (e.g. wppfm_feed_form_sub_header_text,
		 * wppfm_verify_feeds_channel_is_installed). Keyed by feed_id.
		 *
		 * @var array<string, object|null>
		 */
		private static $_feed_row_cache = array();

		/**
		 * Gets a single feed row by feed ID. Uses request-level caching to avoid
		 * duplicate queries when the same feed is requested multiple times.
		 *
		 * @param int|string $feed_id The product feed ID.
		 * @return object|null The feed row object or null if not found.
		 */
		public function get_feed_row( $feed_id ) {
			$cache_key = (string) $feed_id;
			if ( array_key_exists( $cache_key, self::$_feed_row_cache ) ) {
				return self::$_feed_row_cache[ $cache_key ];
			}
			$row = $this->_wpdb->get_row(
				$this->_wpdb->prepare( "SELECT * FROM {$this->_table_prefix}feedmanager_product_feed WHERE product_feed_id = %d", $feed_id ) );
			self::$_feed_row_cache[ $cache_key ] = $row;
			return $row;
		}

		/**
		 * Get a list of all existing countries
		 *
		 * @return array|object|null of the query
		 */
		public function read_countries() {
			return $this->_wpdb->get_results( "SELECT name_short, name FROM {$this->_table_prefix}feedmanager_country ORDER BY name", ARRAY_A );
		}

		public function get_feedmanager_channel_table() {
			return $this->_wpdb->get_results( "SELECT * FROM {$this->_table_prefix}feedmanager_channel", ARRAY_A );
		}

		public function get_feedmanager_product_feed_table() {
			return $this->_wpdb->get_results( "SELECT * FROM {$this->_table_prefix}feedmanager_product_feed", ARRAY_A );
		}

		public function get_feedmanager_product_feedmeta_table() {
			return $this->_wpdb->get_results( "SELECT * FROM {$this->_table_prefix}feedmanager_product_feedmeta", ARRAY_A );
		}

		public function get_feed_type_id( $feed_id ) {
			return $this->_wpdb->get_var(
				$this->_wpdb->prepare( "SELECT feed_type_id FROM {$this->_table_prefix}feedmanager_product_feed WHERE product_feed_id = %d", $feed_id ) );
		}

		/**
		 * Request-level cache for installed channels to avoid duplicate queries.
		 * Cleared when channels are modified via register/remove/clean operations.
		 *
		 * @var array|null
		 */
		private static $_installed_channels_cache = null;

		/**
		 * Reads installed channels from the database. Uses request-level caching to avoid
		 * duplicate queries when called from multiple places (e.g. include_channels,
		 * wppfm_register_full_version_channels, channel_selector).
		 *
		 * @return array Installed channels with channel_id, name, and short keys.
		 */
		public function read_installed_channels() {
			 $google = array( 'channel_id' => '1', 'name' => 'Google Merchant Centre', 'short' => 'google' );
			 return array( $google );
		}

		public function register_a_channel( $channel_short_name, $channel_id, $channel_name ) {
			self::$_installed_channels_cache = null; // Invalidate cache when channels change.
			return $this->_wpdb->query(
				$this->_wpdb->prepare(
					"INSERT INTO {$this->_table_prefix}feedmanager_channel (channel_id, name, short) VALUES
					( %d, '%s', '%s' )",
					$channel_id,
					$channel_name,
					$channel_short_name
				)
			);
		}

		public function get_channel_id( $channel_short_name ) {
			return $this->_wpdb->get_var(
				$this->_wpdb->prepare( "SELECT channel_id FROM {$this->_table_prefix}feedmanager_channel WHERE short = %s", $channel_short_name ) );
		}

		public function get_channel_short_name_from_db( $channel_id ) {
			if ( 'undefined' !== $channel_id ) { // make sure the selected channel is installed
				return $this->_wpdb->get_var(
					$this->_wpdb->prepare( "SELECT short FROM {$this->_table_prefix}feedmanager_channel WHERE channel_id = %d", $channel_id ) );
			} else {
				return false;
			}
		}

		public function remove_channel_from_db( $channel_short ) {
			self::$_installed_channels_cache = null; // Invalidate cache when channels change.
			$main_table = $this->_table_prefix . 'feedmanager_channel';

			return $this->_wpdb->delete( $main_table, array( 'short' => $channel_short ) );
		}

		public function clean_channel_table() {
			self::$_installed_channels_cache = null; // Invalidate cache when channels change.
			return $this->_wpdb->query(
				$this->_wpdb->prepare(
					"DELETE FROM {$this->_table_prefix}feedmanager_channel WHERE channel_id = %d OR name = %s",
					0,
					''
				)
			);
		}

		public function read_active_schedule_data() {
			return $this->_wpdb->get_results( "SELECT product_feed_id, updated, schedule FROM {$this->_table_prefix}feedmanager_product_feed WHERE status_id=1", ARRAY_A );
		}

		public function read_failed_feeds() {
			return $this->_wpdb->get_results( "SELECT product_feed_id, updated, schedule FROM {$this->_table_prefix}feedmanager_product_feed WHERE status_id=5 OR status_id=6", ARRAY_A );
		}

		public function read_sources() {
			return $this->_wpdb->get_results( "SELECT source_id, name FROM {$this->_table_prefix}feedmanager_source ORDER BY name", ARRAY_A );
		}

		public function get_feeds_from_specific_channel( $channel_id ) {
			return $this->_wpdb->get_results(
				$this->_wpdb->prepare( "SELECT product_feed_id FROM {$this->_table_prefix}feedmanager_product_feed WHERE channel_id = %d", $channel_id ), ARRAY_A );
		}

		/**
		 * Queries the Post table to get the parent ids of a specific product.
		 *
		 * @param string $product_id the product id.
		 *
		 * @return array with the products' parent ids.
		 */
		public function get_product_parents( $product_id ) {
			return $this->_wpdb->get_results(
				$this->_wpdb->prepare( "SELECT ID FROM {$this->_table_prefix}posts WHERE post_parent = %d AND post_status = 'publish'", $product_id ), ARRAY_A );
		}

		public function read_feed( $feed_id ) {
			$result = $this->_wpdb->get_results(
				$this->_wpdb->prepare( "SELECT p.product_feed_id, p.source_id AS source, p.title, p.feed_title, p.feed_description, p.main_category, "
				. "p.url, p.include_variations, p.is_aggregator, p.aggregator_name, p.status_id, p.base_status_id, p.updated, p.products, p.feed_type_id, p.schedule, "
				. "p.google_analytics, p.utm_id, p.utm_source, p.utm_medium, p.utm_campaign, p.utm_source_platform, p.utm_term, p.utm_content, c.name_short "
				. "AS country, m.channel_id AS channel, p.language, p.currency "
				. "FROM {$this->_table_prefix}feedmanager_product_feed AS p "
				. "INNER JOIN {$this->_table_prefix}feedmanager_country AS c ON p.country_id = c.country_id "
				. "INNER JOIN {$this->_table_prefix}feedmanager_channel AS m ON p.channel_id = m.channel_id "
				. "WHERE p.product_feed_id = %d", $feed_id ),
				ARRAY_A
			);

			$category_mapping = $this->read_category_mapping( $feed_id );

			if ( isset( $category_mapping[0]['meta_value'] ) && $category_mapping[0]['meta_value'] !== '' ) {
				$result[0]['category_mapping'] = $category_mapping[0]['meta_value'];
			} else {
				$result[0]['category_mapping'] = '';
			}

			return $result;
		}

		public function read_category_mapping( $feed_id ) {
			return $this->_wpdb->get_results(
				$this->_wpdb->prepare( "SELECT meta_value FROM {$this->_table_prefix}feedmanager_product_feedmeta WHERE product_feed_id = %d AND meta_key = 'category_mapping'", $feed_id ), ARRAY_A );
		}

		/**
		 * Returns the status data from a specific feed.
		 * Returns:
		 *      - product_feed_id.
		 *      - channel_id.
		 *      - title.z
		 *      - url.
		 *      - status_id.
		 *      - products.
		 *      - feed_type_id.
		 *
		 * @param string $feed_id   The id of the feed.
		 *
		 * @return array    Array with the status data.
		 */
		public function get_feed_status_data( $feed_id ) {
			$status = $this->_wpdb->get_results(
				$this->_wpdb->prepare( 'SELECT product_feed_id, channel_id, title, url, status_id, products, feed_type_id '
				. "FROM {$this->_table_prefix}feedmanager_product_feed "
				. "WHERE product_feed_id = %d", $feed_id ),
				ARRAY_A
			);

			return $status ? $status[0] : null;
		}

		/**
		 * Returns the post-ids that belong to the selected categories
		 *
		 * @param   string $category_string    A string that contains the selected categories.
		 * @param   bool   $with_variation     True if product variations should be included in the feed. Default false.
		 *
		 * @return array    With the post-ids.
		 */
		public function get_post_ids( $category_string, $with_variation = false ) {
			// If the user has not selected a category, return an empty array.
			if ( empty( $category_string ) ) {
				return array();
			}

			$category_string = wp_strip_all_tags( $category_string );

			$start_product_id = get_transient( 'wppfm_start_product_id' ) ? get_transient( 'wppfm_start_product_id' ) : -1;

			// Limit the number of products per query to 1000 to prevent a result that is too large to handle by the server.
			// When the limit is reached, the next batch will be requested by the fill_the_background_queue function.
			// @since 2.11.0.
			$product_query_limit = apply_filters( 'wppfm_product_query_limit', 1000 );

			// @since 2.20.0 excluded password protected products from the feed.
			$products_query = "SELECT DISTINCT {$this->_table_prefix}posts.ID
				FROM {$this->_table_prefix}posts
				LEFT JOIN {$this->_table_prefix}term_relationships ON ({$this->_table_prefix}posts.ID = {$this->_table_prefix}term_relationships.object_id)
				LEFT JOIN {$this->_table_prefix}term_taxonomy ON ({$this->_table_prefix}term_relationships.term_taxonomy_id = {$this->_table_prefix}term_taxonomy.term_taxonomy_id)
				WHERE {$this->_table_prefix}posts.post_type = 'product' AND {$this->_table_prefix}posts.post_status = 'publish' AND {$this->_table_prefix}posts.post_password = ''
				AND {$this->_table_prefix}term_taxonomy.term_id IN ($category_string)
				AND {$this->_table_prefix}posts.ID > $start_product_id
				ORDER BY ID LIMIT $product_query_limit";

			// Get all main product ids (simple and variable, but not the variations).
			$main_products_ids = $this->_wpdb->get_col( $products_query );

			set_transient( 'wppfm_start_product_id', end( $main_products_ids ), WPPFM_TRANSIENT_LIVE );

			// If variations should not be included, return the main product ids.
			if ( ! $with_variation || empty( $main_products_ids ) ) {
				return $main_products_ids;
			}

			// Put the main ids in a string, so it can be attached to a query string.
			$main_products_ids_string = implode( ', ', $main_products_ids );

			$variation_products_query = "SELECT DISTINCT post_parent FROM {$this->_table_prefix}posts
				WHERE {$this->_table_prefix}posts.post_parent IN ($main_products_ids_string)
				AND {$this->_table_prefix}posts.post_type = 'product_variation'
				AND {$this->_table_prefix}posts.post_status = 'publish'
				ORDER BY ID";

			// Get the ids of the variable products.
			$variation_products = $this->_wpdb->get_col( $variation_products_query );

			// If there are no variations, return the main product ids.
			if ( count( $variation_products ) < 1 ) {
				return $main_products_ids;
			}

			$variation_products_ids_string = implode( ', ', $variation_products );

			// Remove the main product ids of products that have a valid variable version from the list to keep only the ids of the simple products.
			$simple_products_ids = array_diff( $main_products_ids, $variation_products );

			$product_variations_query = "SELECT DISTINCT ID FROM {$this->_table_prefix}posts
				WHERE {$this->_table_prefix}posts.post_parent IN ($variation_products_ids_string)
				AND {$this->_table_prefix}posts.post_type = 'product_variation'
				AND {$this->_table_prefix}posts.post_status = 'publish'
				ORDER BY ID";

			// Now get the variations.
			$product_variations_ids = $this->_wpdb->get_col( $product_variations_query );

			// Combine the variable product ids with the remaining simple product ids.
			$all_product_ids = array_merge( $simple_products_ids, $product_variations_ids );
			asort( $all_product_ids );

			return $all_product_ids;
		}

		/**
		 * Returns all product IDs for the given category selection, without batching/transient.
		 * Used by Performance Prioritizer to get the full product set for analysis.
		 *
		 * @param string $category_string Comma-separated term IDs (from category mapping).
		 * @param bool   $with_variation  True if variations should be included.
		 * @param int    $feed_id         Feed ID for the wppfm_selected_categories filter. Optional.
		 *
		 * @return array Product post IDs.
		 * @since 3.21.0
		 */
		public function get_all_post_ids_for_categories( $category_string, $with_variation = false, $feed_id = 0 ) {
			$category_string = apply_filters( 'wppfm_selected_categories', $category_string, $feed_id );

			if ( empty( $category_string ) ) {
				return array();
			}

			// Sanitize: ensure only integers in the IN clause.
			$ids = array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $category_string ) ) ) );
			if ( empty( $ids ) ) {
				return array();
			}
			$ids = array_unique( $ids );
			$id_list = implode( ',', $ids );

			$product_query_limit = apply_filters( 'wppfm_product_query_limit', 1000 );
			$max_products = apply_filters( 'wppfm_performance_max_products', 100000 );

			$all_main_ids = array();
			$start_id = -1;

			do {
				$products_query = $this->_wpdb->prepare(
					"SELECT DISTINCT p.ID
					FROM {$this->_table_prefix}posts p
					LEFT JOIN {$this->_table_prefix}term_relationships tr ON (p.ID = tr.object_id)
					LEFT JOIN {$this->_table_prefix}term_taxonomy tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
					WHERE p.post_type = 'product' AND p.post_status = 'publish' AND p.post_password = ''
					AND tt.term_id IN ($id_list)
					AND p.ID > %d
					ORDER BY p.ID LIMIT %d",
					$start_id,
					$product_query_limit
				);

				$main_products_ids = $this->_wpdb->get_col( $products_query );

				if ( ! empty( $main_products_ids ) ) {
					$all_main_ids = array_merge( $all_main_ids, $main_products_ids );
					$start_id = (int) end( $main_products_ids );
				}

				if ( count( $main_products_ids ) < $product_query_limit || count( $all_main_ids ) >= $max_products ) {
					break;
				}
			} while ( true );

			if ( ! $with_variation || empty( $all_main_ids ) ) {
				return array_values( array_unique( array_filter( $all_main_ids ) ) );
			}

			// Resolve variations (same logic as get_post_ids).
			$main_products_ids_string = implode( ',', array_map( 'absint', $all_main_ids ) );
			$variation_parents = $this->_wpdb->get_col(
				"SELECT DISTINCT post_parent FROM {$this->_table_prefix}posts
				WHERE post_parent IN ($main_products_ids_string)
				AND post_type = 'product_variation'
				AND post_status = 'publish'
				ORDER BY ID"
			);

			if ( empty( $variation_parents ) ) {
				return array_values( array_unique( array_filter( $all_main_ids ) ) );
			}

			$simple_products_ids = array_diff( $all_main_ids, $variation_parents );
			$variation_parents_string = implode( ',', array_map( 'absint', $variation_parents ) );

			$product_variations_ids = $this->_wpdb->get_col(
				"SELECT DISTINCT ID FROM {$this->_table_prefix}posts
				WHERE post_parent IN ($variation_parents_string)
				AND post_type = 'product_variation'
				AND post_status = 'publish'
				ORDER BY ID"
			);

			$all_product_ids = array_merge( $simple_products_ids, $product_variations_ids ? $product_variations_ids : array() );
			asort( $all_product_ids );

			return array_values( array_unique( array_filter( $all_product_ids ) ) );
		}

		/**
		 * Reads specific performance-related feed meta keys for a feed.
		 *
		 * @param int   $feed_id   Feed ID.
		 * @param array $meta_keys Optional. Keys to fetch. Defaults to all performance keys.
		 *
		 * @return array Associative array meta_key => meta_value.
		 * @since 3.21.0
		 */
		public function get_feed_performance_meta( $feed_id, $meta_keys = array() ) {
			$default_keys = array(
				'wppfm_performance_enabled',
				'wppfm_performance_period_days',
				'wppfm_performance_high_percentage',
				'wppfm_performance_last_update_gmt',
				'wppfm_performance_last_analyzed_count',
			);

			$keys = empty( $meta_keys ) ? $default_keys : $meta_keys;
			$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

			$main_table = $this->_table_prefix . 'feedmanager_product_feedmeta';
			$query = $this->_wpdb->prepare(
				"SELECT meta_key, meta_value FROM $main_table WHERE product_feed_id = %d AND meta_key IN ($placeholders)",
				array_merge( array( $feed_id ), $keys )
			);

			$rows = $this->_wpdb->get_results( $query, ARRAY_A );
			$result = array();

			foreach ( $rows as $row ) {
				$result[ $row['meta_key'] ] = $row['meta_value'];
			}

			return $result;
		}

		/**
		 * Updates or inserts a single feed meta value for performance settings.
		 *
		 * @param int    $feed_id   Feed ID.
		 * @param string $meta_key  Meta key (e.g. wppfm_performance_enabled).
		 * @param string $meta_value Meta value.
		 *
		 * @return int|false Number of affected rows or false.
		 * @since 3.21.0
		 */
		public function update_feed_performance_meta( $feed_id, $meta_key, $meta_value ) {
			$main_table = $this->_table_prefix . 'feedmanager_product_feedmeta';

			$exists = $this->_wpdb->get_var(
				$this->_wpdb->prepare(
					"SELECT meta_id FROM $main_table WHERE product_feed_id = %d AND meta_key = %s",
					$feed_id,
					$meta_key
				)
			);

			if ( $exists ) {
				return $this->_wpdb->update(
					$main_table,
					array( 'meta_value' => $meta_value ),
					array(
						'product_feed_id' => $feed_id,
						'meta_key'        => $meta_key,
					),
					array( '%s' ),
					array( '%d', '%s' )
				);
			}

			return $this->_wpdb->insert(
				$main_table,
				array(
					'product_feed_id' => $feed_id,
					'meta_key'        => $meta_key,
					'meta_value'      => $meta_value,
				),
				array( '%d', '%s', '%s' )
			);
		}

		/**
		 * Deletes performance rows for a feed and period.
		 *
		 * @param int $feed_id    Feed ID.
		 * @param int $period_days Period in days.
		 *
		 * @return int|false Number of deleted rows or false.
		 * @since 3.21.0
		 */
		public function delete_performance_rows_for_feed( $feed_id, $period_days ) {
			$table = $this->_table_prefix . 'feedmanager_product_performance';

			return $this->_wpdb->query(
				$this->_wpdb->prepare(
					"DELETE FROM $table WHERE product_feed_id = %d AND period_days = %d",
					$feed_id,
					$period_days
				)
			);
		}

		/**
		 * Inserts performance rows for a feed in bulk chunks.
		 * Uses INSERT ... ON DUPLICATE KEY UPDATE to handle unique key conflicts efficiently.
		 * Avoids per-row writes for large feeds (~200k products).
		 *
		 * @param int   $feed_id     Feed ID.
		 * @param int   $period_days Period in days.
		 * @param array $rows        Array of rows, each with product_id, orders_count, revenue, performance_tier.
		 *
		 * @return int Number of inserted/replaced rows.
		 * @since 3.21.0
		 */
		public function insert_performance_rows( $feed_id, $period_days, $rows ) {
			if ( empty( $rows ) ) {
				return 0;
			}

			$table = $this->_table_prefix . 'feedmanager_product_performance';
			$updated_gmt = gmdate( 'Y-m-d H:i:s' );
			$inserted = 0;

			// Filterable chunk size for bulk insert (default 1000 rows per query).
			$chunk_size = (int) apply_filters( 'wppfm_performance_insert_chunk_size', 1000 );
			$chunk_size = max( 1, min( 5000, $chunk_size ) );

			$chunks = array_chunk( $rows, $chunk_size );

			foreach ( $chunks as $chunk ) {
				$values = array();
				$placeholders = array();

				foreach ( $chunk as $row ) {
					$product_id = (int) ( $row['product_id'] ?? 0 );
					$orders_count = (int) ( $row['orders_count'] ?? 0 );
					$revenue = (float) ( $row['revenue'] ?? 0 );
					$tier = sanitize_key( $row['performance_tier'] ?? 'low' );
					if ( ! in_array( $tier, array( 'high', 'mid', 'low' ), true ) ) {
						$tier = 'low';
					}

					$placeholders[] = '(%d, %d, %d, %d, %f, %s, %s)';
					$values[] = $feed_id;
					$values[] = $product_id;
					$values[] = $period_days;
					$values[] = $orders_count;
					$values[] = $revenue;
					$values[] = $tier;
					$values[] = $updated_gmt;
				}

				$sql = 'INSERT INTO ' . $table . ' (product_feed_id, product_id, period_days, orders_count, revenue, performance_tier, updated_gmt) VALUES '
					. implode( ', ', $placeholders )
					. ' ON DUPLICATE KEY UPDATE orders_count = VALUES(orders_count), revenue = VALUES(revenue), performance_tier = VALUES(performance_tier), updated_gmt = VALUES(updated_gmt)';

				$result = $this->_wpdb->query( $this->_wpdb->prepare( $sql, ...$values ) );
				if ( false !== $result && $result > 0 ) {
					$inserted += $result;
				}
			}

			return $inserted;
		}

		/**
		 * Gets performance row for a single product.
		 *
		 * @param int $feed_id     Feed ID.
		 * @param int $product_id  Product post ID.
		 * @param int $period_days Period in days.
		 *
		 * @return object|null Row or null.
		 * @since 3.21.0
		 */
		public function get_performance_for_product( $feed_id, $product_id, $period_days ) {
			$table = $this->_table_prefix . 'feedmanager_product_performance';

			$row = $this->_wpdb->get_row(
				$this->_wpdb->prepare(
					"SELECT * FROM $table WHERE product_feed_id = %d AND product_id = %d AND period_days = %d",
					$feed_id,
					$product_id,
					$period_days
				)
			);

			return $row;
		}

		/**
		 * Gets performance rows for multiple products. Returns keyed by product_id.
		 * Chunks large product ID lists to avoid oversized IN clauses (~200k products).
		 *
		 * @param int   $feed_id     Feed ID.
		 * @param int[] $product_ids Product post IDs.
		 * @param int   $period_days Period in days.
		 *
		 * @return array Associative array product_id => row object.
		 * @since 3.21.0
		 */
		public function get_performance_for_products( $feed_id, $product_ids, $period_days ) {
			if ( empty( $product_ids ) ) {
				return array();
			}

			$product_ids = array_map( 'absint', $product_ids );
			$product_ids = array_values( array_unique( array_filter( $product_ids ) ) );

			$prefetch_chunk_size = (int) apply_filters( 'wppfm_performance_prefetch_chunk_size', 2000 );
			$prefetch_chunk_size = max( 1, min( 10000, $prefetch_chunk_size ) );

			$keyed = array();
			$chunks = array_chunk( $product_ids, $prefetch_chunk_size );

			$table = $this->_table_prefix . 'feedmanager_product_performance';

			foreach ( $chunks as $chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				$query_args = array_merge( array( $feed_id ), $chunk, array( $period_days ) );

				$results = $this->_wpdb->get_results(
					$this->_wpdb->prepare(
						"SELECT * FROM $table WHERE product_feed_id = %d AND product_id IN ($placeholders) AND period_days = %d",
						$query_args
					)
				);

				if ( $results ) {
					foreach ( $results as $row ) {
						$keyed[ (int) $row->product_id ] = $row;
					}
				}
			}

			return $keyed;
		}

		/**
		 * Gets the required data from the main
		 *
		 * @param string $post_id           The id of the post.
		 * @param string $column_string     String with the selected columns.
		 *
		 * @return array|object|null
		 */
		public function read_post_data( $post_id, $column_string ) {
			$selecting_columns = $column_string ? ', ' . $column_string : '';

			$result = $this->_wpdb->get_results(
				$this->_wpdb->prepare( "SELECT DISTINCT ID $selecting_columns FROM {$this->_table_prefix}posts WHERE ID = %d", $post_id ) );

			return $result ? $result[0] : null;
		}

		/**
		 * Returns the metadata of a specific product.
		 *
		 * @param string $product_id            The product id.
		 * @param string $parent_product_id     The product id of the parent.
		 * @param array  $record_ids            All record ids.
		 * @param array  $meta_columns          List with meta fields.
		 *
		 * @return array    Array with the metadata from the specified product.
		 */
		public function read_meta_data( $product_id, $parent_product_id, $record_ids, $meta_columns ) {
			$data         = array();
			$product_type = WC_Product_Factory::get_product_type( $product_id );

			foreach ( $meta_columns as $column ) {
				$taxonomy = get_taxonomy( $column );

				if ( $taxonomy ) {

					$taxonomy_value = WPPFM_Taxonomies::make_shop_taxonomies_string( $product_id, $taxonomy->name, ', ' );

					if ( ! $taxonomy_value ) {
						$taxonomy_value = WPPFM_Taxonomies::make_shop_taxonomies_string( $parent_product_id, $taxonomy->name, ', ' );
					}

					if ( $taxonomy_value ) {
						$data[] = $this->make_meta_object( $column, $taxonomy_value, $product_id );
					}
				}

				foreach ( $record_ids as $rec_id ) {
					if ( $rec_id !== $product_id && 'simple' === $product_type ) {
						if ( get_post_meta( $rec_id, '_variation_description' ) ) {
							// Skip old meta-variation data from a previous variation product that was converted to a simple product.
							// @since 2.20.0.
							continue;
						}
					}

					$value = get_post_meta( $rec_id, $column, true );

					if ( $value || '0' === $value ) {
						$data[] = $this->make_meta_object( $column, $value, $rec_id );
						break;
					} else {
						$alt_val  = maybe_unserialize( get_post_meta( $rec_id, '_product_attributes', true ) );
						$col_name = str_replace( ' ', '-', strtolower( $column ) );
						if ( $alt_val && isset( $alt_val[ $col_name ] ) ) {
							$data[] = $this->make_meta_object( $column, $alt_val[ $col_name ]['value'], $rec_id );
						} elseif ( $alt_val && is_array( $alt_val ) ) {
							foreach ( $alt_val as $v ) {
								if ( isset( $v['name'] ) && $v['name'] === $column ) {
									$data[] = $this->make_meta_object( $column, $v['value'], $rec_id );
								}
							}
						}
					}
				}
			}

			$this->polish_data( $data, $product_id );

			return $data;
		}

		private function make_meta_object( $key, $value, $id ) {
			$obj             = new stdClass();
			$obj->meta_key   = $key;
			$obj->meta_value = $value;
			$obj->post_id    = $id;

			return $obj;
		}

		/**
		 * Cleans up the meta-data of a product. It checks for a valid url and converts the timestamp.
		 *
		 * @param array  $data          The data to be polished.
		 * @param string $main_post_id  The post id.
		 */
		private function polish_data( $data, $main_post_id ) {
			$site_url = get_option( 'siteurl' );

			foreach ( $data as $row ) {
				// Make sure the _wp_attached_file data contains a valid url.
				if ( '_wp_attached_file' === $row->meta_key ) {
					$row->meta_value = get_the_post_thumbnail_url( $main_post_id, 'large' );

					// If the _wp_attached_file data is not a valid url than add the url data.
					if ( ! filter_var( $row->meta_value, FILTER_VALIDATE_URL ) ) {
						$row->meta_value = $site_url . '/wp-content/uploads/' . $row->meta_value;
					}
				}

				// Convert the time stamp format to a usable date time format for the feed.
				if ( '_sale_price_dates_from' === $row->meta_key || '_sale_price_dates_to' === $row->meta_key ) {
					$row->meta_value = wppfm_convert_price_date_to_feed_format( $row->meta_value );
				}

				// @since 2.29.0.
				$row->meta_value = apply_filters( "wppfm{$row->meta_key}_value", $row->meta_value, $main_post_id );
			}
		}

		public function delete_feed( $feed_id ) {
			unset( self::$_feed_row_cache[ (string) $feed_id ] ); // Invalidate cache when feed is deleted.
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';

			return $this->_wpdb->delete( $main_table, array( 'product_feed_id' => $feed_id ) );
		}

		public function delete_meta( $feed_id ) {
			$main_table = $this->_table_prefix . 'feedmanager_product_feedmeta';

			return $this->_wpdb->delete( $main_table, array( 'product_feed_id' => $feed_id ) );
		}

		/**
		 * Gets the metadata from a specific feed. It does not include the category mapping and feed filter data.
		 *
		 * @param   string  $feed_id
		 *
		 * @return  array|bool|object|null
		 */
		public function read_metadata( $feed_id ) {
			if ( $feed_id ) {
				$main_table = $this->_table_prefix . 'feedmanager_product_feedmeta';

				return $this->_wpdb->get_results(
					$this->_wpdb->prepare( "SELECT * FROM $main_table WHERE product_feed_id = %d AND meta_key != 'category_mapping' AND meta_key != 'product_filter_query' ORDER BY meta_id", $feed_id ), ARRAY_A );
			} else {
				return false;
			}
		}

		/**
		 * Fetches the Feed Filter data from a specific feed.
		 *
		 * @param   string  $feed_id
		 *
		 * @return  array|bool|object|null
		 */
		public function get_product_filter_query( $feed_id ) {
			if ( $feed_id ) {
				$main_table = $this->_table_prefix . 'feedmanager_product_feedmeta';

				return $this->_wpdb->get_results(
					$this->_wpdb->prepare( "SELECT meta_value FROM $main_table WHERE product_feed_id = %d AND meta_key = 'product_filter_query'", $feed_id ), ARRAY_A );
			} else {
				wppfm_write_log_file( sprintf( 'Function get_filter_query returned false on feed %s', $feed_id ) );

				return false;
			}
		}

		public function get_columns_from_post_table() {
			return $this->_wpdb->get_results( "SHOW COLUMNS FROM {$this->_table_prefix}posts" );
		}

		public function get_custom_product_attributes() {
			$main_table = $this->_table_prefix . 'woocommerce_attribute_taxonomies';

			return $this->_wpdb->get_results( "SELECT attribute_name, attribute_label FROM $main_table" );
		}

		/**
		 * Gets distinct meta_key values from product postmeta for custom product fields.
		 * Excludes WooCommerce internal keys (starting with _) unless they match
		 * third-party attribute keywords. Optimized by restricting to product posts
		 * and using prepared statements. Results are cached via transient.
		 *
		 * @return array List of distinct meta_key strings.
		 */
		public function get_custom_product_fields() {
			$keywords_option = get_option( 'wppfm_third_party_attribute_keywords', '%wpmr%,%cpf%,%unit%,%bto%,%yoast%' );
			$transient_key   = 'wppfm_custom_product_fields_' . md5( $keywords_option );

			$cached = get_transient( $transient_key );
			if ( false !== $cached ) {
				return $cached;
			}

			$keywords_array = array_filter( array_map( 'trim', explode( ',', $keywords_option ) ) );
			$postmeta      = $this->_wpdb->postmeta;
			$posts         = $this->_wpdb->posts;

			// Restrict to products only to avoid scanning order/post meta – major performance win.
			// Build condition: meta_key not starting with _ (WooCommerce internal) OR matches keyword patterns.
			$like_placeholders = array_fill( 0, count( $keywords_array ), '%s' );
			$like_clause       = ! empty( $like_placeholders ) ? ' OR pm.meta_key LIKE ' . implode( ' OR pm.meta_key LIKE ', $like_placeholders ) : '';
			$query             = "SELECT DISTINCT pm.meta_key FROM $postmeta pm
				INNER JOIN $posts p ON pm.post_id = p.ID
				WHERE p.post_type IN ('product', 'product_variation')
				AND (pm.meta_key NOT LIKE %s" . $like_clause . ")
				ORDER BY pm.meta_key";

			$params = array_merge( array( $this->_wpdb->esc_like( '_' ) . '%' ), $keywords_array );
			$result = $this->_wpdb->get_col( $this->_wpdb->prepare( $query, $params ) );

			set_transient( $transient_key, $result, HOUR_IN_SECONDS );

			return $result;
		}

		public function clear_feed_batch_options() {
			delete_site_option( 'wppfm_background_process_key' );
			$this->_wpdb->query( "DELETE FROM {$this->_wpdb->options} WHERE option_name LIKE '%_batch_%'" );
		}

		/**
		 * @since 2.0.11
		 */
		public function clear_feed_batch_sitemeta() {
			$this->_wpdb->query( "DELETE FROM {$this->_wpdb->sitemeta} WHERE meta_key LIKE '%_batch_%'" );
		}

		public function get_own_variable_product_attributes( $variable_id ) {
			$keywords        = get_option( 'wppfm_third_party_attribute_keywords', '%wpmr%,%cpf%,%unit%,%bto%,%yoast%' );
			$wpmr_attributes = array();

			if ( $keywords ) {
				$keywords_array     = explode( ',', $keywords );
				$main_table         = $this->_wpdb->postmeta;
				$query_where_string = count( $keywords_array ) > 0 ? "WHERE (meta_key LIKE '" . trim( $keywords_array[0] ) . "'" : '';

				for ( $i = 1; $i < count( $keywords_array ); $i ++ ) {
					$query_where_string .= " OR meta_key LIKE '" . trim( $keywords_array[ $i ] ) . "'";
				}

				$query_where_string .= count( $keywords_array ) > 0 ? ') AND ' : '';

				foreach ( $this->_wpdb->get_results( $this->_wpdb->prepare( "SELECT meta_key, meta_value FROM $main_table $query_where_string (post_id = %d)", $variable_id ) ) as $row ) {
					$wpmr_attributes[ $row->meta_key ] = $row->meta_value;
				}
			}

			return $wpmr_attributes;
		}

		public function get_all_product_attributes() {
			$main_table = $this->_wpdb->postmeta;

			return $this->_wpdb->get_results( "SELECT DISTINCT meta_value FROM $main_table WHERE meta_key = '_product_attributes'" );
		}

		public function get_current_feed_status( $feed_id ) {
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';

			return $this->_wpdb->get_results(
				$this->_wpdb->prepare( "SELECT status_id FROM $main_table WHERE product_feed_id = %d", $feed_id ) );
		}

		public function get_country_id( $country_code ) {
			$main_table = $this->_table_prefix . 'feedmanager_country';

			return $this->_wpdb->get_row(
				$this->_wpdb->prepare( "SELECT country_id FROM $main_table WHERE name_short = %s", $country_code ) );
		}

		public function get_feed_ids_with_specific_status( $status_id ) {
			return $this->_wpdb->get_results(
				$this->_wpdb->prepare( "SELECT product_feed_id FROM {$this->_table_prefix}feedmanager_product_feed WHERE status_id = %s", $status_id ) );
		}

		public function switch_feed_status( $feed_id, $new_status ) {
			unset( self::$_feed_row_cache[ (string) $feed_id ] ); // Invalidate cache when feed status changes.
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';

			return $this->_wpdb->update(
				$main_table,
				array(
					'status_id'      => $new_status,
					'base_status_id' => $new_status,
				),
				array( 'product_feed_id' => $feed_id )
			);
		}

		public function set_nr_feed_products( $feed_id, $nr_products ) {
			unset( self::$_feed_row_cache[ (string) $feed_id ] ); // Invalidate cache when feed is updated.
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';

			return $this->_wpdb->update( $main_table, array( 'products' => $nr_products ), array( 'product_feed_id' => $feed_id ) );
		}

		/**
		 * Updates a new feed in the product_feed table.
		 *
		 * @param (string) $feed_id
		 * @param (array) $feed_data
		 * @param (array) $data_types
		 *
		 * @return (int|false) nr of affected rows
		 * @since 1.0.0
		 *
		 */
		public function update_feed( $feed_id, $feed_data, $data_types ) {
			unset( self::$_feed_row_cache[ (string) $feed_id ] ); // Invalidate cache when feed is updated.
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';

			return $this->_wpdb->update(
				$main_table,
				$feed_data,
				array(
					'product_feed_id' => $feed_id,
				),
				$data_types,
				array(
					'%d',
				)
			);
		}

		public function update_feed_update_data( $feed_id, $feed_url, $nr_products ) {
			unset( self::$_feed_row_cache[ (string) $feed_id ] ); // Invalidate cache when feed is updated.
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';

			return $this->_wpdb->update(
				$main_table,
				array(
					'updated'  => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
					'url'      => $feed_url,
					'products' => $nr_products,
				),
				array( 'product_feed_id' => $feed_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		public function get_file_url_from_feed( $feed_id ) {
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';
			$result     = $this->_wpdb->get_row(
				$this->_wpdb->prepare( "SELECT url FROM $main_table WHERE product_feed_id = %d", $feed_id ), ARRAY_A );

			return $result['url'];
		}

		/**
		 * Sets the status id of a feed
		 *
		 * @param   string  $feed_id
		 * @param   int     $status
		 *
		 * @return bool
		 */
		public function update_feed_file_status( $feed_id, $status ) {
			unset( self::$_feed_row_cache[ (string) $feed_id ] ); // Invalidate cache when feed status changes.
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';

			return $this->_wpdb->update( $main_table, array( 'status_id' => $status ), array( 'product_feed_id' => $feed_id ), array( '%d' ), array( '%d' ) );
		}

		/**
		 * Updates the metadata of a feed.
		 *
		 * @param string $feed_id
		 * @param array  $meta_data
		 *
		 * @return int the number of affected rows.
		 */
		public function update_meta_data( $feed_id, $meta_data ) {
			// First check if the feed_id is valid.
			if ( $feed_id <= 0 ) {
				return 0;
			}

			$main_table = $this->_table_prefix . 'feedmanager_product_feedmeta';

			// First, remove all metadata belonging to this feed except the product_filter_query.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Deleting feed metadata by meta_key. This is necessary for feed management and uses an indexed feed_id.
			$this->_wpdb->query(
				$this->_wpdb->prepare( "DELETE FROM $main_table WHERE product_feed_id = %d AND meta_key != %s", $feed_id, 'product_filter_query' ) );

			$counter = 0;

			// Now insert the new metadata in the feedmanager_product_feedmeta table.
			foreach( $meta_data as $meta ) {
				if ( ! empty( $meta->value ) && '{}' !== $meta->value ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Inserting feed metadata. meta_key and meta_value are required fields for the feedmanager_product_feedmeta table structure.
					$result = $this->_wpdb->insert(
						$main_table,
						array(
							'product_feed_id' => $feed_id,
							'meta_key'        => $meta->key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required field for table structure.
							'meta_value'      => $meta->value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required field for table structure.
						),
						array(
							'%d',
							'%s',
							'%s',
						)
					);

					$counter += $result;
				}
			}

			return $counter;
		}

		/**
		 * Resets the status_id's of failed feeds.
		 *
		 * @since 2.7.0
		 */
		public function reset_all_feed_status() {
			self::$_feed_row_cache = array(); // Invalidate entire cache when multiple feeds are updated.
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';
			$failed_ids = $this->_wpdb->get_results( "SELECT product_feed_id FROM $main_table WHERE status_id > '2' OR status_id = '0'", 'ARRAY_A' );

			foreach ( $failed_ids as $feed_id ) {
				$id          = $feed_id['product_feed_id'];
				$base_status = $this->_wpdb->get_var( "SELECT base_status_id FROM $main_table WHERE product_feed_id = '$id'" );
				$new_status  = '1' === $base_status || '2' === $base_status ? $base_status : '2';

				$this->_wpdb->update(
					$main_table,
					array(
						'status_id' => $new_status,
						'products'  => 0,
					),
					array(
						'product_feed_id' => $id,
					),
					array(
						'%s',
						'%d',
					)
				);
			}
		}

		/**
		 * Resets the status_id of a specific feed.
		 *
		 * @param string $feed_id
		 */
		public function store_feed_filter( $feed_id, $filter ) {
			$main_table = $this->_table_prefix . 'feedmanager_product_feedmeta';

			if ( $filter ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Querying feed filter metadata by meta_key. Uses indexed feed_id to minimize performance impact.
				$exists = $this->_wpdb->get_results(
					$this->_wpdb->prepare( "SELECT meta_id FROM $main_table WHERE product_feed_id = %d AND meta_key = 'product_filter_query'", $feed_id ) );

				if ( $exists ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Updating feed filter metadata. meta_key and meta_value are required fields for the feedmanager_product_feedmeta table structure.
					$this->_wpdb->update(
						$main_table,
						array(
							'meta_value' => $filter, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required field for table structure.
						),
						array(
							'product_feed_id' => $feed_id,
							'meta_key'        => 'product_filter_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required field for table structure.
						),
						array(
							'%s',
						),
						array(
							'%d',
							'%s',
						)
					);
				} else {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Inserting feed filter metadata. meta_key and meta_value are required fields for the feedmanager_product_feedmeta table structure.
					$this->_wpdb->insert(
						$main_table,
						array(
							'product_feed_id' => $feed_id,
							'meta_key'        => 'product_filter_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required field for table structure.
							'meta_value'      => $filter, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required field for table structure.
						),
						array(
							'%d',
							'%s',
							'%s',
						)
					);
				}
			} else {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Deleting feed filter metadata by meta_key. Uses indexed feed_id to minimize performance impact.
				$this->_wpdb->query(
					$this->_wpdb->prepare( "DELETE FROM $main_table WHERE product_feed_id = %d AND meta_key = %s", $feed_id, 'product_filter_query' ) );
			}
		}

		public function insert_meta_data( $feed_id, $meta_data, $feed_filter_data, $category_mapping ) {
			$main_table = $this->_table_prefix . 'feedmanager_product_feedmeta';

			$counter = 0;

			for ( $i = 0; $i < count( $meta_data ); $i ++ ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Inserting feed metadata. meta_key and meta_value are required fields for the feedmanager_product_feedmeta table structure.
				$result = $this->_wpdb->insert(
					$main_table,
					array(
						'product_feed_id' => $feed_id,
						'meta_key'        => $meta_data[ $i ]['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required field for table structure.
						'meta_value'      => $meta_data[ $i ]['meta_value'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required field for table structure.
					),
					array(
						'%d',
						'%s',
						'%s',
					)
				);

				$counter += $result;
			}

			for ( $i = 0; $i < count( $feed_filter_data ); $i++ ) {
				$this->store_feed_filter( $feed_id, $feed_filter_data[ $i ]['meta_value'] );
			}

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Inserting category mapping metadata. meta_key and meta_value are required fields for the feedmanager_product_feedmeta table structure.
			$counter += $this->_wpdb->insert(
				$main_table,
				array(
					'product_feed_id' => $feed_id,
					'meta_key'        => 'category_mapping', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required field for table structure.
					'meta_value'      => $category_mapping[0]['meta_value'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required field for table structure.
				),
				array(
					'%d',
					'%s',
					'%s',
				)
			);

			echo esc_html( $counter );
		}

		public function title_exists( $feed_title ) {
			$main_table = $this->_table_prefix . 'feedmanager_product_feed';
			$count      = $this->_wpdb->get_var(
				$this->_wpdb->prepare( "SELECT COUNT(*) FROM $main_table WHERE title = %s", $feed_title ) );

			return $count > 0;
		}

		/**
		 * Inserts a new feed in the product_feed table and returns its new id.
		 *
		 * @param array $feed_data_to_store
		 * @param array $feed_types
		 *
		 * @return integer containing the id of the new feed
		 * @since 1.0.0
		 *
		 */
		public function create_feed( $feed_data_to_store, $feed_types ) {

			$main_table = $this->_table_prefix . 'feedmanager_product_feed';

			$this->_wpdb->insert(
				$main_table,
				$feed_data_to_store,
				$feed_types
			);

			return $this->_wpdb->insert_id;
		}
	}


	// End of WPPFM_Queries class

endif;
