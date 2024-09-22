<?php

/**
 * WP Ajax Calls Class.
 *
 * @package WP Product Feed Manager/Data/Classes
 * @version 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Ajax_Calls' ) ) :

	/**
	 * Feed Controller Class
	 */
	class WPPFM_Ajax_Calls {

		public $_queries_class;
		public $_files_class;

		public function __construct() { }

		/**
		 * Verifies if an ajax call is safe.
		 *
		 * @param $nonce
		 * @param $registered_nonce_name
		 * @param $required_capability
		 *
		 * @since 3.9.0 Added an optional capability check
		 * @return bool
		 */
		protected function safe_ajax_call( $nonce, $registered_nonce_name, $required_capability = '' ) {
			// check the nonce and the capability
			if ( ! wp_verify_nonce( $nonce, $registered_nonce_name ) || ( '' != $required_capability && ! current_user_can( $required_capability ) ) ) {
				return false;
			}

			// only return results if the request is for an administrative interface page.
			if ( is_admin() ) {
				return true;
			} else {
				return false;
			}
		}

		protected function show_not_allowed_error_message() {
			return '<div id="error">' . __( 'You are not allowed to do this! Please contact the web administrator.', 'wp-product-feed-manager' ) . '</div>';
		}
	}

	// end of WPPFM_Ajax_Calls class

endif;
