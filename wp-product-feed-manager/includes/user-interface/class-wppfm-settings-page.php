<?php

/**
 * WPPFM Product Feed Manager Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Settings_Page' ) ) :

	/**
	 * Settings Form Class
	 *
	 * @since        3.2.0
	 */
	class WPPFM_Settings_Page {

		/**
		 * Generates the main part of the Settings page
		 *
		 * @since 3.2.0
		 */
		public function display() {
			$this->add_data_storage();
			$this->settings_page();
		}

		/**
		 * Renders the Settings page.
		 */
		private function settings_page() {
			echo '<div class="wppfm-page__title wppfm-center-page__title" id="wppfm-settings-title"><h1>' . esc_html__( 'Feed Manager - Settings', 'wp-product-feed-manager' ) . '</h1></div>
				</div>
				<div class="wppfm-page-layout__main" id="wppfm-feed-manager-settings-table">
				<div class="wppfm-settings-wrapper wppfm-auto-center-page-wrapper">
				<table class="form-table"><tbody>';
				$this->settings_content();
			echo '</tbody></table>
				</div></div>';
		}

		/**
		 * Renders the content of the Settings page.
		 *
		 * @since 1.5.0
		 * @since 1.7.0 Added the backups table.
		 * @since 1.8.0 Added the third party attributes text field.
		 * @since 1.9.0 Added the Re-initialize button.
		 * @since 2.3.0 Added the Notice option.
		 * @since 2.10.0 Added the show product identifiers option.
		 * @since 2.15.0 Added the wpml full resolution url option.
		 * @since 3.2.0 Moved the Clear feed process button two steps up to prevent unintended clicking of the Re-initiate plugin button.
		 */
		private function settings_content() {
			$auto_fix_feed_option            = get_option( 'wppfm_auto_feed_fix', false );
			$auto_feed_fix_checked           = true === $auto_fix_feed_option || 'true' === $auto_fix_feed_option ? ' checked ' : '';
			$background_processing_option    = get_option( 'wppfm_disabled_background_mode', 'false' );
			$background_processing_unchecked = true === $background_processing_option || 'true' === $background_processing_option ? ' checked ' : '';
			$process_logging_option          = get_option( 'wppfm_process_logger_status', 'false' );
			$process_logging_unchecked       = true === $process_logging_option || 'true' === $process_logging_option ? ' checked ' : '';
			$product_identifiers_option      = get_option( 'wppfm_show_product_identifiers', 'false' );
			$show_product_identifiers        = true === $product_identifiers_option || 'true' === $product_identifiers_option ? ' checked ' : '';
			$manual_channel_update_option    = get_option( 'wppfm_manual_channel_update', 'false' );
			$manual_channel_update           = true === $manual_channel_update_option || 'true' === $manual_channel_update_option ? ' checked ' : '';
			$use_full_resolution_option      = get_option( 'wppfm_use_full_url_resolution', 'false' );
			$wpml_use_full_resolution_urls   = true === $use_full_resolution_option || 'true' === $use_full_resolution_option ? ' checked ' : '';
			$omit_price_filters_option       = get_option( 'wppfm_omit_price_filters', 'false' );
			$omit_price_filters              = true === $omit_price_filters_option || 'true' === $omit_price_filters_option ? ' checked ' : '';

			$third_party_attribute_keywords = get_option( 'wppfm_third_party_attribute_keywords', '%wpmr%,%cpf%,%unit%,%bto%,%yoast%' );
			$notice_mailaddress             = get_option( 'wppfm_notice_mailaddress' ) ? get_option( 'wppfm_notice_mailaddress' ) : get_bloginfo( 'admin_email' );

			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Auto feed fix', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-checkbox">
				<fieldset>
				<input name="wppfm-auto-feed-fix-mode" id="wppfm-auto-feed-fix-mode" type="checkbox" class="" value="1"' . esc_attr( $auto_feed_fix_checked ) . '> 
				<label for="wppfm-auto-feed-fix-mode">'
				. esc_html__( 'Automatically try regenerating feeds that are failed (default off).', 'wp-product-feed-manager' ) . '</label></fieldset>
				<p><i>' . esc_html__( 'Leaving this option on can put extra strain on your server when feeds keep failing.', 'wp-product-feed-manager' ) . '</p></i>
				</td></tr>';

			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Disable background processing', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-checkbox">
				<fieldset>
				<input name="wppfm-background-processing-mode" id="wppfm-background-processing-mode" type="checkbox" class="" value="1"' . esc_attr( $background_processing_unchecked ) . '> 
				<label for="wppfm-background-processing-mode">'
				. esc_html__( 'Process feeds directly instead of in the background (default off). Try this option when feeds keep getting stuck in processing. ', 'wp-product-feed-manager' ) . '</label>
				<p><i>' . esc_html__( 'WARNING: When this option is selected the system can only update one feed at a time. Make sure to de-conflict your feeds auto-update schedules to prevent more than one feed auto-updates at a time.', 'wp-product-feed-manager' ) . '</i></p></fieldset>
				</td></tr>';

			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Feed process logger', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-checkbox">
				<fieldset>
				<input name="wppfm-process-logging-mode" id="wppfm-process-logging-mode" type="checkbox" class="" value="1"' . esc_attr( $process_logging_unchecked ) . '> 
				<label for="wppfm-process-logging-mode">'
				. esc_html__( 'When switched on, generates an extensive log of the feed process (default off).', 'wp-product-feed-manager' ) . '</label>
				<p><i>' . esc_html__( 'Switch this option only on request of the help desk. ', 'wp-product-feed-manager' ) . '</i></p></fieldset>
				</td></tr>';

			// @since 2.10.0.
			// @since 3.14.0 - Only allows the product identifiers option for the WP Marketing Robot plugin.
			if ( 'wpmarketingrobot' === WPPFM_PLUGIN_DISTRIBUTOR ) {
				echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Show product identifiers', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-checkbox">
				<fieldset>
				<input name="wppfm-product-identifiers-on" id="wppfm-product-identifiers" type="checkbox" class="" value="1"' . esc_attr( $show_product_identifiers ) . '> 
				<label for="wppfm-product-identifiers">'
					 . esc_html__( 'When switched on, adds Brand, GTIN and MPN product identifiers to the products (default off).', 'wp-product-feed-manager' ) . '</label>
				<p><i>' . esc_html__( 'This option will add product identifier input fields to the Inventory card of your products. The MPN identifier is also added to the product variations.',
						'wp-product-feed-manager' ) . '</i></p></fieldset>
				</td></tr>';
			}

			// @since 2.15.0.
			if ( has_filter( 'wppfm_get_wpml_permalink' ) )
			{
				echo '<tr vertical-align="top" class="wppfm-setting-selector">
					<th scope="row" class="titledesc">' . esc_html__('WPML: Use full resolution URLs', 'wp-product-feed-manager') . '</th>
					<td class="forminp forminp-checkbox">
					<fieldset>
					<input name="wppfm-wpml-use-full-resolution-urls" id="wppfm-wpml-use-full-resolution-urls" type="checkbox" class="" value="0"' . esc_attr( $wpml_use_full_resolution_urls ) . '> 
					<label for="wppfm-wpml-use-full-resolution-urls">'
					. esc_html__('Enables full conversion of hard-coded URLs (default off).', 'wp-product-feed-manager') . '</label>
					<p><i>' . esc_html__('Use this option if you\'re using WPML and are getting incorrect URLs in your feed. This option will slightly increase the load on the database when processing a feed.', 'wp-product-feed-manager') . '</i></p></fieldset>
					</td></tr>';
			}

			// @since 3.12.0.
			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Omit price filters', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-checkbox">
				<fieldset>
				<input name="wppfm-omit-price-filters" id="wppfm-omit-price-filters" type="checkbox" class="" value="0"' . esc_attr( $omit_price_filters ) . '> 
				<label for="wppfm-omit-price-filters">'
				 . esc_html__( 'Omits filters that are used on the prices in your feeds (default off).', 'wp-product-feed-manager' ) . '</label>
				<p><i>' . esc_html__( 'Enable this option to prevent third-party plugins or custom filters from altering the prices in your product feeds. Use this if you notice incorrect prices in your feed.', 'wp-product-feed-manager' ) . '</i></p></fieldset>
				</td></tr>';

			// @since 3.7.0.
			if ( 'full' === WPPFM_PLUGIN_VERSION_ID ) {
				echo '<tr vertical-align="top" class="wppfm-setting-selector">
					<th scope="row" class="titledesc">' . esc_html__( 'Manual channel update', 'wp-product-feed-manager' ) . '</th>
					<td class="forminp forminp-checkbox">
					<fieldset>
					<input name="wppfm-manual-channel-update" id="wppfm-manual-channel-update" type="checkbox" class="" value="1"' . esc_attr( $manual_channel_update ) . '> 
					<label for="wppfm-manual-channel-update">'
					. esc_html__( 'When switched on, you need to manually update channels (default off).', 'wp-product-feed-manager' ) . '</label>
					<p><i>' . esc_html__( 'By default your channels are updated automatically as soon as an update is available. This option allows your to switch off the automatic channel updates and keep your channels manually up to date.', 'wp-product-feed-manager' ) . '</i></p></fieldset>
					</td></tr>';
			}

			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Clear feed process', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-checkbox">
				<fieldset>
				<div class="wppfm-inline-button-wrapper">
				<a href="#" class="wppfm-button wppfm-blue-button" id="wppfm-clear-feed-process-button">' . esc_html__( 'Clear Feed Process', 'wp-product-feed-manager' ) . '</a>
				</div>
				<label for="clear">'
				. esc_html__( 'Use this option when feeds get stuck processing - does not delete your current feeds or settings.', 'wp-product-feed-manager' ) . '</label></fieldset>
				</td></tr>';

			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Third party attributes', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-input">
				<fieldset>
				<input name="wppfm-third-party-attr-keys" id="wppfm-third-party-attr-keys" type="text" class="wppfm-wide-text-input-field" value="' . esc_html( preg_replace( '/[^a-zA-Z0-9 %,_\-]/', '',$third_party_attribute_keywords ) ) . '"> 
				<label for="wppfm-third-party-attr-keys">'
				. esc_html__( 'Enter comma separated keywords and wildcards to use third party attributes.', 'wp-product-feed-manager' ) . '</label></fieldset>
				<p><i>' . esc_html__('Use specific wildcards. Do not use to broad wildcards like %_% because that will include default WooCommerce attributes and can sometimes result in incorrect feed outputs.', 'wp-product-feed-manager') . '</i></p></fieldset>
				</td></tr>';

			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Notice recipient', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-input">
				<fieldset>
				<input name="wppfm-notice-mailaddress" id="wppfm-notice-mailaddress" type="email" class="wppfm-wide-text-input-field" value="' . esc_html( sanitize_email( $notice_mailaddress ) ) . '"> 
				<label for="wppfm-notice-mailaddress">'
				. esc_html__( 'Email address of the feed manager.', 'wp-product-feed-manager' ) . '</label></fieldset>
				<p><i>' . esc_html__('Enter the email address of the person you want to be notified when a feed fails during an automatic feed update. This option requires an SMTP server for WordPress to be installed on your server. If no emails are received, consider using an SMTP plugin to improve email delivery.', 'wp-product-feed-manager') . '</i></p></fieldset>
				</td></tr>';

			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Re-initialize', 'wp-product-feed-manager' ) . '</th>
				<td class="forminp forminp-checkbox">
				<fieldset>
				<div class="wppfm-inline-button-wrapper">
				<a href="#" class="wppfm-button wppfm-orange-button" id="wppfm-reinitiate-plugin-button">' . esc_html__( 'Re-initiate Plugin', 'wp-product-feed-manager' ) . '</a>
				</div>
				<label for="reinitiate">'
				. esc_html__( 'Resets and updates the plugin.', 'wp-product-feed-manager' ) . '</label></fieldset>
				<p><i>' . esc_html__('Updates and cleans the tables if required, re-initiates the cron events and resets the stored license - does not delete your current feeds or settings. You need to re-enter your license after this action.', 'wp-product-feed-manager') . '</i></p></fieldset>
				</td></tr>';

			echo '<tr vertical-align="top" class="wppfm-setting-selector">
				<th scope="row" class="titledesc">' . esc_html__( 'Backups', 'wp-product-feed-manager' ) . '</th>
				<td id="wppfm-backups-table-holder">';

			echo '<table id="wppfm-backups" class="wppfm-table wppfm-smallfat">
				<thead>
				<tr><th class="wppfm-manage-column wppfm-column-name">' . esc_html__( 'File Name', 'wp-product-feed-manager' ) . '</th>
				<th class="wppfm-manage-column wppfm-column-name">' . esc_html__( 'Backup Date', 'wp-product-feed-manager' ) . '</th>
				<th class="wppfm-manage-column wppfm-column-name" id="wppfm-backup-action-column">' . esc_html__( 'Actions', 'wp-product-feed-manager' ) . '</th></tr>
				</thead>
				<tbody id="wppfm-backups-list"></tbody>
				</table>';

			echo '<div class="wppfm-inline-button-wrapper">
				<a href="#" class="wppfm-button wppfm-blue-button" id="wppfm-prepare-backup"><i class="wppfm-button-icon wppfm-icon-plus"></i>' . esc_html__( 'Add New Backup', 'wp-product-feed-manager' ) . '</a>
				</div>
				</td></tr>
				<tr style="display:none;" id="wppfm-backup-wrapper"><th>&nbsp</th><td>
				<input type="text" class="regular-text" id="wppfm-backup-file-name" placeholder="Enter a file name">
				<span class="button-secondary" id="wppfm-make-backup-button" disabled>' . esc_html__( 'Backup current feeds', 'wp-product-feed-manager' ) . '</span>
				<span class="button-secondary" id="wppfm-cancel-backup-button">' . esc_html__( 'Cancel backup', 'wp-product-feed-manager' ) . '</span>';

			echo '</td></tr>';
		}

		/**
		 * Stores data in the DOM for the Feed Manager Settings page
		 */
		private function add_data_storage() {
			echo
			'<div id="wppfm-settings-page-data-storage" class="wppfm-data-storage-element" 
				data-wppfm-wp-uploads-url="' . esc_url( WPPFM_UPLOADS_URL ) . '"
				data-wppfm-plugin-version-id="' . esc_attr( WPPFM_PLUGIN_VERSION_ID ) . '" 
				data-wppfm-plugin-version-nr="' . esc_attr( WPPFM_VERSION_NUM ) . '"
				data-wppfm-plugin-distributor="' . esc_attr( WPPFM_PLUGIN_DISTRIBUTOR ) . '">
			</div>';
		}
	}

	// end of WPPFM_Settings_Page class

endif;
