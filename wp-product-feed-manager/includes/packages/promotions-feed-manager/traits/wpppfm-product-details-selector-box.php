<?php

/**
 * @package WP Google Merchant Promotions Feed Manager/Classes/Traits
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WPPPFM_Product_Details_Selector_Box {

	/**
	 * Renders the product details selector content box.
	 *
	 * @param string $promotion_nr the promotion id.
	 */
	protected static function content_box( $promotion_nr ) {
		echo '<div class="wppfm-content-box-tab-list-back"></div>
			<ul class="wpppfm-details-tab-list wppfm-content-box-tab-list wppfm-tabs" style="display:none;">';

		$details_selector_tabs = apply_filters(
			'wpppfm_promotion_details_selector_tabs',
			array(
				'preconditions'         => array(
					'label'  => __( 'Preconditions', 'wp-product-feed-manager' ),
					'target' => 'wpppfm-promotions-details-preconditions-tab-' . $promotion_nr,
					'class'  => 'wpppfm-promotions-details-preconditions-tab',
				),
				'promotion_categories'  => array(
					'label'  => __( 'Promotion categories', 'wp-product-feed-manager' ),
					'target' => 'wpppfm-promotions-details-promotion-categories-tab-' . $promotion_nr,
					'class'  => '',
				),
				'limits'                => array(
					'label'  => __( 'Limits', 'wp-product-feed-manager' ),
					'target' => 'wpppfm-promotions-details-limits-tab-' . $promotion_nr,
					'class'  => '',
				),
				'additional_attributes' => array(
					'label'  => __( 'Additional attributes', 'wp-product-feed-manager' ),
					'target' => 'wpppfm-promotions-details-additional-attributes-tab-' . $promotion_nr,
					'class'  => '',
				),
			)
		);

		foreach ( $details_selector_tabs as $key => $tab ) {
			echo '<li class="' . esc_attr( $key ) . '_options ' . esc_attr( $key ) . '_tab ' . esc_attr( implode( ' ', (array) $tab['class'] ) ) . ' wppfm-content-box-tab-list-item">
				<a href="#' . esc_attr( $tab['target'] ) . '">
					<span>' . esc_html( $tab['label'] ) . '</span>
				</a>
			</li>';
		}

		echo '</ul>';

		echo
		'<!-- Preconditions Tab -->
		<div id="wpppfm-promotions-details-preconditions-tab-' . esc_attr( $promotion_nr ) . '" name="wpppfm-promotions-details-preconditions-tab-' . esc_attr( $promotion_nr ) . '" class="wppfm-panel wpppfm-promotions-details-panel">
			<p class="wpppfm-input-field form-field"><label for="wpppfm-minimum-purchase-amount-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Minimum purchase amount', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-minimum-purchase-amount-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="minimum_purchase_amount"></p>
			<p class="wpppfm-input-field form-field"><label for="wpppfm-buy-this-quantity-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Minimum purchase quantity for promotion', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-buy-this-quantity-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="buy_this_quantity"></p>
		</div>

		<!-- Promotion categories Tab -->
		<div id="wpppfm-promotions-details-promotion-categories-tab-' . esc_attr( $promotion_nr ) . '" name="wpppfm-promotions-details-promotion-categories-tab-' . esc_attr( $promotion_nr ) . '" class="wppfm-panel wpppfm-promotions-details-panel hidden">
			<p class="wpppfm-input-field form-field"><label for="wpppfm-percent-off-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Percentage discount amount', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-percent-off-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="percent_off"></p>
			<p class="wpppfm-input-field form-field"><label for="wpppfm-money-off-amount-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Monetary discount amount of a promotion', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-money-off-amount-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="money_off_amount"></p>
			<p class="wpppfm-input-field form-field"><label for="wpppfm-get-this-quantity-discounted-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Quantity eligible for promotion', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-get-this-quantity-discounted-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="get_this_quantity_discounted"></p>
			<p class="wpppfm-select-field form-field"><label for="wpppfm-free-shipping-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Free shipping', 'wp-product-feed-manager' ) . '</label>
				<select class="short wpppfm-select-field" id="wpppfm-free-shipping-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="free_shipping">
					<option value="">' . esc_html__( '-- optional --', 'wp-product-feed-manager' ) . '</option>
					<option value="free_shipping_standard">' . esc_html__( 'Free standard shipping', 'wp-product-feed-manager' ) . '</option>
					<option value="free_shipping_overnight">' . esc_html__( 'Free overnight shipping', 'wp-product-feed-manager' ) . '</option>
			</select></p>
			<p class="wpppfm-input-field form-field"><label for="wpppfm-free-gift-value-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Free gift of monetary value', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-free-gift-value-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="free_gift_value"></p>
			<p class="wpppfm-input-field form-field"><label for="wpppfm-free-gift-description-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Free gift description', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-free-gift-description-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="free_gift_description"></p>
			<p class="wpppfm-input-field form-field"><label for="wpppfm-free-gift-item-id-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Free gift item ID', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-free-gift-item-id-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="free_gift_item_id"></p>
			<p class="wpppfm-select-field form-field"><label for="wpppfm-coupon-value-type-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Coupon value type', 'wp-product-feed-manager' ) . '</label>
				<select class="short wpppfm-select-field" id="wpppfm-coupon-value-type-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="coupon_value_type">
					<option value="">' . esc_html__( '-- optional --', 'wp-product-feed-manager' ) . '</option>
					<option value="no_structured_data">' . esc_html__( 'No structured data', 'wp-product-feed-manager' ) . '</option>
					<option value="money_off">' . esc_html__( 'Money off', 'wp-product-feed-manager' ) . '</option>
					<option value="percent_off">' . esc_html__( 'Percent off', 'wp-product-feed-manager' ) . '</option>
					<option value="buy_m_get_n_money_off">' . esc_html__( 'Buy M get N money off', 'wp-product-feed-manager' ) . '</option>
					<option value="buy_m_get_n_percent_off">' . esc_html__( 'Buy M get N percent off', 'wp-product-feed-manager' ) . '</option>
					<option value="buy_m_get_percent_off">' . esc_html__( 'Buy M get percent off', 'wp-product-feed-manager' ) . '</option>
					<option value="buy_m_get_money_off">' . esc_html__( 'Buy M get money off', 'wp-product-feed-manager' ) . '</option>
					<option value="free_gift">' . esc_html__( 'Free gift', 'wp-product-feed-manager' ) . '</option>
					<option value="free_gift_with_value">' . esc_html__( 'Free gift with value', 'wp-product-feed-manager' ) . '</option>
					<option value="free_gift_with_item_id">' . esc_html__( 'Free gift with item ID', 'wp-product-feed-manager' ) . '</option>
					<option value="free_shipping_standard">' . esc_html__( 'Free shipping standard', 'wp-product-feed-manager' ) . '</option>
					<option value="free_shipping_overnight">' . esc_html__( 'Free shipping overnight', 'wp-product-feed-manager' ) . '</option>
					<option value="free_shipping_two_day">' . esc_html__( 'Free shipping two day', 'wp-product-feed-manager' ) . '</option>
					<option value="free_shipping_with_shipping_config">' . esc_html__( 'Free shipping with shipping config', 'wp-product-feed-manager' ) . '</option>
			</select></p>
		</div>

		<!-- Limits Tab -->
		<div id="wpppfm-promotions-details-limits-tab-' . esc_attr( $promotion_nr ) . '" name="wpppfm-promotions-details-limits-tab-' . esc_attr( $promotion_nr ) . '" class="wppfm-panel wpppfm-promotions-details-panel hidden">
			<p class="wpppfm-input-field form-field"><label for="wpppfm-limit-quantity-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Maximum purchase quantity for promotion', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-limit-quantity-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="limit_quantity"></p>
			<p class="wpppfm-input-field form-field"><label for="wpppfm-limit-value-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Maximum product price for promotion', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-limit-value-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="limit_value"></p>
		</div>

		<!-- Additional attributes Tab -->
		<div id="wpppfm-promotions-details-additional-attributes-tab-' . esc_attr( $promotion_nr ) . '" name="wpppfm-promotions-details-additional-attributes-tab-' . esc_attr( $promotion_nr ) . '" class="wppfm-panel wpppfm-promotions-details-panel hidden">
			<p class="wpppfm-input-field form-field wpppfm-text-input-row"><label for="wpppfm-promotion-display-dates-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Display dates for promotion', 'wp-product-feed-manager' ) . '</label>
				<td>' . esc_html__( 'from ', 'wp-product-feed-manager' ) . '<input type="text" class="datepicker date-time-picker wpppfm-date-time-picker" name="wppfm-promotion-display-start-date" id="wpppfm-promotion-display-start-date-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="promotion_display_start_date" />'
				. esc_html__( ' till ', 'wp-product-feed-manager' ) . '<input type="text" class="datepicker date-time-picker wpppfm-date-time-picker" name="wppfm-promotion-display-end-date" id="wpppfm-promotion-display-end-date-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="promotion_display_end_date" /></td></tr>
			<p class="wpppfm-input-field form-field wpppfm-textarea-row"><label for="wpppfm-description-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Description', 'wp-product-feed-manager' ) . '</label>
				<textarea class="short wpppfm-text-area-field" id="wpppfm-description-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="description"></textarea></p>
			<p class="wpppfm-input-field form-field wpppfm-text-input-row"><label for="wpppfm-image-link-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Image link', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-image-link-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="image_link"></p>
			<p class="wpppfm-input-field form-field wpppfm-textarea-row"><label for="wpppfm-fine-print-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Fine print for promotion', 'wp-product-feed-manager' ) . '</label>
				<textarea class="short wpppfm-text-area-field" id="wpppfm-fine-print-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="fine_print"></textarea></p>
			<p class="wpppfm-input-field form-field wpppfm-text-input-row"><label for="wpppfm-promotion-price-input-field-' . esc_attr( $promotion_nr ) . '">' . esc_html__( 'Price for promotion', 'wp-product-feed-manager' ) . '</label>
				<input type="text" class="short wpppfm-text-input-field" id="wpppfm-promotion-price-input-field-' . esc_attr( $promotion_nr ) . '" data-attribute-key="promotion_price"></p>
		</div>';
	}
}
