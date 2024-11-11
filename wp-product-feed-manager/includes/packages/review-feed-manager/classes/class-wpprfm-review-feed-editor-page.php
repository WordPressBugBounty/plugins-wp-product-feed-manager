<?php

/**
 * WPPRFM Google Product Review Feed Page Class.
 *
 * @package WP Product Review Feed Manager/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPRFM_Review_Feed_Editor_Page' ) ) :

	/**
	 * WPPRFM Feed Form Class
	 */
	class WPPRFM_Review_Feed_Editor_Page {

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

			$this->_feed_id = wppfm_get_url_parameter( 'id' );

			// Fill the _feed_data container.
			$this->set_feed_data();

			// Load the language scripts.
			/** @noinspection PhpVoidFunctionResultUsedInspection */
			add_option( 'wp_enqueue_scripts', WPPFM_i18n_Scripts::wppfm_feed_settings_i18n() );
		}

		/**
		 * Generates the main part of the Review Feed Editor page.
		 */
		public function display() {
			$this->add_data_storage();
			$this->review_feed_editor_page();
		}

		/**
		 * Renders the edit Google Product Review feed form page and displays it on the screen.
		 */
		private function review_feed_editor_page() {
			echo '<div class="wppfm-page__title" id="wppfm-edit-feed-title"><h1>' . esc_html__( 'Product Feed Editor', 'wp-product-feed-manager' ) . '</h1></div>';
			$this->sub_title();
			echo '</div>';

			echo '<div class="wpprfm-review-feed-editor-wrapper">';

			$this->main_input_table();

			$this->category_selector_table();

			$this->review_feed_top_buttons();

			$this->attribute_mapping_table();

			$this->review_feed_bottom_buttons();

			echo '</div>';
		}

		/**
		 * Stores data in the DOM for the Feed Manager Feed Editor page
		 */
		private function add_data_storage() {
			echo
				'<div id="wppfm-feed-editor-page-data-storage" class="wppfm-data-storage-element"
				data-wppfm-feed-data="' . wc_esc_json( wp_json_encode( $this->_feed_data ), false ) . '"
				data-wppfm-ajax-feed-data-to-database-conversion-array=' . esc_attr( wp_json_encode( wppfm_ajax_feed_data_to_database_array( 'google-product-review-feed' ) ) ) . '
				data-wppfm-feed-url="' . esc_url( $this->_feed_data['url'] ) . '"
				data-wppfm-all-feed-names="' . esc_attr( implode( ';;',  wppfm_get_all_feed_names() ) ) . '"
				data-wppfm-plugin-version-id="' . esc_attr( WPPFM_PLUGIN_VERSION_ID ) . '" 
				data-wppfm-plugin-version-nr="' . esc_attr( WPPFM_VERSION_NUM ) . '"
				data-wppfm-plugin-distributor="' . esc_attr( WPPFM_PLUGIN_DISTRIBUTOR ) . '">
			</div>';
		}

		/**
		 * Gets the Feed Editor subtitle.
		 */
		private function sub_title() {
			WPPFM_Form_Element::feed_editor_sub_title( wppfm_feed_form_sub_header_text() );
		}

		/**
		 * Fetches feed data from the database and stores it in the _feed_data variable. This data is required to build the edit feed page. Stores empty
		 * data when the page is opened from a new feed.
		 */
		private function set_feed_data() {

			if ( $this->_feed_id ) {
				$review_queries_class = new WPPRFM_Queries();
				$queries_class        = new WPPFM_Queries();
				$review_data_class    = new WPPRFM_Data();
				$data_class           = new WPPFM_Data();

				$feed_data      = $review_queries_class->read_feed( $this->_feed_id )[0];
				$feed_filter    = $queries_class->get_product_filter_query( $this->_feed_id );
				$source_fields  = $data_class->get_source_fields();
				$attribute_data = $review_data_class->get_product_review_feed_attributes( $this->_feed_id );
			} else { // For a new feed.
				$source_fields  = array();
				$attribute_data = array();
				$feed_filter    = '';
				$feed_data      = null; // a new feed
			}

			$this->_feed_data = array(
				'feed_id'               => $this->_feed_id ?: false,
				'feed_file_name'        => $feed_data ? $feed_data['title'] : '',
				'feed_description'      => $feed_data ? $feed_data['feed_description'] : '',
				'schedule'              => $feed_data ? $feed_data['schedule'] : '',
				'url'                   => $feed_data ? $feed_data['url'] : '',
				'category_mapping'      => $feed_data ? $feed_data['category_mapping'] : '',
				'status_id'             => $feed_data ? $feed_data['status_id'] : '',
				'feed_type_id'          => $feed_data ? $feed_data['feed_type_id'] : '',
				'aggregator_name'       => $feed_data ? $feed_data['aggregator_name'] : '',
				'publisher_name'        => $feed_data ? $feed_data['publisher_name'] : '',
				'publisher_favicon_url' => $feed_data ? $feed_data['publisher_favicon_url'] : '',
				'feed_filter'           => $feed_filter ?: null,
				'attribute_data'        => $attribute_data,
				'source_fields'         => $source_fields,
			);
		}

		/**
		 * Sets the main input table.
		 */
		private function main_input_table() {
			$main_input_wrapper = new WPPRFM_Google_Product_Review_Feed_Main_Input_Wrapper();
			$main_input_wrapper->display();
		}

		/**
		 * Sets the category selector table.
		 */
		private function category_selector_table() {
			$category_table_wrapper = new WPPRFM_Google_Product_Review_Feed_Category_Wrapper();
			$category_table_wrapper->display();
		}

		/**
		 * Sets the attribute mapping table.
		 */
		private function attribute_mapping_table() {
			$attribute_mapping_wrapper = new WPPRFM_Google_Product_Review_Feed_Attribute_Mapping_Wrapper();
			$attribute_mapping_wrapper->display();
		}

		/**
		 * Sets the feeds top buttons.
		 */
		private function review_feed_top_buttons() {
			WPPFM_Form_Element::feed_generation_buttons( 'wppfm-top-buttons-wrapper', 'page-center-buttons', 'wpprfm-generate-review-feed-button-top', 'wpprfm-save-review-feed-button-top', 'wppfm-view-feed-button-top' );
		}

		/**
		 * Sets the feeds bottom buttons.
		 */
		private function review_feed_bottom_buttons() {
			WPPFM_Form_Element::feed_generation_buttons( 'wppfm-center-buttons-wrapper', 'page-center-buttons', 'wpprfm-generate-review-feed-button-bottom', 'wpprfm-save-review-feed-button-bottom', 'wppfm-view-feed-button-bottom' );
		}

	}

	// end of WPPRFM_Review_Feed_Editor_Page class

endif;
