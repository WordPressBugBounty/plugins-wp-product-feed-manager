<?php

/**
 * WP Product Feed Processor Functions Trait.
 *
 * @package WP Product Feed Manager/Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WPPFM_Feed_Processor_Functions {

	/**
	 * Adds a file format string to the feed.
	 *
	 * @param array $line_data containing the file format line.
	 *
	 * @return boolean true if the format line has been added, false if it failed.
	 */
	private function add_file_format_line_to_feed( $line_data ) {
		return false !== wppfm_append_line_to_file( $this->_feed_file_path, $line_data['file_format_line'] );
	}

	/**
	 * Adds an error message to the feed.
	 *
	 * @param array $error_message_data containing the error message in a 'feed_line_message' element.
	 *
	 * @return boolean true if the error message has been added, false if it failed.
	 */
	private function add_error_message_to_feed( $error_message_data ) {
		return false !== wppfm_append_line_to_file( $this->_feed_file_path, $error_message_data['feed_line_message'] );
	}


	/**
	 * Register a feed update in the database.
	 *
	 * @param string $feed_id     the feed id.
	 * @param string $feed_name   the name of the feed.
	 * @param string $nr_products the number of products in the feed.
	 * @param string $status      the status of the feed, default null.
	 */
	private function register_feed_update( $feed_id, $feed_name, $nr_products, $status = null ) {
		$data_class = new WPPFM_Data();

		// Register the update and update the feed Last Change time.
		$data_class->update_feed_data( $feed_id, wppfm_get_file_url( $feed_name ), $nr_products );

		$actual_status = $status ?: $data_class->get_feed_status( $feed_id );

		if ( '4' !== $actual_status && '5' !== $actual_status && '6' !== $actual_status ) { // No errors.
			$data_class->update_feed_status( $feed_id, intval( $status ) ); // Put feed on status hold if no errors are reported.
		}
	}

	/**
	 * Gets the main data of a specific product.
	 *
	 * @param string $product_id                the product id.
	 * @param string $parent_product_id         the products parent id.
	 * @param string $post_columns_query_string a query string with the post-columns.
	 *
	 * @return object|bool with the product main data.
	 */
	private function get_products_main_data( $product_id, $parent_product_id, $post_columns_query_string ) {
		$queries_class   = new WPPFM_Queries();
		$prep_meta_class = new WPPFM_Feed_Value_Editors();

		$product_data = $queries_class->read_post_data( $product_id, $post_columns_query_string );

		if ( 'object' !== gettype( $product_data ) ) {
			return false;
		}

		// WPML support.
		if ( has_filter( 'wpml_translation' ) ) {
			$product_data = apply_filters( 'wpml_translation', $product_data, $this->_feed_data->language );
		}

		// Polylang support.
		if ( has_filter( 'pll_translation' ) ) {
			$product_data = apply_filters( 'pll_translation', $product_data, $this->_feed_data->language );
		}

		// Translatepress support.
		if ( has_filter( 'wppfm_transpress_translation' ) ) {
			$product_data = apply_filters( 'wppfm_transpress_translation', $product_data, $this->_feed_data->language );
		}

		// Parent ids are required to get the main data from product variations.
		$product_parent_ids = 0 !== $parent_product_id ? array( $parent_product_id ) : $this->get_product_parent_ids( $product_id );

		array_unshift( $product_parent_ids, $product_id ); // Add the product id to the parent ids.

		$meta_data = $queries_class->read_meta_data( $product_id, $parent_product_id, $product_parent_ids, $this->_pre_data['database_fields']['meta_fields'] );

		foreach ( $meta_data as $meta ) {
			$meta_value = $prep_meta_class->prep_meta_values( $meta, $this->_feed_data->language, $this->_feed_data->currency );

			if ( property_exists( (object) $product_data, $meta->meta_key ) ) {
				$meta_key = $meta->meta_key;

				if ( '' === $product_data->$meta_key ) {
					$product_data = (object) array_merge( (array) $product_data, array( $meta->meta_key => $meta_value ) );
				}
			} else {
				$product_data = (object) array_merge( (array) $product_data, array( $meta->meta_key => $meta_value ) );
			}
		}

		foreach ( $this->_pre_data['database_fields']['active_custom_fields'] as $field ) {
			$product_data->{$field} = $this->get_custom_field_data( $product_data->ID, $parent_product_id, $field );
		}

		foreach ( $this->_pre_data['database_fields']['third_party_custom_fields'] as $third_party_field ) {
			$product_data->{$third_party_field} = $this->get_third_party_custom_field_data( $product_data->ID, $parent_product_id, $third_party_field );
		}

		if ( $this->_feed_data ) { // @since 2.29.0 - To not start this function when using the WooCommerce Google Review Feed Manager plugin version 0.15.0 or lower.
			$this->handle_procedural_attributes( $product_data );
		}

		return $product_data;
	}
}
