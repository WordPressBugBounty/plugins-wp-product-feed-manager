<?php

/**
 * WP Email Class.
 *
 * @package WP Product Feed Manager/Application/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Email' ) ) :

	/**
	 * Email Class.
	 *
	 * @since 2.3.0.
	 */
	class WPPFM_Email {

		/**
		 * Sends a notice  email to the notice mailaddress containing a feed failed processing message.
		 *
		 * @return bool true if the message has been sent.
		 */
		public static function send_feed_failed_message() {
			$to = get_option( 'wppfm_notice_mailaddress', get_bloginfo( 'admin_email' ) );

			if ( ! $to ) {
				return false;
			}

			$header  = self::feed_failed_header();
			$message = self::feed_failed_message();

			return self::send( $to, $header, $message );
		}

		/**
		 * Returns a failed processing header string.
		 *
		 * @return string with the header text.
		 */
		private static function feed_failed_header() {
			return sprintf( 'Feed generation failure on your %s shop', get_bloginfo( 'name' ) );
		}

		/**
		 * Returns a failed processing message string.
		 *
		 * @return string with the message.
		 */
		private static function feed_failed_message() {
			return sprintf(
				'This is an automatic message from your %s plugin. One or more product feeds on your %s shop failed to generate. Please check the status of your feeds and try to manually regenerate them again.

Should this problem persist, please open a support ticket.',
				WPPFM_EDD_SL_ITEM_NAME,
				get_bloginfo( 'name' )
			);
		}

		/**
		 * Sends an email.
		 *
		 * @param string $to to address.
		 * @param string $subject the subject.
		 * @param string $message the message.
		 *
		 * @return bool whether the mail contents were sent successfully.
		 */
		private static function send( $to, $subject, $message ) {
			if ( is_email( $to ) ) {
				return (bool) wp_mail( $to, $subject, $message );
			} else {
				return false;
			}
		}
	}

	// end of WPPFM_Email class

endif;
