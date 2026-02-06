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
			$to = get_option( 'wppfm_notice_mailaddress', '' );
			// Fall back to admin email when notice recipient is not configured or invalid.
			if ( empty( $to ) || ! is_email( $to ) ) {
				$to = get_bloginfo( 'admin_email' );
			}
			if ( empty( $to ) || ! is_email( $to ) ) {
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
		 * Sends a test email to the given address to verify email delivery is working.
		 *
		 * @param string $to Recipient email address.
		 * @return bool Whether the test email was sent successfully.
		 */
		public static function send_test_email( $to ) {
			if ( empty( $to ) || ! is_email( $to ) ) {
				return false;
			}

			$subject = sprintf(
				/* translators: %s: site name */
				__( 'Test email from %s - Feed Manager Notice Recipient', 'wp-product-feed-manager' ),
				get_bloginfo( 'name' )
			);

			$message = sprintf(
				/* translators: 1: plugin name, 2: site name */
				__(
					'This is a test email from the %1$s plugin on your %2$s shop. If you receive this message, feed failure notifications should be delivered to this address.',
					'wp-product-feed-manager'
				),
				WPPFM_EDD_SL_ITEM_NAME,
				get_bloginfo( 'name' )
			);

			return self::send( $to, $subject, $message );
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
