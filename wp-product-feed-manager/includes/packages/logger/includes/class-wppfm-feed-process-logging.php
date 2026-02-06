<?php

/* * ******************************************************************
 * Version 1.0
 * Package: Logger
 * Modified: 18-10-2019
 * Copyright 2019 Accentio. All rights reserved.
 * License: None
 * By: Michel Jongbloed
 * ****************************************************************** */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPPFM_Feed_Process_Logging Class
 */
class WPPFM_Feed_Process_Logging {
	/**
	 * Initiates the logging of a feed process and writes a header to the logging file
	 *
	 * @since 2.7.0
	 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
	 * @param string $feed_id
	 * @param bool   $silent identifies if the feed process has been started manually or automatically
	 */
	public static function initiate_feed_process_logging( $feed_id, $silent ) {
		$wp_filesystem = wppfm_get_wp_filesystem();

		WPPFM_Logging_Folders::make_logs_folder();

		$log_file_name         = self::generate_log_file_name( $feed_id );
		$background_processing = 'true' === get_option( 'wppfm_disabled_background_mode', 'false' ) ? 'foreground' : 'background';
		$starter               = $silent ? 'through a cron' : 'manually';
		$log_header            = sprintf(
			'%sGenerating feed %s %s initiated in %s mode.',
			self::generate_log_tag( 'MESSAGE' ),
			$feed_id,
			$starter,
			$background_processing
		);

		$wp_filesystem->put_contents( WPPFM_LOGGINGS_DIR . '/' . $log_file_name, '', FS_CHMOD_FILE ); // start with a clear log
		$wp_filesystem->put_contents( WPPFM_LOGGINGS_DIR . '/' . $log_file_name, $log_header . "\r\n", FS_CHMOD_FILE );
	}

	/**
	 * Adds a message to the logging file
	 *
	 * @since 2.7.0
	 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
	 * @param string $feed_id the id of the feed.
	 * @param string $message the message to be logged.
	 * @param string $tag     the tag to be used in the log entry.
	 */
	public static function add_to_feed_process_logging( $feed_id, $message, $tag = 'MESSAGE' ) {
		$log_file_name = self::generate_log_file_name( $feed_id );
		$log_message   = self::generate_log_tag( $tag ) . $message;
		$file_path     = WPPFM_LOGGINGS_DIR . '/' . $log_file_name;

		wppfm_append_line_to_file( $file_path, $log_message, true );
	}

	/**
	 * Closes the logging of a feed process and writes a footer to the logging file
	 *
	 * @since 2.7.0
	 * @since 3.12.0 - Switched from using file_put_contents to WP_Filesystem.
	 * @param string $feed_id the id of the feed.
	 * @param string $status  the status of the feed process.
	 */
	public static function close_feed_process_logging( $feed_id, $status = 'ok' ) {
		$log_file_name = self::generate_log_file_name( $feed_id );
		$file_path     = WPPFM_LOGGINGS_DIR . '/' . $log_file_name;

		$message = 'ok' === $status ? 'Feed processing ended' : $status;
		$level   = 'ok' === $status ? 'MESSAGE' : 'ERROR';

		$log_message = self::generate_log_tag( $level ) . $message;

		wppfm_append_line_to_file( $file_path, $log_message, true );
	}

	/**
	 * Makes a file name for the logging file
	 *
	 * @since 2.7.0
	 * @param  string $feed_id
	 * @return string with the name of the logging file
	 */
	private static function generate_log_file_name( $feed_id ) {
		return 'feed-' . $feed_id . '-processing.log';
	}

	/**
	 * Generates the prefix for every log entry
	 *
	 * @since 2.7.0
	 * @param  string $level options are MESSAGE, ERROR or WARNING
	 * @return string
	 */
	private static function generate_log_tag( $level ) {
		return sprintf( '[%s]-[%s]=', gmdate( 'Y-m-d H:i:s', time() ), $level );
	}
}

// End of WPPFM_Feed_Process_Logging Class
