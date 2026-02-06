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
		 * Renders a Sales Promotion notice.
		 */
		public static function render_sales_promotion_notice() {
			$promotion_image_url = WPPFM_PLUGIN_URL . '/images/icon-black-friday-sale.png';
			$get_deal_link = 'https://www.wpmarketingrobot.com/black-friday-sale/?discount=BLACKFRIDAY2024&utm_source=pl_top&utm_medium=banner&utm_campaign=black-friday-24&utm_id=GFP.251124';

			echo
			'<div class="wppfm-message-field notice is-dismissible" id="wppfm-discount-promotion-notice">
				<div class="wppfm-discount-promotion-container">
					<div class="wppfm-discount-promotion-image"><img src="' . esc_url( $promotion_image_url ) . '" alt="Black Friday Promotion"></div>
					<div class="wppfm-discount-promotion-offer">
						<div class="wppfm-discount-promotion-message">
							<h1>' . esc_html__( '50% Black Friday SALE!!', 'wp-product-feed-manager' ) . '</h1><p>' . esc_html__( 'Black Friday Sale: 50% Off', 'wp-product-feed-manager' ) . ' <em>Google Feed Manager Premium!</em> ' . esc_html__( 'From November 29 to December 2, 2024.', 'wp-product-feed-manager' )
			. ' ' . esc_html__( 'Optimize your WooCommerce feed management with this limited-time offer!', 'wp-product-feed-manager' ) . '</p>
							<div class="wppfm-discount-promotion-call-to-action">
							<a class="wppfm-discount-button wppfm-go-for-the-deal" id="wppfm-go-for-the-deal" target="_blank" href="' . esc_url( $get_deal_link ) . '">' . esc_html__( 'Get Your Deal Now!', 'wp-product-feed-manager' ) . '</a>
							<a class="wppfm-discount-button wppfm-dismiss-promotion-notice" id="wppfm-dismiss-promotion-notice" href="#">' . esc_html__( 'Nope, I don\'t like deals!', 'wp-product-feed-manager' ) . '</a>
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
