<?php

/**
 * WPPRFM Add Review Feed Editor Page Class.
 *
 * @package WP Product Review Feed Manager/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPRFM_Add_Review_Feed_Editor_Page' ) ) :

	class WPPRFM_Add_Review_Feed_Editor_Page {
		private $_header_class;
		private $_feed_editor_form;

		public function __construct() {
			$this->_header_class     = new WPPFM_Main_Header();
			$this->_feed_editor_form = new WPPRFM_Review_Feed_Editor_Page();
		}

		/**
		 * Shows the content of a Google Product Review Feed Editor page.
		 */
		public function show() {
			echo '<div class="wppfm-page-layout">';
			$this->_header_class->show( 'feed-editor-page' );
			$this->_feed_editor_form->display();
			echo '</div>';
		}
	}

	// end of WPPRFM_Add_Review_Feed_Editor_Page class

endif;
