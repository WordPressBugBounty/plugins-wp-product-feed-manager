<?php

/**
 * WPPFM Product Feed Manager Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Channel_Manager_Page' ) ) :

	/**
	 * Feed List Form Class
	 *
	 * @since 3.2.0
	 */
	class WPPFM_Channel_Manager_Page {

		/**
		 * Generates the main part of the Channel Manager page.
		 *
		 * @param string $updated contains the channel name if the page is loaded after a channel update.
		 *
		 * @since 3.2.0
		 */
		public function display( $updated ) {
			$this->channel_info_popup();
			$this->channel_manager_page( $updated );
		}

		/**
		 * Renders the channel manager page.
		 *
		 * @param string $updated contains the channel name if the page is loaded after a channel update.
		 */
		private function channel_manager_page( $updated ) {
			echo '<div class="wppfm-page__title wppfm-center-page__title" id="wppfm-channel-manager-title"><h1>' . esc_html__( 'Feed Manager - Channel Manager', 'wp-product-feed-manager' ) . '</h1></div></div>';

			// Feed Manager Channel Manager Table
			echo '<div class="wppfm-page-layout__main" id="wppfm-feed-manager-channel-manager-table">';
			echo '<div class="wppfm-channel-manager-wrapper wppfm-auto-center-page-wrapper">';
			$this->channel_manager_content( $updated );
			echo '</div></div>';
		}

		/**
		 * Renders the channel tiles wrappers for the installed and uninstalled channels.
		 *
		 * @param string $updated contains the channel name if the page is loaded after a channel update.
		 */
		private function channel_manager_content( $updated ) {
			$channels_class = new WPPFM_Channel();

			$response = $channels_class->get_channels_from_server();

			if ( ! is_wp_error( $response ) ) {
				$available_channels = json_decode( $response['body'] );

				if ( $available_channels ) {

					$installed_channels_names = $channels_class->get_installed_channel_names();

					$channels_class->add_status_data_to_available_channels( $available_channels, $installed_channels_names, $updated );

					$channels_class->add_channel_info_links_to_channels( $available_channels );

					// Split the available channels into installed and uninstalled.
					$installed_channels = array_filter(
						$available_channels,
						function ( $channel ) {
							return ( 'installed' === $channel->status );
						}
					);

					$uninstalled_channels = array_filter(
						$available_channels,
						function ( $channel ) {
							return ( 'installed' !== $channel->status );
						}
					);

					if ( 'false' === get_option( 'wppfm_manual_channel_update', 'false' ) ) {
						wppfm_auto_update_installed_channels( $installed_channels );
					}

					echo '<h3>' . esc_html__( 'Installed Channels:', 'wp-product-feed-manager' ) . '</h3>';
					echo '<div class="wppfm-channels-tiles-wrapper--installed">';

					foreach ( $installed_channels as $channel ) {
						$this->installed_channel_tile( $channel );
					}

					echo '</div>';

					echo '<h3>' . esc_html__( 'Available Channels:', 'wp-product-feed-manager' ) . '</h3>';

					echo '<div class="wppfm-channels-tiles-wrapper--available">';

					foreach ( $uninstalled_channels as $channel ) {
						$this->uninstalled_channel_tile( $channel );
					}
				}

			} else {
				/* translators: %s: link to the support page */
				wppfm_handle_wp_errors_response( $response, sprintf( esc_html__( '2965 - Could not connect to the channel download server. Please try to refresh the page in a few minutes again. You can open a support ticket at %s if the issue persists.', 'wp-product-feed-manager' ), WPPFM_SUPPORT_PAGE_URL ) );
			}
		}

		/**
		 * Renders a channel tile of a channel that is installed.
		 *
		 * @param object $channel containing the channel data.
		 */
		private function installed_channel_tile( $channel ) {
			$latest_version = (float) $channel->installed_version >= (float) $channel->version;
			$remove_nonce = wp_create_nonce( 'delete-channel-nonce' );

			echo
			'<div class="wppfm-channel-tile-wrapper" id="wppfm-' . esc_attr( $channel->short_name ) . '-channel-tile-wrapper">
				<img class="wppfm-channel-tile__thumbnail" src="' . esc_url( urldecode( $channel->image ) ) . '" alt="channel-logo">
					<h3>' . esc_html( $channel->channel ) . '</h3>
				<div class="wppfm-inline-button-wrapper">
					<a href="admin.php?page=wppfm-channel-manager-page&wppfm_action=remove&wppfm_channel=' . esc_attr( $channel->short_name ) . '&wppfm_code=' . esc_attr( $channel->dir_code ) . '&wppfm_nonce=' . esc_attr( $remove_nonce ) .
					'" class="wppfm-button wppfm-inline-button wppfm-orange-button" id="wppfm-remove-' . esc_attr( $channel->short_name ) . '-channel-button"
					onclick="return confirm(\'' . esc_html__( 'Please confirm you want to remove this channel! Removing this channel will also remove all its feed files.', 'wp-product-feed-manager' ) . '\')">' . esc_html__( 'Remove', 'wp-product-feed-manager' ) . '</a>';

				if ( $latest_version || 'false' === get_option( 'wppfm_manual_channel_update', 'false' ) ) {
					$this->channel_tile_info_button( $channel->short_name );
				} else {
					$update_nonce = wp_create_nonce( 'update-channel-nonce' );
					echo '<a href="admin.php?page=wppfm-channel-manager-page&wppfm_action=update&wppfm_channel=' . esc_attr( $channel->short_name ) . '&wppfm_code=' . esc_attr( $channel->dir_code ) . '&wppfm_nonce=' . esc_attr( $update_nonce ) . '" class="wppfm-button wppfm-inline-button wppfm-green-button" 
					id="wppfm-install-' . esc_attr( $channel->short_name ) . '-channel-button">' . esc_html__( 'Update Available', 'wp-product-feed-manager' ) . '</a>';
				}

				echo '</div>';
				$this->channel_data_storage_element( $channel );
			echo '</div>';
		}

		/**
		 * Renders a channel tile of a channel that is not yet installed.
		 *
		 * @param object $channel containing the channel data.
		 */
		private function uninstalled_channel_tile( $channel ) {
			$install_nonce = wp_create_nonce( 'install-channel-nonce' );

			echo
				'<div class="wppfm-channel-tile-wrapper" id="wppfm-' . esc_attr( $channel->short_name ) . '-channel-tile-wrapper">
				<img class="wppfm-channel-tile__thumbnail" src="' . esc_url( urldecode( $channel->image ) ) . '" alt="channel-logo">
					<h3>' . esc_html( $channel->channel ) . '</h3>
				<div class="wppfm-inline-button-wrapper">
					<a href="admin.php?page=wppfm-channel-manager-page&wppfm_action=install&wppfm_channel=' . esc_attr( $channel->short_name ) . '&wppfm_code=' . esc_attr( $channel->dir_code ) . '&wppfm_nonce=' . esc_attr( $install_nonce ) .
					'" class="wppfm-button wppfm-inline-button wppfm-green-button" id="wppfm-install-' . esc_attr( $channel->short_name ) . '-channel-button">
					' . esc_html__( 'Install', 'wp-product-feed-manager' ) . '</a>';
				$this->channel_tile_info_button( $channel->short_name );
				echo '</div>';
				$this->channel_data_storage_element( $channel );
			echo '</div>';
		}

		private function channel_tile_info_button( $channel_short_name ) {
			echo '<a href="#" class="wppfm-button wppfm-inline-button wppfm-blue-button" id="wppfm-' . esc_attr( $channel_short_name ) . '-channel-info-button" onclick="wppfm_showChannelInfoPopup( \'' . esc_attr( $channel_short_name ) . '\' )">' . esc_html__( 'Channel Info', 'wp-product-feed-manager' ) . '</a>';
		}

		/**
		 * Renders a hidden channel data storage element containing the channels data.
		 *
		 * @param object $channel containing the channel data.
		 */
		private function channel_data_storage_element( $channel ) {
			echo
			'<div id="wppfm-' . esc_attr( $channel->short_name ) . '-channel-data" class="wppfm-data-storage-element"
				data-channel-name="' . esc_attr( $channel->channel ) . '" 
				data-short-name="' . esc_attr( $channel->short_name ) . '" 
				data-version="' . esc_attr( $channel->version ) . '" 
				data-dir-code="' . esc_attr( $channel->dir_code ) . '" 
				data-status="' . esc_attr( $channel->status ) . '" 
				data-installed-version="' . esc_attr( $channel->installed_version ) . '" 
				data-info-link="' . esc_attr( $channel->info_link ) . '" 
				data-specifications-link="' . esc_attr( $channel->specifications_link ) . '">
			</div>';
		}

		/**
		 * Renders the channel info popup screen. Initial display style is none.
		 */
		private function channel_info_popup() {
			echo
			'<div id="wppfm-channel-info-popup" class="wppfm-popup" style="display:none">
				<div class="wppfm-popup__header">
					<h3 id="wppfm-channel-info-popup__name"></h3>
					<div class="wppfm-popup__close-button"><b>X</b></div>
				</div>
				<div class="wppfm-popup__content">
					<p class="wppfm-popup__content-item" id="wppfm-channel-info-popup__status"></p>
					<p class="wppfm-popup__content-item" id="wppfm-channel-info-popup__installed-version"></p>
					<p class="wppfm-popup__content-item" id="wppfm-channel-info-popup__latest-version"></p>
					<p class="wppfm-popup__content-item" id="wppfm-channel-info-popup__info-link" style="display: none"></p>
					<p class="wppfm-popup__content-item" id="wppfm-channel-info-popup__feed-specifications-link" style="display: none"></p>
				</div>
			</div>';
		}
	}

	// end of WPPFM_Channel_Manager_Page class

endif;
