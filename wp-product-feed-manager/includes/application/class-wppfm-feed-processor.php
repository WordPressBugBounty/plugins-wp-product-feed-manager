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

			do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, 'Started the complete function to clean up the feed process and queue.' );

			// Flush any remaining buffer before completing
			if ( method_exists( $this, 'flush_file_buffer' ) ) {
				$this->flush_file_buffer();
			}

			// Remove the properties from the option table.
			$this->cleanup_background_process_options();

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

		// Hook for performance monitoring - feed generation complete
		do_action( 'wppfm_feed_generation_complete', $this->_feed_data->feedId );

		// Clean up preserved feed context transient now that completion succeeded.
		delete_transient( 'wppfm_feed_completion_context_' . $this->_feed_data->feedId );

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

			// @since 2.3.0
			do_action( 'wppfm_add_product_to_feed', $feed_id, $product_id );

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

			do_action( 'wppfm_feed_generation_message', $log_id, 'Feed completion aborted because the feed metadata could not be restored.', 'ERROR' );

		// Clean up any preserved context to avoid stale data.
		if ( $feed_id ) {
			delete_transient( 'wppfm_feed_completion_context_' . $feed_id );
		}

			if ( $feed_id ) {
				$data_class = new WPPFM_Data();
				$data_class->update_feed_status( $feed_id, 6 );
				WPPFM_Feed_Controller::remove_id_from_feed_queue( $feed_id );
			}

			$this->clear_the_queue();
			$this->cleanup_background_process_options();
			WPPFM_Feed_Controller::set_feed_processing_flag();
		}

	/**
	 * Preserves feed context before batch deletion so complete() can access it.
	 *
	 * @param string $feed_id        Feed ID.
	 * @param string $properties_key Batch properties key.
	 */
	protected function preserve_feed_context_for_completion( $feed_id, $properties_key ) {
		if ( ! $properties_key ) {
			do_action( 'wppfm_feed_generation_message', $feed_id, 'Warning: No properties key available to preserve feed context for completion', 'WARNING' );
			return;
		}

		set_transient( 'wppfm_feed_completion_context_' . $feed_id, $properties_key, 15 * MINUTE_IN_SECONDS );
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

		$properties_key = get_transient( 'wppfm_feed_completion_context_' . $feed_id );

		if ( ! $properties_key ) {
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
	}

endif;
