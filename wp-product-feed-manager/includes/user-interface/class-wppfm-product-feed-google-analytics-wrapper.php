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
		 * Display the product feed Google Analytics table.
		 */
		public function display() {
			$html = '<section class="wppfm-google-analytics-wrapper" style="display: none">';

			$html .= '<div class="wppfm-google-analytics-header">';

			// Add the header.
			$html .= '<div class="wppfm-feed-editor-section__header" id="wppfm-feed-editor-google-analytics-header"><h3>' . __( 'Google Campaign URL Builder', 'wp-product-feed-manager' ) . ':</h3></div>';

			$html .= '</div>';

			// Start the section code.
			$html .= '<div class="wppfm-feed-editor-form-section wppfm-google-analytics-input-wrapper" id="wppfm-google-analytics-map" style="display: none"><div class="wppfm-feed-editor-google-analytics-wrapper">';

			// Link to more information about the Google Analytics settings
			$html .= WPPFM_Google_Analytics_Selector_Element::google_analytics_info_link_element();

			// utm_source (default google)
			$html .= WPPFM_Google_Analytics_Selector_Element::google_utm_source_element();

			// utm_medium (default cpc)
			$html .= WPPFM_Google_Analytics_Selector_Element::google_utm_medium_element();

			// utm_campaign
			$html .= WPPFM_Google_Analytics_Selector_Element::google_utm_campaign_element();

			// utm_term (should contain the Product ID. Maybe just leave it out of the selectors but automatically add it the url)
			$html .= WPPFM_Google_Analytics_Selector_Element::google_utm_term_element();

			// utm_content
			$html .= WPPFM_Google_Analytics_Selector_Element::google_utm_content_element();

			// Close the section.
			$html .= '</div></div>';

			$html .= '</section>';

			return $html;
		}
	}

	// end of WPPFM_Product_Feed_Google_Analytics_Wrapper class

endif;
