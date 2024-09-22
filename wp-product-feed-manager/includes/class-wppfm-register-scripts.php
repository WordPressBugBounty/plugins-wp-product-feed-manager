<?php

/**
 * WPPFM Register Scripts Class.
 *
 * @package WP Product Feed Manager/Classes
 * @version 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Register_Scripts' ) ) :

	/**
	 * Register Scripts Class
	 */
	class WPPFM_Register_Scripts {

		// @private storage of scripts version
		private $_version_stamp;
		// @private register minified scripts
		private $_js_min;

		public function __construct() {
			$premium_version_nr   = 'free' === WPPFM_PLUGIN_VERSION_ID ? 'fr-' : 'pr-'; // prefix for version stamp depending on premium or free version
			$this->_version_stamp = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $premium_version_nr . WPPFM_VERSION_NUM;
			$this->_js_min        = defined( 'WP_DEBUG' ) && WP_DEBUG ? '' : '.min';
			$page_param           = wppfm_get_url_parameter( 'page' );

			// add hooks for registering the scripts that are relevant for all plugin pages
			add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_required_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_required_nonce' ) );

			// load the correct hooks for the specific page.
			switch( $page_param ) {
				case 'wppfm-feed-editor-page':
					add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_required_feed_editor_page_scripts' ) );
					break;

				case 'wppfm-channel-manager-page':
					break;

				case 'wppfm-settings-page':
					add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_required_settings_page_scripts' ) );
					add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_required_settings_page_nonce' ) );
					break;

				case 'wppfm-support-page':
					add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_required_support_page_scripts' ) );
					add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_required_support_page_nonce' ) );
					break;
			}

			if ( 'full' === WPPFM_PLUGIN_VERSION_ID ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_full_version_channels' ) );
			} else {
				add_action( 'admin_enqueue_scripts', array( $this, 'wppfm_register_google_only_version_channels' ) );
			}
		}

		/**
		 * Registers all required JavaScripts for the feed manager pages.
		 */
		public function wppfm_register_required_scripts() {
			// enqueue notice handling script
			wp_enqueue_script( 'wppfm_message-handling-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_msg_events' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_notice-handling-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm-promotion-notice' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );

			// do not load the other scripts unless a wppfm page is on
			if ( ! wppfm_on_own_main_plugin_page() ) {
				return;
			}

			wp_register_style( 'wp-product-feed-manager-main', WPPFM_PLUGIN_URL . '/css/wppfm-main' . $this->_js_min . '.css', '', $this->_version_stamp, 'screen' );
			wp_enqueue_style( 'wp-product-feed-manager-main' );

			// embed the JavaScript file that makes the Ajax requests
			wp_enqueue_script( 'wppfm_business-logic-script', WPPFM_PLUGIN_URL . '/includes/application/js/wppfm_logic' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_form-support-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_support' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_form-support-events-listener-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_support-form-events' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_verify-inputs-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_verify-inputs' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-handling-script', WPPFM_PLUGIN_URL . '/includes/application/js/wppfm_feedhandling' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-html', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_feed-html' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-list-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_feed-list' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-meta-script', WPPFM_PLUGIN_URL . '/includes/application/js/wppfm_object-attribute-meta' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-objects-script', WPPFM_PLUGIN_URL . '/includes/application/js/wppfm_object-feed' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_general-functions-script', WPPFM_PLUGIN_URL . '/includes/application/js/wppfm_general-functions' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_object-handling-script', WPPFM_PLUGIN_URL . '/includes/data/js/wppfm_metadatahandling' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_data-handling-script', WPPFM_PLUGIN_URL . '/includes/data/js/wppfm_ajaxdatahandling' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-queue-string-script', WPPFM_PLUGIN_URL . '/includes/data/js/wppfm_feed-queue-string' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
		}

		/**
		 * Generate the required nonce's.
		 */
		public function wppfm_register_required_nonce() {
			// make a unique nonce for all Ajax requests
			wp_localize_script(
				'wppfm_data-handling-script',
				'myAjaxNonces',
				array(
					// URL to wp-admin/admin-ajax.php to process the request
					'ajaxurl'                        => admin_url( 'admin-ajax.php' ),
					// generate the nonce's
					'categoryListsNonce'             => wp_create_nonce( 'myajax-category-lists-nonce' ),
					'deleteFeedNonce'                => wp_create_nonce( 'myajax-delete-feed-nonce' ),
					'feedDataNonce'                  => wp_create_nonce( 'myajax-feed-data-nonce' ),
					'feedStatusNonce'                => wp_create_nonce( 'myajax-feed-status-nonce' ),
					'inputFieldsNonce'               => wp_create_nonce( 'myajax-input-fields-nonce' ),
					'inputFeedFiltersNonce'          => wp_create_nonce( 'myajax-feed-filters-nonce' ),
					'logMessageNonce'                => wp_create_nonce( 'myajax-log-message-nonce' ),
					'nextCategoryNonce'              => wp_create_nonce( 'myajax-next-category-nonce' ),
					'outputFieldsNonce'              => wp_create_nonce( 'myajax-output-fields-nonce' ),
					'postFeedsListNonce'             => wp_create_nonce( 'myajax-post-feeds-list-nonce' ),
					'switchFeedStatusNonce'          => wp_create_nonce( 'myajax-switch-feed-status-nonce' ),
					'duplicateFeedNonce'             => wp_create_nonce( 'myajax-duplicate-existing-feed-nonce' ),
					'updateFeedDataNonce'            => wp_create_nonce( 'myajax-update-feed-data-nonce' ),
					'updateAutoFeedFixNonce'         => wp_create_nonce( 'myajax-set-auto-feed-fix-nonce' ),
					'updateFeedFileNonce'            => wp_create_nonce( 'myajax-update-feed-file-nonce' ),
					'nextFeedInQueueNonce'           => wp_create_nonce( 'myajax-next-feed-in-queue-nonce' ),
					'noticeDismissionNonce'          => wp_create_nonce( 'myajax-notice-dismission-nonce' ),
				)
			);
		}

		/**
		 * Registers all required JavaScripts for the feed manager Feed Editor page.
		 */
		public function wppfm_register_required_feed_editor_page_scripts() {
			wp_enqueue_script( 'wppfm_data-script', WPPFM_PLUGIN_URL . '/includes/data/js/wppfm_data' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_event-listener-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_feed-form-events' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-settings-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_feed-form' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-select2-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_select2-selector-handling' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_local-product-inventory-functions', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_local-product-inventory-feed-functions' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_vehicle-ads-functions', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_vehicle-ads-feed-functions' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_dynamic-search-ads-functions', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_dynamic-search-ads-feed-functions' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_local-product-functions', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_local-product-feed-functions' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_dynamic-remarketing-functions', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_dynamic-remarketing-feed-functions' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_feed-process-monitoring-script', WPPFM_PLUGIN_URL . '/includes/application/js/wppfm_feed-process-monitoring' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
		}

		/**
		 * Registers all required JavaScripts for the feed manager Settings page.
		 */
		public function wppfm_register_required_settings_page_scripts() {
			// enqueue notice handling script
			wp_enqueue_script( 'wppfm_message-handling-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_msg_events' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );

			wp_enqueue_script( 'wppfm_backup-list-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_backup-list' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_data-handling-script', WPPFM_PLUGIN_URL . '/includes/data/js/wppfm_ajaxdatahandling' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_setting-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_setting-form' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_event-listener-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_feed-form-events' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_form-support-script', WPPFM_PLUGIN_URL . '/includes/user-interface/js/wppfm_support' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );
		}

		/**
		 * Generate the nonce's for the Settings page.
		 */
		public function wppfm_register_required_settings_page_nonce() {
			// make a unique nonce for all Ajax requests
			wp_localize_script(
				'wppfm_data-handling-script',
				'myAjaxNonces',
				array(
					// URL to wp-admin/admin-ajax.php to process the request
					'ajaxurl'                      => admin_url( 'admin-ajax.php' ),
					// generate the required nonce's
					'setAutoFeedFixNonce'          => wp_create_nonce( 'myajax-auto-feed-fix-nonce' ),
					'setBackgroundModeNonce'       => wp_create_nonce( 'myajax-background-mode-nonce' ),
					'setFeedLoggerStatusNonce'     => wp_create_nonce( 'myajax-logger-status-nonce' ),
					'setShowPINonce'               => wp_create_nonce( 'myajax-show-pi-nonce' ),
					'setManualChannelUpdateNonce'  => wp_create_nonce( 'myajax-manual-channel-update-nonce' ),
					'setUseFullResolutionNonce'    => wp_create_nonce( 'myajax-use-full-url-resolution-nonce' ),
					'setThirdPartyKeywordsNonce'   => wp_create_nonce( 'myajax-set-third-party-keywords-nonce' ),
					'setNoticeMailaddressNonce'    => wp_create_nonce( 'myajax-set-notice-mailaddress-nonce' ),
					'setBatchProcessingLimitNonce' => wp_create_nonce( 'myajax-set-batch-processing-limit-nonce' ),
					'backupNonce'                  => wp_create_nonce( 'myajax-backup-nonce' ),
					'deleteBackupNonce'            => wp_create_nonce( 'myajax-delete-backup-nonce' ),
					'restoreBackupNonce'           => wp_create_nonce( 'myajax-restore-backup-nonce' ),
					'duplicateBackupNonce'         => wp_create_nonce( 'myajax-duplicate-backup-nonce' ),
					'postBackupListNonce'          => wp_create_nonce( 'myajax-backups-list-nonce' ),
					'postSetupOptionsNonce'        => wp_create_nonce( 'myajax-setting-options-nonce' ),
					'setClearFeedProcessNonce'     => wp_create_nonce( 'myajax-clear-feed-nonce' ),
					'setReInitiateNonce'           => wp_create_nonce( 'myajax-reinitiate-nonce' ),
				)
			);
		}

		/**
		 * Registers all required JavaScripts for the feed manager Settings page.
		 */
		public function wppfm_register_required_support_page_scripts() {
			// enqueue notice handling script
		}

		/**
		 * Generate the nonce's for the Settings page.
		 */
		public function wppfm_register_required_support_page_nonce() {
			// make a unique nonce for all Ajax requests
			wp_localize_script(
				'wppfm_data-handling-script',
				'myAjaxNonces',
				array()
			);
		}

		public function wppfm_register_full_version_channels() {
			if ( ! wppfm_on_own_main_plugin_page() ) {
				return;
			}

			$data               = new WPPFM_Data;
			$installed_channels = $data->get_channels();

			wp_enqueue_script( 'wppfm_channel-functions-script', WPPFM_PLUGIN_URL . '/includes/application/js/wppfm_channel-functions' . $this->_js_min . '.js', array( 'jquery' ), $this->_version_stamp, true );

			foreach ( $installed_channels as $channel ) {
				wp_enqueue_script( 'wppfm_' . $channel['short'] . '-source-script', WPPFM_UPLOADS_URL . '/wppfm-channels/' . $channel['short'] . '/wppfm_' . $channel['short'] . '-source.js', array( 'jquery' ), $this->_version_stamp, true );
			}
		}

		public function wppfm_register_google_only_version_channels() {
			if ( ! wppfm_on_own_main_plugin_page() ) {
				return;
			}

			wp_enqueue_script('wppfm_channel-functions-script', WPPFM_PLUGIN_URL . '/includes/application/js/wppfm_channel-functions.js', array( 'jquery' ), $this->_version_stamp, true );
			wp_enqueue_script( 'wppfm_google-source-script', WPPFM_PLUGIN_URL . '/includes/application/google/wppfm_google-source.js', array( 'jquery' ), $this->_version_stamp, true );
		}
	}

	// End of WPPFM_Register_Scripts class

endif;

$my_ajax_registration_class = new WPPFM_Register_Scripts();
