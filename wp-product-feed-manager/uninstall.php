<?php

/**
 * The uninstallation functions.
 *
 * @package WP Product Feed Manager/Functions
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$upload_dir = wp_get_upload_dir();

require_once __DIR__ . '/includes/data/wppfm-admin-functions.php';

if ( ! class_exists( 'WPPFM_Folders' ) ) {
	require_once __DIR__ . '/includes/setup/class-wppfm-folders.php';
}

if ( ! class_exists( 'WPPFM_Db_Management' ) ) {
	require_once __DIR__ . '/includes/data/class-wppfm-db-management.php';
}

if ( ! class_exists( 'WPPFM_Queries' ) ) {
	require_once __DIR__ . '/includes/data/class-wppfm-queries.php';
}

// Stop the scheduled feed update actions.
wp_clear_scheduled_hook( 'wppfm_feed_update_schedule' );

// Remove the support folders.
WPPFM_Folders::delete_folder( $upload_dir['basedir'] . '/wppfm-channels' );
WPPFM_Folders::delete_folder( $upload_dir['basedir'] . '/wppfm-feeds' );
WPPFM_Folders::delete_folder( $upload_dir['basedir'] . '/wppfm-logs' );

$tables = array(
	$wpdb->prefix . 'feedmanager_country',
	$wpdb->prefix . 'feedmanager_feed_status',
	$wpdb->prefix . 'feedmanager_field_categories',
	$wpdb->prefix . 'feedmanager_channel',
	$wpdb->prefix . 'feedmanager_product_feed',
	$wpdb->prefix . 'feedmanager_product_feedmeta',
	$wpdb->prefix . 'feedmanager_source',
	$wpdb->prefix . 'feedmanager_errors',
);

// Remove the feedmanager tables.
foreach ( $tables as $table ) {
	//phpcs:ignore
	$wpdb->query( "DROP TABLE IF EXISTS $table" );
}

// Reset the keyed options @since 3.11.0.
WPPFM_Db_Management::clean_options_table();

// Remove the custom capabilities.
wppfm_remove_custom_capabilities();

// Unregister the plugin.
wppfm_unregister_plugin();

/**
 * Removes the custom capabilities
 *
 * @since 3.9.0
 */
function wppfm_remove_custom_capabilities() {
	$admin_role = get_role( 'administrator' );
	$editor_role = get_role( 'editor' );

	$admin_role->remove_cap( 'edit_feeds' );
	$admin_role->remove_cap( 'delete_feeds' );

	$editor_role->remove_cap( 'edit_feeds' );
	$editor_role->remove_cap( 'delete_feeds' );
}


/**
 * Removes the registration info from the database.
 */
function wppfm_unregister_plugin() {
	// Retrieve the license from the database.
	$license = get_option( 'wppfm_lic_key' );

	foreach( wp_load_alloptions() as $option => $value ) {
		if( false !== strpos( $option, 'wppfm_' ) ) { delete_option( $option );	}
	}

	if ( $license ) { // If the plugin is a licensed version, then deactivate it on the license server.
		// Data to send in our API request.
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => rawurlencode( 'Woocommerce Google Feed Manager' ), // the name of the plugin in EDD.
			'url'        => home_url(),
		);

		// Call the custom API.
		wp_remote_post(
			'https://www.wpmarketingrobot.com/',
			array(
				'timeout' => 15,
				'body'    => $api_params,
			)
		);
	}
}
