<?php

/**
 * WPPFM Product Feed Google Analytics Selector Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Google_Analytics_Selector_Element' ) ) :

	class WPPFM_Google_Analytics_Selector_Element {

		/**
		 * Renders an element with a link to the Google Analytics information.
		 */
		public static function google_analytics_info_link_element() {
			echo '<div class="wppfm-google-analytics__sub-title-wrapper">
						<p class="wppfm-analytics-info-sub-title" id="wppfm-google-analytics-intro">' . esc_html__( 'Use the campaign url builder to track clicks in Google Analytics and see which campaigns and products refer traffic.', 'wp-product-feed-manager' ) . '</p>
						<p class="wppfm-analytics-info-sub-title" id="wppfm-google-analytics-info-link">' .
							esc_html__( 'Fill out the required fields (market with *). Empty fields will not be added to the url.', 'wp-product-feed-manager' ) .
							' <a href="https://support.google.com/analytics/answer/10917952?hl=en" target="_blank">' . esc_html__( 'Click here for more info about Google Analytics', 'wp-product-feed-manager' ) . '</a>
						</p>
					</div>';
		}

		/**
		 * Renders a Google Analytics on/off selector.
		 */
		public static function google_analytics_selector_element() {
			echo '<tr class="wppfm-main-feed-input-row" id="wppfm-add-google-analytics-row" style="display:none">
					<th id="wppfm-main-feed-input-label"><label
						for="wppfm-google-analytics">' . esc_html__( 'Google Analytics Tracking', 'wp-product-feed-manager' ) . '</label> :
					</th><td>';
			WPPFM_Feed_Form_Control::google_analytics_selector();
			echo '  ( ' . esc_html__( 'settings at the bottom of this page', 'wp-product-feed-manager') . ' )</td></tr>';
		}

		/**
		 * Renders the Google UTM Source element.
		 */
		public static function google_utm_source_element() {
			echo '<div class="wppfm-google-analytics-element" id="wppfm-google-utm-source-row">
					<div id="wppfm-main-feed-input-label"><label
						for="wppfm-google-utm-source">*' . esc_html__( 'Campaign Source', 'wp-product-feed-manager' ) . '</label> :
					</div>
					<div><input type="text" name="wppfm-google-utm-source" value="Google Shopping" id="wppfm-google-utm-source" /></div>
					<div>' . esc_html__( 'e.g. google shopping, newsletter', 'wp-product-feed-manager' ) . '</div></div>';
		}

		/**
		 * Renders the Google UTM Medium element.
		 */
		public static function google_utm_medium_element() {
			echo '<div class="wppfm-google-analytics-element" id="wppfm-google-utm-medium-row">
					<div id="wppfm-main-feed-input-label"><label
						for="wppfm-google-utm-medium">*' . esc_html__( 'Campaign Medium', 'wp-product-feed-manager' ) . '</label> :
					</div>
					<div><input type="text" name="wppfm-google-utm-medium" value="CPC" id="wppfm-google-utm-medium" /></div>
					<div>' . esc_html__( 'e.g. cpc, banner, email', 'wp-product-feed-manager' ) . '</div></div>';
		}

		/**
		 * Renders the Google UTM Campaign element.
		 */
		public static function google_utm_campaign_element() {
			echo '<div class="wppfm-google-analytics-element" id="wppfm-google-utm-campaign-row">
					<div id="wppfm-main-feed-input-label"><label
						for="wppfm-google-utm-campaign">' . esc_html__( 'Campaign Name', 'wp-product-feed-manager' ) . '</label> :
					</div>
					<div><input type="text" name="wppfm-google-utm-campaign" id="wppfm-google-utm-campaign" /></div>
					<div>' . esc_html__( 'e.g. feed title, spring sale', 'wp-product-feed-manager' ) . '</div></div>';
		}

		/**
		 * Renders the Google UTM Term element.
		 */
		public static function google_utm_term_element() {
			echo '<div class="wppfm-google-analytics-element" id="wppfm-google-utm-term-row">
					<div id="wppfm-main-feed-input-label"><label
						for="wppfm-google-utm-term">' . esc_html__( 'Campaign Term', 'wp-product-feed-manager' ) . '</label> :
					</div>
					<div><input type="text" name="wppfm-google-utm-term" id="wppfm-google-utm-term" /></div>
					<div>' . esc_html__( 'identify the paid keywords', 'wp-product-feed-manager' ) . '</div></div>';
		}

		/**
		 * Renders the Google UTM Content element.
		 */
		public static function google_utm_content_element() {
			echo '<div class="wppfm-google-analytics-element" id="wppfm-google-utm-content-row">
					<div id="wppfm-main-feed-input-label"><label
						for="wppfm-google-utm-content">' . esc_html__( 'Campaign Content', 'wp-product-feed-manager' ) . '</label> :
					</div>
					<div><input type="text" name="wppfm-google-utm-content" id="wppfm-google-utm-content" /></div>
					<div>' . esc_html__( 'use to differentiate ads', 'wp-product-feed-manager' ) . '</div></div>';
		}
	}

	// end of WPPFM_Google_Analytics_Selector_Element class

endif;
