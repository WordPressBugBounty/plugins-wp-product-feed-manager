<?php

/**
 * WPPPFM Google Merchant Promotion_Wrapper.
 *
 * @package WP Google Merchant Promotions Feed Manager/Classes
 * @since 2.41.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPPFM_Google_Merchant_Promotion_Wrapper' ) ) :

	class WPPPFM_Google_Merchant_Promotion_Wrapper {

		private $promotion_nr;

		public function __construct( $promotion_nr = 'template' ) {
			$this->promotion_nr = $promotion_nr;
		}

		/**
		 * Display the product feed attribute mapping table.
		 */
		public function display() {
			$this->promotion_wrapper();

			$this->promotion_header_buttons();

			$this->mandatory_promotion_fields();

			$this->product_filters_table();

			$this->product_details_table();

			$this->end_of_promotion_wrapper();
		}

		/**
		 * Renders the promotion wrapper. The promotion wrapper contains all the promotion fields, divided over the header buttons, mandatory fields, product filters, and product details.
		 */
		private function promotion_wrapper() {
			echo '<section class="wpppfm-promotion-wrapper" id="wpppfm-promotion-wrapper-' . esc_attr( $this->promotion_nr ) . '" style="display: none">';
		}

		/**
		 * Renders the promotion header buttons.
		 */
		private function promotion_header_buttons() {
			echo '<section class="wpppfm-promotion-header-buttons" id="wpppfm-promotion-buttons-' . esc_attr( $this->promotion_nr ) . '">
				<a href="javascript:void(0);" id="wpppfm-promotion-add-button-' . esc_attr( $this->promotion_nr ) . '" class="wpppfm-promotion-header-button" onclick="wpppfm_addPromotion()">' . esc_html__( 'Add', 'wp-product-feed-manager' ) . '</a>  
				<a href="javascript:void(0);" id="wpppfm-promotion-delete-button-' . esc_attr( $this->promotion_nr ) . '" class="wpppfm-promotion-header-button" onclick="wpppfm_deletePromotion(\'' . esc_attr( $this->promotion_nr ) . '\')">' . esc_html__( 'Delete', 'wp-product-feed-manager' ) . '</a>  
				<a href="javascript:void(0);" id="wpppfm-promotion-duplicate-button-' . esc_attr( $this->promotion_nr ) . '" class="wpppfm-promotion-header-button" onclick="wpppfm_duplicatePromotion(\'' . esc_attr( $this->promotion_nr ) . '\')">' . esc_html__( 'Duplicate', 'wp-product-feed-manager' ) . '</a>
			</section>';
		}

		/**
		 * The mandatory promotion fields.
		 */
		private function mandatory_promotion_fields() {
			$mandatory_fields_wrapper = new WPPPFM_Google_Merchant_Promotions_Feed_Mandatory_Input_Wrapper( $this->promotion_nr );
			$mandatory_fields_wrapper->display();
		}

		/**
		 * The product filters table.
		 */
		private function product_filters_table() {
			$product_filters_wrapper = new WPPPFM_Google_Merchant_Promotions_Feed_Product_Filters_Wrapper();
			$product_filters_wrapper->display( $this->promotion_nr );
		}

		/**
		 * The product details table.
		 */
		private function product_details_table() {
			$product_details_wrapper = new WPPPFM_Google_Merchant_Promotions_Feed_Product_Details_Wrapper();
			$product_details_wrapper->display( $this->promotion_nr );
		}

		/**
		 * The end of the promotion wrapper.
		 */
		private function end_of_promotion_wrapper() {
			echo '</section>';
		}
	}

	// end of WPPPFM_Google_Merchant_Promotion_Wrapper class

endif;

