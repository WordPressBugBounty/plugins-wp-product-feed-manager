<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a conversion table between the ajax data items from a feed generation process to the corresponding database items.
 *
 * @since 2.5.0
 *
 * @return mixed|void
 */
function wppfm_ajax_feed_data_to_database_array( $feed_type ) {
	$mappings = [
		'feedId' => ['product_feed_id', '%d'],
		'channel' => ['channel_id', '%d'],
		'language' => ['language', '%s'],
		'includeVariations' => ['include_variations', '%d'],
		'isAggregator' => ['is_aggregator', '%d'],
		'aggregatorName' => ['aggregator_name', '%s'],
		'country' => ['country_id', '%d'],
		'dataSource' => ['source_id', '%d'],
		'title' => ['title', '%s'],
		'feedTitle' => ['feed_title', '%s'],
		'feedDescription' => ['feed_description', '%s'],
		'mainCategory' => ['main_category', '%s'],
		'url' => ['url', '%s'],
		'status' => ['status_id', '%d'],
		'updateSchedule' => ['schedule', '%s'],
		'feedType' => ['feed_type_id', '%d'],
	];

	if ( 'product-feed' === $feed_type ) { // add in case of a normal product feed
		$mappings['currency'] = ['currency', '%s'];
		$mappings['googleAnalytics'] = [ 'google_analytics', '%d' ];
		$mappings['utmSource'] = [ 'utm_source', '%s' ];
		$mappings['utmMedium'] = [ 'utm_medium', '%s' ];
		$mappings['utmId'] = [ 'utm_id', '%s' ];
		$mappings['utmCampaign'] = [ 'utm_campaign', '%s' ];
		$mappings['utmSourcePlatform'] = [ 'utm_source_platform', '%s' ];
		$mappings['utmTerm'] = [ 'utm_term', '%s' ];
		$mappings['utmContent'] = [ 'utm_content', '%s' ];
	} else { // add in case of a Review feed or a Promotion feed
		$mappings['publisherName'] = [ 'publisher_name', '%s' ];
		$mappings['publisherFavicon'] = [ 'publisher_favicon_url', '%s' ];
	}

	$conversionTable = [];

	foreach ($mappings as $feed => $mapping) {
		list($db, $type) = $mapping;
		$conversionTable[] = (object) ['feed' => $feed, 'db' => $db, 'type' => $type];
	}

	return apply_filters('wppfm_feed_data_ajax_to_database_conversion_table', $conversionTable);
}

/**
 * Returns the feed column names that may be written from AJAX feed payloads.
 *
 * Array keys passed to $wpdb->update() and $wpdb->insert() are used as SQL column
 * identifiers and must never contain user-controlled characters such as backticks.
 *
 * @since 3.24.0.1
 *
 * @return string[] Allowed feedmanager_product_feed column names.
 */
function wppfm_get_allowed_ajax_feed_column_names() {
	static $allowed_columns = null;

	if ( null !== $allowed_columns ) {
		return $allowed_columns;
	}

	$feed_types = apply_filters(
		'wppfm_ajax_feed_data_feed_types',
		array(
			'product-feed',
			'google-product-review-feed',
			'google-merchant-promotions-feed',
		)
	);

	$columns = array();

	foreach ( (array) $feed_types as $feed_type ) {
		$conversion_table = wppfm_ajax_feed_data_to_database_array( $feed_type );

		foreach ( (array) $conversion_table as $conversion_item ) {
			if ( ! is_object( $conversion_item ) || empty( $conversion_item->db ) ) {
				continue;
			}

			// product_feed_id is only used in the WHERE clause, never as a SET column.
			if ( 'product_feed_id' === $conversion_item->db ) {
				continue;
			}

			$columns[] = $conversion_item->db;
		}
	}

	$allowed_columns = array_values( array_unique( $columns ) );

	/**
	 * Filters the allow-list of feed table columns writable from AJAX feed payloads.
	 *
	 * @since 3.24.0
	 *
	 * @param string[] $allowed_columns Allowed feedmanager_product_feed column names.
	 */
	return apply_filters( 'wppfm_allowed_ajax_feed_column_names', $allowed_columns );
}

/**
 * Validates a feed column name before it is used as a $wpdb array key.
 *
 * @since 3.24.0
 *
 * @param string $column_name Candidate column identifier from an AJAX payload.
 *
 * @return bool True when the column may be written via AJAX feed data.
 */
function wppfm_is_valid_ajax_feed_column_name( $column_name ) {
	if ( ! is_string( $column_name ) || '' === $column_name ) {
		return false;
	}

	if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $column_name ) ) {
		return false;
	}

	return in_array( $column_name, wppfm_get_allowed_ajax_feed_column_names(), true );
}

/**
 * Returns an array with all the feed names.
 *
 * @return array with all the feed names as strings.
 */
function wppfm_get_all_feed_names() {
	$query_class = new WPPFM_Queries();
	$feed_names  = $query_class->get_all_feed_names();
	$used_names  = array();

	foreach ( $feed_names as $name ) {
		$used_names[] = $name->title;
	}

	return $used_names;
}
