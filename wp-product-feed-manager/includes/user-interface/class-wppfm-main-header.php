<?php

/**
 * WP Product Feed Manager Main Header Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Main_Header' ) ) :

	class WPPFM_Main_Header {

		/**
		 * Generates HTML code for a default page header that contains the logo, navigation buttons, and hidden elements for a working-spinner, alert fields, message fields, and a progress bar.
		 *
		 * @param string $page
		 *
		 * @return void
		 */
		public function show( $page = '' ) {
			echo '<div class="wppfm-page-layout__header">';

			$this->logo();

			$this->navigation( $page );

			$this->working_spinner(); // Hidden container for the working spinner.

			echo '</div>';

			$this->alert_fields(); // Hidden container for the alert fields.

			$this->progress_bar();

			$this->message_fields(); // Hidden container for the message fields.
		}

		/**
		 * Generates the logo part of the header. The logo also contains a link to the main page of the plugin.
		 *
		 * @since 3.2.0.
		 * @return void
		 */
		private function logo() {
			echo
			'<div class="wppfm-page-layout__header__logo">
			   <a href="admin.php?page=wp-product-feed-manager" class="wppfm-logo">
			      <img src="' . esc_url( WPPFM_PLUGIN_URL ) . '/images/email-logo-wpmr-color.png" alt="WP Marketing Robot" class="wppfm-page-layout__header__nav-wrapper__logo">
			   </a>
			</div>';
		}

		/**
		 * Generates the navigation part of the header. The navigation contains links to the different pages of the plugin.
		 *
		 * @param string $page the page that is currently active.
		 *
		 * @since 3.2.0.
		 * @return void
		 * @noinspection HtmlUnknownTarget
		 *
		 */
		private function navigation( $page ) {
			echo '<div class="wppfm-page-layout__header__nav-wrapper">
					<div class="wppfm-nav-wrapper">
						<ul class="wppfm-nav__feed-selectors">
							<li class="wppfm-nav__selector" id="wppfm-feed-list-page-selector"><a href="admin.php?page=wp-product-feed-manager" class="wppfm-nav-link';
			if ( 'feed-list-page' === $page ) { echo ' wppfm-nav-link--selected'; }
			echo			'">' . esc_html__( 'Feed List', 'wp-product-feed-manager' ) . '</a></li>
							<li class="wppfm-nav__selector" id="wppfm-feed-editor-page-selector"><a href="admin.php?page=wppfm-feed-editor-page" class="wppfm-nav-link';
			if ( 'feed-editor-page' === $page ) { echo ' wppfm-nav-link--selected'; }
			echo			'">' . esc_html__( 'Feed Editor', 'wp-product-feed-manager' ) . '</a></li>
						</ul>
					<ul class="wppfm-nav__support-selectors">';

			if ( 'full' === WPPFM_PLUGIN_VERSION_ID ) { // only show the Channel Manager button in the full version
				echo '<li class="wppfm-nav__selector" id="wppfm-channel-manager-page-selector"><a href="admin.php?page=wppfm-channel-manager-page" class="wppfm-nav-link';
				if ( 'channel-manager-page' === $page ) { echo ' wppfm-nav-link--selected'; }
				echo '">' . esc_html__( 'Channel Manager', 'wp-product-feed-manager' ) . '</a></li>';
			}

			echo '<li class="wppfm-nav__selector" id="wppfm-settings-page-selector"><a href="admin.php?page=wppfm-settings-page" class="wppfm-nav-link';
			if ( 'settings-page' === $page ) { echo ' wppfm-nav-link--selected'; }
			echo '">' . esc_html__( 'Settings', 'wp-product-feed-manager' ) . '</a></li>
						<li class="wppfm-nav__selector" id="wppfm-support-page-selector"><a href="admin.php?page=wppfm-support-page" class="wppfm-nav-link';
			if ( 'support-page' === $page ) { echo ' wppfm-nav-link--selected'; }
			echo '">' . esc_html__( 'Support', 'wp-product-feed-manager' ) . '</a></li>
					</ul>
				</div>
			</div>';

		}

		/**
		 * Generates the spinner part of the header. The working spinner is used to indicate that a specific action is in progress.
		 *
		 * @since 3.2.0.
		 * @return void
		 */
		private function working_spinner() {
			echo
			'<div class="wppfm-working-spinner" id="wppfm-working-spinner" style="display:none;">
				<img id="img-spinner" src="' . esc_url( WPPFM_PLUGIN_URL ) . '/images/ajax-loader.gif" alt="Working" />
			</div>';
		}

		/**
		 * Generates the alert fields part of the header. The alert fields are used to display error, success and warning messages.
		 *
		 * @since 3.2.0.
		 * @return void
		 */
		private function alert_fields() {
			echo
			'<div class="wppfm-message-field notice notice-error" id="wppfm-error-message" style="display:none;"></div>
			 <div class="wppfm-message-field notice notice-info" id="wppfm-info-message" style="display:none;"></div>
			 <div class="wppfm-message-field notice notice-success" id="wppfm-success-message" style="display:none;"></div>
			 <div class="wppfm-message-field notice notice-warning" id="wppfm-warning-message" style="display:none;"></div>';
		}

		/**
		 * Generates a progress bar element.
		 *
		 * @since 3.7.0.
		 * @return void
		 */
		private function progress_bar() {
			echo
			'<div class="wppfm-progress-bar" id="wppfm-progress-bar" style="display:none;">
				<div class="wppfm-progress-bar__bar" id="wppfm-progress-bar__bar"></div>
			</div>';
		}

		/**
		 * Can be used to display a notice message like a promotion message.
		 *
		 * @since 3.5.0.
		 * @return void
		 */
		private function message_fields() {
			echo '';
			//'<div class="wppfm-message-field notice notice-promotion-message is-dismissible" id="wppfm-eastern-promotion-message">This is an eastern promotion message!</div>';
		}
	}

	// end of WPPFM_Main_Header class

endif;
