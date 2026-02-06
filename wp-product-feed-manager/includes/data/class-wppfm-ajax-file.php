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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'nextCategoryNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-next-category-nonce' ) ) {
				$file_class = new WPPFM_File();

				$channel_id      = filter_input( INPUT_POST, 'channelId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$requested_level = filter_input( INPUT_POST, 'requestedLevel', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$parent_category = filter_input( INPUT_POST, 'parentCategory', FILTER_CALLBACK, ['options' => [$this, 'sanitize_string_with_ampersand']] ); // Categories can contain an ampersand.
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'categoryListsNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-category-lists-nonce' ) ) {
				$file_class = new WPPFM_File();

				$channel_id             = filter_input( INPUT_POST, 'channelId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$main_categories_string = filter_input( INPUT_POST, 'mainCategories', FILTER_CALLBACK, ['options' => [$this, 'sanitize_string_with_ampersand']] ); // Categories can contain an ampersand.
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'deleteFeedNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-delete-feed-nonce', 'delete_feeds' ) ) {
				$file_name = filter_input( INPUT_POST, 'fileTitle', FILTER_CALLBACK, ['options' => [$this, 'sanitize_title_string']] );

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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'updateFeedFileNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-update-feed-file-nonce', 'edit_feeds' ) ) {

				// Fetch the data from $_POST.
				$feed_id                  = filter_input( INPUT_POST, 'feedId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$background_mode_disabled = get_option( 'wppfm_disabled_background_mode', 'false' );

				// @since: 2.40.0
				do_action( 'wppfm_feed_generation_message', $feed_id, 'Received the wppfm-ajax-update-feed-file post request call from javascript to initiate the feed generation process.' );

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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'logMessageNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-log-message-nonce' ) ) {
				// Fetch the data from $_POST.
				$message      = filter_input( INPUT_POST, 'messageList', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$file_name    = filter_input( INPUT_POST, 'fileName', FILTER_CALLBACK, ['options' => [$this, 'sanitize_title_string']] );
				$text_message = wp_strip_all_tags( $message );

				wppfm_write_log_file( $text_message, $file_name );
			} else {
				$this->show_not_allowed_error_message();
			}

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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'updateAutoFeedFixNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-auto-feed-fix-nonce', 'manage_options' ) ) {
				$selection = filter_input( INPUT_POST, 'fix_selection', FILTER_CALLBACK, ['options' => [$this, 'sanitize_true_false_string']] );
				update_option( 'wppfm_auto_feed_fix', $selection );

				echo esc_html( get_option( 'wppfm_auto_feed_fix' ) );
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'backgroundModeNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-background-mode-nonce', 'manage_options' ) ) {
				$selection = filter_input( INPUT_POST, 'mode_selection', FILTER_CALLBACK, ['options' => [$this, 'sanitize_true_false_string']] );
				update_option( 'wppfm_disabled_background_mode', $selection );

				echo esc_html( get_option( 'wppfm_disabled_background_mode' ) );
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'feedLoggerStatusNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-logger-status-nonce', 'manage_options' ) ) {
				$selection = filter_input( INPUT_POST, 'statusSelection', FILTER_CALLBACK, ['options' => [$this, 'sanitize_true_false_string']] );
				update_option( 'wppfm_process_logger_status', $selection );

				echo esc_html( get_option( 'wppfm_process_logger_status' ) );
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'showPINonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-show-pi-nonce', 'manage_options' ) ) {
				$selection = filter_input( INPUT_POST, 'showPiSelection', FILTER_CALLBACK, ['options' => [$this, 'sanitize_true_false_string']] );
				update_option( 'wppfm_show_product_identifiers', $selection );

				echo esc_html( get_option( 'wppfm_show_product_identifiers' ) );
			} else {
				$this->show_not_allowed_error_message();
			}

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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'manualChannelUpdateNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-manual-channel-update-nonce', 'manage_options' ) ) {
				$selection = filter_input( INPUT_POST, 'manualChannelUpdateSelection', FILTER_CALLBACK, ['options' => [$this, 'sanitize_true_false_string']] );
				update_option( 'wppfm_manual_channel_update', $selection );

				echo esc_html( get_option( 'wppfm_manual_channel_update' ) );
			} else {
				$this->show_not_allowed_error_message();
			}

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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'urlResolutionNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-use-full-url-resolution-nonce', 'manage_options' ) ) {
				$selection = filter_input( INPUT_POST, 'urlResolutionSelection', FILTER_CALLBACK, ['options' => [$this, 'sanitize_true_false_string']] );
				update_option( 'wppfm_use_full_url_resolution', $selection );

				echo esc_html( get_option( 'wppfm_use_full_url_resolution' ) );
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'omitPriceFiltersNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-omit-price-filters-nonce', 'manage_options' ) ) {
				$selection = filter_input( INPUT_POST, 'omitPriceFiltersSelection', FILTER_CALLBACK, ['options' => [$this, 'sanitize_true_false_string']] );
				update_option( 'wppfm_omit_price_filters', $selection );

				echo esc_html( get_option( 'wppfm_omit_price_filters' ) );
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'thirdPartyKeywordsNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-set-third-party-keywords-nonce', 'manage_options' ) ) {
				$new_keywords = filter_input( INPUT_POST, 'keywords', FILTER_CALLBACK, ['options' => [$this, 'sanitize_third_party_attributes_string']] );
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'noticeMailaddressNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-set-notice-mailaddress-nonce', 'manage_options' ) ) {
				$mailaddress = filter_input( INPUT_POST, 'mailaddress', FILTER_SANITIZE_EMAIL );
				update_option( 'wppfm_notice_mailaddress', $mailaddress );

				$saved_email = get_option( 'wppfm_notice_mailaddress' );
				$test_sent  = false;

				// Send test email when a valid address is configured.
				if ( ! empty( $saved_email ) && is_email( $saved_email ) && class_exists( 'WPPFM_Email' ) ) {
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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'reInitiateNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-reinitiate-nonce', 'update_plugins' ) ) {

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
			if ( $this->safe_ajax_call( filter_input( INPUT_POST, 'clearFeedNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm-ajax-clear-feed-nonce', 'manage_options' ) ) {

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
	}

	// End of WPPFM_Ajax_File_Class.

endif;

$myajax_file_class = new WPPFM_Ajax_File();
