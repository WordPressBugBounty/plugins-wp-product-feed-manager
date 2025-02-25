<?php

/**
 * WPPRFM Google Product Review Main Input Selector Element Class.
 *
 * @package WP Google Product Review Feed Manager/Classes/Elements
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPRFM_Main_Input_Selector_Element' ) ) :

	class WPPRFM_Main_Input_Selector_Element {

		/**
		 * Returns the file name input field code.
		 */
		public static function file_name_input_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wpprfm-file-name-input-row">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-feed-file-name">' . esc_html__( 'File Name', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><input type="text" name="wppfm-feed-file-name" id="wppfm-feed-file-name" /></td></tr>';
		}

		/**
		 * Returns the aggregator name input field code.
		 */
		public static function aggregator_name_input_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wpprfm-aggregator-name-input-row">
					<th id="wppfm-main-feed-input-label"><label
						for="aggregator-name">' . esc_html__( 'Aggregator Name', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><input type="text" name="aggregator-name" id="wpprfm-aggregator-name" /></td></tr>';
		}

		/**
		 * Returns the publisher name input field code.
		 */
		public static function publisher_name_input_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wpprfm-publisher-name-input-row">
					<th id="wppfm-main-feed-input-label"><label
						for="publisher-name">' . esc_html__( 'Publisher Name', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><input type="text" name="publisher-name" id="wpprfm-publisher-name" /></td></tr>';
		}

		/**
		 * Returns the publisher favicon input field code.
		 */
		public static function publisher_favicon_input_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wpprfm-publisher-favicon-input-row">
					<th id="wppfm-main-feed-input-label"><label
						for="publisher-favicon">' . esc_html__( 'Publisher Icon (url)', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><input type="text" name="publisher-favicon" id="wpprfm-publisher-favicon" /></td></tr>';
		}

	}

	// end of WPPRFM_Main_Input_Selector_Element class

endif;
