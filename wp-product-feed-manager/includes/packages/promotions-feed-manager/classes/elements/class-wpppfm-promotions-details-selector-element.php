<?php

/**
 * WPPRFM Google Merchant Promotions Details Selector Element Class.
 *
 * @package WP Google Merchant Promotions Feed Manager/Classes/Elements
 * @since 2.39.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPPFM_Promotions_Details_Selector_Element' ) ) :

	class WPPPFM_Promotions_Details_Selector_Element {

		use WPPPFM_Product_Details_Selector_Box;

		/**
		 * Renders the promotion details section start code.
		 *
		 * @param string $promotion_nr the promotion id.
		 */
		public static function promotions_details_section_start( $promotion_nr ) {
			echo '<section class="wpppfm-edit-promotions-feed-form-element-wrapper wpppfm-product-details-wrapper" id="wpppfm-product-details-map-' . esc_attr( $promotion_nr ) . '" style="display: none;">
				<div id="wpppfm-details-selector" class="wpppfm-details-selector wppfm-selector-box">';
		}

		/**
		 * Renders the promotion details selection header.
		 */
		public static function promotions_details_section_header() {
			echo '<div id="wpppfm-details-header" class="wppfm-selector-box-header"><h2 class="wppfm-selector-box-header">' . esc_html__( 'Promotion Details Selector', 'wp-product-feed-manager' ) . ':</h2></div>';
		}

		/**
		 * Renders the Promotions Details content box.
		 *
		 * @param string $promotion_nr the promotion id.
		 */
		public static function promotions_details_content_box( $promotion_nr ) {
			echo '<div id="wpppfm-details-content-box' . esc_attr( $promotion_nr ) . '" class="wppfm-selector-box-content">
				<div class="wppfm-selector-box-content-panel-wrapper panel-wrap">';
			self::content_box( $promotion_nr );
			echo '</div></div>';
		}

		/**
		 * Renders the promotion details selection end code.
		 */
		public static function promotions_details_section_close() {
			echo '</div></section>';
		}
	}

	// end of WPPPFM_Promotions_Details_Selector_Element class

endif;
