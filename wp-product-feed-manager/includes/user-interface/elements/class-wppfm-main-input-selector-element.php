<?php

/**
 * WPPFM Product Feed Category Selector Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Main_Input_Selector_Element' ) ) :

	class WPPFM_Main_Input_Selector_Element {

		/**
		 * Renders file name input field element.
		 */
		public static function file_name_input_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wppfm-main-feed-selector-row">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-feed-file-name">' . esc_html__( 'File Name', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><input type="text" name="wppfm-feed-file-name" id="wppfm-feed-file-name" /></td></tr>';
		}

		/**
		 * Renders a product source selector element.
		 */
		public static function product_source_selector_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wppfm-product-source-selector-row" style="display:none;">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-sources">' . esc_html__( 'Products source', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::source_selector();
			echo '</td></tr>';
		}

		/**
		 * Renders a merchant selector element.
		 */
		public static function merchant_selector_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wppfm-merchant-selector-row">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-merchants-selector">' . esc_html__( 'Channel', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::channel_selector();
			echo '</td></tr>';
		}

		/**
		 * Renders a Google Feed Type selector element.
		 *
		 * @since 2.38.0.
		 * @param string $preselected preselected feed type.
		 */
		public static function google_type_selector_element( $preselected ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wppfm-feed-types-list-row" style="display:none">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-feed-types-selector">' . esc_html__( 'Google Feed Type', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::feed_type_selector( $preselected );
			echo '</td></tr>';
		}

		/**
		 * Renders Google a Dynamic Remarketing Business Type selector element.
		 * This selector is only used for the Google Dynamic Remarketing feed.
		 *
		 * $since 3.1.0
		 */
		public static function google_dynamic_remarketing_business_type_selector_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wppfm-feed-dynamic-remarketing-business-types-list-row" style="display:none">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-feed-drm-types-selector">' . esc_html__( 'Business Type', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::feed_business_type_selector();
			echo '</td></tr>';
		}

		/**
		 * Renders a country selector element.
		 */
		public static function country_selector_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wppfm-country-list-row" style="display:none;">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-countries-selector">' . esc_html__( 'Target Country', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::country_selector();
			echo '</td></tr>';
		}

		/**
		 * Renders a default category list element.
		 */
		public static function category_list_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="category-list-row" style="display:none;">
					<th id="wppfm-main-feed-input-label"><label>' . esc_html__( 'Default Category', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Category_Selector_Element::category_mapping_selector( 'lvl', '-1', true );
			echo '</td></tr>';
		}

		/**
		 * Renders an aggregator selector element.
		 */
		public static function aggregator_selector_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="aggregator-selector-row" style="display:none">
					<th id="wppfm-main-feed-input-label"><label
						for="aggregator">' . esc_html__( 'Aggregator Shop', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::aggregation_selector();
			echo '</td></tr>';
		}

		/**
		 * Renders a 'include product variation' selector element.
		 */
		public static function product_variation_selector_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="add-product-variations-row" style="display:none">
					<th id="wppfm-main-feed-input-label"><label
						for="variations">' . esc_html__( 'Include Product Variations', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::product_variation_selector();
			echo '</td></tr>';
		}

		/**
		 * Renders a product feed title field element.
		 */
		public static function google_product_feed_title_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="google-feed-title-row" style="display:none">
					<th id="wppfm-main-feed-input-label"><label
						for="google-feed-title-selector">' . esc_html__( 'Feed Title', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::google_feed_title_selector();
			echo '</td></tr>';
		}

		/**
		 * Renders a product feed description field element.
		 */
		public static function google_product_feed_description_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="google-feed-description-row" style="display:none">
					<th id="wppfm-main-feed-input-label"><label
						for="google-feed-description-selector">' . esc_html__( 'Feed Description', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::google_feed_description_selector();
			echo '</td></tr>';
		}

		/**
		 * Renders a feed update schedule selector element.
		 *
		 * @param string $display initial display style for  this element (default none).
		 */
		public static function feed_update_schedule_selector_element( $display = 'none' ) {
			echo '<tr class="wppfm-main-feed-input-row" id="update-schedule-row" style="display:' . esc_attr( $display ) . '">
					<th id="wppfm-main-feed-input-label"><label>' . esc_html__( 'Update Schedule', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::schedule_selector();
			echo '</td></tr>';
		}
	}

	// end of WPPFM_Main_Input_Selector_Element class

endif;
