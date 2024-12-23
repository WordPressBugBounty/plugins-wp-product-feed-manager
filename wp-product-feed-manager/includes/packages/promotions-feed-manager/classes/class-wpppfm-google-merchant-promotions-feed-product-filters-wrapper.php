<?php

/**
 * WPPPFM Google Merchant Promotions Feed Filters Wrapper.
 *
 * @package WP Google Merchant Promotions Feed Manager/Classes
 * @since 2.39.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPPFM_Google_Merchant_Promotions_Feed_Product_Filters_Wrapper' ) ) :

	class WPPPFM_Google_Merchant_Promotions_Feed_Product_Filters_Wrapper extends WPPFM_Filter_Wrapper {

		/**
		 * Renders the Google Merchant Promotions Filter Wrapper.
		 *
		 * @param string $promotion_nr the promotion id.
		 */
		public function display( $promotion_nr ) {

			// Start with the section code.
			echo '<section class="wpppfm-edit-promotions-feed-form-element-wrapper wpppfm-product-filter-wrapper" id="wppfm-product-filter-map-' . esc_attr( $promotion_nr ) . '" style="display: none">
			<div id="wpppfm-filter-header" class="wppfm-feed-editor-section__header"><h3>' . esc_html__( 'Product Filter Selector', 'wp-product-feed-manager' ) . ':</h3></div>
			<table class="wppfm-product-filter-table widefat" id="wppfm-product-filter-table-' . esc_attr( $promotion_nr ) . '">';

			$this->include_products_input( $promotion_nr );

			$this->exclude_products_input( $promotion_nr );

			// Closing the section.
			echo '</table></section>';
		}
	}

	// end of WPPPFM_Google_Merchant_Promotions_Feed_Product_Filters_Wrapper class

endif;
