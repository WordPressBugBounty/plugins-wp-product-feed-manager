<?php

/**
 * WPPFM Main Input Wrapper Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Main_Input_Wrapper' ) ) :

	abstract class WPPFM_Main_Input_Wrapper {

		abstract public function display();

		/**
		 * Renders the table and tbody opening code for the main input wrapper.
		 */
		protected function main_input_wrapper_table_start() {
			echo '<section class="wppfm-feed-editor-form-section wppfm-main-input-wrapper" id="wppfm-main-input-map"><table class="wppfm-feed-editor-main-input-table"><tbody id="wppfm-main-feed-data">';
		}

		/**
		 * Renders the table and tbody closing code for the main input wrapper.
		 */
		protected function main_input_wrapper_table_end() {
			echo '</tbody></table></section>';
		}
	}

	// end of WPPFM_Main_Input_Wrapper class

endif;
