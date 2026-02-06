<?php

/**
 * WPPPFM Add Promotions Feed Editor Page Class.
 *
 * @package WP Product Promotions Feed Manager/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPRPM_Add_Promotions_Feed_Editor_Page' ) ) :

	class WPPPFM_Add_Promotions_Feed_Editor_Page {
		private $_header_class;
		private $_feed_editor_form;

		public function __construct() {
			$this->_header_class     = new WPPFM_Main_Header();
			$this->_feed_editor_form = new WPPPFM_Promotions_Feed_Editor_Page();
		}

		public function show() {
			echo '<div class="wppfm-page-layout">';
			$this->_header_class->show( 'feed-editor-page' );
			$this->_feed_editor_form->display();
			echo '</div>';
		}
	}

	// end of WPPPFM_Add_Promotions_Feed_Editor_Page class

endif;
