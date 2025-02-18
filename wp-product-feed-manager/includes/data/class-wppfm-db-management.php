<?php /** @noinspection ALL */

/**
 * WP Db Management Class.
 *
 * @package WP Product Feed Manager/Data/Classes
 * @version 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Db_Management' ) ) :

	/**
	 * Db Management Class
	 */
	class WPPFM_Db_Management {

		/**
		 * Checks if a table exists in the database.
		 *
		 * @param   string  $table_name Name of the table to check.
		 *
		 * @return  boolean true if the table exists, false if not.
		 */
		public static function table_exists( $table_name ) {
			global $wpdb;

			// Check if the table can be queried
			$table = $wpdb->prefix . $table_name;
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
				return true;
			}

			return false;
		}

		/**
		 * Makes a duplicate of a selected feed and stores it in the database.
		 *
		 * @param   string  $feed_id    Feed id of the feed that needs to be duplicated.
		 *
		 * @return  boolean False if duplication process failed.
		 */
		public static function duplicate_feed( $feed_id ) {
			$queries_class = new WPPFM_Queries();
			$support_class = new WPPFM_Feed_Support();

			// Get the feed data.
			$feed_data = $queries_class->get_feed_row( $feed_id );

			// Get the meta data.
			$meta_data = $queries_class->read_metadata( $feed_id );

			// Get the Feed Filter data.
			$feed_filter_data = $queries_class->get_product_filter_query( $feed_id );

			// Get the category mapping.
			$category_mapping = $queries_class->read_category_mapping( $feed_id );

			// Generate a new unique feed name.
			$feed_data->title = $support_class->next_unique_feed_name( $feed_data->title );
			$feed_data->url   = __( 'Feed needs to be generated first', 'wp-product-feed-manager' );

			$feed_data_to_store = array(
				'channel_id'            => $feed_data->channel_id,
				'language'              => $feed_data->language,
				'is_aggregator'         => $feed_data->is_aggregator,
				'include_variations'    => $feed_data->include_variations,
				'country_id'            => $feed_data->country_id,
				'source_id'             => $feed_data->source_id,
				'title'                 => $feed_data->title,
				'feed_title'            => $feed_data->feed_title,
				'feed_description'      => $feed_data->feed_description,
				'main_category'         => $feed_data->main_category,
				'url'                   => $feed_data->url,
				'status_id'             => $feed_data->status_id,
				'schedule'              => $feed_data->schedule,
				'products'              => 0,
				'feed_type_id'          => $feed_data->feed_type_id,
				'aggregator_name'       => $feed_data->aggregator_name,
				'publisher_name'        => $feed_data->publisher_name,
				'publisher_favicon_url' => $feed_data->publisher_favicon_url,
				'currency'              => $feed_data->currency,
				'google_analytics'      => $feed_data->google_analytics,
				'utm_id'                => $feed_data->utm_id,
				'utm_source'            => $feed_data->utm_source,
				'utm_medium'            => $feed_data->utm_medium,
				'utm_campaign'          => $feed_data->utm_campaign,
				'utm_source_platform'   => $feed_data->utm_source_platform,
				'utm_term'              => $feed_data->utm_term,
				'utm_content'           => $feed_data->utm_content,
			);

			$feed_data_types = array(
				'%d',
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			);

			// Store a copy of the new feed in the database.
			$new_feed_id = $queries_class->create_feed( $feed_data_to_store, $feed_data_types );

			$new_feed_id > 0 ? $queries_class->insert_meta_data( $new_feed_id, $meta_data, $feed_filter_data, $category_mapping ) : false;
		}

		/**
		 * Backups all plugin related data from the database to a file
		 *
		 * @since 1.7.2
		 *
		 * @param string $backup_file_name the file name under which to store the backup.
		 *
		 * @return boolean true if the backup is successfull, false if not.
		 */
		public static function backup_database_tables( $backup_file_name ) {
			$backup_class = new WPPFM_Backup();
			$file_class   = new WPPFM_File();

			$backup_file = WPPFM_BACKUP_DIR . '/' . $backup_file_name . '.sql';
			$backup_path = str_replace( '\\', '/', $backup_file );

			// Prepare the folder structure to support saving backup files.
			if ( ! file_exists( WPPFM_BACKUP_DIR ) ) {
				WPPFM_Folders::make_backup_folder();
			}

			if ( ! file_exists( $backup_path ) ) {
				$backup_file_text = $backup_class->read_full_backup_data();

				return $file_class->write_full_backup_file( $backup_path, $backup_file_text );
			} else {
				return 'name_exists';
			}
		}

		/**
		 * Checks the existing backup files for non-compliant versions
		 *
		 * @since 1.8.0
		 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
		 *
		 * @return boolean true if a non-compliant backup file exists
		 */
		public static function invalid_backup_exist() {
			if ( ! file_exists( WPPFM_BACKUP_DIR ) ) {
				return false;
			}

			$wp_filesystem = wppfm_get_wp_filesystem();
			$files         = wppfm_list_sql_files( WPPFM_BACKUP_DIR );

			if ( count( $files ) === 0 ) {
				return false;
			}

			foreach ( $files as $file ) {
				$backup_string = $wp_filesystem->get_contents( $file );

				// get the db version
				$backup_version_string = ltrim( substr( $backup_string, stripos( $backup_string, '#' ) ), '#' );
				$backup_db_version     = substr( $backup_version_string, 0, strpos( $backup_version_string, '#' ) );

				if ( $backup_db_version < get_option( 'wppfm_db_version' ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Restores the data from a backup file
		 *
		 * @since 1.7.2
		 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
		 *
		 * @param string name of the backup file
		 *
		 * @return boolean if restored successfully, string when not
		 */
		public static function restore_backup( $backup_file_name ) {
			$backup_class  = new WPPFM_Backup();
			$wp_filesystem = wppfm_get_wp_filesystem();

			$backup_file = WPPFM_BACKUP_DIR . '/' . $backup_file_name;
			$backup_path = str_replace( '\\', '/', $backup_file );

			$current_db_version = get_option( 'wppfm_db_version' );

			if ( file_exists( $backup_path ) ) {
				$table_queries = array();
				$backup_string = $wp_filesystem->get_contents( $backup_file );

				// remove the date string
				$backup_string = substr( $backup_string, stripos( $backup_string, '#' ) );

				// get the db version
				$backup_db_version = ltrim( $backup_string, '#' );
				$backup_db_version = substr( $backup_db_version, 0, strpos( $backup_db_version, '#' ) );

				if ( $backup_db_version < $current_db_version ) {
					return __( 'The backup file is of an older version of the database and can not be restored as it is not compatible with the current database.', 'wp-product-feed-manager' );
				}

				// remove the version
				$backup_string = self::remove_left_data_part( $backup_string );

				// remove the ftp passive setting
				$backup_string = self::remove_left_data_part( $backup_string );

				// reset the auto feed fix setting
				$auto_feed_fix_setting = ltrim( $backup_string, '#' );
				$auto_feed_fix_setting = substr( $auto_feed_fix_setting, 0, strpos( $auto_feed_fix_setting, '#' ) );
				update_option( 'wppfm_auto_feed_fix', $auto_feed_fix_setting );

				// remove the auto feed fix setting
				$backup_string = self::remove_left_data_part( $backup_string );

				// reset the third party attributes string
				$third_party_attributes_string = ltrim( $backup_string, '#' );
				$third_party_attributes_string = substr( $third_party_attributes_string, 0, strpos( $third_party_attributes_string, '#' ) );
				update_option( 'wppfm_third_party_attribute_keywords', $third_party_attributes_string );

				// remove the third party attributes string
				$backup_string = self::remove_left_data_part( $backup_string );

				// reset the disable background option
				$disable_background_setting = ltrim( $backup_string, '#' );
				$disable_background_setting = substr( $disable_background_setting, 0, strpos( $disable_background_setting, '#' ) );
				update_option( 'wppfm_disabled_background_mode', $disable_background_setting );

				// remove the disable background option string
				$backup_string = self::remove_left_data_part( $backup_string );

				// reset the process logger option string
				$feed_logger_setting = ltrim( $backup_string, '#' );
				$feed_logger_setting = substr( $feed_logger_setting, 0, strpos( $feed_logger_setting, '#' ) );
				update_option( 'wppfm_process_logger_status', $feed_logger_setting );

				// remove the process logger option string
				$backup_string = self::remove_left_data_part( $backup_string );

				// reset the show product identifiers option string
				$show_product_identifiers_setting = ltrim( $backup_string, '#' );
				$show_product_identifiers_setting = substr( $show_product_identifiers_setting, 0, strpos( $show_product_identifiers_setting, '#' ) );
				update_option( 'wppfm_show_product_identifiers', $show_product_identifiers_setting );

				// remove the show product identifiers option string
				$backup_string = self::remove_left_data_part( $backup_string );

				// reset the WPML use full URL resolution option string
				$wpml_use_full_url_resolution_setting = ltrim( $backup_string, '#' );
				$wpml_use_full_url_resolution_setting = substr( $wpml_use_full_url_resolution_setting, 0, strpos( $wpml_use_full_url_resolution_setting, '#' ) );
				update_option( 'wppfm_use_full_url_resolution', $wpml_use_full_url_resolution_setting );

				// remove the use full url resolution option string
				$backup_string = self::remove_left_data_part( $backup_string );

				// split the string in table specific rows
				$table_strings = explode( '# backup string for database -> ', $backup_string );

				foreach ( $table_strings as $string ) {
					$table_name   = substr( $string, 0, stripos( $string, '#' ) );
					$query_string = substr( $string, stripos( $string, '#' ), strlen( $string ) );
					array_push( $table_queries, [ trim( $table_name ), ltrim( $query_string, '# <- # ' ) ] );
				}

				// remove the first (empty) element
				array_shift( $table_queries );

				return $backup_class->restore_backup_data( $table_queries );
			} else {
				return __( 'A backup file with the selected name does not exists.', 'wp-product-feed-manager' );
			}
		}

		/**
		 * Deletes an existing backup file.
		 *
		 * @since 1.7.2
		 * @since 3.11.0 changed the return value from a string to a boolean.
		 *
		 * @param bool true if the backup file is deleted, false if not.
		 */
		public static function delete_backup_file( $backup_file_name ) {
			$backup_file = realpath( WPPFM_BACKUP_DIR . '/' . $backup_file_name );

			/* translators: %s: Selected backup file */
			return file_exists( $backup_file ) ? wp_delete_file( $backup_file ) : false;
		}

		/**
		 * Duplicate an existing backup file
		 *
		 * @since 1.7.2
		 *
		 * @param string name of the backup file to be duplicated
		 */
		public static function duplicate_backup_file( $backup_file_name ) {
			$support_class = new WPPFM_Feed_Support();

			$backup_file_name_without_extension = rtrim( $backup_file_name, '.sql' );
			$new_backup_file_title              = $support_class->next_unique_feed_name( $backup_file_name_without_extension );
			$new_backup_file_name               = $new_backup_file_title . '.sql';

			if ( ! copy( WPPFM_BACKUP_DIR . '/' . $backup_file_name, WPPFM_BACKUP_DIR . '/' . $new_backup_file_name ) ) {
				/* translators: %s: selected backup file name */
				return sprintf( __( 'Failed to make a copy of %s', 'wp-product-feed-manager' ), $backup_file_name );
			} else {
				return true;
			}
		}

		/**
		 * Clears several options that could hinder a new feed process.
		 */
		public static function clean_options_table() {
			// @since 2.10.0.
			delete_option( 'wppfm_processed_products' );

			// @since 3.11.0
			delete_site_transient( 'wppfm_background_process_is_active' );

			$queries_class = new WPPFM_Queries();
			$queries_class->clear_feed_batch_options();
			// also clear the multi site feed batch data
			if ( is_multisite() ) {
				$queries_class->clear_feed_batch_sitemeta();
			}
		}

		/**
		 * Resets the status_id's of failed feeds.
		 *
		 * @since 2.7.0
		 */
		public static function reset_status_of_failed_feeds() {
			$queries_class = new WPPFM_Queries();
			$queries_class->reset_all_feed_status();
		}

		private static function remove_left_data_part( $data_string ) {
			$ds = ltrim( $data_string, '#' );

			return substr( $ds, stripos( $ds, '#' ) );
		}
	}


endif;
