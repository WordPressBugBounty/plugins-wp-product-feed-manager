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
		 * Sends a notice email to the notice mail address containing a feed failed processing message.
		 *
		 * @since 3.22.0 Added $feed_id and $args for contextual failure notices tied to terminal states.
		 *
		 * @param string $feed_id Affected feed ID, or empty string when unknown.
		 * @param array  $args {
		 *     Optional. Extra context for the message body.
		 *
		 *     @type int    $detected_at Unix timestamp when the failure was detected (defaults to now).
		 *     @type string $source      One of: batch_aborted, watchdog_stall, missing_feed_context, unknown.
		 * }
		 *
		 * @return bool true if the message has been sent.
		 */
		public static function send_feed_failed_message( $feed_id = '', $args = array() ) {
			$defaults = array(
				'detected_at' => time(),
				'source'      => 'unknown',
			);
			$args     = wp_parse_args( $args, $defaults );
			$feed_id  = is_scalar( $feed_id ) ? (string) $feed_id : '';

			$to = get_option( 'wppfm_notice_mailaddress', '' );
			// Fall back to admin email when notice recipient is not configured or invalid.
			if ( empty( $to ) || ! is_email( $to ) ) {
				$to = get_bloginfo( 'admin_email' );
			}
			if ( empty( $to ) || ! is_email( $to ) ) {
				return false;
			}

			$header  = self::feed_failed_header( $feed_id );
			$message = self::feed_failed_message( $feed_id, $args );

			return self::send( $to, $header, $message );
		}

		/**
		 * Returns a failed processing header string.
		 *
		 * @param string $feed_id Feed ID or empty.
		 *
		 * @return string with the header text.
		 */
		private static function feed_failed_header( $feed_id = '' ) {
			if ( '' !== $feed_id ) {
				return sprintf(
					/* translators: 1: Site name, 2: Feed ID */
					__( 'Feed generation failure on %1$s (feed ID %2$s)', 'wp-product-feed-manager' ),
					get_bloginfo( 'name' ),
					$feed_id
				);
			}

			return sprintf(
				/* translators: %s: Site name */
				__( 'Feed generation failure on %s', 'wp-product-feed-manager' ),
				get_bloginfo( 'name' )
			);
		}

		/**
		 * Maps an internal failure source key to a short user-facing label.
		 *
		 * @param string $source Internal key.
		 *
		 * @return string
		 */
		private static function get_failure_source_label( $source ) {
			switch ( $source ) {
				case 'batch_aborted':
					return __( 'Background batch ended early', 'wp-product-feed-manager' );
				case 'watchdog_stall':
					return __( 'Feed generation stalled (confirmed after automatic check)', 'wp-product-feed-manager' );
				case 'missing_feed_context':
					return __( 'Feed completion failed (could not restore feed data)', 'wp-product-feed-manager' );
				default:
					return __( 'Unknown', 'wp-product-feed-manager' );
			}
		}

		/**
		 * Formats the failure detection moment in the site timezone.
		 *
		 * @param int $detected_at Unix timestamp.
		 *
		 * @return string
		 */
		private static function format_failure_detected_at( $detected_at ) {
			$ts = max( 0, intval( $detected_at ) );
			if ( $ts <= 0 ) {
				return __( 'Unknown time', 'wp-product-feed-manager' );
			}

			$format = sprintf(
				'%s %s',
				get_option( 'date_format' ),
				get_option( 'time_format' )
			);

			return wp_date( $format, $ts );
		}

		/**
		 * Returns a failed processing message string.
		 *
		 * @param string $feed_id Feed ID or empty.
		 * @param array  $args    Arguments (detected_at, source).
		 *
		 * @return string with the message.
		 */
		private static function feed_failed_message( $feed_id, $args ) {
			$detected_display = self::format_failure_detected_at( $args['detected_at'] );
			$source_label     = self::get_failure_source_label( $args['source'] );

			if ( '' !== $feed_id ) {
				return sprintf(
					/* translators: 1: Plugin name, 2: Site name, 3: Feed ID, 4: Date/time (localized), 5: Failure reason label */
					__(
						'This is an automatic message from the %1$s plugin on your %2$s shop.

A product feed has been marked as failed after generation stopped or could not complete.

Feed ID: %3$s
Failure detected: %4$s
Details: %5$s

Please open the feed list in your WordPress admin, review the affected feed, and try regenerating it manually.

If the problem continues, open a support ticket and include this message.',
						'wp-product-feed-manager'
					),
					WPPFM_EDD_SL_ITEM_NAME,
					get_bloginfo( 'name' ),
					$feed_id,
					$detected_display,
					$source_label
				);
			}

			return sprintf(
				/* translators: 1: Plugin name, 2: Site name, 3: Date/time (localized), 4: Failure reason label */
				__(
					'This is an automatic message from the %1$s plugin on your %2$s shop.

One or more product feeds failed to generate.

Failure detected: %3$s
Details: %4$s

Please check the status of your feeds in WordPress admin and try manually regenerating them.

If the problem continues, open a support ticket and include this message.',
					'wp-product-feed-manager'
				),
				WPPFM_EDD_SL_ITEM_NAME,
				get_bloginfo( 'name' ),
				$detected_display,
				$source_label
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
