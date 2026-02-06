<?php
/**
 * Feed health monitoring helpers.
 *
 * Tracks low-memory failures and surfaces actionable notices to operators.
 *
 * @package WP Product Feed Manager/Application/Functions
 * 
 * @since 3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wppfm_register_memory_failure_notice' ) ) {
	/**
	 * Store the latest memory failure so we can warn operators in the UI.
	 *
	 * @param string $feed_id                   Feed identifier.
	 * @param int    $current_memory_consumption Bytes used at the time of failure.
	 * @param int    $memory_cap                 Maximum bytes allowed (90% of PHP memory_limit).
	 */
	function wppfm_register_memory_failure_notice( $feed_id, $current_memory_consumption, $memory_cap ) {
		$payload = array(
			'timestamp'      => current_time( 'timestamp' ),
			'feed_id'        => $feed_id ? sanitize_text_field( $feed_id ) : '',
			'memory_limit'   => intval( $memory_cap ),
			'current_memory' => intval( $current_memory_consumption ),
		);

		set_site_transient( 'wppfm_last_memory_failure', $payload, DAY_IN_SECONDS );
	}

	add_action( 'wppfm_batch_memory_limit_exceeded', 'wppfm_register_memory_failure_notice', 20, 4 );
}

if ( ! function_exists( 'wppfm_handle_memory_warning_dismissal' ) ) {
	/**
	 * Allow admins to dismiss the warning until a new failure occurs.
	 */
	function wppfm_handle_memory_warning_dismissal() {
		if ( ! is_admin() || ! isset( $_GET['wppfm-dismiss-memory-warning'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'wppfm_dismiss_memory_warning' );

		update_site_option( 'wppfm_memory_warning_dismissed_at', current_time( 'timestamp' ) );

		wp_safe_redirect(
			remove_query_arg(
				array(
					'wppfm-dismiss-memory-warning',
					'_wpnonce',
				)
			)
		);
		exit;
	}

	add_action( 'admin_init', 'wppfm_handle_memory_warning_dismissal' );
}

if ( ! function_exists( 'wppfm_should_show_memory_warning_notice' ) ) {
	/**
	 * Decide whether the memory warning notice should be displayed.
	 *
	 * @return array|false
	 */
	function wppfm_should_show_memory_warning_notice() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$page_slug = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( empty( $page_slug ) || 'wp-product-feed-manager' !== $page_slug ) {
			return false;
		}

		$notice = get_site_transient( 'wppfm_last_memory_failure' );

		if ( false === $notice || empty( $notice['timestamp'] ) ) {
			return false;
		}

		$dismissed_at = intval( get_site_option( 'wppfm_memory_warning_dismissed_at', 0 ) );

		if ( $dismissed_at && $dismissed_at >= intval( $notice['timestamp'] ) ) {
			return false;
		}

		return $notice;
	}
}

if ( ! function_exists( 'wppfm_format_bytes_human_readable' ) ) {
	/**
	 * Convert bytes into a readable size.
	 *
	 * @param int $bytes Amount in bytes.
	 *
	 * @return string
	 */
	function wppfm_format_bytes_human_readable( $bytes ) {
		$bytes = intval( $bytes );

		if ( $bytes <= 0 ) {
			return '';
		}

		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$power = $bytes > 0 ? floor( log( $bytes, 1024 ) ) : 0;
		$power = min( $power, count( $units ) - 1 );
		$value = $bytes / pow( 1024, $power );

		return sprintf( '%s %s', number_format_i18n( $value, 1 ), $units[ $power ] );
	}
}

if ( ! function_exists( 'wppfm_render_memory_warning_notice' ) ) {
	/**
	 * Output the memory warning admin notice on the feed list page.
	 */
	function wppfm_render_memory_warning_notice() {
		$notice = wppfm_should_show_memory_warning_notice();

		if ( ! $notice ) {
			return;
		}

		$detected_limit = wppfm_format_bytes_human_readable( $notice['memory_limit'] );
		$time_since     = human_time_diff( intval( $notice['timestamp'] ), current_time( 'timestamp' ) );
		$dismiss_url    = wp_nonce_url( add_query_arg( 'wppfm-dismiss-memory-warning', '1' ), 'wppfm_dismiss_memory_warning' );

		$feed_fragment = '';

		if ( ! empty( $notice['feed_id'] ) ) {
			$feed_fragment = ' ' . sprintf(
				/* translators: %s feed ID. */
				esc_html__( '(first affected feed ID: %s)', 'wp-product-feed-manager' ),
				esc_html( $notice['feed_id'] )
			);
		}

		$cron_example = 'php -d memory_limit=256M /path/to/wp-cron.php';

		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Automatic feed updates are running out of memory.', 'wp-product-feed-manager' ); ?></strong></p>
			<p>
				<?php
				printf(
					/* translators: 1: human-readable time, 2: memory limit. */
					esc_html__( 'We detected a cron run that failed %1$s ago because the PHP memory available to the cron job is too low (limit detected: %2$s). %3$s Automatic schedules will keep failing until the cron PHP memory limit is increased.', 'wp-product-feed-manager' ),
					esc_html( $time_since ),
					esc_html( $detected_limit ? $detected_limit : esc_html__( 'unknown', 'wp-product-feed-manager' ) ),
					esc_html( $feed_fragment )
				);
				?>
			</p>
			<p><strong><?php esc_html_e( 'How to fix it:', 'wp-product-feed-manager' ); ?></strong></p>
			<ul style="list-style:disc;margin-left:20px;">
				<li><?php esc_html_e( 'Ask your hosting provider to raise the PHP memory limit for cron/CLI processes to at least 256 MB.', 'wp-product-feed-manager' ); ?></li>
				<li>
					<?php
					printf(
						/* translators: %s example command. */
						esc_html__( 'If you manage the cron command yourself, run it with an explicit limit (for example: %s).', 'wp-product-feed-manager' ),
						esc_html( $cron_example )
					);
					?>
				</li>
			</ul>
			<p class="description"><?php esc_html_e( 'Manual feed regenerations can still work because wp-admin uses a different (higher) memory limit; only the automated cron runs are affected.', 'wp-product-feed-manager' ); ?></p>
			<p><a class="button button-secondary" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Hide this warning for now', 'wp-product-feed-manager' ); ?></a></p>
		</div>
		<?php
	}

	add_action( 'admin_notices', 'wppfm_render_memory_warning_notice' );
}

