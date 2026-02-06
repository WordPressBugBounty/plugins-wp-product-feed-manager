<?php /** @noinspection PhpComposerExtensionStubsInspection */

/**
 * WP Product Feed Manager Channel FTP Class.
 *
 * @package WP Product Feed Manager/Data/Classes
 * @version 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WPPFM_Channel_FTP' ) ) :

	/**
	 * Channel FTP Class
	 */
	class WPPFM_Channel_FTP {

		/**
		 * Gets the correct channel zip file from the wpmarketingrobot server.
		 *
		 * @since 1.9.3  - switched from ftp to cURL procedures.
		 * @since 3.12.0 - switched from cURL to wp_remote_get.
		 *
		 * @param string $channel The channel short name.
		 * @param string $code    The channel code.
		 *
		 * @return boolean True if the channel file was downloaded successfully, false otherwise.
		 */
		public function get_channel_source_files( $channel, $code ) {
			$wp_filesystem = wppfm_get_wp_filesystem();

			// Make the channel folder if it does not exist.
			if ( ! $wp_filesystem->is_dir( WPPFM_CHANNEL_DATA_DIR ) ) {
				WPPFM_Folders::make_channels_support_folder();
			}

			// Check if the directory is writable.
			if ( ! $wp_filesystem->is_writable( WPPFM_CHANNEL_DATA_DIR ) ) {
				wppfm_show_wp_error(
					sprintf(
						/* translators: %s: Folder that contains the channel data */
						__( 'You have no read/write permission to the %s folder. Please update the file permissions of this folder to make it writable and then try installing a channel again.', 'wp-product-feed-manager' ),
						WPPFM_CHANNEL_DATA_DIR
					)
				);
				return false;
			}

			// Define local file path and remote file URL.
			$local_file      = WPPFM_CHANNEL_DATA_DIR . '/' . $channel . '.zip';
			$remote_file_url = esc_url( WPPFM_EDD_SL_STORE_URL . 'system/wp-content/uploads/wppfm_channel_downloads/' . $code . '.zip?ts=' . time() ); // Avoid caching issues.

			// Fetch the remote file using wp_remote_get.
			$response = wp_remote_get( $remote_file_url, [
				'timeout' => 10,
				'headers' => [
					'Cache-Control' => 'no-cache',
				],
				'sslverify' => true, // Verify SSL for security.
			]);

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				wppfm_write_log_file(
					sprintf(
						'Downloading a channel file failed. Error: %s',
						is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_message( $response )
					)
				);
				return false;
			}

			// Get the file contents from the response.
			$file_contents = wp_remote_retrieve_body( $response );

			// Write the contents to the local file using the WordPress Filesystem API.
			if ( ! $wp_filesystem->put_contents( $local_file, $file_contents, FS_CHMOD_FILE ) ) {
				wppfm_write_log_file( 'Failed to write the channel file to the local directory.' );
				return false;
			}

			return true;
		}

		/**
		 * Registers the download of a channel.
		 *
		 * @param string $channel The channel to register the download for.
		 *
		 * @since 3.13.0
		 * @return bool True if the download was registered successfully, false otherwise.
		 */
		public function register_channel_download( $channel ) {
			// Skip registration in local environment.
			if ( str_contains( get_home_url(), '.test' ) ) {
				return true;
			}

			$response = wp_remote_post( WPPFM_EDD_SL_STORE_URL . 'wpmr/channels/channels.php', [
				'body' => [
					'action'  => 'count_channel_download',
					'channel' => $channel,
				],
			] );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				wppfm_write_log_file(
					sprintf(
						'Failed registering the download of channel %s. Error: %s',
						$channel, is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_message( $response )
					)
				);

				return false;
			}

			return true;
		}
	}

	// end of WPPFM_Channel_FTP class

endif;
