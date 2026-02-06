<?php
/**
 * WP Product Feed File Class.
 *
 * @package WP Product Feed Manager/Data/Classes
 * @version 1.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_File' ) ) :

	/**
	 * The File Class
	 */
	class WPPFM_File {

		private $_queries;

		public function __construct() {
			$this->_queries = new WPPFM_Queries();
		}

		/**
		 * Reads the correct categories from a channel-specific taxonomy text file
		 *
		 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
		 *
		 * @param int $channel_id
		 * @param string $search_level
		 * @param string $parent_category
		 * @param string $language_code
		 *
		 * @return array containing the categories
		 */
		public function get_categories_for_list( $channel_id, $search_level, $parent_category, $language_code ) {
			$channel_class = new WPPFM_Channel();
			$wp_filesystem = wppfm_get_wp_filesystem();

			$last_cat     = '';
			$categories   = array();
			$channel_name = $channel_class->get_channel_short_name( $channel_id );

			$path = WPPFM_CHANNEL_DATA_DIR . "/$channel_name/taxonomy.$language_code.txt";

			if ( $wp_filesystem->exists( $path ) ) {
				$file_content = $wp_filesystem->get_contents( $path );

				if ( false === $file_content ) {
					return $categories;
				}

				$lines = explode( "\n", $file_content );

				// step over the first lines that do not contain categories
				$lines = array_filter( $lines, function( $line ) {
					return strpos($line, '#') === false;
				});

				// step through all the lines in the file
				foreach ( $lines as $line ) {
					$category_line_array = explode( '>', $line );

					if ( 0 === $search_level ) {
						if ( trim( $category_line_array[ $search_level ] ) !== $last_cat ) {
							$categories[] = trim( $category_line_array[ $search_level ] );
							$last_cat     = trim( $category_line_array[ $search_level ] );
						}
					} elseif ( count( $category_line_array ) > $search_level && $search_level > 0 && trim( $category_line_array[ $search_level - 1 ] ) === trim( $parent_category ) ) {
						if ( trim( $category_line_array[ $search_level ] ) !== $last_cat ) {
							$categories[] = trim( $category_line_array[ $search_level ] );
							$last_cat     = trim( $category_line_array[ $search_level ] );
						}
					}
				}
			}

			return $categories;
		}

		/**
		 * Reads the correct attributes from a channel-specific taxonomy text file.
		 *
		 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
		 *
		 * @param string $channel the name of the channel.
		 *
		 * @return array containing the output fields.
		 */
		public function get_attributes_for_specific_channel( $channel ) {
			$attributes    = array();
			$path          = WPPFM_CHANNEL_DATA_DIR . "/$channel/$channel.txt";
			$wp_filesystem = wppfm_get_wp_filesystem();

			if ( $wp_filesystem->exists( $path ) ) {
				$file_contents = $wp_filesystem->get_contents( $path );

				if ( $file_contents === false ) {
					die( esc_html__( 'Unable to read the file containing the categories', 'wp-product-feed-manager' ) );
				}

				// Split the file contents into lines
				$lines = explode( "\n", $file_contents );

				// Step through all the lines in the file
				foreach ( $lines as $line ) {
					$field_object = new stdClass();

					// Parse the line as CSV using tab delimiter
					$line_data = str_getcsv( $line, "\t", "\"", "\\" );

					if ( ! empty( $line_data[0] ) ) {
						$field_object->field_id    = $line_data[0];
						$field_object->category_id = $line_data[1];
						$field_object->field_label = $line_data[2];

						$attributes[] = $field_object;
					}
				}
			}

			return $attributes;
		}

		/**
		 * Check the standard backup folder and return the .sql file names in it.
		 *
		 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
		 * @return array containing the backup file names.
		 */
		public function make_list_of_active_backups() {
			$backups       = array();
			$path          = WPPFM_BACKUP_DIR;
			$wp_filesystem = wppfm_get_wp_filesystem();

			// List all .sql files in the directory
			$files = glob( $path . '/*.sql' );

			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( ! $wp_filesystem->exists( $file ) ) {
						continue;
					}

					// Read the first line of the file
					$file_contents = $wp_filesystem->get_contents( $file );
					if ( $file_contents === false ) {
						continue; // Skip if the file couldn't be read
					}

					$lines = explode( "\n", $file_contents );
					$first_line = isset( $lines[0] ) ? $lines[0] : '';

					$file_name   = str_replace( WPPFM_BACKUP_DIR . '/', '', $file );
					$date_string = strtok( $first_line, '#' );
					$file_date   = strlen( $date_string ) < 15 ? gmdate( 'Y-m-d H:i:s', $date_string ) : 'unknown';

					$backups[] = $file_name . '&&' . $file_date;
				}
			}

			return $backups;
		}

		/**
		 * Checks the uploads/wppfm-revisions folder for .zip files and returns the names of these files.
		 *
		 * @since 3.13.0
		 * @return array containing the names of the revision files.
		 */
		public function get_list_of_available_revisions() {
			$revisions = array();
			$path      = WPPFM_REVISIONS_DIR;

			// List all .zip files in the directory.
			$files = glob( $path . '/*.zip' );

			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					$file_name = str_replace( WPPFM_REVISIONS_DIR . '/', '', $file );
					$revisions[] = $file_name;
				}
			}

			return $revisions;
		}

		/**
		 * Write the backup string to a .sql file in the backup folder.
		 *
		 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
		 *
		 * @param string $backup_file   the name of the backup file.
		 * @param string $backup_string a string containing the content of the backup.
		 *
		 * @return string with the result of the backup.
		 */
		public function write_full_backup_file( $backup_file, $backup_string ) {
			$wp_filesystem = wppfm_get_wp_filesystem();

			// Check if the backup directory is writable
			if ( ! $wp_filesystem->is_writable( WPPFM_BACKUP_DIR ) ) {
				return 'write_protected';
			}

			// Write the backup string to the file
			$result = $wp_filesystem->put_contents( $backup_file, $backup_string, FS_CHMOD_FILE );

			if ( false !== $result ) {
				return 'success';
			} else {
				return 'backup_failed';
			}
		}

		/**
		 * Get the names of the installed channels.
		 *
		 * @return array with the installed channels.
		 */
		public function get_installed_channels_from_file() {
			$active_channels = array();

			if ( file_exists( WPPFM_CHANNEL_DATA_DIR ) ) {
				$dir_iterator = new RecursiveDirectoryIterator( WPPFM_CHANNEL_DATA_DIR );
				$iterator     = new RecursiveIteratorIterator( $dir_iterator, RecursiveIteratorIterator::SELF_FIRST );

				foreach ( $iterator as $folder ) {
					if ( $folder->isDir() && $folder->getFilename() !== '.' & $folder->getFilename() !== '..' ) {
						$active_channels[] = $folder->getBaseName();
					}
				}
			}

			return $active_channels;
		}

		/**
		 * Takes the installed channel .zip file and unzips it in the channel folder.
		 *
		 * @param string $channel_name the name of the channel.
		 *
		 * @return bool false if installing the channel failed.
		 */
		public function unzip_channel_file( $channel_name ) {
			if ( ! file_exists( WPPFM_CHANNEL_DATA_DIR ) ) {
				WPPFM_Folders::make_channels_support_folder();
			}

			wppfm_get_wp_filesystem();

			$zip_file         = WPPFM_CHANNEL_DATA_DIR . '/' . $channel_name . '.zip';
			$destination_path = WPPFM_CHANNEL_DATA_DIR . '/';

			if ( ! file_exists( $zip_file ) ) {
				wppfm_write_log_file( sprintf( 'Failed installing the Channel %s. Could not download the .zip file from the server.', $channel_name ) );

				return false;
			}

			$unzip_result = unzip_file( $zip_file, $destination_path );

			if ( is_wp_error( $unzip_result ) ) {
				wppfm_handle_wp_errors_response( $unzip_result, sprintf( 'The installation of channel %s failed. Unable to unpack the channel file in folder %s.', $channel_name, WPPFM_CHANNEL_DATA_DIR ) );
			}

			wp_delete_file( $zip_file ); // clean up the zip file

			return true;
		}

		/**
		 * Deletes the channel source files and the channel folder.
		 *
		 * @param string $channel_short_name the short name of the channel.
		 */
		public function delete_channel_source_files( $channel_short_name ) {
			$channel_folder = WPPFM_CHANNEL_DATA_DIR . '/' . $channel_short_name;

			if ( file_exists( $channel_folder ) && is_dir( $channel_folder ) ) {
				// remove the channel definition files
				WPPFM_Folders::delete_folder( $channel_folder );
			}

			if ( 'google' === $channel_short_name ) {
				$free_version_google_folder = WPPFM_PLUGIN_DIR . 'includes/application/google';

				if ( file_exists( $free_version_google_folder ) && is_dir( $free_version_google_folder ) ) {
					WPPFM_Folders::delete_folder( $free_version_google_folder );
				}
			}
		}

		/**
		 * Deletes the channel feed files.
		 *
		 * @param int $channel_id the id of the channel.
		 */
		public function delete_channel_feed_files( $channel_id ) {
			$feeds = $this->_queries->get_feeds_from_specific_channel( $channel_id );

			foreach ( $feeds as $feed_id ) {
				$file_url  = $this->_queries->get_file_url_from_feed( $feed_id['product_feed_id'] );
				$file_name = basename( $file_url );
				$file_path = WPPFM_FEEDS_DIR . '/' . $file_name;

				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}
			}
		}

		public function get_previous_plugin_version_file_titles() {
			$previous_versions = array();
			$path              = WPPFM_REVISIONS_DIR;

			// List all .zip files in the directory.
			$files = glob( $path . '/*.zip' );

			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					$file_name = str_replace( WPPFM_REVISIONS_DIR . '/', '', $file );
					$previous_versions[] = $file_name;
				}
			}

			return $previous_versions;
		}
	}

	// end of WPPFM_File_Class

endif;
