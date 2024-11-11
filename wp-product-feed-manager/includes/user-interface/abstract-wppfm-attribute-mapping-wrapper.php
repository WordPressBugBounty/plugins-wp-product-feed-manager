<?php

/**
 * WPPFM Attribute Mapping Wrapper Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Attribute_Mapping_Wrapper' ) ) :

	abstract class WPPFM_Attribute_Mapping_Wrapper {

		abstract public function display();

		/**
		 * Renders the attribute mapping wrapper table element.
		 *
		 * @param string $display display style (default none).
		 */
		protected function attribute_mapping_wrapper_table_start( $display = 'none' ) {
			echo '<section class="wppfm-feed-editor-section wppfm-attribute-mapping-wrapper" id="wppfm-attribute-map" style="display:' . esc_attr( $display ) . ';">';
		}

		/**
		 * Renders the attribute mapping wrapper table header element.
		 */
		protected function attribute_mapping_wrapper_table_header() {
			echo '<div class="wppfm-feed-editor-section__header" id="wppfm-feed-editor-attribute-mapping-header"><h3>' . esc_html__( 'Attribute Mapping', 'wp-product-feed-manager' ) . ':</h3></div>';
		}

		/**
		 * Renders the attribute mapping wrapper section end element.
		 */
		protected function attribute_mapping_wrapper_table_end() {
			echo '</section>';
		}
	}

	// end of WPPFM_Attribute_Mapping_Wrapper class

endif;
