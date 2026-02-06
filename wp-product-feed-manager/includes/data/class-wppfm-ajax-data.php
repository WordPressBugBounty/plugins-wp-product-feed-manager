<?php

/**
 * WP Ajax Data Class.
 *
 * @package WP Product Feed Manager/Data/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Ajax_Data' ) ) :

	/**
	 * Ajax Data Class.
	 */
	class WPPFM_Ajax_Data extends WPPFM_Ajax_Calls {

		public function __construct() {
			parent::__construct();

			$this->_queries_class = new WPPFM_Queries();
			$this->_files_class   = new WPPFM_File();

			add_action( 'wp_ajax_wppfm-ajax-get-list-of-feeds', array( $this, 'wppfm_get_list_of_feeds' ) );
			add_action( 'wp_ajax_wppfm-ajax-get-list-of-backups', array( $this, 'wppfm_get_list_of_backups' ) );
			add_action( 'wp_ajax_wppfm-ajax-get-settings-options', array( $this, 'wppfm_get_settings_options' ) );
			add_action( 'wp_ajax_wppfm-ajax-get-output-fields', array( $this, 'wppfm_get_output_fields' ) );
			add_action( 'wp_ajax_wppfm-ajax-get-input-fields', array( $this, 'wppfm_get_input_fields' ) );
			add_action( 'wp_ajax_wppfm-ajax-get-feed-status', array( $this, 'wppfm_get_feed_status' ) );
			add_action( 'wp_ajax_wppfm-ajax-get-main-feed-filters', array( $this, 'wppfm_get_feed_filters' ) );
			add_action( 'wp_ajax_wppfm-ajax-switch-feed-status', array( $this, 'wppfm_switch_feed_status_between_hold_and_ok' ) );
			add_action( 'wp_ajax_wppfm-ajax-duplicate-existing-feed', array( $this, 'wppfm_duplicate_feed_data' ) );
			add_action( 'wp_ajax_wppfm-ajax-update-feed-data', array( $this, 'wppfm_update_feed_data' ) );
			add_action( 'wp_ajax_wppfm-ajax-delete-feed', array( $this, 'wppfm_delete_feed' ) );
			add_action( 'wp_ajax_wppfm-ajax-backup-current-data', array( $this, 'wppfm_backup_current_data' ) );
			add_action( 'wp_ajax_wppfm-ajax-delete-backup-file', array( $this, 'wppfm_delete_backup_file' ) );
			add_action( 'wp_ajax_wppfm-ajax-restore-backup-file', array( $this, 'wppfm_restore_backup_file' ) );
			add_action( 'wp_ajax_wppfm-ajax-duplicate-backup-file', array( $this, 'wppfm_duplicate_backup_file' ) );
			add_action( 'wp_ajax_wppfm-ajax-get-next-feed-in-queue', array( $this, 'wppfm_get_next_feed_in_queue' ) );
			add_action( 'wp_ajax_wppfm-ajax-register-notice-dismission', array( $this, 'wppfm_register_notice_dismission' ) );
			add_action( 'wp_ajax_wppfm-ajax-cancel-promotion-notice', array( $this, 'wppfm_cancel_promotion' ) );
		}

		/**
		 * Returns a list of all active feeds to an ajax caller.
		 */
		public function wppfm_get_list_of_feeds() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'postFeedsListNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-post-feeds-list-nonce' ) ) {
				$list = $this->_queries_class->get_feeds_list();

				// @since 2.1.0 due to implementation of i18n to the plugin and for backwards compatibility, we need to change
				// the status string entries from the database to identification strings (i.e., OK to ok and On hold in on_hold).
				if ( $list && ! ctype_lower( $list[0]->status ) ) {
					wppfm_correct_old_feeds_list_status( $list );
				}

				$this->convert_type_ids_to_type_names( $list );

				$result = array(
					'list' => $list,
				);

				echo wp_json_encode( $result );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Returns a list of backups the user has made.
		 */
		public function wppfm_get_list_of_backups() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'postBackupListNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-backups-list-nonce' ) ) {
				echo wp_json_encode( $this->_files_class->make_list_of_active_backups() );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Returns a JSON string containing an array with the setting options.
		 */
		public function wppfm_get_settings_options() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'postSetupOptionsNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-setting-options-nonce' ) ) {
				$auto_feed_fix_option = 'true' === get_option( 'wppfm_auto_feed_fix' ) ? 'true' : 'false';
				$disabled_background_mode = 'true' === get_option( 'wppfm_disabled_background_mode' ) ? 'true' : 'false';
				$feed_process_logger = 'true' === get_option( 'wppfm_process_logger_status' ) ? 'true' : 'false';
				$show_product_identifiers = 'true' === get_option( 'wppfm_show_product_identifiers' ) ? 'true' : 'false';
				$manual_channel_update = 'true' === get_option( 'wppfm_manual_channel_update' ) ? 'true' : 'false';
				$third_party_attribute_keywords = sanitize_text_field( get_option( 'wppfm_third_party_attribute_keywords' ) );
				$notice_mailaddress = sanitize_email( get_option( 'wppfm_notice_mailaddress' ) );

				$options = array(
					$auto_feed_fix_option,
					$disabled_background_mode,
					$feed_process_logger,
					$show_product_identifiers,
					$manual_channel_update,
					$third_party_attribute_keywords,
					$notice_mailaddress,
				);
				echo wp_json_encode( $options );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Retrieves the output fields that are specific for a given merchant and
		 * also adds stored metadata to the output fields
		 */
		public function wppfm_get_output_fields() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'outputFieldsNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-output-fields-nonce' ) ) {
				$data_class = new WPPFM_Data();

				// Get the posted inputs.
				$channel_id   = filter_input( INPUT_POST, 'channelId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$feed_type_id = filter_input( INPUT_POST, 'feedType', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$feed_id      = filter_input( INPUT_POST, 'feedId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$channel      = trim( $this->_queries_class->get_channel_short_name_from_db( $channel_id ) );
				$is_custom    = function_exists( 'wppfm_channel_is_custom_channel' ) && wppfm_channel_is_custom_channel( $channel_id );

				if ( ! $is_custom ) {
					$channel_class = new WPPFM_Channel();

					// Read the output fields.
					$outputs = apply_filters( 'wppfm_get_feed_attributes', $this->_files_class->get_attributes_for_specific_channel( $channel ), $feed_id, $feed_type_id );

					// if the feed is a stored feed, look for metadata to add (a feed an id of -1 is a new feed that not yet has been saved)
					if ( $feed_id >= 0 ) {
						// Add metadata to the feed output fields.
						$outputs = $data_class->fill_output_fields_with_metadata( $feed_id, $outputs );
					}

					// Add the channel-specific feed specification url to the output fields.
					$outputs['feed_specification_url'] = $channel_class->get_channel_specifications_link( $channel );
				} else {
					$data_class = new WPPFM_Data();
					$outputs    = $data_class->get_custom_fields_with_metadata( $feed_id );
				}

				echo wp_json_encode( $outputs );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Gets all the different source fields from the custom products and third party sources and combines them into one list.
		 */
		public function wppfm_get_input_fields() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'inputFieldsNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-input-fields-nonce' ) ) {
				$source_id = filter_input( INPUT_POST, 'sourceId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				switch ( $source_id ) {
					case '1':
						$data_class = new WPPFM_Data();

						$custom_product_attributes = $this->_queries_class->get_custom_product_attributes();
						$custom_product_fields     = $this->_queries_class->get_custom_product_fields();
						$product_attributes        = $this->_queries_class->get_all_product_attributes();
						$product_taxonomies        = get_taxonomies();
						$third_party_custom_fields = $data_class->get_third_party_custom_fields();

						$all_source_fields = $this->combine_custom_attributes_and_feeds(
							$custom_product_attributes,
							$custom_product_fields,
							$product_attributes,
							$product_taxonomies,
							$third_party_custom_fields
						);

						echo wp_json_encode( apply_filters( 'wppfm_all_source_fields', $all_source_fields ) );
						break;

					default:
						if ( 'valid' === get_option( 'wppfm_lic_status' ) ) { // error message for paid versions
							echo '<div id="error">' . esc_html__(
								'Could not add custom fields because I could not identify the channel.
									If not already done add the correct channel in the Manage Channels page.
									Also try to deactivate and then activate the plugin.',
								'wp-product-feed-manager'
							) . '</div>';

							wppfm_write_log_file( sprintf( 'Could not define the channel in a valid Premium plugin version. Feed id = %s', $source_id ) );
						} else { // error message for a free version
							echo '<div id="error">' . esc_html__(
								'Could not identify the channel.
								Try to deactivate and then activate the plugin.
								If that does not work remove the plugin through the WordPress Plugins page and than reinstall and activate it again.',
								'wp-product-feed-manager'
							) . '</div>';

							wppfm_write_log_file( sprintf( 'Could not define the channel in a free plugin version. Feed id = %s', $source_id ) );
						}

						break;
				}
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Gets the stored main feed query string.
		 */
		public function wppfm_get_feed_filters() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'inputFeedFiltersNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-feed-filters-nonce' ) ) {
				$feed_id = filter_input( INPUT_POST, 'feedId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				$data_class = new WPPFM_Data();
				$filters    = $data_class->get_filter_query( $feed_id );

				echo $filters ? wp_json_encode( $filters ) : '0';
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Gets the current feed status.
		 */
		public function wppfm_get_feed_status() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'feedStatusNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-feed-status-nonce' ) ) {
				$feed_id = filter_input( INPUT_POST, 'sourceId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				$feed_master = new WPPFM_Feed_Master_Class( $feed_id );
				$feed_data   = $feed_master->feed_status_check( $feed_id );

				echo wp_json_encode( $feed_data );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Updates the feed data to the database. Creates a new record if the feed data does not exist.
		 */
		public function wppfm_update_feed_data() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'updateFeedDataNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-update-feed-data-nonce', 'edit_feeds' ) ) {
				// Get the posted feed data.
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by safe_ajax_call() above.
				$ajax_feed_data = isset( $_POST['feed'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['feed'] ) ) ) : array();
				$feed_filter    = isset( $_POST['feedFilter'] ) ? sanitize_text_field( wp_unslash( $_POST['feedFilter'] ) ) : '';
				$m_data         = isset( $_POST['metaData'] ) ? sanitize_text_field( wp_unslash( $_POST['metaData'] ) ) : '';
				// phpcs:enable WordPress.Security.NonceVerification.Missing

				WPPFM_Feed_CRUD_Handler::create_or_update_feed_data( $ajax_feed_data, $m_data, $feed_filter );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Switches the status of a feed between hold and ok.
		 */
		public function wppfm_switch_feed_status_between_hold_and_ok() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'switchFeedStatusNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-switch-feed-status-nonce', 'edit_feeds' ) ) {
				$feed_id = filter_input( INPUT_POST, 'feedId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				$feed_status    = $this->_queries_class->get_current_feed_status( $feed_id );
				$current_status = $feed_status[0]->status_id;

				$new_status = '1' === $current_status ? '2' : '1'; // Only allow status 1 or 2.

				$result = $this->_queries_class->switch_feed_status( $feed_id, $new_status );

				/**
				 * Send FluentCRM tag "First auto-on enabled" (tag id=13) once per licensed user.
				 *
				 * Fires only when switching a feed to status `1` (Auto-on), and only after the
				 * status change has been successfully stored.
				 *
				 * @since 3.19.0
				 */
				if (
					false !== $result
					&& '1' === $new_status
					&& function_exists( 'wppfm_fluentcrm_send_tag_once_for_current_user' )
				) {
					wppfm_fluentcrm_send_tag_once_for_current_user( 13, 'wppfm_fluentcrm_tag_13_sent' );
				}

				echo ( false === $result ) ? esc_html( $current_status ) : esc_html( $new_status );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit
			exit;
		}

		/**
		 * Duplicates a feed.
		 */
		public function wppfm_duplicate_feed_data() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'duplicateFeedNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-duplicate-existing-feed-nonce', 'edit_feeds' ) ) {
				$feed_id = filter_input( INPUT_POST, 'feedId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				WPPFM_Db_Management::duplicate_feed( $feed_id );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Removes a feed from the feedmanager_product_feed table.
		 */
		public function wppfm_delete_feed() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'deleteFeedNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-delete-feed-nonce', 'delete_feeds' ) ) {
				$feed_id = filter_input( INPUT_POST, 'feedId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				WPPFM_Feed_Controller::remove_id_from_feed_queue( $feed_id );
				$this->_queries_class->delete_meta( $feed_id );
				$result = $this->_queries_class->delete_feed( $feed_id );
				echo esc_html( $result );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Generates a backup of the current feeds and plugin settings.
		 */
		public function wppfm_backup_current_data() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'backupNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-backup-nonce', 'manage_options' ) ) {
				$backup_file_name = filter_input( INPUT_POST, 'fileName', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$result = WPPFM_Db_Management::backup_database_tables( $backup_file_name );
				echo esc_html( $result );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Removes a backup file from the backup folder.
		 */
		public function wppfm_delete_backup_file() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'deleteBackupNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-delete-backup-nonce', 'manage_options' ) ) {
				$backup_file_name = filter_input( INPUT_POST, 'fileName', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$result = WPPFM_Db_Management::delete_backup_file( $backup_file_name );
				echo esc_html( $result );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Restores a Feed Manager backup.
		 */
		public function wppfm_restore_backup_file() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'restoreBackupNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-restore-backup-nonce', 'manage_options' ) ) {
				$backup_file_name = filter_input( INPUT_POST, 'fileName', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$result = WPPFM_Db_Management::restore_backup( $backup_file_name );
				echo esc_html( $result );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Makes a duplicate of an existing backup.
		 */
		public function wppfm_duplicate_backup_file() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'duplicateBackupNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-duplicate-backup-nonce', 'manage_options' ) ) {
				$backup_file_name = filter_input( INPUT_POST, 'fileName', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$result = WPPFM_Db_Management::duplicate_backup_file( $backup_file_name );
				echo esc_html( $result );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Gets the next feed if from the feed queue. Returns a false string if the feed queue is empty.
		 */
		public function wppfm_get_next_feed_in_queue() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'nextFeedInQueueNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-next-feed-in-queue-nonce' ) ) {
				$next_feed_id = WPPFM_Feed_Controller::get_next_id_from_feed_queue();
				echo false !== $next_feed_id ? esc_html( $next_feed_id ) : 'false';
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Registers a notice dismission.
		 */
		public function wppfm_register_notice_dismission() {
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'noticeDismissionNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-duplicate-backup-nonce', 'manage_options' ) ) {

				update_option( 'wppfm_license_notice_suppressed', true );
				echo 'true';
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Handling the cancellation of the summer promotion notice.
		 */
		public function wppfm_cancel_promotion() {
			update_option( 'wppfm_black_friday_promotion_2024_dismissed', 'canceled' );

			$result = get_option( 'wppfm_black_friday_promotion_2024_dismissed' );
			echo 'Removed black friday promotion ' . esc_html( $result ) . '!';

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Combines custom attributes and feeds.
		 *
		 * @param $attributes
		 * @param $feeds
		 * @param $product_attributes
		 * @param $product_taxonomies
		 * @param $third_party_fields
		 *
		 * @return mixed
		 */
		private function combine_custom_attributes_and_feeds( $attributes, $feeds, $product_attributes, $product_taxonomies, $third_party_fields ) {
			$prev_dup_array = array(); // used to prevent doubles

			foreach ( $feeds as $feed ) {
				$obj = new stdClass();

				$obj->attribute_name  = $feed;
				$obj->attribute_label = $feed;

				$attributes[]     = $obj;
				$prev_dup_array[] = $obj->attribute_label;
			}

			foreach ( $product_taxonomies as $taxonomy ) {
				if ( ! in_array( $taxonomy, $prev_dup_array, true ) ) {
					$obj                  = new stdClass();
					$obj->attribute_name  = $taxonomy;
					$obj->attribute_label = $taxonomy;

					$attributes[]     = $obj;
					$prev_dup_array[] = $taxonomy;
				}
			}

			foreach ( $product_attributes as $attribute_string ) {
				$attribute_object = maybe_unserialize( $attribute_string->meta_value );

				if ( $attribute_object && ( is_object( $attribute_object ) || is_array( $attribute_object ) ) ) {
					foreach ( $attribute_object as $attribute ) {
						if ( is_array( $attribute ) && array_key_exists( 'name', $attribute ) && ! in_array( $attribute['name'], $prev_dup_array, true ) ) {
							$obj                  = new stdClass();
							$obj->attribute_name  = $attribute['name'];
							$obj->attribute_label = $attribute['name'];

							$attributes[]     = $obj;
							$prev_dup_array[] = $attribute['name'];
						}
					}
				} else {
					if ( $attribute_object ) {
						wppfm_write_log_file( $attribute_object );
					}
				}
			}

			foreach ( $third_party_fields as $field_label ) {
				if ( ! in_array( $field_label, $prev_dup_array, true ) ) {
					$obj                  = new stdClass();
					$obj->attribute_name  = $field_label;
					$obj->attribute_label = $field_label;

					$attributes[]     = $obj;
					$prev_dup_array[] = $field_label;
				}
			}

			return $attributes;
		}

		/**
		 * Converts a list of feed type ids to type names.
		 *
		 * @param array $list with type ids to convert.
		 *
		 * @return void
		 */
		private function convert_type_ids_to_type_names( $list ) {
			$channel_class = new WPPFM_Channel();

			$feed_types = wppfm_list_feed_type_text();

			for ( $i = 0; $i < count( $list ); $i ++ ) {
				$list[ $i ]->feed_type_name = '1' === $list[ $i ]->feed_type_id ?
					$channel_class->get_channel_name( $list[ $i ]->channel_id ) . ' ' . __( 'Feed', 'wp-product-feed-manager' ) :
					$feed_types[ $list[ $i ]->feed_type_id ];

				$list[ $i ]->feed_type = $feed_types[ $list[ $i ]->feed_type_id ];
			}
		}
	}

	// end of WPPFM_Ajax_Data_Class

endif;

$my_ajax_data_class = new WPPFM_Ajax_Data();
