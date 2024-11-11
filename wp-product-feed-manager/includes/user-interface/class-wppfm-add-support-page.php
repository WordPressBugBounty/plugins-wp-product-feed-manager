<?php

/**
 * WP Product Feed Manager Add Support Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Add_Support_Page' ) ) :

	class WPPFM_Add_Support_Page {
		private $_header_class;
		private $_support_form;

		/** @noinspection PhpVoidFunctionResultUsedInspection */
		public function __construct() {
			// enqueue the js translation scripts
			add_option( 'wp_enqueue_scripts', WPPFM_i18n_Scripts::wppfm_support_i18n() );

			$this->_header_class = new WPPFM_Main_Header();
			$this->_support_form = new WPPFM_Support_Page();
		}

		/**
		 * Show the Support page.
		 */
		public function show() {
			echo '<div class="wppfm-page-layout">';
			$this->_header_class->show( 'support-page' );
			$this->_support_form->display();
			echo '</div>';
		}
	}

	// end of WPPFM_Add_Support_Page class

endif;
