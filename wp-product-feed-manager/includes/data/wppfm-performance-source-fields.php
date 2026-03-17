<?php

/**
 * Adds Performance Metrics (Feed Manager) source fields to the attribute mapping source selector.
 *
 * @package WP Product Feed Manager/Data
 * @since 3.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends performance metric sources to the wppfm_all_source_fields filter.
 *
 * Sources are grouped under "Performance Metrics (Feed Manager)" optgroup.
 *
 * @param array $source_fields Existing source fields (objects with attribute_name, attribute_label).
 * @return array Modified source fields with performance sources prepended.
 */
function wppfm_add_performance_source_fields( $source_fields ) {
	$performance_sources = array(
		(object) array(
			'attribute_name'  => 'wppfm_performance_tier',
			'attribute_label' => __( 'Performance (high/mid/low)', 'wp-product-feed-manager' ),
			'attribute_group' => __( 'Performance Metrics (Feed Manager)', 'wp-product-feed-manager' ),
		),
		(object) array(
			'attribute_name'  => 'wppfm_performance_revenue',
			'attribute_label' => __( 'Revenue last N days', 'wp-product-feed-manager' ),
			'attribute_group' => __( 'Performance Metrics (Feed Manager)', 'wp-product-feed-manager' ),
		),
		(object) array(
			'attribute_name'  => 'wppfm_performance_orders',
			'attribute_label' => __( 'Orders last N days', 'wp-product-feed-manager' ),
			'attribute_group' => __( 'Performance Metrics (Feed Manager)', 'wp-product-feed-manager' ),
		),
	);

	return array_merge( $performance_sources, (array) $source_fields );
}

add_filter( 'wppfm_all_source_fields', 'wppfm_add_performance_source_fields', 5 );
