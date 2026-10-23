<?php

/**
 * Adds product-level WooCommerce review sources to the attribute mapping source selector.
 *
 * @package WP Product Feed Manager/Data
 * @since 3.25.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends product review aggregate sources to the wppfm_all_source_fields filter.
 *
 * These differ from per-comment review feed sources (Comment Rating, etc.): they
 * expose WooCommerce product aggregates suitable for fields such as review_count.
 *
 * @param array $source_fields Existing source fields (objects with attribute_name, attribute_label).
 * @return array Modified source fields with product review sources prepended.
 */
function wppfm_add_product_review_source_fields( $source_fields ) {
	$review_sources = array(
		(object) array(
			'attribute_name'  => 'wppfm_product_review_count',
			'attribute_label' => __( 'Product review count', 'wp-product-feed-manager' ),
			'attribute_group' => __( 'Product Reviews (WooCommerce)', 'wp-product-feed-manager' ),
		),
	);

	return array_merge( $review_sources, (array) $source_fields );
}

add_filter( 'wppfm_all_source_fields', 'wppfm_add_product_review_source_fields', 6 );
