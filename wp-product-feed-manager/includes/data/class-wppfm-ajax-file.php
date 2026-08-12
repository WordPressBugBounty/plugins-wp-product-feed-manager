<?php
/**
 * WP Ajax File Class.
 *
 * @package WP Product Feed Manager/Data/Classes
 * @version 2.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Ajax_File' ) ) :

	/**
	 * Ajax File Class
	 */
	class WPPFM_Ajax_File extends WPPFM_Ajax_Calls {

		/**
		 * WPPFM_Ajax_File constructor.
		 */
		public function __construct() {
			parent::__construct();

			// Add the hooks.
			add_action( 'wp_ajax_wppfm-ajax-get-next-categories', array( $this, 'myajax_read_next_categories' ) );
			add_action( 'wp_ajax_wppfm-ajax-get-category-lists', array( $this, 'myajax_read_category_lists' ) );
			add_action( 'wp_ajax_wppfm-ajax-delete-feed-file', array( $this, 'myajax_delete_feed_file' ) );
			add_action( 'wp_ajax_wppfm-ajax-update-feed-file', array( $this, 'myajax_update_feed_file' ) );
			add_action( 'wp_ajax_wppfm-ajax-log-message', array( $this, 'myajax_log_message' ) );
			add_action( 'wp_ajax_wppfm-ajax-auto-feed-fix-mode-selection', array( $this, 'myajax_auto_feed_fix_mode_selection' ) );
			add_action( 'wp_ajax_wppfm-ajax-background-processing-mode-selection', array( $this, 'myajax_background_processing_mode_selection' ) );
			add_action( 'wp_ajax_wppfm-ajax-feed-logger-status-selection', array( $this, 'myajax_feed_logger_status_selection' ) );
			add_action( 'wp_ajax_wppfm-ajax-show-product-identifiers-selection', array( $this, 'myajax_show_product_identifiers_selection' ) );
			add_action( 'wp_ajax_wppfm-ajax-switch-to-manual-channel-update-selection', array( $this, 'myajax_switch_to_manual_channel_update' ) );
			add_action( 'wp_ajax_wppfm-ajax-wpml-use-full-url-resolution-selection', array( $this, 'myajax_wpml_use_full_url_resolution_selection' ) );
			add_action( 'wp_ajax_wppfm-ajax-omit-price-filters-selection', array( $this, 'myajax_omit_price_filters_selection' ) );
			add_action( 'wp_ajax_wppfm-ajax-third-party-attribute-keywords', array( $this, 'myajax_set_third_party_attribute_keywords' ) );
			add_action( 'wp_ajax_wppfm-ajax-set-notice-mailaddress', array( $this, 'myajax_set_notice_mailaddress' ) );
			add_action( 'wp_ajax_wppfm-ajax-clear-feed-process-data', array( $this, 'myajax_clear_feed_process_data' ) );
			add_action( 'wp_ajax_wppfm-ajax-reinitiate-plugin', array( $this, 'myajax_reinitiate_plugin' ) );
		}

		/**
		 * Returns the subcategories from a selected category
		 */
		public function myajax_read_next_categories() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'nextCategoryNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-next-category-nonce' ) && current_user_can( 'edit_feeds' ) && is_admin() ) {
				$file_class = new WPPFM_File();

				$channel_id      = filter_input( INPUT_POST, 'channelId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$requested_level = filter_input( INPUT_POST, 'requestedLevel', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are validated above before reading request payload.
				$parent_category = isset( $_POST['parentCategory'] ) && is_string( $_POST['parentCategory'] )
					? $this->sanitize_string_with_ampersand( wp_unslash( $_POST['parentCategory'] ) )
					: '';
				// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$file_language   = filter_input( INPUT_POST, 'fileLanguage', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$categories      = $file_class->get_categories_for_list( $channel_id, $requested_level, $parent_category, $file_language );

				if ( ! is_array( $categories ) ) {
					if ( '0' === substr( $categories, - 1 ) ) {
						/** @noinspection PhpExpressionResultUnusedInspection */
						chop( $categories, '0' );
					}
				}

				echo wp_json_encode( $categories );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Read the category list
		 */
		public function myajax_read_category_lists() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'categoryListsNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-category-lists-nonce' ) && current_user_can( 'edit_feeds' ) && is_admin() ) {
				$file_class = new WPPFM_File();

				$channel_id = filter_input( INPUT_POST, 'channelId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are validated above before reading request payload.
				$main_categories_string = isset( $_POST['mainCategories'] ) && is_string( $_POST['mainCategories'] )
					? $this->sanitize_string_with_ampersand( wp_unslash( $_POST['mainCategories'] ) )
					: '';
				// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$file_language          = filter_input( INPUT_POST, 'fileLanguage', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$categories_array       = explode( ' > ', $main_categories_string );
				$categories             = array();
				$required_levels        = count( $categories_array ) > 0 ? ( count( $categories_array ) + 1 ) : 0;

				for ( $i = 0; $i < $required_levels; $i ++ ) {
					$parent_category = $i > 0 ? $categories_array[ $i - 1 ] : '';
					$c               = $file_class->get_categories_for_list( $channel_id, $i, $parent_category, $file_language );
					if ( $c ) {
						$categories[] = $c;
					}
				}

				echo wp_json_encode( $categories );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Delete a specific feed file
		 *
		 * @since: 3.9.0 Removed the link to the older feed folder as it is not in use anymore.
		 */
		public function myajax_delete_feed_file() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'deleteFeedNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-delete-feed-nonce' ) && current_user_can( 'delete_feeds' ) && is_admin() ) {
				// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are validated above before reading request payload.
				$file_name = isset( $_POST['fileTitle'] ) && is_string( $_POST['fileTitle'] )
					? $this->sanitize_title_string( wp_unslash( $_POST['fileTitle'] ) )
					: '';
				// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

				$file = realpath( WPPFM_FEEDS_DIR . '/' . basename( $file_name ) );

				if ( file_exists( $file ) ) {
					wp_delete_file( $file );
				} else {
					/* translators: %s: Title of the feed file */
					echo '<div id="error">' . sprintf( esc_html__( 'Could not remove file %s because it does not seem to exist.', 'wp-product-feed-manager' ), esc_url( $file ) ) . '</div>';
				}
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * This function fetches the posted data and triggers the update of the feed file on the server.
		 */
		public function myajax_update_feed_file() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'updateFeedFileNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-update-feed-file-nonce' ) && current_user_can( 'edit_feeds' ) && is_admin() ) {

				// Fetch the data from $_POST.
				$feed_id                  = filter_input( INPUT_POST, 'feedId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$background_mode_disabled = get_option( 'wppfm_disabled_background_mode', 'false' );
				$client_request_id        = filter_input( INPUT_POST, 'client_request_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

				// Store the client request id so all subsequent feed process log entries can be correlated.
				if ( is_string( $client_request_id ) && '' !== $client_request_id ) {
					set_transient( 'wppfm_client_request_id_' . $feed_id, $client_request_id, HOUR_IN_SECONDS );
				}

				if ( class_exists( 'WPPFM_Feed_Process_Logging' ) && function_exists( 'wppfm_process_logger_is_active' ) && wppfm_process_logger_is_active() ) {
					WPPFM_Feed_Process_Logging::set_active_process_log_feed_id( $feed_id );
				}

				// @since: 2.40.0
				do_action(
					'wppfm_feed_generation_message',
					$feed_id,
					sprintf(
						'Received the wppfm-ajax-update-feed-file post request call from javascript to initiate the feed generation process (client_request_id=%s).',
						is_string( $client_request_id ) && '' !== $client_request_id ? $client_request_id : '(none)'
					)
				);


				/**
				 * Send FluentCRM tag "First feed generated" (tag id=12) once per licensed user.
				 *
				 * Only count a generation started from the Feed Editor page ("Save & Generate Feed"),
				 * and avoid counting regenerations initiated from the Feed List.
				 *
				 * The admin referrer check is used as a best-effort guard to distinguish the origin.
				 *
				 * @since 3.19.0
				 */
				$referrer = wp_get_referer();
				if (
					is_string( $referrer )
					&& false !== strpos( $referrer, 'page=wppfm-feed-editor-page' )
					&& function_exists( 'wppfm_fluentcrm_send_tag_once_for_current_user' )
				) {
					wppfm_fluentcrm_send_tag_once_for_current_user( 12, 'wppfm_fluentcrm_tag_12_sent' );
				}

				WPPFM_Feed_Controller::add_id_to_feed_queue( $feed_id );

				// If there is no feed processing in progress, of background processing is switched off, start updating the current feed.
				if ( ! WPPFM_Feed_Controller::feed_is_processing() || 'true' === $background_mode_disabled ) {
					do_action( 'wppfm_manual_feed_update_activated', $feed_id );

					$feed_master_class = new WPPFM_Feed_Master_Class( $feed_id );
					$feed_master_class->update_feed_file( false );
				} else {
					$data_class = new WPPFM_Data();
					$data_class->update_feed_status( $feed_id, 4 ); // Feed status to waiting in queue.
					echo 'pushed_to_queue';
				}
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Logs a message from a JavaScript call to the server
		 */
		public function myajax_log_message() {
			// Make sure this call is legal.
			$nonce = filter_input( INPUT_POST, 'logMessageNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wppfm-ajax-log-message-nonce' ) || ! current_user_can( 'edit_feeds' ) ) {
				$this->show_not_allowed_error_message();
				exit;
			}

			if ( ! is_admin() ) {
				$this->show_not_allowed_error_message();
				exit;
			}

			// Fetch the data from $_POST.
			$message = filter_input( INPUT_POST, 'messageList', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are validated above before reading request payload.
			$file_name = isset( $_POST['fileName'] ) && is_string( $_POST['fileName'] )
				? $this->sanitize_title_string( wp_unslash( $_POST['fileName'] ) )
				: '';
			// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$text_message = wp_strip_all_tags( $message );

			wppfm_write_log_file( $text_message, $file_name );

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the Auto Feed Fix setting from the Settings page
		 *
		 * @since 1.7.0
		 */
		public function myajax_auto_feed_fix_mode_selection() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'updateAutoFeedFixNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-auto-feed-fix-nonce' ) && current_user_can( 'manage_options' ) && is_admin() ) {
				echo esc_html( $this->update_boolean_setting_option( 'fix_selection', 'wppfm_auto_feed_fix' ) );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the Disable Background processing setting from the Settings page
		 *
		 * @since 2.0.7
		 */
		public function myajax_background_processing_mode_selection() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'backgroundModeNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-background-mode-nonce' ) && current_user_can( 'manage_options' ) && is_admin() ) {
				echo esc_html( $this->update_boolean_setting_option( 'mode_selection', 'wppfm_disabled_background_mode' ) );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the Feed Process Logger setting from the Settings page.
		 *
		 * @since 2.8.0
		 */
		public function myajax_feed_logger_status_selection() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'feedLoggerStatusNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-logger-status-nonce' ) && current_user_can( 'manage_options' ) && is_admin() ) {
				echo esc_html( $this->update_boolean_setting_option( 'statusSelection', 'wppfm_process_logger_status' ) );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the Show Product Identifiers setting from the Settings page.
		 *
		 * @since 2.10.0
		 */
		public function myajax_show_product_identifiers_selection() {
			// Make sure this call is legal.
			$nonce = filter_input( INPUT_POST, 'showPINonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wppfm-ajax-show-pi-nonce' ) || ! current_user_can( 'manage_options' ) || ! is_admin() ) {
				$this->show_not_allowed_error_message();
				exit;
			}

			echo esc_html( $this->update_boolean_setting_option( 'showPiSelection', 'wppfm_show_product_identifiers' ) );

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the Manual Channel Update setting from the Settings page.
		 *
		 * @since 3.7.0
		 */
		public function myajax_switch_to_manual_channel_update() {
			// Make sure this call is legal.
			$nonce = filter_input( INPUT_POST, 'manualChannelUpdateNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wppfm-ajax-manual-channel-update-nonce' ) || ! current_user_can( 'manage_options' ) || ! is_admin() ) {
				$this->show_not_allowed_error_message();
				exit;
			}

			echo esc_html( $this->update_boolean_setting_option( 'manualChannelUpdateSelection', 'wppfm_manual_channel_update' ) );

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the WPML Use full resolution URLs setting from the Settings page.
		 *
		 * @since 2.15.0
		 */
		public function myajax_wpml_use_full_url_resolution_selection() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'urlResolutionNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-use-full-url-resolution-nonce' ) && current_user_can( 'manage_options' ) && is_admin() ) {
				echo esc_html( $this->update_boolean_setting_option( 'urlResolutionSelection', 'wppfm_use_full_url_resolution' ) );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the Omit price filters setting from the Settings page.
		 *
		 * @since 3.12.0
		 */
		public function myajax_omit_price_filters_selection() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'omitPriceFiltersNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-omit-price-filters-nonce' ) && current_user_can( 'manage_options' ) && is_admin() ) {
				echo esc_html( $this->update_boolean_setting_option( 'omitPriceFiltersSelection', 'wppfm_omit_price_filters' ) );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the Third party attribute keywords from the Settings page
		 */
		public function myajax_set_third_party_attribute_keywords() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'thirdPartyKeywordsNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-set-third-party-keywords-nonce' ) && current_user_can( 'manage_options' ) && is_admin() ) {
				// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are validated above before reading request payload.
				$new_keywords = isset( $_POST['keywords'] ) && is_string( $_POST['keywords'] )
					? $this->sanitize_third_party_attributes_string( wp_unslash( $_POST['keywords'] ) )
					: '';
				// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$clean_keywords = sanitize_option( 'wppfm_third_party_attribute_keywords', $new_keywords );
				update_option( 'wppfm_third_party_attribute_keywords', $clean_keywords );

				echo esc_html( get_option( 'wppfm_third_party_attribute_keywords' ) );
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Changes the notice recipient email address and sends a test email to verify delivery.
		 */
		public function myajax_set_notice_mailaddress() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'noticeMailaddressNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-set-notice-mailaddress-nonce' ) && current_user_can( 'manage_options' ) && is_admin() ) {
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is handled above before reading request payload.
				$mailaddress = isset( $_POST['mailaddress'] ) ? sanitize_text_field( wp_unslash( $_POST['mailaddress'] ) ) : '';
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				$saved_email = class_exists( 'WPPFM_Email' ) ? WPPFM_Email::sanitize_recipient_list( $mailaddress ) : sanitize_text_field( $mailaddress );

				update_option( 'wppfm_notice_mailaddress', $saved_email );

				$test_sent  = false;

				// Send test email when at least one valid address is configured.
				if ( ! empty( $saved_email ) && class_exists( 'WPPFM_Email' ) ) {
					$test_sent = WPPFM_Email::send_test_email( $saved_email );
				}

				wp_send_json_success(
					array(
						'email'     => $saved_email,
						'test_sent' => $test_sent,
					)
				);
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit (wp_send_json_success exits, but ensure for error path).
			exit;
		}

		/**
		 * Re-initiates the plugin, updates the database and loads all cron jobs
		 *
		 * @since 1.9.0
		 */
		public function myajax_reinitiate_plugin() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'reInitiateNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-reinitiate-nonce' ) && current_user_can( 'update_plugins' ) && is_admin() ) {

				if ( wppfm_reinitiate_plugin() ) {
					echo 'Plugin re-initiated';
				} else {
					echo '<div id="error">' . esc_html__( 'Failed to re-initialize the plugin. Please try again.', 'wp-product-feed-manager' ) . '</div>';
				}
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Clears all option data that is related to the feed processing
		 *
		 * @since 1.10.0
		 */
		public function myajax_clear_feed_process_data() {
			// Make sure this call is legal.
			if ( wp_verify_nonce( filter_input( INPUT_POST, 'clearFeedNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-clear-feed-nonce' ) && current_user_can( 'manage_options' ) && is_admin() ) {

				if ( wppfm_clear_feed_process_data() ) {
					echo esc_html__( 'Feed processing data cleared', 'wp-product-feed-manager' );
				} else {
					/* translators: clearing the feed data failed */
					echo '<div id="error">' . esc_html__( 'Failed to clear the feed process. Please try again.', 'wp-product-feed-manager' ) . '</div>';
				}
			} else {
				$this->show_not_allowed_error_message();
			}

			// IMPORTANT: don't forget to exit.
			exit;
		}

		/**
		 * Updates a boolean option based on posted input and verifies the stored value.
		 *
		 * @param string $post_key   Posted field key.
		 * @param string $option_key Option name to update.
		 *
		 * @return string Normalized stored value ('true' or 'false').
		 */
		private function update_boolean_setting_option( $post_key, $option_key ) {
			global $wpdb;

			$raw_selection = 'false';
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability verification are handled in caller methods.
			if ( isset( $_POST[ $post_key ] ) ) {
				$raw_selection = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$selection = $this->sanitize_true_false_string( $raw_selection );

			update_option( $option_key, $selection );

			// Validate both filtered and raw database values so filter interference becomes visible in logs.
			$stored_filtered = $this->sanitize_true_false_string( (string) get_option( $option_key, 'false' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads raw option_value intentionally to detect option filter interference during this request-scoped settings update.
			$raw_db_value_before = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					$option_key
				)
			);
			$stored_raw = $this->sanitize_true_false_string( (string) $raw_db_value_before );

			if ( $stored_raw !== $selection ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Raw options table write is intentional to reconcile filtered option values with stored values.
				$updated_rows = $wpdb->update(
					$wpdb->options,
					array( 'option_value' => $selection ),
					array( 'option_name' => $option_key ),
					array( '%s' ),
					array( '%s' )
				);

				if ( false === $updated_rows || 0 === (int) $updated_rows ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert fallback is required when the option row is missing while enforcing a normalized boolean value.
					$wpdb->insert(
						$wpdb->options,
						array(
							'option_name'  => $option_key,
							'option_value' => $selection,
							'autoload'     => 'yes',
						),
						array( '%s', '%s', '%s' )
					);
				}

				wp_cache_delete( $option_key, 'options' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads raw option_value after reconciliation to confirm persisted state in the same request.
				$raw_db_value_after = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
						$option_key
					)
				);
				$stored_raw = $this->sanitize_true_false_string( (string) $raw_db_value_after );
			}

			$stored_filtered_after = $this->sanitize_true_false_string( (string) get_option( $option_key, 'false' ) );

			if ( ( $stored_filtered_after !== $selection || $stored_raw !== $selection ) && function_exists( 'wppfm_write_log_file' ) ) {
				wppfm_write_log_file(
					sprintf(
						'Settings write mismatch for option "%1$s". Requested=%2$s, filtered_before=%3$s, raw_before=%4$s, filtered_after=%5$s, raw_after=%6$s.',
						$option_key,
						$selection,
						$stored_filtered,
						$this->sanitize_true_false_string( (string) $raw_db_value_before ),
						$stored_filtered_after,
						$stored_raw
					)
				);
			}

			// Return the raw stored value so UI reflects the actual database state.
			return $stored_raw;
		}
	}

	// End of WPPFM_Ajax_File_Class.

endif;

$wppfm_ajax_file_class = new WPPFM_Ajax_File();
