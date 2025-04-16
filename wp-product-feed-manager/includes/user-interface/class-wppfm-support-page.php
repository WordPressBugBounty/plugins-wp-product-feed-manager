<?php

/**
 * WPPFM Product Feed Manager Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Support_Page' ) ) :

	/**
	 * Option Form Class
	 *
	 * @since 3.2.0
	 */
	class WPPFM_Support_Page {

		private $_plugin_version_mapping = [
			'full'   => 'pfm',
			'google' => 'gfm',
			'free'   => 'glfree',
		];

		/**
		 * Generates the main part of the Support page.
		 *
		 * @since 3.2.0
		 */
		public function display() {
			$this->add_data_storage();
			$this->support_page();
		}

		/**
		 * Renders the Support page.
		 */
		private function support_page() {
			echo '<div class="wppfm-page__title wppfm-center-page__title" id="wppfm-support-title"><h1>' . esc_html__( 'Feed Manager - Support', 'wp-product-feed-manager' ) . '</h1></div>
				</div>
				<div class="wppfm-page-layout__main" id="wppfm-feed-manager-support-layout">
				<div class="wppfm-support-wrapper wppfm-auto-center-page-wrapper">';
				$this->support_content();
			echo '</div>
				</div>';
		}

		/**
		 * Renders the content of the Support page.
		 */
		private function support_content() {
			$this->getting_started_card();
			$this->user_guide_card();
			$this->need_support_card();

			if ( 'free' === WPPFM_PLUGIN_VERSION_ID ) { // The check-in card is only for the free version
				$this->get_google_shopping_checklist_card();
				$this->google_shopping_checklist_popup();
			}

			$this->show_your_love_card();
			$this->documentation_card();
			// $this->join_our_community_card(); // Switched off until the community funnel is ready
			$this->join_our_facebook_group_card();
			$this->request_a_feature_card();
		}

		/**
		 * Renders the card header of a card for the Support page.
		 *
		 * @param string $header_text the header of the card.
		 */
		private function card_header( $header_text ) {
			echo
			'<div class="wppfm-support-card__header">
				<h2 class="wppfm-support-card__title">' . esc_html( $header_text ) . '</h2>
			</div>';
		}

		/**
		 * Renders the content part of a card for the Support page.
		 *
		 * @param string $content_html the content of the card.
		 */
		private function card_content( $content_html ) {
			$allowed_tags = array(
				'p' => array(),
				'div' => array(
					'class' => array(),
					'id' => array(),
				),
				'iframe' => array(
					'src' => array(),
					'class' => array(),
					'target' => array(),
					'id' => array(),
				),
				'ul' => array(),
				'li' => array(),
				'a' => array(
					'class' => array(),
					'id' => array(),
					'href' => array(),
					'target' => array(),
				),
				'input' => array(
					'class' => array(),
					'name' => array(),
					'id' => array(),
					'type' => array(),
					'placeholder' => array(),
				),
			);

			echo
			'<div class="wppfm-support-card__content">' .
				wp_kses( $content_html, $allowed_tags ) .
			'</div>';
		}

		/**
		 * Renders a card icon for the Support page.
		 *
		 * @param $icon
		 *
		 * @return void
		 */
		private function card_icon( $icon = null ): void {
			if ( null === $icon ) {
				return;
			}

			echo
			'<div class="wppfm-support-card__icon">
				<img src="' . esc_url( WPPFM_PLUGIN_URL ) . '/images/' . esc_attr( $icon ) . '" alt="Icon" />
			</div>';
		}

		/**
		 * Renders the action part of a card in the Support page.
		 *
		 * @param string $action_text the action text.
		 * @param string $action_url  the action url.
		 */
		private function card_action( $action_text, $action_url ) {
			$card_action_id = 'wppfm-' . str_replace( ' ', '-', strtolower( $action_text ) ) . '-button';

			echo
			'<div class="wppfm-support-card__footer">
				<div class="wppfm-inline-button-wrapper">
					<a href="' . esc_url( $action_url ) . '" target="_blank" class="wppfm-button wppfm-blue-button" id="' . esc_attr( $card_action_id ) . '">' . esc_html( $action_text ) . '</a>
				</div>
			</div>';
		}

		/**
		 * Renders a getting started card for the Support page.
		 */
		private function getting_started_card() {
			$content_html  = '<p>' . esc_html__( 'Getting started with your WooCommerce Product Feed Manager is easier than you could imagine. All our customers are not feed marketeers and we want to make your life easier.', 'wp-product-feed-manager' ) . '</p>';
			$content_html .= '<div class="wppfm-video-wrapper">';
			$content_html .= '<iframe class="wppfm-youtube-element" id="wppfm-getting-started-video" src="https://www.youtube.com/embed/68v63Q9jhIw"></iframe>';
			$content_html .= '</div>';

			echo '<div class="wppfm-support-card" id="wppfm-getting-started-support-card">';
			$this->card_header( esc_html__( 'Getting Started With Product Feed Manager', 'wp-product-feed-manager' ) );
			$this->card_content( $content_html );
			echo '</div>';
		}

		/**
		 * Renders a user guide card for the Support page.
		 */
		private function user_guide_card() {
			$sanitized_edd_store_url = esc_url( WPPFM_EDD_SL_STORE_URL );

			$content_html  = '<p>' . esc_html__( 'Please check the following articles to get started with your WooCommerce Product Feed Manager.', 'wp-product-feed-manager' ) . '</p>';
			$content_html .= '<ul>';
			$content_html .= '<li><a id="wppfm-create-basic-product-feed" href="' . $sanitized_edd_store_url . 'help-item/getting-started/create-a-basic-product-feed/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'Create a basic product feed', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-using-the-plugin-settings-dashboard" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/navigating-your-plugin-settings-dashboard/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'Navigating your plugin settings dashboard', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-using-category-mapping" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/category-mapping-in-your-product-feed/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'Category mapping', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-adding-data-to-a-product-feed" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/adding-data-to-a-product-feed-attribute/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'Adding data to a product feed attribute', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-using-unique-product-identifier" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/using-the-unique-product-identifier-function/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'Using the unique product identifier function', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-automatic-feed-updates" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/how-to-set-automatic-feed-updates/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'How to set automatic feed updates', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-use-data-from-unsupported-plugin" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/how-to-use-data-from-unsupported-plugins-in-the-feed/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'How to use data from unsupported plugins in the feed', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-create-repeating-fields" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/how-to-create-repeating-fields-in-sub-attributes/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'How to create repeating fields in sub-attributes', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-use-advanced-product-filter" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/how-to-use-the-advanced-product-filter/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'How to use the advanced product filter', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-use-the-channel-manager" href="' . $sanitized_edd_store_url . 'help-item/using-product-feed-manager/the-channel-manager-explained/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'The Channel Manager explained', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '<li><a id="wppfm-faq" href="' . $sanitized_edd_store_url . 'help-item/faq/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_items&utm_id=PST.14224" target="_blank">' . esc_html__( 'Frequently asked questions', 'wp-product-feed-manager' ) . '</a></li>';
			$content_html .= '</ul>';

			echo '<div class="wppfm-support-card" id="wppfm-user-guide-support-card">';
			$this->card_header( esc_html__( 'User Guide', 'wp-product-feed-manager' ) );
			$this->card_content( $content_html );
			echo '</div>';
		}

		/**
		 * Renders a Need Support card for the Support page.
		 */
		private function need_support_card() {
			$content_html = '<p>' . esc_html__( 'Our Experts would like to assist you with your query and any help you need.', 'wp-product-feed-manager' ) . '</p>';

			echo '<div class="wppfm-support-card" id="wppfm-need-support-support-card">';
			$this->card_icon( 'support.png' );
			$this->card_header( __( 'Need Expert Support?', 'wp-product-feed-manager' ) );
			$this->card_content( $content_html );
			$this->card_action( __( 'Contact Support', 'wp-product-feed-manager' ), WPPFM_EDD_SL_STORE_URL . 'support/?ref=' . $this->_plugin_version_mapping[WPPFM_PLUGIN_VERSION_ID] . '&utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=support_request&utm_id=PST.14225' );
			echo '</div>';
		}

		/**
		 * Renders a Free Google Shopping Checklist card for the Support page.
		 */
		private function get_google_shopping_checklist_card() {
			$action_text = __( 'Download', 'wp-product-feed-manager' );

			$content_html  = '<div class="wppfm-support-card__footer">';
			$content_html .= '<p>' . esc_html__( 'Improve your Google shopping campaigns with proven, actionable steps from our detailed Google Product Feed checklist.', 'wp-product-feed-manager' ) . '</p>';
			$content_html .= '<p><input name="wppfm-subscription-address" class="wppfm-support-card-input-field" id="wppfm-sign-up-mail-input" type="email" placeholder="Your Email"></p>';
			$content_html .= '<div class="wppfm-inline-button-wrapper" id="wppfm-sign-up-button-wrapper">';
			$content_html .= '<a href="#" class="wppfm-button wppfm-blue-button" id="wppfm-sign-up-button">' . esc_html( $action_text ) . '</a>';
			$content_html .= '</div>';
			$content_html .= '</div>';

			echo '<div class="wppfm-support-card wppfm-action-card" id="wppfm-sign-up-support-card">';
			$this->card_header( __( 'Free Google Shopping Checklist', 'wp-product-feed-manager' ) );
			$this->card_content( $content_html );
			echo '</div>';
		}

		/**
		 * Renders a pop-up card that acknowledges the user's subscription to the Free Google Shopping Checklist.
		 *
		 * @since 3.11.0
		 */
		private function google_shopping_checklist_popup() {
			$first_centence_html  = __( 'Thank you for requesting the Google Shopping Checklist: The Complete Guide. Please check your email for the download link.', 'wp-product-feed-manager' );
			$second_centence_html = __( 'If you don\'t see it in your inbox, kindly check your spam or junk folder.', 'wp-product-feed-manager' );

			echo '<div id="wppfm-google-shopping-checklist-popup" class="wppfm-popup" style="display:none">
				<div class="wppfm-popup__header">
					<h3>' . esc_html__( 'Google Shopping Checklist', 'wp-product-feed-manager' ) . '</h3>
					<div class="wppfm-popup__close-button"><b>X</b></div>
				</div>
				<div class="wppfm-popup__content">
					<p>' . esc_html( $first_centence_html ) . '</p><p>' . esc_html( $second_centence_html ) . '</p>
				</div>
			</div>';
		}

		/**
		 * Renders a Show your Love card for the Support page.
		 */
		private function show_your_love_card() {
			$content_html = '<p>' . esc_html__( 'We need your help to keep developing the plugin. Please review it and spread the love to keep us motivated.', 'wp-product-feed-manager' ) . '</p>';

			echo '<div class="wppfm-support-card" id="wppfm-show-your-love-support-card">';
			$this->card_icon( 'love.png' );
			$this->card_header( __( 'Show Your Love', 'wp-product-feed-manager' ) );
			$this->card_content( $content_html );
			$this->card_action( __( 'Leave a Review', 'wp-product-feed-manager' ), 'https://wordpress.org/support/plugin/wp-product-feed-manager/reviews/#new-post' );
			echo '</div>';
		}

		/**
		 * Renders a Documentation card for the Support page.
		 */
		private function documentation_card() {
			$content_html = '<p>' . esc_html__( 'Get detailed and guided instructions to level up your feeds with the necessary setup.', 'wp-product-feed-manager' ) . '</p>';

			echo '<div class="wppfm-support-card" id="wppfm-documentation-support-card">';
			$this->card_icon( 'documentation.png' );
			$this->card_header( __( 'Documentation', 'wp-product-feed-manager' ) );
			$this->card_content( $content_html );
			$this->card_action( __( 'Visit Documentation', 'wp-product-feed-manager' ), WPPFM_EDD_SL_STORE_URL . 'help-center/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=help_center&utm_id=PST.14226' );
			echo '</div>';
		}

//		/**
//		 * Renders the Join Our Community card for the Support page.
//		 */
//		private function join_our_community_card() {
//			$content_html = '<p>' . esc_html__( 'We have a strong community where we discuss ideas and help each other.', 'wp-product-feed-manager' ) . '</p>';
//
//			echo '<div class="wppfm-support-card" id="wppfm-join-our-community-support-card">';
//			$this->card_icon( 'community.png' );
//			$this->card_header( __( 'Join Our Community', 'wp-product-feed-manager' ) );
//			$this->card_content( $content_html );
//			$this->card_action( __( 'Join Community', 'wp-product-feed-manager' ), 'https://www.wpproductfeedmanager.com/documentation/' );
//			echo '</div>';
//		}

		/**
		 * Renders the Join Our Facebook Group card for the Support page.
		 *
		 * @since 3.14.0.
		 */
		private function join_our_facebook_group_card() {
			$content_html = '<p>' . esc_html__( 'Join our Facebook page for free expert marketing tips and strategies to boost your web shop sales!', 'wp-product-feed-manager' ) . '</p>';

			echo '<div class="wppfm-support-card" id="wppfm-join-our-facebook-page-card">';
			$this->card_icon( 'facebook.png' );
			$this->card_header( __( 'Join Our Facebook Page', 'wp-product-feed-manager' ) );
			$this->card_content( $content_html );
			$this->card_action( __( 'Join Facebook Page', 'wp-product-feed-manager' ), 'https://www.facebook.com/profile.php?id=100069788955827' );
			echo '</div>';
		}

		/**
		 * Renders the Request a feature card for the Settings page.
		 */
		private function request_a_feature_card() {
			$content_html = '<p>' . esc_html__( 'If you need any feature on your WooCommerce Product Feed Manager that we currently do not have, please send us a request with your wishes and requirements.', 'wp-product-feed-manager' ) . '</p>';

			echo '<div class="wppfm-support-card" id="wppfm-request-a-feature-support-card">';
			$this->card_icon( 'request.png' );
			$this->card_header( __( 'Request a Feature', 'wp-product-feed-manager' ) );
			$this->card_content( $content_html );
			$this->card_action( __( 'Request a Feature', 'wp-product-feed-manager' ), WPPFM_EDD_SL_STORE_URL . 'help-center/feature-request/?utm_source=pl_sup_tab&utm_medium=textlink&utm_campaign=feature_request&utm_id=PST.14227' );
			echo '</div>';
		}

		/**
		 * Stores data in the DOM for the Feed Manager Settings page
		 *
		 * @since 3.11.0 - Added the first-name and last-name data.
		 */
		private function add_data_storage() {
			$current_user = wp_get_current_user();

			echo
				'<div id="wppfm-support-page-data-storage" class="wppfm-data-storage-element" 
				data-wppfm-username="' . esc_attr( $current_user->user_login ) . '"
				data-wppfm-user-first-name="' . esc_attr( $current_user->first_name ) . '"
				data-wppfm-user-last-name="' . esc_attr( $current_user->last_name ) . '"
				data-wppfm-plugin-version-id="' . esc_attr( WPPFM_PLUGIN_VERSION_ID ) . '" 
				data-wppfm-plugin-version-nr="' . esc_attr( WPPFM_VERSION_NUM ) . '"
				data-wppfm-plugin-distributor="' . esc_attr( WPPFM_PLUGIN_DISTRIBUTOR ) . '">
			</div>';
		}
	}

	// end of WPPFM_Support_Page class

endif;
