<?php

/**
 * WPPFM Product Feed Google Analytics Wrapper Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 3.7.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Product_Feed_Google_Analytics_Wrapper' ) ) :

	class WPPFM_Product_Feed_Google_Analytics_Wrapper {

		/**
		 * Renders the product feed Google Analytics table.
		 */
		public function display() {
			echo '<section class="wppfm-google-analytics-wrapper" style="display: none">
				<div class="wppfm-google-analytics-header">
				<div class="wppfm-feed-editor-section__header" id="wppfm-feed-editor-google-analytics-header"><h3>' . esc_html__( 'Google Campaign URL Builder', 'wp-product-feed-manager' ) . ':</h3></div>
				</div>
				<div class="wppfm-feed-editor-form-section wppfm-google-analytics-input-wrapper" id="wppfm-google-analytics-map" style="display: none"><div class="wppfm-feed-editor-google-analytics-wrapper">';

			// Link to more information about the Google Analytics settings
			WPPFM_Google_Analytics_Selector_Element::google_analytics_info_link_element();

			// utm_source (default google)
			WPPFM_Google_Analytics_Selector_Element::google_utm_source_element();

			// utm_medium (default cpc)
			WPPFM_Google_Analytics_Selector_Element::google_utm_medium_element();

			// utm_campaign
			WPPFM_Google_Analytics_Selector_Element::google_utm_campaign_element();

			// utm_term (should contain the Product ID. Maybe just leave it out of the selectors but automatically add it the url)
			WPPFM_Google_Analytics_Selector_Element::google_utm_term_element();

			// utm_content
			WPPFM_Google_Analytics_Selector_Element::google_utm_content_element();

			// Close the section.
			echo '</div></div></section>';
		}
	}

	// end of WPPFM_Product_Feed_Google_Analytics_Wrapper class

endif;
