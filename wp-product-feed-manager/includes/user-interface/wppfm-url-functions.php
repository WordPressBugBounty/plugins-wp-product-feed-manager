<?php

/**
 * Checks if the user is on one of the plugin pages or not.
 * Also returns true when the current page cannot be defined
 *
 * @since 2.3.4
 * @since 2.30.0 Modified the function to use the actual parameters and not the url as a string.
 *
 * @return boolean
 */
function wppfm_on_any_own_plugin_page() {
	$page_param = wppfm_get_url_parameter( 'page' );

	if ( false === stripos( $page_param, WPPFM_PLUGIN_NAME ) && false === stripos( $page_param, 'wppfm-' ) ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Checks if the user is on one of the plugin pages but not on the option page
 * Also returns true when the current page cannot be defined
 *
 * @since 2.3.4
 * @since 2.30.0 Modified the function to use the actual parameters and not the url as a string.
 * @since 3.3.0 Modified the function to include the new pages added from the Project Blue update.
 *
 * @return boolean
 */
function wppfm_on_own_main_plugin_page() {
	$ref_url = $_SERVER['REQUEST_URI'] ?? '';

	// return true if the current page url has not been identified
	if ( empty( $ref_url ) ) {
		return true;
	}

	$own_plugin_pages = wppfm_valid_page_names();

	$page_param = wppfm_get_url_parameter( 'page' ) ?: '';

	// return true if the url contains the plugin name under the page attribute or if it's an ajax call
	if ( false === stripos( $page_param, WPPFM_PLUGIN_NAME ) && false === stripos( $ref_url, '/wp-admin/admin-ajax.php' ) && ! in_array( $page_param, $own_plugin_pages ) ) {
		return false;
	} else {
		return true;
	}
}

function wppfm_get_url_parameter( $parameter_name ) {
	$result = array_key_exists( $parameter_name, $_GET ) && $_GET[$parameter_name] ? $_GET[$parameter_name] : null;

	switch ( $parameter_name ) {
		case 'id':
			return is_numeric( $result ) ? $result : null;

		case 'feed-type':
			return in_array( $result, wppfm_valid_feed_types() ) ? $result : null;

		case 'page':
			return in_array( $result, wppfm_valid_page_names() ) ? $result : null;

		default:
			return htmlspecialchars( $result );
	}
}

function wppfm_valid_feed_types() {
	return array(
		'product-feed',
		'google-product-review-feed',
		'google-merchant-promotions-feed',
		'google-local-product-inventory-feed',
		'google-dynamic-remarketing-feed',
		'google-vehicle-ads-feed',
		'google-dynamic-search-ads-feed',
		'google-local-product-feed',
	);
}

function wppfm_valid_page_names() {
	return array(
		'wp-product-feed-manager',
		'wppfm-feed-editor-page',
		'wppfm-channel-manager-page',
		'wppfm-settings-page',
		'wppfm-support-page',
	);
}
