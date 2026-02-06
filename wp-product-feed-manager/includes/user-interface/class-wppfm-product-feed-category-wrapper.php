<?php

/**
 * WPPFM Product Feed Category Wrapper Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Product_Feed_Category_Wrapper' ) ) :

	class WPPFM_Product_Feed_Category_Wrapper extends WPPFM_Category_Wrapper {

		/**
		 * Renders a product feed category mapping table.
		 */
		public function display() {
			// Start with the section code.
			echo '<section class="wppfm-category-mapping-and-filter-wrapper">
				<section class="wppfm-feed-editor-form-section wppfm-category-mapping-wrapper" id="wppfm-category-map" style="display:none;">
				<div id="wppfm-feed-editor-category-mapping-header" class="wppfm-feed-editor-section__header"><h3>' . esc_html__( 'Category Mapping', 'wp-product-feed-manager' ) . ':</h3></div>
				<table class="wppfm-category-mapping-table wppfm-table widefat" id="wppfm-product-feed-category-mapping-table">';

			// The category mapping table header.
			WPPFM_Category_Selector_Element::category_selector_table_head( 'mapping' );

			echo'<tbody id="wppfm-category-mapping-body">';
			// The content of the table.
			$this->category_table_content( 'mapping' );

			echo '</tbody></table></section>';

			// Add the product filter element.
			$this->product_filter();

			echo '</section>';
		}
	}

	// end of WPPFM_Product_Feed_Category_Wrapper class

endif;
