<?php

/**
 * WP Product Feed Manager Add Feed List Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Add_Feed_List_Page' ) ) :

	class WPPFM_Add_Feed_List_Page {
		private $_header_class;
		private $_feed_list_form;

		public function __construct() {
			$this->_header_class   = new WPPFM_Main_Header();
			$this->_feed_list_form = new WPPFM_Feed_List_Page();
		}

		/**
		 * Shows the content of the Feed List page.
		 */
		public function show() {
			echo '<div class="wppfm-page-layout">';
			$this->_header_class->show( 'feed-list-page' );
			$this->_feed_list_form->display();
			echo '</div>';
		}
	}

	// end of WPPFM_Add_Feed_List_Page class

endif;
