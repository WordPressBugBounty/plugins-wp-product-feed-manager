<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts the logging
 *
 * @since 2.7.0
 * @param string $feed_id
 * @param bool $silent
 */
function wppfm_logger_prepare_logging( $feed_id, $silent ) {
	if ( $feed_id ) {
		WPPFM_Feed_Process_Logging::initiate_feed_process_logging( $feed_id, $silent );
	}
}

add_action( 'wppfm_feed_process_prepared', 'wppfm_logger_prepare_logging', 10, 6 );

/**
 * Adds a message to the logging
 *
 * @since 2.7.0
 * @param string $feed_id
 * @param string $message
 * @param string $tag options are MESSAGE, NOTICE, WARNING and ERROR
 */
function wppfm_logger_handle_feed_generation_message( $feed_id, $message, $tag = 'MESSAGE' ) {
	if ( $message ) {
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $message, $tag );
	}
}

add_action( 'wppfm_feed_generation_message', 'wppfm_logger_handle_feed_generation_message', 10, 3 );

function wppfm_logger_feed_queue_filled_message( $feed_id, $nr_products ) {
	if ( $feed_id ) {
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, sprintf( 'Pushed %s products in the feed queue', $nr_products ) );
	}
}

add_action( 'wppfm_feed_queue_filled', 'wppfm_logger_feed_queue_filled_message', 10, 2 );

function wppfm_logger_started_batch( $feed_id, $memory_limit, $items_in_batch ) {
	if ( $feed_id ) {
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, sprintf( 'Started a new batch with %s memory available. The stored batch still contains %d items', $memory_limit, $items_in_batch - 1 ) );
	}
}

add_action( 'wppfm_feed_processing_batch_activated', 'wppfm_logger_started_batch', 10, 3 );

function wppfm_logger_started_product_processing( $feed_id, $product_id ) {
	if ( $feed_id ) {
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, sprintf( 'Started processing product %s', $product_id ) );
	}
}

add_action( 'wppfm_started_product_processing', 'wppfm_logger_started_product_processing', 10, 2 );

function wppfm_logger_add_product_to_feed_message( $feed_id, $product_id ) {
	if ( $feed_id ) {
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, sprintf( 'Added product %s to the feed', $product_id ) );
	}
}

add_action( 'wppfm_add_product_to_feed', 'wppfm_logger_add_product_to_feed_message', 10, 2 );

function wppfm_logger_activated_next_batch_message( $feed_id ) {
	if ( $feed_id ) {
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, 'Starting a next batch' );
	}
}

add_action( 'wppfm_activated_next_batch', 'wppfm_logger_activated_next_batch_message', 10, 1 );

function wppfm_logger_completed_a_feed_message( $feed_id ) {
	if ( $feed_id ) {
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, 'Completed the feed' );
	}
}

add_action( 'wppfm_complete_a_feed', 'wppfm_logger_completed_a_feed_message', 10, 1 );

/**
 * Logs UI stall failure with an optional diagnostic snapshot.
 *
 * @param string               $feed_id               Feed id.
 * @param int                  $ids_remaining_in_queue Products left in queue.
 * @param array<string, mixed> $stall_diagnostics     Optional diagnostic context.
 */
function wppfm_logger_processing_stopped_message( $feed_id, $ids_remaining_in_queue, $stall_diagnostics = array() ) {
	if ( ! $feed_id ) {
		return;
	}

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging(
		$feed_id,
		sprintf(
			'Feed processing stopped as the file size did not increase anymore with still %s products in the queue',
			$ids_remaining_in_queue
		),
		'ERROR'
	);

	if ( ! empty( $stall_diagnostics ) && class_exists( 'WPPFM_Feed_Processing_Diagnostics' ) ) {
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging(
			$feed_id,
			WPPFM_Feed_Processing_Diagnostics::format_diagnostic_block( $stall_diagnostics ),
			'ERROR'
		);
	}
}

add_action( 'wppfm_feed_processing_failed_file_size_stopped_increasing', 'wppfm_logger_processing_stopped_message', 10, 3 );

function wppfm_logger_feed_generation_warning_message( $feed_id, $message ) {
	if ( $feed_id ) {
		if ( is_wp_error( $message ) ) {
			$err_message = method_exists( $message, 'get_error_messages' ) ? $message->get_error_messages() : array( 'Error unknown' );
			$message     = ! empty( $err_message ) ? implode( ' :: ', $err_message ) : 'Error unknown!';
		}

		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $message, 'WARNING' );
	}
}

add_action( 'wppfm_feed_generation_warning', 'wppfm_logger_feed_generation_warning_message', 10, 2 );

/**
 * Logs the feeds url.
 *
 * @since 2.8.0
 *
 * @param $feed_id
 * @param $feed_url
 */
function wppfm_logger_register_feed_url( $feed_id, $feed_url ) {
	if ( $feed_id ) {
		$url_message = sprintf( 'The feeds url = %s.', $feed_url );

		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $url_message );
	}
}

add_action( 'wppfm_register_feed_url', 'wppfm_logger_register_feed_url', 10, 2 );

/**
 * Registers the wp_remote_post arguments used when dispatching a feed update
 *
 * @since 2.8.0
 *
 * @param $feed_id
 * @param $url
 * @param $args
 */
function wppfm_logger_remote_post_arguments( $feed_id, $url, $args ) {
	if ( ! $feed_id ) {
		return;
	}

	$args_body_string   = '';
	$cookie_names       = array();

	foreach ( $args as $key => $value ) {
		if ( 'body' === $key ) {
			$args_body_string = null !== $value ? wppfm_recursive_implode( $value, ', ', true, false ) : 'empty';
		} elseif ( 'cookies' === $key && is_array( $value ) ) {
			$cookie_names = array_keys( $value );
		}
	}

	$blocking     = isset( $args['blocking'] ) ? ( false === $args['blocking'] ? 'false' : 'true' ) : 'n/a';
	$sslverify    = isset( $args['sslverify'] ) ? ( $args['sslverify'] ? 'true' : 'false' ) : 'n/a';
	$post_body    = ! empty( $args['body'] ) ? 'yes' : 'no';
	$host_header  = 'not_set';
	$headers      = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();

	if ( isset( $headers['Host'] ) && '' !== (string) $headers['Host'] ) {
		$host_header = sanitize_text_field( (string) $headers['Host'] );
	}

	$message  = "Feed Update dispatched.\r\n";
	$message .= sprintf( "Dispatched url = %s\r\n", esc_url_raw( $url ) );
	$message .= sprintf( "Dispatched loopback_host = %s\r\n", $host_header );
	$message .= sprintf( "Dispatched post_body = %s\r\n", $post_body );
	$message .= sprintf( "Dispatched blocking = %s\r\n", $blocking );
	$message .= sprintf( "Dispatched sslverify = %s\r\n", $sslverify );
	$message .= sprintf( "Dispatched args body = %s\r\n", $args_body_string );
	$message .= sprintf(
		'Dispatched cookie names = %s',
		! empty( $cookie_names ) ? implode( ', ', array_map( 'sanitize_key', $cookie_names ) ) : 'none'
	);

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $message );
}

add_action( 'wppfm_register_remote_post_args', 'wppfm_logger_remote_post_arguments', 10, 3 );

/**
 * Registers when the memory limit of a batch is reached
 *
 * @since 2.7.0
 *
 * @param   string  $feed_id                    Id of the active feed.
 * @param   string  $current_memory             Memory used in this batch.
 * @param   string  $memory_limit               Current active memory limit.
 * @param   int     $products_handled_by_batch  Number of products handled by this batch. // @since 2.12.0
 */
function wppfm_logger_batch_memory_limit_exceeded( $feed_id, $current_memory, $memory_limit, $products_handled_by_batch ) {
	$batch_counter = get_option( 'wppfm_batch_counter', 1 ); // @since 2.12.0.
	$nr_of_products_in_queue = WPPFM_Feed_Controller::nr_ids_remaining_in_product_queue(); // $since 3.9.0.

	$message = sprintf( 'Batch nr %d memory limit reached. Currently %s bytes used with a limit of %s bytes. This batch added %d products to the feed, leaving %d products in the product queue', $batch_counter, $current_memory, $memory_limit, $products_handled_by_batch, $nr_of_products_in_queue );

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $message );
}

add_action( 'wppfm_batch_memory_limit_exceeded', 'wppfm_logger_batch_memory_limit_exceeded', 10, 4 );

/**
 * Registers when the time limit of a batch is reached
 *
 * @since 2.7.0
 *
 * @param   string  $feed_id                    Id of the active feed.
 * @param   string  $time_limit                 Current active time limit.
 * @param   int     $products_handled_by_batch  Number of products handled by this batch. // @since 2.12.0
 */
function wppfm_logger_batch_time_limit_exceeded( $feed_id, $time_limit, $products_handled_by_batch ) {
	$batch_counter = get_option( 'wppfm_batch_counter', 1 ); // @since 2.12.0.
	$nr_of_products_in_queue = WPPFM_Feed_Controller::nr_ids_remaining_in_product_queue(); // $since 3.9.0.

	$message = sprintf( 'Batch nr %d time limit reached. Current limit is %s seconds. This batch added %d products to the feed, leaving %d products in the feed queue', $batch_counter, $time_limit, $products_handled_by_batch, $nr_of_products_in_queue );

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $message );
}

add_action( 'wppfm_batch_time_limit_exceeded', 'wppfm_logger_batch_time_limit_exceeded', 10, 3 );

/**
 * Registers the response of a remote post-call
 *
 * @since 2.41.0
 *
 * @param   string          $feed_id     Id of the active feed.
 * @param   WP_Error|array  $response    Response of the remote post-call.
 */
function wppfm_logger_wp_remote_post( $feed_id, $response ) {
	if ( $feed_id ) {
		if ( ! is_wp_error( $response ) ) {
			$response_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( is_array( $response_data ) ) {
				$response_data = wppfm_recursive_implode( $response_data, ', ', true, false );
			}

			$response_data = $response_data ?: 'no response data';
			$response_code = json_decode( wp_remote_retrieve_response_code( $response ), true );
			$response_code = $response_code ?: 'no response code';
			$message       = sprintf( 'Response on dispatched (wp_remote_post) call is -> code: %s, message: %s', $response_code, $response_data );

			WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $message );
		} else {
			$err_message = method_exists( $response, 'get_error_messages' ) ? $response->get_error_messages() : array( 'Error unknown' );
			$err_code    = method_exists( $response, 'get_error_code' ) ? $response->get_error_code() : 'Error unknown';

			if ( is_array( $err_message ) ) {
				$err_message = wppfm_recursive_implode( $err_message, ', ', true, false );
			}

			// @since 3.11.0 replaced the use of str_contains to using strpos for pre 8.0 versions of PHP.
			if ( strpos( $err_message, 'cURL error 28' ) !== false ) {
				$message = 'The known cURL error has been returned by the wp_remote_post call. The full error message is: ' . $err_message . '. Error code: ' . $err_code;
				$tag     = 'MESSAGE';
			} else {
				$message  = ! empty( $err_message ) ? $err_message : 'Error unknown!';
				$message .= '. Error code: ' . $err_code;
				$tag      = 'ERROR';
			}

			WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $message, $tag );
		}
	} else {
		$message = 'Feed ID not found.';
		WPPFM_Feed_Process_Logging::add_to_feed_process_logging( $feed_id, $message, 'ERROR' );
	}
}

add_action( 'wppfm_wp_remote_post_response', 'wppfm_logger_wp_remote_post', 10, 2 );

/**
 * Logs dispatch failure details to the feed processing log.
 *
 * @param string              $feed_id        Feed id.
 * @param array|WP_Error|null $response       Remote response.
 * @param string              $url            Dispatch URL.
 * @param array               $args           Request args.
 * @param string              $classification Outcome token.
 */
function wppfm_logger_dispatch_failed( $feed_id, $response, $url, $args, $classification ) {
	if ( ! $feed_id ) {
		return;
	}

	$error_message = is_wp_error( $response ) ? $response->get_error_message() : '';

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging(
		$feed_id,
		sprintf(
			'Dispatch failed (outcome=%s, url=%s%s). Pending dispatch marker set; recovery cron scheduled.',
			$classification,
			esc_url_raw( $url ),
			'' !== $error_message ? ', error=' . $error_message : ''
		),
		'ERROR'
	);
}

add_action( 'wppfm_dispatch_failed', 'wppfm_logger_dispatch_failed', 10, 5 );

/**
 * Logs successful dispatch outcomes when verbose feed logging is enabled.
 *
 * @param string              $feed_id        Feed id.
 * @param string              $classification Outcome token.
 * @param array|WP_Error|null $response       Remote response.
 * @param string              $url            Dispatch URL.
 * @param array               $args           Request args.
 */
function wppfm_logger_dispatch_completed( $feed_id, $classification, $response, $url, $args ) {
	if ( ! $feed_id || 'success' !== $classification ) {
		return;
	}

	if ( ! class_exists( 'WPPFM_Feed_Processing_Diagnostics' ) || ! WPPFM_Feed_Processing_Diagnostics::verbose_logging_enabled() ) {
		return;
	}

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging(
		$feed_id,
		sprintf( 'Dispatch completed successfully (url=%s).', esc_url_raw( $url ) ),
		'MESSAGE'
	);
}

add_action( 'wppfm_dispatch_completed', 'wppfm_logger_dispatch_completed', 10, 5 );

/**
 * Logs when the cron health check exits without calling handle().
 *
 * @param string $reason     Skip reason code.
 * @param string $feed_id    Feed id context.
 * @param string $cron_hook  Recovery hook name.
 */
function wppfm_logger_cron_health_check_skipped( $reason, $feed_id, $cron_hook ) {
	if ( ! function_exists( 'wppfm_process_logger_is_active' ) || ! wppfm_process_logger_is_active() ) {
		return;
	}

	$resolved_feed_id = WPPFM_Feed_Process_Logging::resolve_log_feed_id( $feed_id );

	if ( '' === $resolved_feed_id ) {
		return;
	}

	$recovery_next = wp_next_scheduled( $cron_hook );
	$recovery_in   = $recovery_next > 0 ? max( 0, $recovery_next - time() ) : 'not_scheduled';

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging(
		$resolved_feed_id,
		sprintf(
			'Cron health check skipped (reason=%s, recovery_hook=%s, recovery_in=%s).',
			$reason,
			$cron_hook,
			is_numeric( $recovery_in ) ? $recovery_in . 's' : $recovery_in
		),
		'WARNING'
	);
}

add_action( 'wppfm_cron_health_check_skipped', 'wppfm_logger_cron_health_check_skipped', 10, 3 );

/**
 * Logs when the watchdog/cron resumes a batch via handle().
 *
 * @param string $feed_id Feed id.
 * @param string $context Resume context.
 */
function wppfm_logger_batch_resume_via_handle( $feed_id, $context ) {
	if ( ! $feed_id ) {
		return;
	}

	WPPFM_Feed_Process_Logging::add_to_feed_process_logging(
		$feed_id,
		sprintf( 'Resuming existing batch via handle() (context=%s).', sanitize_key( (string) $context ) ),
		'WARNING'
	);

	if ( class_exists( 'WPPFM_Feed_Processing_Diagnostics' ) ) {
		WPPFM_Feed_Processing_Diagnostics::maybe_log_verbose_snapshot( $feed_id, 'batch_resume' );
	}
}

add_action( 'wppfm_batch_resume_via_handle', 'wppfm_logger_batch_resume_via_handle', 10, 2 );

/**
 * Verbose diagnostics after each batch handoff dispatch.
 *
 * @param string $feed_id Feed id.
 */
function wppfm_logger_next_batch_handoff( $feed_id ) {
	if ( class_exists( 'WPPFM_Feed_Processing_Diagnostics' ) ) {
		WPPFM_Feed_Processing_Diagnostics::maybe_log_verbose_snapshot( $feed_id, 'next_batch_handoff' );
	}
}

add_action( 'wppfm_activated_next_batch', 'wppfm_logger_next_batch_handoff', 20, 1 );
