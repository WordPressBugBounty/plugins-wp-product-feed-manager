<?php

/**
 * WP Ajax Calls Class.
 *
 * @package WP Product Feed Manager/Data/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Ajax_Calls' ) ) :

	/**
	 * Feed Controller Class.
	 */
	class WPPFM_Ajax_Calls {

		public $_queries_class;
		public $_files_class;

		public function __construct() { }

		/**
		 * Verifies if an ajax call is safe.
		 *
		 * @param string $nonce                 the nonce that indicates if the call is made by an authorized caller.
		 * @param string $registered_nonce_name the registered nonce name.
		 * @param string $required_capability   the WordPress capability the user needs to have to get access to this functionality. Empty as default.
		 *
		 * @since 3.9.0 Added an optional capability check.
		 * @return bool true if the ajax call is safe, false if not.
		 */
		protected function safe_ajax_call( $nonce, $registered_nonce_name, $required_capability = '' ) {
			// Check the nonce and the capability.
			if ( ! wp_verify_nonce( $nonce, $registered_nonce_name ) || ( '' != $required_capability && ! current_user_can( $required_capability ) ) ) {
				return false;
			}

			// Only return results if the request is for an administrative interface page.
			return is_admin();
		}

		/**
		 * Shows a not allowed error message.
		 */
		protected function show_not_allowed_error_message() {
			echo '<div id="error">' . esc_html__( 'You are not allowed to do this! Please contact the web administrator.', 'wp-product-feed-manager' ) . '</div>';
		}

		/**
		 * Custom function to allow & but sanitize other unwanted characters.
		 *
		 * @param string $string the string to sanitize.
		 *
		 * @since 3.11.0.
		 * @return string the sanitized string.
		 */
		protected function sanitize_string_with_ampersand( $string ) {
			return preg_replace( '/[^a-zA-Z0-9\s&,]/', '', $string );
		}

		/**
		 * Custom function allows spaces, hyphens, underscores and periods & but sanitize other unwanted characters. Specially meant for titles.
		 *
		 * @param string $string the string to sanitize.
		 *
		 * @since 3.11.0.
		 * @return string the sanitized string.
		 */
		protected function sanitize_title_string( $string ) {
			return preg_replace( '/[^a-zA-Z0-9\s_.-]/', '', $string );
		}

		/**
		 * Custom function that only allows a true or false string.
		 *
		 * @param string $string the string to sanitize.
		 *
		 * @since 3.11.0.
		 * @return string the sanitized string.
		 */
		protected function sanitize_true_false_string( $string ) {
			return 'true' === $string ? 'true' : 'false';
		}

		/**
		 * Custom function that allows a string with normal characters, comma's and percent characters.
		 *
		 * @param string $string the string to sanitize.
		 *
		 * @since 3.11.0.
		 * @return string the sanitized string.
		 */
		protected function sanitize_third_party_attributes_string( $string ) {
			return preg_replace( '/[^a-zA-Z0-9\s,%_-]/', '', $string );
		}
	}

	// end of WPPFM_Ajax_Calls class

endif;
