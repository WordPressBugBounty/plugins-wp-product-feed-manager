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
 * @param wp_error $response Object wit the error message.
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
 * Enables writing log files in the plugin folder.
 *
 * @since 1.5.1
 * @since 2.41.0 error log files should go to the wp-content folder.
 * @since 2.42.0 fixed an error where the error file was not placed in the wp-content folder.
 *
 * @param string $error_message The error message to write.
 * @param string $filename      The file name in which the error message is written (default 'error').
 */
function wppfm_write_log_file( $error_message, $filename = 'debug' ) {
	$file = 'error' === $filename ? WP_CONTENT_DIR . '/' . $filename . '.log' : WPPFM_PLUGIN_DIR . $filename . '.log';

	if ( is_null( $error_message ) || is_string( $error_message ) || is_int( $error_message ) || is_bool( $error_message ) || is_float( $error_message ) ) {
		$message_line = $error_message;
	} elseif ( is_array( $error_message ) || is_object( $error_message ) ) {
		$message_line = wp_json_encode( $error_message );
	} else {
		$message_line = 'ERROR! Could not write messages of type ' . gettype( $error_message );
	}

	if ( false === wppfm_append_line_to_file( $file, gmdate( 'Y-m-d H:i:s', time() ) . ' - ' . ucfirst( $filename ) . ' Message: ' . $message_line, true ) ) {
		/* translators: %s: Error message */
		wppfm_show_wp_error( sprintf( __( 'There was an error but I was unable to store the error message in the log file. The message was %s', 'wp-product-feed-manager' ), $error_message ) );
	}
}

/**
 * Shows a message to inform the user that he has to update the WooCommerce plugin.
 */
function wppfm_update_your_woocommerce_version_message() {
	// To prevent several PHP Warnings if the WC folder name has been changed whilst the plugin is still registered.
	// @since 2.11.0.
	if ( file_exists( WPPFM_PLUGIN_DIR . '../woocommerce/woocommerce.php' ) ) {
		$wc_version = get_plugin_data( WPPFM_PLUGIN_DIR . '../woocommerce/woocommerce.php' )['Version'];
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
 * Writes a http_requests_error.log file in the plugin folder when there is a normal http request failed.
 *
 * @since 1.9.0
 *
 * @param string $response
 * @param array  $args
 * @param string $url
 *
 * @return string
 */
function wppfm_log_http_requests( $response, $args, $url ) {
	if ( false !== is_wp_error( $response ) && wppfm_on_any_own_plugin_page() ) {
		$logfile = WPPFM_PLUGIN_DIR . 'http_request_error.log';
		wppfm_append_line_to_file( $logfile, sprintf( "### %s, URL: %s\r\nREQUEST: %sRESPONSE: %s\r\n", gmdate( 'c' ), $url, wp_json_encode( $args, true ), wp_json_encode( $response, true ) ) );
	}

	return $response;
}

// Hook into WP_Http::_dispatch_request().
add_filter( 'http_response', 'wppfm_log_http_requests', 10, 3 );
