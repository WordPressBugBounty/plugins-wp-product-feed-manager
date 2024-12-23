<?php

/**
 * WP Product Feed Manager Add Settings Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Add_Settings_Page' ) ) :

	class WPPFM_Add_Settings_Page {
		private $_header_class;
		private $_settings_form;

		/** @noinspection PhpVoidFunctionResultUsedInspection */
		public function __construct() {
			// enqueue the js translation scripts
			add_option( 'wp_enqueue_scripts', WPPFM_i18n_Scripts::wppfm_settings_i18n() );

			$this->_header_class  = new WPPFM_Main_Header();
			$this->_settings_form = new WPPFM_Settings_Page();
		}

		/**
		 * Shows the Settings Page.
		 */
		public function show() {
			echo '<div class="wppfm-page-layout">';
			$this->_header_class->show( 'settings-page' );
			$this->_settings_form->display();
			echo '</div>';
		}
	}

	// end of WPPFM_Add_Settings_Page class

endif;
