<?php

/**
 * @package WP Product Feed Manager/User Interface/Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the feed manager menu in the Admin page
 *
 * @param bool $channel_updated default false
 */
function wppfm_add_feed_manager_menu( $channel_updated = false ) {

	// defines the feed manager menu
	add_menu_page(
		__( 'WP Feed Manager', 'wp-product-feed-manager' ),
		__( 'Feed Manager', 'wp-product-feed-manager' ),
		'manage_woocommerce',
		'wp-product-feed-manager',
		'wppfm_feed_list_page',
		wppfm_get_menu_icon_svg()
	);

	// add the feed editor page
	add_submenu_page(
		'wp-product-feed-manager',
		__( 'Feed Editor', 'wp-product-feed-manager' ),
		__( 'Feed Editor', 'wp-product-feed-manager' ),
		'manage_woocommerce',
		'wppfm-feed-editor-page',
		'wppfm_feed_editor_page'
	);

	// add the settings page
	add_submenu_page(
		'wp-product-feed-manager',
		__( 'Settings', 'wp-product-feed-manager' ),
		__( 'Settings', 'wp-product-feed-manager' ),
		'manage_woocommerce',
		'wppfm-settings-page',
		'wppfm_settings_page'
	);

	// add the support page
	add_submenu_page(
		'wp-product-feed-manager',
		__( 'Support', 'wp-product-feed-manager' ),
		__( 'Support', 'wp-product-feed-manager' ),
		'manage_woocommerce',
		'wppfm-support-page',
		'wppfm_support_page'
	);
}

add_action( 'admin_menu', 'wppfm_add_feed_manager_menu' );

/**
 * Checks if the backups are valid for the current database version and warns the user if not
 *
 * @since 1.9.6
 */
function wppfm_check_backups() {
	if ( ! wppfm_check_backup_status() ) {
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Due to an update of your Feed Manager plugin, your feed backups are no longer valid! Please open the Feed Manager Settings page, remove all current backups, and make a new one.', 'wp-product-feed-manager' ); ?></p>
		</div>
		<?php
	}
}

add_action( 'wppfm_daily_event', 'wppfm_check_backups' );

/**
 * Sets the global background process. Gets triggered by the wp_loaded action.
 *
 * @since 1.10.0
 *
 * @global WPPFM_Feed_Processor $wppfm_background_process
 */
function initiate_background_process() {
	global $wppfm_background_process;

	$feed_type = filter_input( INPUT_GET, 'feed-type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

	if ( $feed_type ) {
		$active_tab = wppfm_get_url_parameter( 'feed-type' );
		set_transient( 'wppfm_set_global_background_process', $active_tab, WPPFM_TRANSIENT_LIVE );
	} else {
		$active_tab = ! get_transient( 'wppfm_set_global_background_process' ) ? 'feed-list' : get_transient( 'wppfm_set_global_background_process' );
	}

	if ( ( 'product-feed' === $active_tab || 'feed-list' === $active_tab ) ) {
		if ( ! class_exists( 'WPPFM_Feed_Processor' ) ) {
			require_once __DIR__ . '/../application/class-wppfm-feed-processor.php';
		}

		$wppfm_background_process = new WPPFM_Feed_Processor();
		return;
	}

	if ( 'google-product-review-feed' === $active_tab ) {
		if ( ! class_exists( 'WPPRFM_Review_Feed_Processor' ) && function_exists( 'wppfm_rf_include_background_classes' ) ) {
			wppfm_rf_include_background_classes();
		}

		// @since 2.29.0 to prevent a PHP fatal error when a review feed fails and the user deactivates the plugin.
		if ( class_exists( 'WPPRFM_Review_Feed_Processor' ) ) {
			$wppfm_background_process = new WPPRFM_Review_Feed_Processor();
			return;
		}
	}

	if ( 'google-merchant-promotions-feed' === $active_tab ) {
		if ( ! class_exists( 'WPPPFM_Promotions_Feed_Processor' ) && function_exists( 'wppfm_pf_include_background_classes' ) ) {
			wppfm_pf_include_background_classes();
		}

		if ( class_exists( 'WPPPFM_Promotions_Feed_Processor' ) ) {
			$wppfm_background_process = new WPPPFM_Promotions_Feed_Processor();
		}
	}
}

add_action( 'wp_loaded', 'initiate_background_process' );

/**
 * Sets a day event schedule that can be used to activate important actions that need to run only once a day. Gets triggered by the wp_loaded action.
 *
 * @since 3.7.0.
 */
function wppfm_setup_schedule_daily() {
	if ( ! wp_next_scheduled( 'wppfm_daily_event' ) ) {
		wp_schedule_event( time(), 'daily', 'wppfm_daily_event' );
	}
}

add_action( 'wp_loaded', 'wppfm_setup_schedule_daily' );

/**
 * Makes sure the automatic feed update cron schedule is still installed. Gets triggered by the admin_init action.
 *
 * @since 2.20.0
 */
function wppfm_verify_feed_update_schedule_registration() {
	wppfm_check_feed_update_schedule();
}

add_action( 'admin_menu', 'wppfm_verify_feed_update_schedule_registration' );

/**
 * Generates a Sales Promotion notice for the free version of the plugin. Gets triggered by the admin_notices action.
 *
 * @return bool true if the notice has been displayed, false if not.
 */
function wppfm_sales_promotion_notice() {
	// Only show on the free version.
	if ( 'free' !== WPPFM_PLUGIN_VERSION_ID ) {
		return false;
	}

	$current_date = gmdate( 'Y-m-d' );
	$start_date = '2024-11-25';
	$end_date = '2024-12-08';

	// Check the correct time frame.
	if ( $current_date < $start_date || $current_date > $end_date ) {
		return false;
	}

	// Check if the promotion message has been dismissed.
	if ( 'canceled' === get_option( 'wppfm_black_friday_promotion_2024_dismissed', 'keep_it_on' ) ) {
		return false;
	}

	wp_localize_script( 'wppfm_notice-handling-script', 'wppfm_notice_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php') ) );

	wp_register_style( 'wp-product-feed-manager-promotion-banner', WPPFM_PLUGIN_URL . '/css/wppfm-promotion-notice.css', '', WPPFM_VERSION_NUM, 'screen' );
	wp_enqueue_style( 'wp-product-feed-manager-promotion-banner' );

	// Show the sales promotion message.
	WPPFM_Notice::render_sales_promotion_notice();

	return true;
}

add_action( 'admin_notices', 'wppfm_sales_promotion_notice' );

/**
 * Adds the plugin product identifier field values to the quick edit form as soon as the quick edit option is opened. Gets triggered by the admin_footer action.
 */
function wppfm_add_product_identifiers_to_quick_edit_custom_fields() {
	?>
	<script type="text/javascript">
			(function($) {
				$('#the-list').on('click', '.editinline', function() {

					var post_id = $(this).closest('tr').attr('id');
					post_id = post_id.replace('post-', '');

					var brand_field = $('#wppfm_product_brand_data_' + post_id).text();
					$('input[name="wppfm_product_brand"]', '.inline-edit-row').val(brand_field);

					var gtin_field = $('#wppfm_product_gtin_data_' + post_id).text();
					$('input[name="wppfm_product_gtin"]', '.inline-edit-row').val(gtin_field);

					var mpn_field = $('#wppfm_product_mpn_data_' + post_id).text();
					$('input[name="wppfm_product_mpn"]', '.inline-edit-row').val(mpn_field);
				});
			})(jQuery);		</script>
	<?php
}

add_action( 'admin_footer', 'wppfm_add_product_identifiers_to_quick_edit_custom_fields' );

/**
 * Adds the custom fields data to the products quick edit form by placing the data in a hidden data field. Gets triggered by the manage_product_posts_custom_column action.
 *
 * @param string $column  used to check if we are in the correct column.
 * @param int    $post_id the post id.
 */
function wppfm_add_wc_quick_edit_custom_fields_data( $column, $post_id ){
	if ( 'name' !== $column ) {
		return;
	}

	echo '<div id="wppfm_product_brand_data_' . esc_html( $post_id ) . '" hidden>' . esc_html( get_post_meta( $post_id, 'wppfm_product_brand', true ) ) . '</div>
	<div id="wppfm_product_gtin_data_' . esc_html( $post_id ) . '" hidden>' . esc_html( get_post_meta( $post_id, 'wppfm_product_gtin', true ) ) . '</div>
	<div id="wppfm_product_mpn_data_' . esc_html( $post_id ) . '" hidden>' . esc_html( get_post_meta( $post_id, 'wppfm_product_mpn', true ) ) . '</div>';
}

add_action( 'manage_product_posts_custom_column', 'wppfm_add_wc_quick_edit_custom_fields_data', 10, 2 );

/**
 * Checks the wpmarketingrobot server for new blogs and adds them to the blog list that is stored in the wppfm_latest_weblogs option. Gets triggered by the wppfm_daily_event action.
 *
 * @return void
 * @since 3.14.0.
 */
function wppfm_check_for_new_blogs() {
	$response = wp_remote_get(WPPFM_EDD_SL_STORE_URL . 'wp-json/wp/v2/posts?per_page=1&type=post&status=publish' );

	if ( is_wp_error( $response ) ) {
		return;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( empty( $data ) || ! is_array( $data ) || ! $data[0]['id'] || ! $data[0]['featured_media'] || ! $data[0]['link'] ) {
		return;
	}

	$latest_post       = $data[0];
	$id                = $latest_post['id'];
	$date              = $latest_post['modified'];
	$title             = $latest_post['title']['rendered'];
	$featured_image_id = $latest_post['featured_media'];
	$post_url          = $latest_post['link'];

	$image_response = wp_remote_get( WPPFM_EDD_SL_STORE_URL . 'wp-json/wp/v2/media/' . $featured_image_id );

	if ( is_wp_error( $image_response ) ) {
		return;
	}

	$featured_image_url = json_decode( wp_remote_retrieve_body( $image_response ), true )['source_url'];

	if ( ! $featured_image_url ) {
		return;
	}

	$latest_blog_data = array(
		'id'        => $id,
		'date'      => $date,
		'title'     => $title,
		'url'       => $post_url,
		'image_url' => $featured_image_url,
	);

	wppfm_store_latest_blog( $latest_blog_data );
}

add_action( 'wppfm_daily_event', 'wppfm_check_for_new_blogs' );
