<?php

/**
 * WP Product Feed Manager Add Feed Editor Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Add_Feed_Editor_Page' ) ) :

	class WPPFM_Add_Feed_Editor_Page {
		private $_header_class;
		private $_feed_editor_form;

		public function __construct() {
			$this->_header_class     = new WPPFM_Main_Header();
			$this->_feed_editor_form = new WPPFM_Feed_Editor_Page();
		}

		public function show() {
			echo '<div class="wppfm-page-layout">';
			echo $this->_header_class->show( 'feed-editor-page' );
			echo $this->_feed_editor_form->display();
			echo '</div>';
		}
	}

	// end of WPPFM_Add_Feed_Editor_Page class

endif;
