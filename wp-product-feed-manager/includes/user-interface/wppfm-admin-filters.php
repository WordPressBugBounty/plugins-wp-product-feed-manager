<?php

/**
 * @package WP Product Feed Manager/User Interface/Functions
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds links to the started guide and premium site in the plugin description on the Plugins page.
 *
 * @param   array   $actions        Associative array of action names to anchor tags.
 * @param   string  $plugin_file    Plugin file name.
 * @param   array   $plugin_data    Array of plugin data from the plugin file.
 * @param   string  $context        Plugin status context.
 *
 * @return  array   HTML code that adds links to the plugin description.
 * @noinspection PhpUnusedParameterInspection
 * @since 2.6.0
 */
function wppfm_plugins_action_links( $actions, $plugin_file, $plugin_data, $context ) {
	$starters_guide_link = 'wpmarketingrobot' === WPPFM_PLUGIN_DISTRIBUTOR ? WPPFM_EDD_SL_STORE_URL . '/support/documentation' : 'https://woocommerce.com/document/product-feed-manager/';
	$get_support_link = 'wpmarketingrobot' === WPPFM_PLUGIN_DISTRIBUTOR ? WPPFM_EDD_SL_STORE_URL . '/support' : 'https://woocommerce.com/my-account/contact-support/?select=product-feed-manager';

	$actions['starter_guide'] = '<a href="' . $starters_guide_link . '" target="_blank">' . __( 'Starter Guide', 'wp-product-feed-manager' ) . '</a>';

	if ( 'free' === WPPFM_PLUGIN_VERSION_ID ) {
		$actions['go_premium'] = '<a style="color:green;" href="' . WPPFM_EDD_SL_STORE_URL . '" target="_blank"><b>' . __( 'Go Premium', 'wp-product-feed-manager' ) . '</b></a>';
	} else {
		$actions['support'] = '<a href="' . $get_support_link . '" target="_blank">' . __( 'Get Support', 'wp-product-feed-manager' ) . '</a>';
	}

//	if ( current_user_can( 'activate_plugin' ) ) {
//		$actions['reverse'] = '<a href="#" class="wppfm-reverse-feed">' . __( 'Reverse', 'wp-product-feed-manager' ) . '</a>';
//	}

	return $actions;
}

add_filter( 'plugin_action_links_' . WPPFM_PLUGIN_CONSTRUCTOR, 'wppfm_plugins_action_links', 10, 4 );

function wppfm_change_query_filter() {
	return 100;
}

add_filter( 'wppfm_product_query_limit', 'wppfm_change_query_filter' );

/**
 * Removes the Visit plugin site item at the plugin description on the Plugins page.
 *
 * @param   array  $plugin_meta Associative array of action names to anchor tags.
 * @param   string $plugin_file Plugin file name.
 *
 * @return  array   HTML code that adds a link to the plugin settings page.
 * @since 3.13.0
 */
function remove_visit_plugin_site_link_for_woo_product_feed_manager( $plugin_meta, $plugin_file, $plugin_data, $status ) {
	if ( $plugin_file === 'woo-product-feed-manager/woo-product-feed-manager.php' ) {
		array_pop( $plugin_meta ); // Removes the last item in the array, which always is the Visit plugin site link.
	}
	return $plugin_meta;
}

add_filter( 'plugin_row_meta', 'remove_visit_plugin_site_link_for_woo_product_feed_manager', 10, 4 );
