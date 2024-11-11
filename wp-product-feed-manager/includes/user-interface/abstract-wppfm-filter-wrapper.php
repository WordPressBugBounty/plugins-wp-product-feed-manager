<?php

/**
 * WPPFM Filter Wrapper Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.39.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Filter_Wrapper' ) ) :

	abstract class WPPFM_Filter_Wrapper {

		protected abstract function display( $promotion_nr );

		/**
		 * The "include products" form.
		 *
		 * @param string $promotion_nr the promotion id.
		 */
		protected function include_products_input( $promotion_nr ) {
			WPPFM_Product_Filter_Selector_Element::include_products_input( $promotion_nr );
		}

		/**
		 * The "exclude products" form.
		 *
		 * @param string $promotion_nr the promotion id.
		 */
		protected function exclude_products_input( $promotion_nr ) {
			WPPFM_Product_Filter_Selector_Element::exclude_products_input( $promotion_nr );
		}
	}

	// end of WPPFM_Filter_Wrapper class

endif;
