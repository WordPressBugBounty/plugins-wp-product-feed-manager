<?php

/**
 * WPPPFM Google Merchant Promotions Feed Main Input Wrapper.
 *
 * @package WP Google Merchant Promotions Feed Manager/Classes
 * @since 2.39.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPPFM_Google_Merchant_Promotions_Feed_Main_Input_Wrapper' ) ) :

	class WPPPFM_Google_Merchant_Promotions_Feed_Main_Input_Wrapper extends WPPFM_Main_Input_Wrapper {

		/**
		 * The Google product review feed main input table.
		 */
		public function display() {
			// Start with the table and body code
			$this->main_input_wrapper_table_start();

			// Feed file name input
			WPPPFM_Main_Input_Selector_Element::file_name_input_element();

			// Channel selector
			WPPFM_Main_Input_Selector_Element::merchant_selector_element();

			// Google Feed type selector
			WPPFM_Main_Input_Selector_Element::google_type_selector_element( '3' );

			// Close the body and table code
			$this->main_input_wrapper_table_end();
		}
	}

	// end of WPPPFM_Google_Merchant_Promotions_Feed_Main_Input_Wrapper class

endif;

