<?php

/**
 * WP Product Feed Manager Add Channel Manager Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Add_Channel_Manager_Page' ) ) :

	class WPPFM_Add_Channel_Manager_Page {
		private $_header_class;
		private $_channel_manager_form;

		/** @noinspection PhpVoidFunctionResultUsedInspection */
		public function __construct() {
			// enqueue the js translation scripts
			add_option( 'wp_enqueue_scripts', WPPFM_i18n_Scripts::wppfm_channel_manager_i18n() );

			$this->_header_class         = new WPPFM_Main_Header();
			$this->_channel_manager_form = new WPPFM_Channel_Manager_Page();
		}

		/**
		 * Shows the Channel Manager page.
		 *
		 * @param string $updated contains the channel name if the page is loaded after a channel update.
		 */
		public function show( $updated ) {
			echo '<div class="wppfm-page-layout">';
			$this->_header_class->show( 'channel-manager-page' );
			$this->_channel_manager_form->display( $updated );
			echo '</div>';
		}
	}

	// end of WPPFM_Add_Channel_Manager_Page class

endif;
