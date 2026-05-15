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
		 * Verifies nonce, capability, and admin context for an AJAX request (callable without inheriting this class).
		 *
		 * @param string|false|null $nonce                 Nonce from the request.
		 * @param string            $registered_nonce_name Nonce action name registered with wp_create_nonce().
		 * @param string            $required_capability   WordPress capability the current user must have (e.g. edit_feeds, manage_options).
		 *
		 * @since 3.9.0 Added capability check to AJAX verification.
		 * @return bool True if the AJAX call is allowed, false otherwise.
		 */
		public static function verify_safe_ajax_call( $nonce, $registered_nonce_name, $required_capability ) {
			// Check the nonce and the capability (both are required for authorization).
			if ( ! wp_verify_nonce( $nonce, $registered_nonce_name ) || ! current_user_can( $required_capability ) ) {
				return false;
			}

			// Only return results if the request is for an administrative interface page.
			return is_admin();
		}

		/**
		 * Verifies if an ajax call is safe.
		 *
		 * @param string $nonce                 the nonce that indicates if the call is made by an authorized caller.
		 * @param string $registered_nonce_name the registered nonce name.
		 * @param string $required_capability   WordPress capability the current user must have (e.g. edit_feeds, manage_options).
		 *
		 * @since 3.9.0 Added capability check to AJAX verification.
		 * @return bool true if the ajax call is safe, false if not.
		 */
		protected function safe_ajax_call( $nonce, $registered_nonce_name, $required_capability ) {
			return self::verify_safe_ajax_call( $nonce, $registered_nonce_name, $required_capability );
		}

		/**
		 * Outputs the standard "not allowed" message for AJAX responses.
		 */
		public static function echo_ajax_not_allowed_message() {
			echo '<div id="error">' . esc_html__( 'You are not allowed to do this! Please contact the web administrator.', 'wp-product-feed-manager' ) . '</div>';
		}

		/**
		 * Shows a not allowed error message.
		 */
		protected function show_not_allowed_error_message() {
			self::echo_ajax_not_allowed_message();
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
		$normalized = is_string( $string ) ? strtolower( trim( $string ) ) : $string;

		// Accept common truthy values from browser form posts and JavaScript payloads.
		if ( true === $normalized || 1 === $normalized || '1' === $normalized || 'true' === $normalized || 'on' === $normalized || 'yes' === $normalized ) {
			return 'true';
		}

		return 'false';
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
