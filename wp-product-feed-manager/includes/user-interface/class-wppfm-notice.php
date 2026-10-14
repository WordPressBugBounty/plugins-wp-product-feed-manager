<?php

/**
 * WP Product Feed Manager Notice Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Notice' ) ) :

	class WPPFM_Notice {

		/**
		 * Renders a Black Friday 2026 sales promotion notice for a plugin edition.
		 *
		 * @param string $plugin_version_id Plugin edition ('free' or 'google').
		 */
		public static function render_black_friday_2026_promotion_notice( $plugin_version_id ) {
			if ( 'free' === $plugin_version_id ) {
				self::render_discount_promotion_notice(
					WPPFM_PLUGIN_URL . '/images/bf26-icon-free-35-180x140.png',
					'https://wpmarketingrobot.com/purchase-wp-product-feed-manager/?discount=BLCKFD26FRMUM&utm_source=plugin-free&utm_medium=banner&utm_campaign=bf26',
					__( 'Product Feed Manager - Black Friday: Save 35% on Premium', 'wp-product-feed-manager' ),
					__( 'Upgrade to Google Premium or go straight to Multichannel and send your products to Google, Meta, Bing and more.', 'wp-product-feed-manager' )
						. ' ' . __( ' Use code BLCKFD26FRMUM at checkout. Valid until November 30.', 'wp-product-feed-manager' ),
					__( 'See The Offer!', 'wp-product-feed-manager' )
				);
				return;
			}

			if ( 'google' === $plugin_version_id ) {
				self::render_discount_promotion_notice(
					WPPFM_PLUGIN_URL . '/images/bf26-icon-google-30-180x140.png',
					'https://www.wpmarketingrobot.com/purchase-wp-product-feed-manager/?discount=BLCKFD26DLPREMIUM&utm_source=plugin-google&utm_medium=banner&utm_campaign=bf26',
					__( 'Product Feed Manager - Black Friday: save €30 on Multichannel', 'wp-product-feed-manager' ),
					__( 'Add Meta, Bing and more to your Google license. Upgrade to Multichannel and use code BLCKFD26DLPREMIUM to save €30. Valid until November 30.', 'wp-product-feed-manager' ),
					__( 'Upgrade now', 'wp-product-feed-manager' )
				);
			}
		}

		/**
		 * Renders a Sales Promotion notice (free edition). Kept for backwards compatibility.
		 */
		public static function render_sales_promotion_notice() {
			self::render_black_friday_2026_promotion_notice( 'free' );
		}

		/**
		 * Outputs the shared Black Friday promotion notice markup.
		 *
		 * @param string $promotion_image_url URL of the promotion image.
		 * @param string $get_deal_link       Primary call-to-action link.
		 * @param string $title               Notice heading.
		 * @param string $message_html        Notice body (plain text; escaped on output).
		 * @param string $primary_button_label Label for the primary button.
		 */
		private static function render_discount_promotion_notice( $promotion_image_url, $get_deal_link, $title, $message_html, $primary_button_label ) {
			echo
			'<div class="wppfm-message-field notice is-dismissible" id="wppfm-discount-promotion-notice">
				<div class="wppfm-discount-promotion-container">
					<div class="wppfm-discount-promotion-image"><img src="' . esc_url( $promotion_image_url ) . '" alt="Black Friday Promotion"></div>
					<div class="wppfm-discount-promotion-offer">
						<div class="wppfm-discount-promotion-message">
							<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message_html ) . '</p>
							<div class="wppfm-discount-promotion-call-to-action">
							<a class="wppfm-discount-button wppfm-go-for-the-deal" id="wppfm-go-for-the-deal" target="_blank" href="' . esc_url( $get_deal_link ) . '">' . esc_html( $primary_button_label ) . '</a>
							<a class="wppfm-discount-button wppfm-dismiss-promotion-notice" id="wppfm-dismiss-promotion-notice" href="#">' . esc_html__( 'Dismiss', 'wp-product-feed-manager' ) . '</a>
							</div>
						</div>
					</div>
				</div>
				<div class="wppfm-discount-promotion-dismiss-action"><a class="wppfm-dismiss-discount-link" id="wppfm-dismiss-promotion-notice-link" href="#">' . esc_html__( 'don\'t show this Black Friday offer anymore', 'wp-product-feed-manager' ) . '</a></div>
			</div>';
		}
	}

	// end of WPPFM_Notice class

endif;
