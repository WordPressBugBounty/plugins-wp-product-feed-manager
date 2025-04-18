<?php

/**
 * WP Product Feed Controller Class.
 *
 * @package WP Product Feed Manager/Application/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Feed_Processor' ) ) :


	/**
	 * Feed Processor Class.
	 *
	 * @since 1.10.0
	 */
	class WPPFM_Feed_Processor extends WPPFM_Background_Process {

		use WPPFM_Processing_Support;
		use WPPFM_Feed_Processor_Functions;

		/**
		 * Action identifier.
		 *
		 * @var string
		 */
		protected $action = 'feed_generation_process';

		/**
		 * Path to the feed file.
		 *
		 * @var string
		 */
		private $_feed_file_path;

		/**
		 * General feed data.
		 *
		 * @var stdClass
		 */
		private $_feed_data;

		/**
		 * Required pre feed generation data.
		 *
		 * @var array
		 */
		private $_pre_data;

		/**
		 * Contains the channel-specific main category title and description title.
		 *
		 * @var array
		 */
		private $_channel_details;

		/**
		 * Contains the relations between WooCommerce and channel fields.
		 *
		 * @var array
		 */
		private $_relation_table;

		/**
		 * Placeholder for the correct channel class.
		 *
		 * @var string
		 */
		private $_channel_class;

		/**
		 * Starts a single feed update task.
		 *
		 * @param array    $item            the work value, usually a product id, but it can also be an XML header line.
		 * @param stdClass $feed_data       a class containing the required feed data.
		 * @param string   $feed_file_path  the path to the feed file
		 * @param array    $pre_data        an array with column names, active fields, and database fields.
		 * @param array    $channel_details an array with the details of the channel for this feed.
		 * @param array    $relation_table  an array that contains the relations between the field name and the database table field name.
		 *
		 * @return boolean returns true if the task has succeeded.
		 */
		protected function task( $item, $feed_data, $feed_file_path, $pre_data, $channel_details, $relation_table ) {
			if ( ! $item ) {
				return false;
			}

			$this->_feed_data       = $feed_data;
			$this->_feed_file_path  = $feed_file_path;
			$this->_pre_data        = $pre_data;
			$this->_channel_details = $channel_details;
			$this->_relation_table  = $relation_table;

			if ( ! $this->_channel_details['channel_id'] ) {
				return false;
			}

			// instantiate the correct channel class

			$this->_channel_class = new WPPFM_Google_Feed_Class();

			return $this->do_task( $item );
		}

		/**
		 * Handles the actions after completing a feed update task.
		 */
		public function complete() {
			do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, 'Started the complete function to clean up the feed process and queue.' );

			// Remove the properties from the option table.
			$properties_key = get_site_option( 'wppfm_background_process_key' );
			delete_site_option( 'wppfm_background_process_key' );
			delete_site_option( 'file_path_' . $properties_key );
			delete_site_option( 'feed_data_' . $properties_key );
			delete_site_option( 'pre_data_' . $properties_key );
			delete_site_option( 'channel_details_' . $properties_key );
			delete_site_option( 'relations_table_' . $properties_key );

			$feed_status = '0' !== $this->_feed_data->status && '3' !== $this->_feed_data->status && '4' !== $this->_feed_data->status ? $this->_feed_data->status : $this->_feed_data->baseStatusId;
			$feed_title  = $this->_feed_data->title . '.' . pathinfo( $this->_feed_file_path, PATHINFO_EXTENSION );
			$this->register_feed_update( $this->_feed_data->feedId, $feed_title, count( $this->processed_products ), $feed_status );
			$this->clear_the_queue();

			// Now the feed is ready to go, remove the feed id from the feed queue.
			WPPFM_Feed_Controller::remove_id_from_feed_queue( $this->_feed_data->feedId );
			WPPFM_Feed_Controller::set_feed_processing_flag();

			$message = sprintf( 'Completed feed %s. The feed should contain %d products and its status has been set to %s.', $this->_feed_data->feedId, count( $this->processed_products ), $feed_status );
			do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message );
			do_action( 'wppfm_register_feed_url', $this->_feed_data->feedId, $this->_feed_data->url );

			if ( ! WPPFM_Feed_Controller::feed_queue_is_empty() ) {
				do_action( 'wppfm_next_in_queue_feed_update_activated', $this->_feed_data->feedId );

				// So there is another feed in the queue.
				$feed_master_class = new WPPFM_Feed_Master_Class( WPPFM_Feed_Controller::get_next_id_from_feed_queue() );
				$feed_master_class->initiate_update_next_feed_in_queue();
			}
		}

		/**
		 * Selects the required action.
		 *
		 * @param array $task_data the work value, usually a product id, but it can also be an XML header line.
		 *
		 * @return boolean true if the action is started.
		 */
		private function do_task( $task_data ) {

			if ( array_key_exists( 'product_id', $task_data ) ) {
				return $this->add_product_to_feed( $task_data['product_id'] );
			} elseif ( array_key_exists( 'file_format_line', $task_data ) ) {
				// the WordFence plugin sometimes identifies the <link> string as a XSS vulnerability and blocks wp_remote_post action starting the feed process
				// To counter that, I changed the <link> string in the (google) xml feed header to <wf-connection-string> (in the Google channels class-feed.php file)
				// and now I need to change that back to <link> again.
				$task_data['file_format_line'] = str_replace( '<wf-connection-string>', '<link>', $task_data['file_format_line'] );

				return $this->add_file_format_line_to_feed( $task_data );
			} elseif ( array_key_exists( 'error_message', $task_data ) ) {
				return $this->add_error_message_to_feed( $task_data );
			} else {
				return false;
			}
		}

		/**
		 * Ads a single product based on a product id to the feed file.
		 *
		 * @param string $product_id the id of the product to be added to the feed.
		 *
		 * @since 2.37.0. Changed the return value for Grouped products to exclude them from being counted as processed products.
		 * @return boolean true if the product has been added correctly to the feed.
		 */
		private function add_product_to_feed( $product_id ) {
			if ( ! $product_id ) {
				$message = 'Add product to feed process started without product id';
				do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message, 'ERROR' );
				return false;
			}

			$wc_product = wc_get_product( $product_id );

			if ( false === $wc_product ) {
				$message = sprintf( 'Failed to get the WooCommerce product data from product with id %s.', $product_id );
				do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message, 'ERROR' );
				return false;
			}

			if ( $wc_product instanceof WC_Product_Grouped ) {
				$message = sprintf( 'The product with id %s is a grouped product and has been skipped.', $product_id );
				do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message, 'ERROR' );
				return false; // Skip grouped products.
			}

			do_action( 'wppfm_started_product_processing', $this->_feed_data->feedId, $product_id );

			$class_data = new WPPFM_Data();

			$product_placeholder       = array();
			$post_columns_query_string = $this->_pre_data['database_fields']['post_column_string'] ? substr( $this->_pre_data['database_fields']['post_column_string'], 0, - 2 ) : '';
			$product_parent_id         = $product_id; // Keep the Parent Id equal to the Product Id for non-variation products
			$product_data              = (array) $this->get_products_main_data( $product_id, $wc_product->get_parent_id(), $post_columns_query_string );

			/**
			 * Users can use the wppfm_leave_links_in_descriptions filter if they want to keep links in the product descriptions by changing the
			 * filter output to true. They can also target specific feeds.
			 *
			 * @since 2.6.0
			 */
			if ( ! apply_filters( 'wppfm_leave_links_in_descriptions', false, $this->_feed_data->feedId ) ) {
				$this->remove_links_from_product_data_description( $product_data );
			}

			if ( ( $wc_product instanceof WC_Product_Variation && $this->_pre_data['include_vars'] )
				|| ( $wc_product instanceof WC_Product_Variable ) && $this->_pre_data['include_vars'] ) {

				$product_parent_id = $wc_product->get_parent_id();

				// Add parent data when this item is not available in the variation.
				if ( $post_columns_query_string ) {
					$class_data->add_parent_data( $product_data, $product_parent_id, $post_columns_query_string, $this->_feed_data->language );
				}

				$wpmr_variation_data = $class_data->get_own_variation_data( $product_id );

				// Get the correct variation data.
				WPPFM_Variations::fill_product_data_with_variation_data( $product_data, $wc_product, $wpmr_variation_data, $this->_feed_data->language, $this->_feed_data->currency );
			}

			$row_category = $this->get_mapped_category( $product_parent_id, $this->_feed_data->mainCategory, json_decode( $this->_feed_data->categoryMapping ) );

			if ( ! $row_category ) {
				$message = sprintf( 'Could not identify the correct category map for product %s', $product_id );
				do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message, 'ERROR' );

				return false;
			}

			$row_filtered = $this->is_product_filtered( $this->_pre_data['filters'], $product_data );

			// Only process the product if it is not filtered out.
			if ( ! $row_filtered ) {
				// For each row loop through each active field.
				foreach ( $this->_pre_data['active_fields'] as $field ) {
					$attribute_meta_data = $this->get_meta_data_from_specific_attribute( $field, $this->_feed_data->attributes );

					// Get the field data based on the user settings.
					$feed_object = $this->process_product_field(
						$product_data,
						$attribute_meta_data,
						$this->_channel_details['category_name'],
						$row_category,
						$this->_feed_data->language,
						$this->_feed_data->currency,
						$this->_relation_table
					);

					$key = key( $feed_object );

					// For an XML file only add fields that contain data.
					if ( ( ! empty( $feed_object[ $key ] ) || '0' === $feed_object[ $key ] ) || 'xml' !== pathinfo( $this->_feed_file_path, PATHINFO_EXTENSION ) ) {

						// Keep money values that have a 0 value out of the feed. @since 2.11.2.
						// Modified @since 2.24.0, so it first converts money values to a standard decimal separator that it evaluates correctly in the floatval() evaluation.
						if ( wppfm_meta_key_is_money( $key ) ) {
							$money_value = wppfm_number_format_parse( $feed_object[ $key ] );
							if ( 0.0 === floatval( $money_value ) ) {
								continue;
							}
						}

						// Catch the DraftImages key for the Ricardo.ch channel.
						if ( 'DraftImages' !== $key ) {
							$product_placeholder[ $key ] = $feed_object[ $key ];
						} else {
							$support_class = new WPPFM_Feed_Support();
							$support_class->process_ricardo_draft_images( $product_placeholder, $feed_object[ $key ] );
						}

						// @since 3.8.0.
						// The Google feed specifications allow the material and color attributes to have a maximum of three items, displayed with a / separated string
						// Here the material and color features are converted to the prescribed format
						if ( ( 'material' === $key || 'color' === $key ) && false !== strpos( $product_placeholder[ $key ], ',' ) && '1' === $this->_channel_details['channel_id'] ) {
							$product_features = explode( ',', $product_placeholder[ $key ] );
							foreach( $product_features as $i => $product_feature ) { $product_features[ $i ] = trim( $product_feature ); }
							$main_materials = array_slice( $product_features, 0, 3 );
							$product_placeholder[ $key ] = implode( '/', $main_materials );
						}
					}
				}
			} else {
				$message = sprintf( 'Product %s is filtered out', $product_id );
				do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message );

				return 'filtered';
			}

			if ( $product_placeholder ) {
				// The wppfm_feed_item_value filter allows users to modify the data that goes into the feed. The $data variable contains an array
				// with all the data that goes into the feed, with the item name as a key.
				$product_placeholder = apply_filters( 'wppfm_feed_item_value', $product_placeholder, $this->_feed_data->feedId, $product_id );
				return $this->write_product_object( $product_placeholder, $this->_feed_data->feedId, $product_id );
			} else {
				$message = sprintf( 'Product %s has no data to write to the feed', $product_id );
				do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message, 'ERROR' );
				return false;
			}
		}

		/**
		 * Appends a processed product to the feed.
		 *
		 * @param array  $product_placeholder an array with the product data to be written to the feed file.
		 * @param string $feed_id             the id of the feed.
		 * @param string $product_id          the id of the product.
		 *
		 * @return string product added or boolean false.
		 */
		private function write_product_object( $product_placeholder, $feed_id, $product_id ) {

			$product_text = $this->generate_feed_text( $product_placeholder );

			if ( false === wppfm_append_line_to_file( $this->_feed_file_path, $product_text ) ) {
				wppfm_write_log_file( sprintf( 'Could not write product %s to the feed', $product_id ) );
				$message = sprintf( 'Could not write product %s to the feed', $product_id );
				do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message, 'ERROR' );

				return false;
			} else {
				//@since 2.3.0
				do_action( 'wppfm_add_product_to_feed', $feed_id, $product_id );

				return 'product added';
			}
		}

		/**
		 * Convert the feed data of a single product into XML or csv text depending on the channel.
		 *
		 * @param array $product_placeholder contains the product data.
		 *
		 * @return string
		 */
		private function generate_feed_text( $product_placeholder ) {
			switch ( pathinfo( $this->_feed_file_path, PATHINFO_EXTENSION ) ) {
				case 'xml':
					return $this->convert_data_to_xml( $product_placeholder, $this->_channel_details['category_name'], $this->_channel_details['description_name'], $this->_channel_details['channel_id'] );

				case 'txt':
					$txt_sep = apply_filters( 'wppfm_txt_separator', wppfm_get_correct_txt_separator( $this->_channel_details['channel_id'] ) );
					return $this->convert_data_to_txt( $product_placeholder, $txt_sep );

				case 'csv':
					$csv_sep = apply_filters( 'wppfm_csv_separator', wppfm_get_correct_csv_separator( $this->_channel_details['channel_id'] ) );
					return $this->convert_data_to_csv( $product_placeholder, $this->_pre_data['active_fields'], $csv_sep );

				case 'tsv':
					return $this->convert_data_to_tsv( $product_placeholder );
			}

			return '';
		}
	}

endif;
