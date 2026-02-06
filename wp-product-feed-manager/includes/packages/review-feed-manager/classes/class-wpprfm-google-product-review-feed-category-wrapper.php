<?php

/**
 * WPPRFM Google Product Review Feed Category Wrapper.
 *
 * @package WP Product Review Feed Manager/Classes
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPRFM_Google_Product_Review_Feed_Category_Wrapper' ) ) :

	class WPPRFM_Google_Product_Review_Feed_Category_Wrapper extends WPPFM_Category_Wrapper {

		/**
		 * Renders the Category wrapper for a Google Product Review feed.
		 */
		public function display() {
			// Start with the section code.
			echo '<section class="wppfm-category-mapping-and-filter-wrapper">
				<section class="wpprfm-edit-review-feed-form-element-wrapper wppfm-category-mapping-wrapper" id="wppfm-category-map" style="display:none;">
				<div id="wppfm-review-feed-editor-category-mapping-header" class="wppfm-feed-editor-section__header"><h3>' . esc_html__( 'Category Selector', 'wp-product-feed-manager' ) . ':</h3></div>
				<table class="wppfm-category-mapping-table wppfm-table widefat" id="wppfm-review-feed-category-mapping-table">';

			// The category mapping table header.
			WPPFM_Category_Selector_Element::category_selector_table_head();

			echo '<tbody id="wppfm-category-selector-body">';
			// The content of the table.
			$this->category_table_content();
			echo '</tbody></table></section>';

			// Add the product filter element.
			$this->product_filter();

			echo '</section>';
		}
	}

	// end of WPPRFM_Google_Product_Review_Feed_Category_Wrapper class

endif;
