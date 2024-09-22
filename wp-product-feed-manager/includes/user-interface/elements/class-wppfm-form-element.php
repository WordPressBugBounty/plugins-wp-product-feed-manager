<?php

/**
 * WPPFM Form Element Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.2
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Form_Element' ) ) :

	/**
	 * WPPFM Category Selector Element Class
	 *
	 * Contains the html elements code for the forms
	 */
	class WPPFM_Form_Element {

		public static function feed_editor_sub_title( $header_sub_title ) {
			return '<div class="wppfm-page__sub-title-wrapper"><p class="wppfm-tab-page-sub-title" id="wppfm-tab-page-sub-title">' . $header_sub_title . '<br><a href="#" target="_blank"></a></p></div>';
		}

		/**
		 * Returns the code for both Save & Generate and Save buttons.
		 *
		 * @param   string  $button_section_class   Class name for a whole button section
		 * @param   string  $button_section_id      ID for a whole button section
		 * @param   string  $generate_button_id     ID for the Save & Generate button
		 * @param   string  $save_button_id         ID for the Save button
		 * @param   string  $open_feed_button_id    ID for the Open Feed button
		 * @param   string  $initial_display        sets the initial display to any of the display style options (default none)
		 *
		 * @return string
		 */
		public static function feed_generation_buttons( $button_section_class, $button_section_id, $generate_button_id, $save_button_id, $open_feed_button_id, $initial_display = 'none' ) {
			return '<section class="' . $button_section_class . '" id="' . $button_section_id . '" style="display:' . $initial_display . ';">
				<div class="wppfm-inline-button-wrapper">
				<a href="#" class="wppfm-button wppfm-blue-button wppfm-disabled-button" id="' . $generate_button_id . '">' . esc_html__( 'Save & Generate Feed', 'wp-product-feed-manager' ) . '</a>
				</div>
				<div class="wppfm-inline-button-wrapper">
				<a href="#" class="wppfm-button wppfm-blue-button wppfm-disabled-button" id="' . $save_button_id . '">' . esc_html__( 'Save Feed', 'wp-product-feed-manager' ) . '</a>
				</div>
				<div class="wppfm-inline-button-wrapper">
				<a href="#" class="wppfm-button wppfm-blue-button wppfm-disabled-button" id="' . $open_feed_button_id . '">' . esc_html__( 'View Feed', 'wp-product-feed-manager' ) . '</a>
				</div>
				</section>';
		}
	}

	// end of WPPFM_Form_Element class

endif;
