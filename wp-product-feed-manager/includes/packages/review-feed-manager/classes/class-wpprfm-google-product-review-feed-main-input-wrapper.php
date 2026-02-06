<?php

/**
 * WPPRFM Google Product Review Feed Main Input Wrapper.
 *
 * @package WP Google Product Review Feed Manager/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPRFM_Google_Product_Review_Feed_Main_Input_Wrapper' ) ) :

	class WPPRFM_Google_Product_Review_Feed_Main_Input_Wrapper extends WPPFM_Main_Input_Wrapper {

		/**
		 * Display the Google product review feed main input table.
		 */
		public function display() {
			// Start with the table and body code
			$this->main_input_wrapper_table_start();

			// Feed file name input
			WPPRFM_Main_Input_Selector_Element::file_name_input_element();

			// Channel selector
			WPPFM_Main_Input_Selector_Element::merchant_selector_element();

			// Google Feed type selector
			WPPFM_Main_Input_Selector_Element::google_type_selector_element( '2' );

			// Aggregator name input
			WPPRFM_Main_Input_Selector_Element::aggregator_name_input_element();

			// Publisher name input
			WPPRFM_Main_Input_Selector_Element::publisher_name_input_element();

			// Publisher favorite icon url input
			WPPRFM_Main_Input_Selector_Element::publisher_favicon_input_element();

			// Feed update schedule selector
			WPPFM_Main_Input_Selector_Element::feed_update_schedule_selector_element( 'table-row' );

			// Close the body and table code
			$this->main_input_wrapper_table_end();
		}
	}

	// end of WPPRFM_Google_Product_Review_Feed_Main_Input_Wrapper class

endif;
