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
		 * File write buffer for batching writes.
		 *
		 * @var array
		 * @since 3.15.0
		 */
		private $file_write_buffer = array();

		/**
		 * Buffer size before flushing to disk.
		 *
		 * @var int
		 * @since 3.15.0
		 */
		private $file_buffer_size = 50;

		/**
		 * Stores the active completion lock option key for this request.
		 *
		 * @var string
		 */
		private $active_completion_lock_key = '';

		/**
		 * Stores the active completion lock token for this request.
		 *
		 * @var string
		 */
		private $active_completion_lock_token = '';

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
			if ( ! $this->ensure_feed_context_is_available() ) {
				$this->handle_missing_feed_context();
				return;
			}

			if ( ! $this->acquire_feed_completion_lock( (string) $this->_feed_data->feedId ) ) {
				return;
			}

			try {

			do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, 'Started the complete function to clean up the feed process and queue.' );

			// Successful completion cancels any deferred watchdog failure email for this feed.
			if ( function_exists( 'wppfm_cancel_deferred_feed_failure_notice' ) ) {
				wppfm_cancel_deferred_feed_failure_notice( (string) $this->_feed_data->feedId );
			}

			// Flush any remaining buffer before completing
			if ( method_exists( $this, 'flush_file_buffer' ) ) {
				$this->flush_file_buffer();
			}

			// Merge paths from the completion transient when older runs cleared metadata early; temp-feed batches keep live metadata until promotion finishes.
			$batch_metadata = $this->resolve_batch_metadata_for_completion();

			$use_temporary_file = is_array( $batch_metadata ) && ! empty( $batch_metadata['use_temporary_file'] );
			$temporary_path     = ( is_array( $batch_metadata ) && ! empty( $batch_metadata['temporary_file_path'] ) ) ? (string) $batch_metadata['temporary_file_path'] : '';
			$final_publish_path = ( is_array( $batch_metadata ) && ! empty( $batch_metadata['final_file_path'] ) ) ? (string) $batch_metadata['final_file_path'] : '';

			if ( $use_temporary_file ) {
				if ( '' === $temporary_path || '' === $final_publish_path ) {
					$this->handle_feed_promotion_failure_at_completion(
						sprintf(
							/* translators: %s: Feed id. */
							__( 'Feed %s: batch metadata is missing temporary or final file paths; publication was aborted and the published file was not modified.', 'wp-product-feed-manager' ),
							$this->_feed_data->feedId
						),
						''
					);
					return;
				}

				$pre_promotion_validation = $this->validate_temporary_feed_file_before_promotion( $temporary_path, $final_publish_path, $batch_metadata );
				if ( ! $pre_promotion_validation['is_valid'] ) {
					$this->handle_feed_promotion_failure_at_completion(
						$pre_promotion_validation['error_message'],
						$temporary_path
					);
					return;
				}

				if ( ! $this->promote_temporary_feed_file_to_final_location( $temporary_path, $final_publish_path ) ) {
					$this->handle_feed_promotion_failure_at_completion(
						sprintf(
							/* translators: 1: Temporary file path, 2: Final file path. */
							__( 'Could not move the temporary feed file (%1$s) to the final location (%2$s). The previous published feed was left unchanged.', 'wp-product-feed-manager' ),
							$temporary_path,
							$final_publish_path
						),
						$temporary_path
					);
					return;
				}

				// Processor state and extension detection must follow the published file after promotion.
				$this->_feed_file_path = $final_publish_path;
			}

			// Clear batch metadata only after a successful promotion (when applicable) so options stay available until then.
			$this->cleanup_background_process_options();

			$feed_status             = '0' !== $this->_feed_data->status && '3' !== $this->_feed_data->status && '4' !== $this->_feed_data->status ? $this->_feed_data->status : $this->_feed_data->baseStatusId;
			$feed_title              = $this->_feed_data->title . '.' . pathinfo( $this->_feed_file_path, PATHINFO_EXTENSION );
			$total_handled_products  = get_transient( 'wppfm_nr_of_processed_products' );
			$total_handled_products  = false === $total_handled_products ? 0 : intval( $total_handled_products );
			$this->register_feed_update( $this->_feed_data->feedId, $feed_title, $total_handled_products, $feed_status );
			$this->clear_the_queue();

			// Now the feed is ready to go, remove the feed id from the feed queue.
			WPPFM_Feed_Controller::remove_id_from_feed_queue( $this->_feed_data->feedId );
			WPPFM_Feed_Controller::set_feed_processing_flag();

		$message = sprintf( 'Completed feed %s. The feed should contain %d products and its status has been set to %s.', $this->_feed_data->feedId, $total_handled_products, $feed_status );
		do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message );
		do_action( 'wppfm_register_feed_url', $this->_feed_data->feedId, $this->_feed_data->url );


		// Clean up preserved feed context transient now that completion succeeded.
		delete_transient( 'wppfm_feed_completion_context_' . $this->_feed_data->feedId );
		delete_transient( 'wppfm_client_request_id_' . $this->_feed_data->feedId );
		delete_transient( 'wppfm_nr_of_products_to_process_' . $this->_feed_data->feedId );
		delete_transient( 'wppfm_feed_validation_failure_notice_' . $this->_feed_data->feedId );

		if ( ! WPPFM_Feed_Controller::feed_queue_is_empty() ) {
				do_action( 'wppfm_next_in_queue_feed_update_activated', $this->_feed_data->feedId );

				// So there is another feed in the queue.
				$feed_master_class = new WPPFM_Feed_Master_Class( WPPFM_Feed_Controller::get_next_id_from_feed_queue() );
				$feed_master_class->initiate_update_next_feed_in_queue();
			}
			} finally {
				// Always release the completion lock for this feed, including early returns.
				$this->release_feed_completion_lock();
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
		$product_data              = (array) $this->get_products_main_data( $product_id, $wc_product->get_parent_id(), $post_columns_query_string, $wc_product );

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

				$translated_variation_id = $product_id;
				$variation_product       = $wc_product;

				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML exposes this public hook name; using it is required for multilingual variation ID resolution.
				if ( $wc_product instanceof WC_Product_Variation && has_filter( 'wpml_object_id' ) ) {
					// Use the translated variation object so variation-specific fields do not fall back to the original-language ID or description.
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML exposes this public hook name; using it is required for multilingual variation ID resolution.
					$wpml_variation_id = apply_filters( 'wpml_object_id', $product_id, 'product_variation', true, $this->_feed_data->language );

					if ( $wpml_variation_id && (int) $wpml_variation_id !== (int) $product_id ) {
						$translated_wc_product = wc_get_product( $wpml_variation_id );

						if ( $translated_wc_product instanceof WC_Product_Variation ) {
							$translated_variation_id = (int) $wpml_variation_id;
							$variation_product       = $translated_wc_product;
						}
					}
				}

				$wpmr_variation_data = $class_data->get_own_variation_data( $translated_variation_id );

				// Get the correct variation data.
				WPPFM_Variations::fill_product_data_with_variation_data( $product_data, $variation_product, $wpmr_variation_data, $this->_feed_data->language, $this->_feed_data->currency );
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

						// @since 3.21.0.
						// The Facebook feed specifications allow the internal_label attribute that contains comma separated labels to be wrapped in square brackets.
						if ( 'internal_label' === $key ) {
							$raw_value = (string) $product_placeholder[ $key ];
							$labels    = array_map( 'trim', explode( ',', $raw_value ) );
							$labels    = array_filter( $labels );
						
							$quoted_labels = array_map( function ( $label ) {
								return "'" . $label . "'";
							}, $labels );
						
							$product_placeholder[ $key ] = implode( ',', $quoted_labels );
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
		 * Uses buffering to reduce file I/O operations.
		 *
		 * @param array  $product_placeholder an array with the product data to be written to the feed file.
		 * @param string $feed_id             the id of the feed.
		 * @param string $product_id          the id of the product.
		 *
		 * @return string product added or boolean false.
		 * @since 3.15.0 - Updated to use buffering for better performance.
		 */
		private function write_product_object( $product_placeholder, $feed_id, $product_id ) {

			$product_text = $this->generate_feed_text( $product_placeholder );

			// Buffer the output instead of writing immediately
			return $this->buffer_product_output( $product_text, $feed_id, $product_id );
		}

		/**
		 * Buffer product output for batch writing.
		 *
		 * @param string $product_text  The generated product text.
		 * @param string $feed_id       Feed ID.
		 * @param string $product_id    Product ID.
		 *
		 * @return string 'product added' on success, false on failure.
		 * @since 3.15.0
		 */
		private function buffer_product_output( $product_text, $feed_id, $product_id ) {
			// Initialize buffer if not already initialized
			if ( ! isset( $this->file_write_buffer ) ) {
				$this->file_write_buffer = array();
			}

			// Get buffer size from filter (allows customization)
			$this->file_buffer_size = apply_filters( 'wppfm_file_buffer_size', 50 );

			// Add to buffer
			$this->file_write_buffer[] = $product_text;

			// Flush if buffer is full
			if ( count( $this->file_write_buffer ) >= $this->file_buffer_size ) {
				if ( ! $this->flush_file_buffer() ) {
					wppfm_write_log_file( sprintf( 'Could not flush buffer for product %s to the feed', $product_id ) );
					$message = sprintf( 'Could not flush buffer for product %s to the feed', $product_id );
					do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message, 'ERROR' );
					return false;
				}
			}


			return 'product added';
		}

		/**
		 * Flush buffered content to file.
		 * Uses the improved wppfm_append_line_to_file() function with file locking.
		 * Made protected so it can be called from parent class methods.
		 * For XML files, ensures each item is on its own line.
		 *
		 * @return bool Success status.
		 * @since 3.15.0
		 */
		protected function flush_file_buffer() {
			if ( empty( $this->file_write_buffer ) ) {
				return true;
			}

			// Determine file type to handle line breaks appropriately
			$file_extension = pathinfo( $this->_feed_file_path, PATHINFO_EXTENSION );
			
			// For XML files, join items with newlines to ensure each item is on its own line
			// For other file types (CSV, TXT, TSV), join without separator as they handle formatting internally
			if ( 'xml' === $file_extension ) {
				// Join with PHP_EOL to ensure proper line breaks between XML items
				$combined_text = implode( PHP_EOL, $this->file_write_buffer );
			} else {
				// For non-XML files, join without separator (they may have their own formatting)
				$combined_text = implode( '', $this->file_write_buffer );
			}

			// Write using the improved append function (with file locking)
			// For XML, we add PHP_EOL at the end to ensure the last item ends with a newline
			// For other formats, we don't add extra newline as they handle it themselves
			$add_newline = ( 'xml' === $file_extension );
			
			if ( false === wppfm_append_line_to_file( $this->_feed_file_path, $combined_text, $add_newline ) ) {
				wppfm_write_log_file( 'Failed to flush file buffer to feed file' );
				do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, 'Failed to flush file buffer', 'ERROR' );
				return false;
			}

			// Clear buffer after successful write
			$this->file_write_buffer = array();

			return true;
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

		/**
		 * Ensures the feed context is available before completing the process.
		 *
		 * @return bool
		 */
		private function ensure_feed_context_is_available() {
			if ( $this->_feed_data && property_exists( $this->_feed_data, 'feedId' ) ) {
				return true;
			}

			list( , $batch_metadata ) = $this->get_current_batch_metadata();

			if ( $batch_metadata ) {
				if ( empty( $this->_feed_data ) && isset( $batch_metadata['feed_data'] ) ) {
					$this->_feed_data = $batch_metadata['feed_data'];
				}

				if ( empty( $this->_feed_file_path ) && ! empty( $batch_metadata['file_path'] ) ) {
					$this->_feed_file_path = $batch_metadata['file_path'];
				}
			}

			return $this->_feed_data && property_exists( $this->_feed_data, 'feedId' );
		}

		/**
		 * Handles the situation where the feed context can no longer be restored.
		 */
		private function handle_missing_feed_context() {
			$feed_id = $this->resolve_active_feed_id();
			$log_id  = $feed_id ? $feed_id : 'unknown';

			if ( $feed_id && ! $this->acquire_feed_completion_lock( (string) $feed_id ) ) {
				return;
			}

			try {

			do_action( 'wppfm_feed_generation_message', $log_id, 'Feed completion aborted because the feed metadata could not be restored.', 'ERROR' );

		// Clean up any preserved context to avoid stale data.
		if ( $feed_id ) {
			delete_transient( 'wppfm_feed_completion_context_' . $feed_id );
		}

			if ( $feed_id ) {
				$data_class = new WPPFM_Data();
				$data_class->update_feed_status( $feed_id, 6 );
				WPPFM_Feed_Controller::remove_id_from_feed_queue( $feed_id );

				// Terminal failure while metadata could not be restored (automatic runs only).
				if ( get_transient( 'wppfm_running_silent' ) && class_exists( 'WPPFM_Email' ) ) {
					if ( function_exists( 'wppfm_cancel_deferred_feed_failure_notice' ) ) {
						wppfm_cancel_deferred_feed_failure_notice( (string) $feed_id );
					}
					WPPFM_Email::send_feed_failed_message(
						(string) $feed_id,
						array(
							'detected_at' => time(),
							'source'      => 'missing_feed_context',
						)
					);
				}
			}

			$this->clear_the_queue();
			$this->cleanup_background_process_options();
			WPPFM_Feed_Controller::set_feed_processing_flag();
			} finally {
				$this->release_feed_completion_lock();
			}
		}

		/**
		 * Acquires a per-feed completion lock to prevent overlapping completion handlers.
		 *
		 * This lock is feed-specific so scheduled (cron) runs stay compatible and do not require a user session.
		 *
		 * @param string $feed_id Feed id.
		 *
		 * @return bool
		 */
		private function acquire_feed_completion_lock( $feed_id ) {
			if ( '' === $feed_id ) {
				do_action(
					'wppfm_feed_generation_message',
					'unknown',
					'Skipped completion lock acquisition because feed id is empty.',
					'WARNING'
				);
				return false;
			}

			$lock_key = 'wppfm_feed_completion_lock_' . $feed_id;
			$now      = time();
			$ttl      = max( MINUTE_IN_SECONDS, intval( apply_filters( 'wppfm_feed_completion_lock_ttl', 10 * MINUTE_IN_SECONDS ) ) );
			$current  = get_site_option( $lock_key );

			if ( is_array( $current ) && ! empty( $current['acquired_at'] ) ) {
				$age = $now - intval( $current['acquired_at'] );
				if ( $age <= $ttl ) {
					do_action(
						'wppfm_feed_generation_message',
						$feed_id,
						sprintf( 'Skipped duplicate completion attempt because a completion lock is active (age=%ds).', $age ),
						'WARNING'
					);
					return false;
				}

				// Stale lock found, remove it so completion can recover after interrupted processes.
				do_action(
					'wppfm_feed_generation_message',
					$feed_id,
					sprintf( 'Detected stale completion lock; clearing it (age=%ds, ttl=%ds).', $age, $ttl ),
					'WARNING'
				);
				delete_site_option( $lock_key );
			}

			$token   = uniqid( 'wppfm_completion_', true );
			$payload = array(
				'acquired_at' => $now,
				'token'       => $token,
			);

			// add_site_option behaves atomically for our locking use case.
			if ( ! add_site_option( $lock_key, $payload ) ) {
				do_action(
					'wppfm_feed_generation_message',
					$feed_id,
					'Skipped duplicate completion attempt because completion lock acquisition failed.',
					'WARNING'
				);
				return false;
			}

			$this->active_completion_lock_key   = $lock_key;
			$this->active_completion_lock_token = $token;
			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				sprintf( 'Acquired completion lock (key=%s, ttl=%ds).', $lock_key, $ttl )
			);

			return true;
		}

		/**
		 * Releases the active per-feed completion lock when this request owns it.
		 *
		 * @return void
		 */
		private function release_feed_completion_lock() {
			if ( '' === $this->active_completion_lock_key || '' === $this->active_completion_lock_token ) {
				do_action(
					'wppfm_feed_generation_message',
					'unknown',
					'Skipped completion lock release because no active completion lock is set.',
					'WARNING'
				);
				return;
			}

			$current = get_site_option( $this->active_completion_lock_key );
			if ( is_array( $current ) && ! empty( $current['token'] ) && $current['token'] === $this->active_completion_lock_token ) {
				delete_site_option( $this->active_completion_lock_key );
				do_action(
					'wppfm_feed_generation_message',
					'unknown',
					sprintf( 'Released completion lock (key=%s).', $this->active_completion_lock_key )
				);
			} else {
				do_action(
					'wppfm_feed_generation_message',
					'unknown',
					sprintf( 'Skipped completion lock release because this request is not the lock owner (key=%s).', $this->active_completion_lock_key ),
					'WARNING'
				);
			}

			$this->active_completion_lock_key   = '';
			$this->active_completion_lock_token = '';
		}

	/**
	 * Preserves feed context before the drained product-queue option is deleted so complete() can access it.
	 *
	 * Stores temporary-file promotion fields for loopback requests; batch metadata now remains until completion when using a temp artifact.
	 *
	 * @param string $feed_id        Feed ID.
	 * @param string $properties_key Batch properties key.
	 */
	protected function preserve_feed_context_for_completion( $feed_id, $properties_key ) {
		if ( ! $properties_key ) {
			do_action( 'wppfm_feed_generation_message', $feed_id, 'Warning: No properties key available to preserve feed context for completion', 'WARNING' );
			return;
		}

		$batch_metadata = get_site_option( 'wppfm_batch_metadata_' . $properties_key, array() );

		$payload = array(
			'batch_key' => $properties_key,
		);

		if ( is_array( $batch_metadata ) && ! empty( $batch_metadata['use_temporary_file'] ) ) {
			$payload['use_temporary_file']  = true;
			$payload['final_file_path']     = isset( $batch_metadata['final_file_path'] ) ? (string) $batch_metadata['final_file_path'] : '';
			$payload['temporary_file_path'] = isset( $batch_metadata['temporary_file_path'] ) ? (string) $batch_metadata['temporary_file_path'] : '';
		} else {
			$payload['use_temporary_file'] = false;
		}

		set_transient( 'wppfm_feed_completion_context_' . $feed_id, $payload, 15 * MINUTE_IN_SECONDS );
		do_action( 'wppfm_feed_generation_message', $feed_id, sprintf( 'Preserved feed context for completion (properties key: %s)', $properties_key ) );
	}

	/**
	 * Ensures feed context is available before calling complete().
	 *
	 * @param string $feed_id Feed ID.
	 */
	protected function ensure_feed_context_before_completion( $feed_id ) {
		// If feed context is already available, nothing to do.
		if ( $this->_feed_data && property_exists( $this->_feed_data, 'feedId' ) ) {
			do_action( 'wppfm_feed_generation_message', $feed_id, 'Feed context already available, no restoration needed' );
			return;
		}

		$stored_context = get_transient( 'wppfm_feed_completion_context_' . $feed_id );

		if ( is_array( $stored_context ) && ! empty( $stored_context['batch_key'] ) ) {
			$properties_key = $stored_context['batch_key'];
		} elseif ( is_string( $stored_context ) && '' !== $stored_context ) {
			$properties_key = $stored_context;
		} else {
			do_action( 'wppfm_feed_generation_message', $feed_id, 'Warning: No preserved feed context found for completion', 'WARNING' );
			return;
		}

		$batch_metadata = get_site_option( 'wppfm_batch_metadata_' . $properties_key );

		if ( $batch_metadata && is_array( $batch_metadata ) ) {
			if ( empty( $this->_feed_data ) && isset( $batch_metadata['feed_data'] ) ) {
				$this->_feed_data = $batch_metadata['feed_data'];
			}

			if ( empty( $this->_feed_file_path ) && ! empty( $batch_metadata['file_path'] ) ) {
				$this->_feed_file_path = $batch_metadata['file_path'];
			}

			if ( empty( $this->_pre_data ) && ! empty( $batch_metadata['pre_data'] ) ) {
				$this->_pre_data = $batch_metadata['pre_data'];
			}

			if ( empty( $this->_channel_details ) && ! empty( $batch_metadata['channel_details'] ) ) {
				$this->_channel_details = $batch_metadata['channel_details'];
			}

			if ( empty( $this->_relations_table ) && ! empty( $batch_metadata['relations_table'] ) ) {
				$this->_relations_table = $batch_metadata['relations_table'];
			}

			do_action( 'wppfm_feed_generation_message', $feed_id, 'Restored feed context from preserved metadata: feed_data, file_path, pre_data, channel_details, relations_table' );
		} else {
			do_action( 'wppfm_feed_generation_message', $feed_id, 'Warning: Could not load batch metadata for preserved context', 'WARNING' );
		}
	}

		/**
		 * Removes the stored batch metadata from the options table.
		 */
		private function cleanup_background_process_options() {
			list( $properties_key ) = $this->get_current_batch_metadata();

			delete_site_option( 'wppfm_background_process_key' );

			if ( $properties_key ) {
				delete_site_option( 'wppfm_batch_metadata_' . $properties_key );
				delete_site_option( $properties_key );
			}
		}

		/**
		 * Resolves the active feed id from the available context.
		 *
		 * @return string|null
		 */
		private function resolve_active_feed_id() {
			if ( $this->_feed_data && property_exists( $this->_feed_data, 'feedId' ) ) {
				return $this->_feed_data->feedId;
			}

			list( , $batch_metadata ) = $this->get_current_batch_metadata();

			if ( $batch_metadata && ! empty( $batch_metadata['feed_id'] ) ) {
				return $batch_metadata['feed_id'];
			}

			$request_feed_id = filter_input( INPUT_GET, 'feed_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			return $request_feed_id ? $request_feed_id : null;
		}

		/**
		 * Returns the current batch metadata and key stored in the options table.
		 *
		 * @return array
		 */
		private function get_current_batch_metadata() {
			$properties_key = get_site_option( 'wppfm_background_process_key' );

			if ( ! $properties_key ) {
				return array( null, null );
			}

			$batch_metadata = get_site_option( 'wppfm_batch_metadata_' . $properties_key );

			if ( ! is_array( $batch_metadata ) ) {
				$batch_metadata = null;
			}

			return array( $properties_key, $batch_metadata );
		}

		/**
		 * Resolves batch metadata for complete(), including temp-file promotion data after handle() deleted site options.
		 *
		 * @return array|null Metadata array or null when nothing is available.
		 */
		private function resolve_batch_metadata_for_completion() {
			list( , $meta_from_options ) = $this->get_current_batch_metadata();

			if ( is_array( $meta_from_options ) && ! empty( $meta_from_options['use_temporary_file'] ) ) {
				return $meta_from_options;
			}

			if ( ! $this->_feed_data || ! property_exists( $this->_feed_data, 'feedId' ) ) {
				return is_array( $meta_from_options ) ? $meta_from_options : null;
			}

			$stored = get_transient( 'wppfm_feed_completion_context_' . $this->_feed_data->feedId );

			if ( ! is_array( $stored ) || empty( $stored['use_temporary_file'] ) ) {
				return is_array( $meta_from_options ) ? $meta_from_options : null;
			}

			$merged = is_array( $meta_from_options ) ? $meta_from_options : array();

			$merged['use_temporary_file']   = true;
			$merged['final_file_path']      = isset( $stored['final_file_path'] ) ? (string) $stored['final_file_path'] : '';
			$merged['temporary_file_path']  = isset( $stored['temporary_file_path'] ) ? (string) $stored['temporary_file_path'] : '';

			return $merged;
		}

		/**
		 * Runs pre-promotion validation rules for temporary feed files.
		 *
		 * @param string     $temporary_path Absolute path to the temporary artifact.
		 * @param string     $final_path     Absolute path to the published feed file.
		 * @param array|null $batch_metadata Batch metadata used by validation rules.
		 *
		 * @return array {
		 *     Validation result payload.
		 *
		 *     @type bool   $is_valid      Whether all configured rules passed.
		 *     @type string $error_message Human-readable failure reason when validation fails.
		 * }
		 */
		private function validate_temporary_feed_file_before_promotion( $temporary_path, $final_path, $batch_metadata ) {
			$rules = $this->get_temporary_feed_promotion_validation_rules( $temporary_path, $final_path, $batch_metadata );

			foreach ( $rules as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['id'] ) || empty( $rule['callback'] ) || ! is_callable( $rule['callback'] ) ) {
					continue;
				}

				$rule_result = call_user_func( $rule['callback'], $temporary_path, $final_path, $batch_metadata );

				if ( is_array( $rule_result ) && array_key_exists( 'is_valid', $rule_result ) ) {
					$is_valid = (bool) $rule_result['is_valid'];

					if ( ! $is_valid ) {
						$error_message = ! empty( $rule_result['error_message'] )
							? (string) $rule_result['error_message']
							: __( 'Temporary feed file validation failed before publication.', 'wp-product-feed-manager' );

						return array(
							'is_valid'      => false,
							'error_message' => $error_message,
						);
					}
				}
			}

			return array(
				'is_valid'      => true,
				'error_message' => '',
			);
		}

		/**
		 * Returns the temporary feed publication validation rules.
		 *
		 * @param string     $temporary_path Absolute path to the temporary artifact.
		 * @param string     $final_path     Absolute path to the published feed file.
		 * @param array|null $batch_metadata Batch metadata used by validation rules.
		 *
		 * @return array
		 */
		private function get_temporary_feed_promotion_validation_rules( $temporary_path, $final_path, $batch_metadata ) {
			$rules = array(
				array(
					'id'       => 'xml_header_footer',
					'callback' => array( $this, 'validate_xml_header_and_footer_before_promotion' ),
				),
			);

			/**
			 * Filters pre-promotion temporary feed validation rules.
			 *
			 * Each rule must be an array with:
			 * - id: string identifier.
			 * - callback: callable returning array( 'is_valid' => bool, 'error_message' => string ).
			 *
			 * @since 3.18.0
			 *
			 * @param array      $rules          Validation rule definitions.
			 * @param string     $temporary_path Absolute path to the temporary artifact.
			 * @param string     $final_path     Absolute path to the published feed file.
			 * @param array|null $batch_metadata Batch metadata for the active feed run.
			 * @param object     $feed_data      Feed data object.
			 */
			return apply_filters( 'wppfm_temporary_feed_promotion_validation_rules', $rules, $temporary_path, $final_path, $batch_metadata, $this->_feed_data );
		}

		/**
		 * Validates XML temporary feed files contain the expected header and footer before publication.
		 *
		 * Non-XML feeds bypass this rule so additional format-specific rules can be added independently.
		 *
		 * @param string     $temporary_path Absolute path to the temporary artifact.
		 * @param string     $final_path     Absolute path to the published feed file.
		 * @param array|null $batch_metadata Batch metadata for the active feed run.
		 *
		 * @return array Validation result payload.
		 */
		private function validate_xml_header_and_footer_before_promotion( $temporary_path, $final_path, $batch_metadata ) {
			$extension = strtolower( (string) pathinfo( $final_path, PATHINFO_EXTENSION ) );

			if ( 'xml' !== $extension ) {
				return array(
					'is_valid'      => true,
					'error_message' => '',
				);
			}

			$wp_filesystem = wppfm_get_wp_filesystem();
			$contents      = $wp_filesystem->get_contents( $temporary_path );

			if ( false === $contents || '' === (string) $contents ) {
				return array(
					'is_valid'      => false,
					'error_message' => sprintf(
						/* translators: %s: Temporary feed file path. */
						__( 'XML temporary feed validation failed: file %s is missing or empty.', 'wp-product-feed-manager' ),
						$temporary_path
					),
				);
			}

			$normalized_contents = str_replace( "\r", '', (string) $contents );
			$trimmed_contents    = trim( $normalized_contents );

			$expected_header = '<?xml version="1.0"';
			$expected_footer = '</rss>';

			$header_is_valid = 0 === strpos( ltrim( $trimmed_contents ), $expected_header );
			$footer_is_valid = $this->ends_with_ignore_trailing_whitespace( $trimmed_contents, $expected_footer );

			if ( $header_is_valid && $footer_is_valid ) {
				return array(
					'is_valid'      => true,
					'error_message' => '',
				);
			}

			$feed_id = (string) $this->_feed_data->feedId;
			$found_header_sample = $this->make_validation_log_snippet( substr( $trimmed_contents, 0, 300 ) );
			$found_footer_sample = $this->make_validation_log_snippet( substr( $trimmed_contents, max( 0, strlen( $trimmed_contents ) - 300 ) ) );
			$expected_header_sample = $this->make_validation_log_snippet( $expected_header );
			$expected_footer_sample = $this->make_validation_log_snippet( $expected_footer );

			// Add actionable diagnostics so XML formatting mismatches can be diagnosed quickly from feed logs.
			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				sprintf(
					'XML validation diagnostics (temp=%1$s): expected_header="%2$s" | found_header="%3$s"',
					$temporary_path,
					$expected_header_sample,
					$found_header_sample
				),
				'ERROR'
			);

			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				sprintf(
					'XML validation diagnostics (temp=%1$s): expected_footer="%2$s" | found_footer="%3$s"',
					$temporary_path,
					$expected_footer_sample,
					$found_footer_sample
				),
				'ERROR'
			);

			return array(
				'is_valid'      => false,
				'error_message' => sprintf(
					/* translators: %s: Temporary feed file path. */
					__( 'XML temporary feed validation failed: file %s does not contain the expected XML header and footer. The previous published feed remains active.', 'wp-product-feed-manager' ),
					$temporary_path
				),
			);
		}

		/**
		 * Checks if a string ends with a specific suffix after normalizing trailing whitespace.
		 *
		 * @param string $value  The full string to evaluate.
		 * @param string $suffix The expected string suffix.
		 *
		 * @return bool
		 */
		private function ends_with_ignore_trailing_whitespace( $value, $suffix ) {
			$normalized_value = rtrim( (string) $value );
			$suffix           = (string) $suffix;
			$suffix_length    = strlen( $suffix );

			if ( 0 === $suffix_length ) {
				return true;
			}

			if ( strlen( $normalized_value ) < $suffix_length ) {
				return false;
			}

			return substr( $normalized_value, -$suffix_length ) === $suffix;
		}

		/**
		 * Sanitizes feed-content snippets for compact single-line validation diagnostics.
		 *
		 * @param string $snippet Raw content snippet from the temporary feed.
		 *
		 * @return string
		 */
		private function make_validation_log_snippet( $snippet ) {
			if ( '' === (string) $snippet ) {
				return '[empty]';
			}

			$single_line_snippet = preg_replace( '/\s+/', ' ', (string) $snippet );
			$single_line_snippet = trim( (string) $single_line_snippet );

			if ( '' === $single_line_snippet ) {
				return '[empty]';
			}

			return substr( $single_line_snippet, 0, 220 );
		}

		/**
		 * Publishes the completed temporary feed file to the final path without unlinking the live file first.
		 *
		 * {@see WP_Filesystem_Direct::move()} with $overwrite true deletes the destination before renaming,
		 * which can leave the published feed missing if the rename step fails. This method uses a safe strategy
		 * per filesystem type instead.
		 *
		 * @param string $temporary_path Absolute path to the temporary artifact.
		 * @param string $final_path     Absolute path to the published feed file.
		 *
		 * @return bool True when the temporary file was promoted successfully.
		 */
		private function promote_temporary_feed_file_to_final_location( $temporary_path, $final_path ) {
			if ( ! function_exists( 'wppfm_is_safe_temporary_feed_artifact_path' ) || ! wppfm_is_safe_temporary_feed_artifact_path( $temporary_path ) ) {
				return false;
			}

			$tmp_parent = wp_normalize_path( (string) realpath( dirname( $temporary_path ) ) );
			$fin_parent = wp_normalize_path( (string) realpath( dirname( $final_path ) ) );

			if ( '' === $tmp_parent || '' === $fin_parent || $tmp_parent !== $fin_parent ) {
				return false;
			}

			if ( false !== strpos( basename( $final_path ), '.tmp.' ) ) {
				return false;
			}

			if ( ! file_exists( $temporary_path ) || ! is_readable( $temporary_path ) ) {
				return false;
			}

			$wp_filesystem = wppfm_get_wp_filesystem();

			if ( ! $wp_filesystem->exists( $final_path ) ) {
				return (bool) $wp_filesystem->move( $temporary_path, $final_path, false );
			}

			// Never use move(,, true) here: WordPress core unlinks the destination first.
			if ( $wp_filesystem instanceof WP_Filesystem_Direct ) {
				// Use WP_Filesystem-only backup swap so Plugin Check does not require direct rename().
				return $this->promote_temporary_feed_file_replace_existing_with_backup( $wp_filesystem, $temporary_path, $final_path );
			}

			// FTP/SSH and other transports: copy-overwrite then remove the temp artifact (best effort; not atomic).
			return (bool) $wp_filesystem->copy( $temporary_path, $final_path, true )
				&& $wp_filesystem->delete( $temporary_path, false, 'f' );
		}

		/**
		 * Replaces an existing final feed file using a same-directory backup swap through WP_Filesystem::move().
		 *
		 * @param WP_Filesystem_Direct $wp_filesystem  Initialized direct filesystem API.
		 * @param string               $temporary_path Source temp artifact path.
		 * @param string               $final_path     Destination published path.
		 *
		 * @return bool
		 */
		private function promote_temporary_feed_file_replace_existing_with_backup( $wp_filesystem, $temporary_path, $final_path ) {
			$dir = dirname( $final_path );

			for ( $attempt = 0; $attempt < 5; $attempt ++ ) {
				$backup = $dir . '/' . basename( $final_path ) . '.wppfm-replace-bak-' . wp_generate_password( 8, false, false );
				if ( ! $wp_filesystem->exists( $backup ) ) {
					if ( ! $wp_filesystem->move( $final_path, $backup, false ) ) {
						return false;
					}
					if ( ! $wp_filesystem->move( $temporary_path, $final_path, false ) ) {
						$wp_filesystem->move( $backup, $final_path, false );

						return false;
					}
					$wp_filesystem->delete( $backup, false, 'f' );

					return true;
				}
			}

			return false;
		}

		/**
		 * Terminal cleanup when the temporary file cannot be promoted at completion time.
		 *
		 * @param string $reason_message  Human-readable reason for logging.
		 * @param string $temporary_path  Known temp artifact path when promotion failed, or empty when unknown.
		 *
		 * @return void
		 */
		private function handle_feed_promotion_failure_at_completion( $reason_message, $temporary_path = '' ) {
			$feed_id = (string) $this->_feed_data->feedId;
			$feed_title = isset( $this->_feed_data->title ) ? (string) $this->_feed_data->title : (string) $feed_id;
			$user_visible_failure_message = sprintf(
				/* translators: %s: Feed title. */
				__( 'Product feed %s failed validation, so the previous feed remains active.', 'wp-product-feed-manager' ),
				$feed_title
			);

			do_action( 'wppfm_feed_generation_message', $feed_id, $reason_message, 'ERROR' );
			do_action(
				'wppfm_feed_generation_message',
				$feed_id,
				$user_visible_failure_message,
				'ERROR'
			);
			set_transient( 'wppfm_feed_validation_failure_notice_' . $feed_id, $user_visible_failure_message, HOUR_IN_SECONDS );

			$data_class = new WPPFM_Data();
			$data_class->update_feed_status( $feed_id, 6 );

			$this->clear_the_queue();

			// Remove the temp artifact while metadata still resolves, then drop batch options.
			if ( '' !== $temporary_path && function_exists( 'wppfm_delete_temporary_feed_artifact_if_safe' ) ) {
				$removed = wppfm_delete_temporary_feed_artifact_if_safe( $temporary_path );
				if ( ! $removed && function_exists( 'wppfm_is_safe_temporary_feed_artifact_path' ) && wppfm_is_safe_temporary_feed_artifact_path( $temporary_path ) && file_exists( $temporary_path ) && function_exists( 'wppfm_register_orphan_temporary_feed_cleanup_path' ) ) {
					wppfm_register_orphan_temporary_feed_cleanup_path( $temporary_path );
				}
			}

			// Covers missing-path failures where batch metadata still stores temporary_file_path, and is a no-op when already deleted.
			if ( function_exists( 'wppfm_delete_active_batch_temporary_feed_file_if_present' ) ) {
				wppfm_delete_active_batch_temporary_feed_file_if_present();
			}

			$this->cleanup_background_process_options();

			WPPFM_Feed_Controller::remove_id_from_feed_queue( $feed_id );
			WPPFM_Feed_Controller::set_feed_processing_flag();

			delete_transient( 'wppfm_feed_completion_context_' . $feed_id );
			delete_transient( 'wppfm_client_request_id_' . $feed_id );
			delete_transient( 'wppfm_nr_of_products_to_process_' . $feed_id );

			if ( get_transient( 'wppfm_running_silent' ) && function_exists( 'wppfm_schedule_deferred_feed_failure_notice' ) ) {
				wppfm_schedule_deferred_feed_failure_notice( $feed_id, time() );
			}

			if ( ! WPPFM_Feed_Controller::feed_queue_is_empty() ) {
				$feed_master_class = new WPPFM_Feed_Master_Class( WPPFM_Feed_Controller::get_next_id_from_feed_queue() );
				$feed_master_class->initiate_update_next_feed_in_queue();
			}
		}
	}

endif;
