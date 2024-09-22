<?php

/**
 * WP Product Feed Manager Notice Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Notice' ) ) :

	class WPPFM_Notice {

		public static function summer_promotion_2024() {
			$promotion_image = '<img src="' . WPPFM_PLUGIN_URL . '/images/icon-summer-sale-24.png" alt="Summer Promotion">';
			$get_deal_link = '<a class="wppfm-discount-button wppfm-go-for-the-deal" id="wppfm-go-for-the-deal" target="_blank" href="https://www.wpmarketingrobot.com/?discount=summersale24&utm_source=pl_top&utm_medium=banner&utm_campaign=summer_sale&utm_id=GFP.11724">' . __( 'Get Your Deal Now, Click Here!', 'wp-product-feed-manager' ) . '</a>';
			$dismiss_link = '<a class="wppfm-discount-button wppfm-dismiss-promotion-notice" id="wppfm-dismiss-promotion-notice" href="#">' . __( 'Nope, I hate summers!', 'wp-product-feed-manager' ) . '</a>';

			echo
			'<div class="wppfm-message-field notice is-dismissible" id="wppfm-discount-promotion-notice">
				<div class="wppfm-discount-promotion-container">
					<div class="wppfm-discount-promotion-image">' . $promotion_image . '</div>
					<div class="wppfm-discount-promotion-offer">
						<div class="wppfm-discount-promotion-message">
							<h1>' . __( 'SUMMER SALE!!!', 'wp-product-feed-manager' ) . '</h1><p>' . __( 'Enjoy a', 'wp-product-feed-manager' ) . ' <em>25% DISCOUNT</em> ' . __( 'on Google Feed Manager Premium from July to August 2024.', 'wp-product-feed-manager' ) . '</p>
							<p>' . __( 'Use CODE:', 'wp-product-feed-manager' ) . ' <em>SUMMERSALE24</em> ' . __( 'at checkout. Don\'t miss out on this special deal - grab it now!', 'wp-product-feed-manager' ) . '</p>
							<div class="wppfm-discount-promotion-call-to-action">' . $get_deal_link . $dismiss_link . '</div>
						</div>
					</div>
				</div>
				<div class="wppfm-discount-promotion-dismiss-action"><a class="wppfm-dismiss-discount-link" id="wppfm-dismiss-promotion-notice-link" href="#">' . __( 'don\'t show this summer sale offer anymore', 'wp-product-feed-manager' ) . '</a></div>
			</div>';
		}
	}

	// end of WPPFM_Notice class

endif;
