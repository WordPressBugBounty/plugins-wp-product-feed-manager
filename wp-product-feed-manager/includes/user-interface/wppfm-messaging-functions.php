<?php

/**
 * @package WP Product Feed Manager/User Interface/Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a standard WordPress error message.
 *
 * @param string $message                  The message to show.
 * @param bool   $dismissible              Is dismissible or not (default false).
 * @param string $permanent_dismissible_id Permanent dismissible id (default '').
 */
function wppfm_show_wp_error( $message, $dismissible = false, $permanent_dismissible_id = '' ) {
	wppfm_show_wp_message( $message, 'error', $dismissible, $permanent_dismissible_id );
}

/**
 * Shows a standard WordPress warning message.
 *
 * @param string $message                  The message to show.
 * @param bool   $dismissible              Is dismissible or not (default false).
 * @param string $permanent_dismissible_id Permanent dismissible id (default '').
 */
function wppfm_show_wp_warning( $message, $dismissible = false, $permanent_dismissible_id = '' ) {
	wppfm_show_wp_message( $message, 'warning', $dismissible, $permanent_dismissible_id );
}

/**
 * Shows a standard WordPress success message.
 *
 * @param string $message                  The message to show.
 * @param bool   $dismissible              Is dismissible or not (default false).
 * @param string $permanent_dismissible_id Permanent dismissible id (default '').
 */
function wppfm_show_wp_success( $message, $dismissible = false, $permanent_dismissible_id = '' ) {
	wppfm_show_wp_message( $message, 'success', $dismissible, $permanent_dismissible_id );
}

/**
 * Shows a standard WordPress info message.
 *
 * @param string $message                  The message to show.
 * @param bool   $dismissible              Is dismissible or not (default false).
 * @param string $permanent_dismissible_id Permanent dismissible id (default '').
 */
function wppfm_show_wp_info( $message, $dismissible = false, $permanent_dismissible_id = '' ) {
	wppfm_show_wp_message( $message, 'info', $dismissible, $permanent_dismissible_id );
}

/**
 * Shows a standard WordPress message.
 *
 * @param string $message                  The message to show.
 * @param string $type                     The message type (info, success, warning or error).
 * @param bool   $dismissible              Is dismissible or not.
 * @param string $permanent_dismissible_id Permanent dismissible id.
 */
function wppfm_show_wp_message( $message, $type, $dismissible, $permanent_dismissible_id ) {
	$dismissible_text    = $dismissible ? ' is-dismissible' : '';
	$perm_dismissible    = $permanent_dismissible_id ? ' id="disposable-warning-message"' : '';
	$dismiss_permanently = '' !== $permanent_dismissible_id ? '<p id=dismiss-permanently>dismiss permanently<p>' : '';

	echo '<div' . esc_attr( $perm_dismissible ) . ' class="notice notice-' . esc_attr( $type ) . esc_attr( $dismissible_text ) . '"><p>' . esc_html( $message ) . '</p>' . esc_attr( $dismiss_permanently ) . '</div>';
}

/**
 * Shows an error message to the user and writes an error log based on the wp_error given.
 *
 * @since 1.9.3
 *
 * @param wp_error $response Object with the error message.
 * @param string   $message  The error message to show.
 */
function wppfm_handle_wp_errors_response( $response, $message ) {
	$error_messages = method_exists( (object) $response, 'get_error_messages' ) ? $response->get_error_messages() : array( 'Error unknown' );
	$error_message  = method_exists( (object) $response, 'get_error_message' ) ? $response->get_error_message() : 'Error unknown';
	$error_text     = ! empty( $error_messages ) ? implode( ' :: ', $error_messages ) : 'error unknown!';

	wppfm_write_log_file( $message . ' ' . $error_text );

	wppfm_show_wp_error( $message . ' Error message: ' . $error_message );
}

/**
 * Returns an absolute path for a log file under the uploads directory (WPPFM_LOGGINGS_DIR / wppfm-logs).
 *
 * Plugin review: logs must never be written under the plugin package directory. This uses the same
 * uploads-backed folder as feed process logging.
 *
 * @since 3.23.0
 *
 * @param string $filename_base Log file base name without extension (e.g. debug, http_request_error).
 * @return string Full path to the .log file.
 */
function wppfm_get_plugin_log_file_path( $filename_base ) {
	$safe_base = sanitize_file_name( (string) $filename_base );

	if ( '' === $safe_base ) {
		$safe_base = 'debug';
	}

	if ( defined( 'WPPFM_LOGGINGS_DIR' ) ) {
		$log_dir = WPPFM_LOGGINGS_DIR;
	} else {
		$upload = wp_upload_dir( null, false );

		if ( ! empty( $upload['error'] ) ) {
			// Avoid the plugin directory if uploads are misconfigured; still keep outside wp-content/plugins.
			$log_dir = WP_CONTENT_DIR . '/wppfm-logs';
		} else {
			$log_dir = trailingslashit( $upload['basedir'] ) . 'wppfm-logs';
		}
	}

	$log_dir = untrailingslashit( $log_dir );
	wp_mkdir_p( $log_dir );

	return $log_dir . '/' . $safe_base . '.log';
}

/**
 * Writes a line to a log file under the uploads directory (never inside the plugin folder).
 *
 * @since 1.5.1
 * @since 2.41.0 Error log files moved toward wp-content; @since 2.42.0 path fixes.
 * @since 3.23.0 All log names use WPPFM_LOGGINGS_DIR / uploads (Plugin Directory guideline).
 *
 * @param string $error_message The error message to write.
 * @param string $filename      Base log file name without extension (default 'debug').
 */
function wppfm_write_log_file( $error_message, $filename = 'debug' ) {
	$file = wppfm_get_plugin_log_file_path( $filename );

	if ( is_null( $error_message ) || is_string( $error_message ) || is_int( $error_message ) || is_bool( $error_message ) || is_float( $error_message ) ) {
		$message_line = $error_message;
	} elseif ( is_array( $error_message ) || is_object( $error_message ) ) {
		$message_line = wp_json_encode( $error_message );
	} else {
		$message_line = 'ERROR! Could not write messages of type ' . gettype( $error_message );
	}

	$log_line = gmdate( 'Y-m-d H:i:s', time() ) . ' - ' . ucfirst( $filename ) . ' Message: ' . $message_line;

	if ( false === wppfm_append_line_to_file( $file, $log_line, true ) ) {
		/* translators: %s: Error message */
		wppfm_show_wp_error( sprintf( __( 'There was an error but I was unable to store the error message in the log file. The message was %s', 'wp-product-feed-manager' ), $error_message ) );
	}

	wppfm_maybe_mirror_log_line_to_feed_process_log( $filename, $message_line );
}

/**
 * Copies general debug log lines into the active feed process log when enabled.
 *
 * @param string $filename     Log channel name (e.g. debug).
 * @param string $message_line Normalized message text.
 *
 * @return void
 */
function wppfm_maybe_mirror_log_line_to_feed_process_log( $filename, $message_line ) {
	if ( ! apply_filters( 'wppfm_mirror_debug_log_to_feed_process_log', true ) ) {
		return;
	}

	if ( ! function_exists( 'wppfm_process_logger_is_active' ) || ! wppfm_process_logger_is_active() ) {
		return;
	}

	if ( ! class_exists( 'WPPFM_Feed_Process_Logging' ) ) {
		return;
	}

	$feed_id = WPPFM_Feed_Process_Logging::resolve_log_feed_id( '' );

	if ( '' === $feed_id ) {
		return;
	}

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging(
		$feed_id,
		sprintf( '[%s] %s', sanitize_key( (string) $filename ), $message_line ),
		'DEBUG'
	);
}

/**
 * Shows a message to inform the user that he has to update the WooCommerce plugin.
 *
 * @since 3.16.0 - Changed the use of WPPFM_PLUGIN_DIR + '..' to find the plugins folder, to the use of WP_PLUGIN_DIR.
 */
function wppfm_update_your_woocommerce_version_message() {
	// To prevent several PHP Warnings if the WC folder name has been changed whilst the plugin is still registered.
	// @since 2.11.0.
	// Use dirname() to get the plugins directory from the plugin directory.
	$wc_plugin_file = dirname( WPPFM_PLUGIN_DIR ) . '/woocommerce/woocommerce.php';
	if ( file_exists( $wc_plugin_file ) ) {
		$wc_version = get_plugin_data( $wc_plugin_file )['Version'];
	} else {
		$wc_version = '"UNKNOWN"';
	}

	echo '<div class="wppfm-full-screen-message-field">
		<div class="wppfm-warning-message__icon"><img src="' . esc_url( WPPFM_PLUGIN_URL . '/images/alert.png' ) . '" alt="Alert" /></div>
		<div class="wppfm-warning-message__content">';
	echo '<p>*** ' . sprintf(
		/* translators: %1$s: minimum version of the WooCommerce plugin, %2$s: installed version of the WooCommerce plugin */
		esc_html__(
			'This plugin requires WooCommerce version %1$s as a minimum!
			It seems you have installed WooCommerce version %2$s which is a version that is not supported.
			Please update to the latest version ***',
			'wp-product-feed-manager'
		),
		esc_html( WPPFM_MIN_REQUIRED_WC_VERSION ),
		esc_html( $wc_version )
	) . '</p>';
	echo '</div></div>';
}

/**
 * Shows a message to the user that WooCommerce is not installed on the server.
 */
function wppfm_you_have_no_woocommerce_installed_message() {
	echo '<div class="wppfm-full-screen-message-field">
		<div class="wppfm-warning-message__icon"><img src="' . esc_url( WPPFM_PLUGIN_URL . '/images/alert.png' ) . '" alt="Alert" /></div>
		<div class="wppfm-warning-message__content">';
	echo '<p>*** ' . esc_html__(
		'This plugin only works in conjunction with the WooCommerce Plugin!
				It seems you have not installed and activated the WooCommerce Plugin yet, so please do so before using this Plugin.',
		'wp-product-feed-manager'
	) . ' ***</p>';
	/* translators: %s: link to information about the WooCommerce plugin */
	echo '<p>' . sprintf( esc_html__( 'You can find more information about the Woocommerce Plugin %sby clicking here</a>.', 'wp-product-feed-manager' ), '<a href="https://wordpress.org/plugins/woocommerce/">' ) . '</p>';
	echo '</div></div>';
}

/**
 * Appends failed HTTP request details to http_request_error.log under the uploads log directory.
 *
 * @since 1.9.0
 * @since 3.23.0 Log file stored in WPPFM_LOGGINGS_DIR instead of the plugin directory.
 *
 * @param string|WP_Error $response HTTP response or WP_Error.
 * @param array           $args     Request arguments.
 * @param string          $url      Request URL.
 *
 * @return string|WP_Error Unchanged $response.
 */
function wppfm_log_http_requests( $response, $args, $url ) {
	if ( is_wp_error( $response ) && wppfm_on_any_own_plugin_page() ) {
		$logfile = wppfm_get_plugin_log_file_path( 'http_request_error' );
		wppfm_append_line_to_file( $logfile, sprintf( "### %s, URL: %s\r\nREQUEST: %sRESPONSE: %s\r\n", gmdate( 'c' ), $url, wp_json_encode( $args, true ), wp_json_encode( $response, true ) ) );
	}

	return $response;
}

// Hook into WP_Http::_dispatch_request().
add_filter( 'http_response', 'wppfm_log_http_requests', 10, 3 );
