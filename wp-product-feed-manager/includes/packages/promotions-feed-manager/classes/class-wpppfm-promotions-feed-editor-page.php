<?php

/**
 * WPPRFM Google Product Promotions Feed Page Class.
 *
 * @package WP Product Promotions Feed Manager/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPPFM_Promotions_Feed_Editor_Page' ) ) :

	/**
	 * WPPPFM Feed Form Class
	 */
	class WPPPFM_Promotions_Feed_Editor_Page {

		/**
		 * @var string|null contains the feed id, null for a new feed.
		 */
		private $_feed_id;

		/**
		 * @var array|null  container for the feed data.
		 */
		private $_feed_data;

		public function __construct() {

			wppfm_check_db_version();

			$this->_feed_id = filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT );

			// Fill the _feed_data container.
			$this->set_feed_data();

			// Load the language scripts.
			/** @noinspection PhpVoidFunctionResultUsedInspection */
			add_option( 'wp_enqueue_scripts', WPPFM_i18n_Scripts::wppfm_feed_settings_i18n() );
		}

		public function display() {
			$this->add_data_storage();
			$this->promotions_feed_editor_page();
		}

		/**
		 * Collects the HTML code for the Google merchant promotions feed form page and displays it on the screen.
		 */
		private function promotions_feed_editor_page() {
			echo '<div class="wppfm-page__title" id="wppfm-edit-feed-title"><h1>' . esc_html__( 'Product Feed Editor', 'wp-product-feed-manager' ) . '</h1></div>';
			$this->sub_title();
			echo '</div>';

			echo '<div class="wpppfm-promotions-feed-editor-wrapper">';

			$this->main_input_table();

			$this->promotions_feed_top_buttons();

			$this->start_promotions_area();

			$this->promotion_template();

			$this->end_promotions_area();

			$this->promotions_feed_bottom_buttons();

			echo '</div>';
		}

		/**
		 * Stores data in the DOM for the Feed Manager Feed Editor page
		 */
		private function add_data_storage() {
			echo
				'<div id="wppfm-feed-editor-page-data-storage" class="wppfm-data-storage-element"
				data-wppfm-feed-data="' . wc_esc_json( wp_json_encode( $this->_feed_data ), false ) . '"
				data-wppfm-ajax-feed-data-to-database-conversion-array=' . esc_attr( wp_json_encode( wppfm_ajax_feed_data_to_database_array( 'google-merchant-promotions-feed' ) ) ) . '
				data-wppfm-feed-url="' . esc_url( $this->_feed_data['url'] ) . '"
				data-wppfm-all-feed-names="' . esc_attr( implode( ';;',  wppfm_get_all_feed_names() ) ) . '"
				data-wppfm-plugin-version-id="' . esc_attr( WPPFM_PLUGIN_VERSION_ID ) . '" 
				data-wppfm-plugin-version-nr="' . esc_attr( WPPFM_VERSION_NUM ) . '"
				data-wppfm-plugin-distributor="' . esc_attr( WPPFM_PLUGIN_DISTRIBUTOR ) . '">
			</div>';
		}

		/**
		 * The promotion feed editor page subtitle.
		 */
		private function sub_title() {
			WPPFM_Form_Element::feed_editor_sub_title( wppfm_feed_form_sub_header_text() );
		}

		/**
		 * Fetches feed data from the database and stores it in the _feed_data variable. This data is required to build the edit feed page.
		 */
		private function set_feed_data() {
			$promotions_data_class    = new WPPPFM_Data();
			$promotions_queries_class = new WPPPFM_Queries();

			$feed_data                     = $promotions_queries_class->read_feed( $this->_feed_id )[0];
			$promotion_destination_options = $promotions_data_class->get_promotion_destination_options();
			$promotion_filter_options      = $promotions_data_class->get_merchant_promotion_filter_selector_options();
			$attribute_data                = $promotions_queries_class->get_meta_data( $this->_feed_id );

			$this->_feed_data = array(
				'feed_id'                       => $this->_feed_id ?: false,
				'feed_file_name'                => $feed_data ? $feed_data['title'] : '',
				'url'                           => $feed_data ? $feed_data['url'] : '',
				'status_id'                     => $feed_data ? $feed_data['status_id'] : '',
				'feed_type_id'                  => $feed_data ? $feed_data['feed_type_id'] : '',
				'promotion_destination_options' => $promotion_destination_options,
				'promotion_filter_options'      => $promotion_filter_options,
				'attribute_data'                => $attribute_data,
			);
		}

		/**
		 * Sets the main input table.
		 */
		private function main_input_table() {
			$main_input_wrapper = new WPPPFM_Google_Merchant_Promotions_Feed_Main_Input_Wrapper();
			$main_input_wrapper->display();
		}

		/**
		 * Sets the feed top buttons.
		 */
		private function promotions_feed_top_buttons() {
			WPPFM_Form_Element::feed_generation_buttons( 'wppfm-top-buttons-wrapper', 'wpppfm-promotions-feed-buttons-section', 'wpppfm-generate-merchant-promotions-feed-button-bottom', 'wpppfm-save-merchant-promotions-feed-button-bottom', 'wppfm-view-feed-button-bottom' );
		}

		/**
		 * Sets the feed bottom buttons.
		 */
		private function promotions_feed_bottom_buttons() {
			WPPFM_Form_Element::feed_generation_buttons( 'wppfm-center-buttons-wrapper', 'wpppfm-promotions-feed-buttons-section', 'wpprfm-generate-merchant-promotions-feed-button-bottom', 'wpppfm-save-merchant-promotions-feed-button-bottom', 'wppfm-view-feed-button-bottom' );
		}

		/**
		 * Renders the promotion area start code.
		 */
		private function start_promotions_area() {
			echo '<section class="wpppfm-promotions-group-area">';
		}

		/**
		 * Renders the promotion area end code.
		 */
		private function end_promotions_area() {
			echo '</section>';
		}

		/**
		 * Sets a promotion template wrapper.
		 */
		private function promotion_template() {
			$promotion_template = new WPPPFM_Google_Merchant_Promotion_Wrapper();
			$promotion_template->display();
		}

	}

	// end of WPPPFM_Promotions_Feed_Editor_Page class

endif;
