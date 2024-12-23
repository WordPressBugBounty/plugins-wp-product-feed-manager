<?php

/**
 * WP Folder Class.
 *
 * @package WP Product Feed Manager/Setup/Classes
 * @version 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Folders' ) ) :


	/**
	 * Folder Class
	 */
	class WPPFM_Folders {

		public static function make_feed_support_folder() {
			if ( ! file_exists( WPPFM_FEEDS_DIR ) ) {
				self::make_wppfm_dir( WPPFM_FEEDS_DIR );
			}
		}

		public static function make_channels_support_folder() {
			if ( ! file_exists( WPPFM_CHANNEL_DATA_DIR ) ) {
				self::make_wppfm_dir( WPPFM_CHANNEL_DATA_DIR );
			}
		}

		public static function make_backup_folder() {
			if ( ! file_exists( WPPFM_BACKUP_DIR ) ) {
				self::make_wppfm_dir( WPPFM_BACKUP_DIR );
			}
		}

		public static function make_wppfm_dir( $dir ) {
			wp_mkdir_p( $dir );
		}

		/**
		 * Deletes a directory and all its content.
		 *
		 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
		 * @param string $folder_name name and path of the folder.
		 *
		 * @return boolean true when the directory has been deleted.
		 */
		public static function delete_folder( $folder_name ) {
			$wp_filesystem = wppfm_get_wp_filesystem();


			// Check if the folder exists
			if ( ! $wp_filesystem->is_dir( $folder_name ) ) {
				return false;
			}

			// Recursively delete the folder and its contents
			return $wp_filesystem->delete( $folder_name, true );
		}

		public static function copy_folder( $source_folder, $target_folder ) {
			$result = true;
			$dir    = opendir( $source_folder );

			self::make_wppfm_dir( $target_folder );

			while ( false !== ( $file = readdir( $dir ) ) ) {

				if ( ! $result ) {
					break;
				}

				if ( ( '.' != $file ) && ( '..' != $file ) ) {
					if ( is_dir( $source_folder . '/' . $file ) ) {
						self::copy_folder( $source_folder . '/' . $file, $target_folder . '/' . $file );

					} else {
						$result = copy( $source_folder . '/' . $file, $target_folder . '/' . $file );
					}
				}

				closedir( $dir );
			}

			return $result;
		}

		public static function folder_is_empty( $folder ) {
			if ( ! is_readable( $folder ) ) {
				return null;
			}

			$handle = opendir( $folder );

			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( $entry != "." && $entry != ".." ) {
					return false;
				}
			}

			return true;
		}

	}


	// end of WPPFM_Folders_Class

endif;
