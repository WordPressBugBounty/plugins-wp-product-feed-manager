<?php

/**
 * WPPPFM Google Merchant Promotions Feed Details Wrapper.
 *
 * @package WP Google Merchant Promotions Feed Manager/Classes
 * @since 2.39.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPPFM_Google_Merchant_Promotions_Feed_Product_Details_Wrapper' ) ) :

	class WPPPFM_Google_Merchant_Promotions_Feed_Product_Details_Wrapper extends WPPFM_Filter_Wrapper {

		/**
		 * Displays the Google Merchant Promotions Feed Product Details Wrapper.
		 *
		 * @param string $promotion_nr the promotion id.
		 */
		public function display( $promotion_nr ) {

			// Start with the section code.
			WPPPFM_Promotions_Details_selector_Element::promotions_details_section_start( $promotion_nr );

			WPPPFM_Promotions_Details_selector_Element::promotions_details_section_header();

			WPPPFM_Promotions_Details_selector_Element::promotions_details_content_box( $promotion_nr );

			WPPPFM_Promotions_Details_Selector_Element::promotions_details_section_close();
		}
	}

	// end of WPPPFM_Google_Merchant_Promotions_Feed_Product_Details_Wrapper class

endif;

