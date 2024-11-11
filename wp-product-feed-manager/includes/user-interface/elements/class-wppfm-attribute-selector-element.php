<?php

/**
 * WPPFM Attribute Selector Element Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Attribute_Selector_Element' ) ) :

	class WPPFM_Attribute_Selector_Element {

		/**
		 * Renders the required fields wrapper.
		 */
		public static function required_fields() {
			echo '<div class="wppfm-feed-editor-attributes-wrapper" id="wppfm-required-fields" style="display:block;">
				<legend class="wppfm-feed-editor-attributes__label">
				<h4 id="wppfm-required-attributes-header">' . esc_html__( 'Required attributes', 'wp-product-feed-manager' ) . ':</h4>
				</legend>';
				self::attributes_wrapper_table_header();
				echo '<div class="wppfm-feed-editor-attributes__table" id="wppfm-required-field-table"></div></div>';
		}

		/**
		 * Renders the highly recommended fields wrapper.
		 */
		public static function highly_recommended_fields() {
			echo '<div class="wppfm-feed-editor-attributes-wrapper" id="wppfm-highly-recommended-fields" style="display:none;">
				<legend class="wppfm-feed-editor-attributes__label">
				<h4 id="wppfm-highly-recommended-attributes-header">' . esc_html__( 'Highly recommended attributes', 'wp-product-feed-manager' ) . ':</h4>
				</legend>';
				self::attributes_wrapper_table_header();
				echo '<div class="wppfm-feed-editor-attributes__table" id="wppfm-highly-recommended-field-table"></div></div>';
		}

		/**
		 * Renders the recommended fields wrapper.
		 */
		public static function recommended_fields() {
			echo '<div class="wppfm-feed-editor-attributes-wrapper" id="wppfm-recommended-fields" style="display:none;">
				<legend class="wppfm-feed-editor-attributes__label">
				<h4 id="wppfm-recommended-attributes-header">' . esc_html__( 'Recommended attributes', 'wp-product-feed-manager' ) . ':</h4>
				</legend>';
				self::attributes_wrapper_table_header();
				echo '<div class="wppfm-feed-editor-attributes__table" id="wppfm-recommended-field-table"></div>
				</div>';
		}

		/**
		 * Renders the optional fields' wrapper.
		 */
		public static function optional_fields() {
			echo '<div class="wppfm-feed-editor-attributes-wrapper" id="wppfm-optional-fields" style="display:block;">
				<legend class="wppfm-feed-editor-attributes__label">
				<h4 id="wppfm-optional-attributes-header">' . esc_html__( 'Optional attributes', 'wp-product-feed-manager' ) . ':</h4>
				</legend>';
				self::attributes_wrapper_table_header();
				echo '<div class="wppfm-feed-editor-attributes__table" id="wppfm-optional-field-table"></div>
				</div>';
		}

		/**
		 * Renders the custom fields' wrapper.
		 */
		public static function custom_fields() {
			echo '<div class="wppfm-feed-editor-attributes-wrapper" id="wppfm-custom-fields" style="display:block;">
				<legend class="wppfm-feed-editor-attributes__label">
				<h4 id="wppfm-custom-attributes-header">' . esc_html__( 'Custom attributes', 'wp-product-feed-manager' ) . ':</h4>
				</legend>';
				self::attributes_wrapper_table_header();
				echo '<div class="wppfm-feed-editor-attributes__table" id="wppfm-custom-field-table"></div>
				</div>';
		}

		/**
		 * Renders the feed form table titles
		 */
		private static function attributes_wrapper_table_header() {
			echo '<div class="wppfm-feed-editor-attributes__table-header">
				<div class="wppfm-column-header wppfm-col20w">' . esc_html__( 'Attributes', 'wp-product-feed-manager' ) . '</div>
				<div
					class="wppfm-column-header wppfm-col30w">' . esc_html__( 'From WooCommerce source', 'wp-product-feed-manager' ) . '</div>
				<div class="wppfm-column-header wppfm-col40w">' . esc_html__( 'Condition', 'wp-product-feed-manager' ) . '</div>
			</div>';
		}
	}

	// end of WPPFM_Attribute_Selector_Element class

endif;
