<?php

/**
 * WPPRFM Google Merchant Promotions Main Input Selector Element Class.
 *
 * @package WP Google Merchant Promotions Feed Manager/Classes/Elements
 * @since 2.39.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPPFM_Main_Input_Selector_Element' ) ) :

	class WPPPFM_Main_Input_Selector_Element {

		/**
		 * Renders the file name input field.
		 */
		public static function file_name_input_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-file-name-input-row">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-feed-file-name">' . esc_html__( 'File Name', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><input type="text" name="wppfm-feed-file-name" id="wppfm-feed-file-name" /></td></tr>';
		}

		/**
		 * Renders the promotion id input field.
		 *
		 * @param string $promotion_nr the promotion id number.
		 */
		public static function promotion_id_input_element( $promotion_nr ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-promotion-id-input-row">
					<th id="wppfm-main-feed-input-label"><label
						for="wpppfm-promotion-id-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Promotion ID', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><input type="text" name="wpppfm-promotion-id" id="wpppfm-promotion-id-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="promotion_id" /></td></tr>';
		}

		/**
		 * Renders the eligible for promotion field.
		 *
		 * @param string $promotion_nr the promotion id number.
		 */
		public static function products_eligible_for_promotion_select_element( $promotion_nr ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-products-eligible-for-promotion-select-row">
					<th id="wppfm-main-feed-input-label"><label
						for="wpppfm-product-applicability-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Products Eligible for Promotion', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><select class="wppfm-main-input-selector" name="wppfm-products-eligible-for-promotion-select" id="wpppfm-product-applicability-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="product_applicability">
						<option value="all_products">' . esc_html__( 'All Products', 'wp-product-feed-manager' ) . '</option>
						<option value="specific_products">' . esc_html__( 'Specific Products', 'wp-product-feed-manager' ) . '</option>
					</select></td></tr>';
		}

		/**
		 * Renders the coupon code required selector.
		 *
		 * @param string $promotion_nr the promotion id number.
		 */
		public static function coupon_code_required_select_element( $promotion_nr ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-coupon-code-required-select-row">
					<th id="wppfm-main-feed-input-label"><label
						for="wpppfm-offer-type-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Coupon Code Required', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><select class="wppfm-main-input-selector" name="wppfm-coupon-code-required-select" id="wpppfm-offer-type-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="offer_type">
						<option value="no_code">' . esc_html__( 'No code', 'wp-product-feed-manager' ) . '</option>
						<option value="generic_code">' . esc_html__( 'Generic code', 'wp-product-feed-manager' ) . '</option>
					</select></td></tr>';
		}

		/**
		 * Renders the generic redemption code input selector.
		 *
		 * @param string $promotion_nr the promotion id number.
		 */
		public static function generic_redemption_code_input_element( $promotion_nr ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-generic-redemption-code-input-row-'. esc_attr( $promotion_nr ) . '" style="display: none">
					<th id="wppfm-main-feed-input-label"><label
						for="wpppfm-generic-redemption-code-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Generic Redemption Code', 'wp-product-feed-manager' ) . '</label> :
					</th>
					<td><input type="text" name="wppfm-generic-redemption-code" id="wpppfm-generic-redemption-code-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="generic_redemption_code" /></td></tr>';
		}

		/**
		 * Renders the promotions title input field.
		 *
		 * @param string $promotion_nr the promotion id number.
		 */
		public static function promotion_title_input_element( $promotion_nr ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-promotion-title-input-row">
				<th id="wppfm-main-feed-input-label"><label
					for="wpppfm-long-title-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Promotion Title', 'wp-product-feed-manager' ) . '</label> :
				</th>
				<td><input type="text" name="wppfm-promotion-title" id="wpppfm-long-title-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="long_title" /></td></tr>';
		}

		/**
		 * Renders the promotion effective date input element.
		 *
		 * @param string $promotion_nr the promotion id number.
		 */
		public static function promotion_effective_date_input_element( $promotion_nr ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-promotion-effective-date-input-row">
				<th id="wppfm-main-feed-input-label"><label
					for="wppfm-promotion-effective-date-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Promotion Effective Dates', 'wp-product-feed-manager' ) . '</label> :
				</th>
				<td>' . esc_html__( 'from ', 'wp-product-feed-manager' ) . '<input type="text" class="datepicker date-time-picker wpppfm-date-time-picker" name="wppfm-promotion-effective-start-date" id="wpppfm-promotion-effective-start-date-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="promotion_effective_start_date" />'
				. esc_html__( ' till ', 'wp-product-feed-manager' ) . '<input type="text" class="datepicker date-time-picker wpppfm-date-time-picker" name="wppfm-promotion-effective-end-date" id="wpppfm-promotion-effective-end-date-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="promotion_effective_end_date" /></td></tr>';
		}

		/**
		 * Renders the eligible channel for a promotion select element.
		 *
		 * @param string $promotion_nr the promotion id number.
		 */
		public static function eligible_channel_for_promotion_select_element( $promotion_nr ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-eligible-channel-for-promotion-select-row">
				<th id="wppfm-main-feed-input-label"><label
					for="wpppfm-redemption-channel-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Eligible Channel for Promotion', 'wp-product-feed-manager' ) . '</label> :
				</th>
				<td><select class="wppfm-main-input-selector" name="wppfm-eligible-channel-for-promotion-select" id="wpppfm-redemption-channel-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="redemption_channel">
					<option value="online">' . esc_html__( 'Online', 'wp-product-feed-manager' ) . '</option>
					<option value="in_store">' . esc_html__( 'In store', 'wp-product-feed-manager' ) . '</option>
				</select></td></tr>';
		}

		/**
		 * Renders the promotion destination select element.
		 *
		 * @param string $promotion_nr the promotion id number.
		 */
		public static function promotion_destination_select_element( $promotion_nr ) {
			echo '<tr class="wppfm-main-feed-input-row" id="wpppfm-promotion-destination-select-row">
				<th id="wppfm-main-feed-input-label"><label
					for="wppfm-promotion-destination-select-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Promotion Destination', 'wp-product-feed-manager' ) . '</label> :
				</th>
				<td><select class="wppfm-main-input-selector wppfm-select2-promotion-destination-pillbox-selector" name="wppfm-promotion-destination-select" id="wpppfm-promotion-destination-input-field-' . esc_attr( $promotion_nr ) . '" multiple="multiple" data-attribute-key="promotion_destination"></select>
				</td></tr>';
		}
	}

	// end of WPPRPM_Main_Input_Selector_Element class

endif;
