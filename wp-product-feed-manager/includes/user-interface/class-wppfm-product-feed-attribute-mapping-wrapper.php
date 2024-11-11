<?php

/**
 * WPPFM Product Feed Attribute Mapping Wrapper Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Product_Feed_Attribute_Mapping_Wrapper' ) ) :

	class WPPFM_Product_Feed_Attribute_Mapping_Wrapper extends WPPFM_Attribute_Mapping_Wrapper {

		/**
		 * Renders the product feed attribute mapping table.
		 */
		public function display() {
			// Start the section code.
			$this->attribute_mapping_wrapper_table_start();

			// Add the header.
			$this->attribute_mapping_wrapper_table_header();

			echo '<div class="wppfm-feed-editor-form-section__body">';

			WPPFM_Attribute_Selector_Element::required_fields();

			WPPFM_Attribute_Selector_Element::highly_recommended_fields();

			WPPFM_Attribute_Selector_Element::recommended_fields();

			WPPFM_Attribute_Selector_Element::optional_fields();

			WPPFM_Attribute_Selector_Element::custom_fields();

			echo '</div>';

			// Close the section.
			$this->attribute_mapping_wrapper_table_end();
		}
	}

	// end of WPPFM_Product_Feed_Attribute_Mapping_Wrapper class

endif;
