<?php

/**
 * Initiates the Plugin Reversion functions required for reverting the plugin to previous versions.
 *
 * @package WP Product Feed Manager/Application/Functions
 * @since 3.14.0.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores a zipped copy of the current version of our plugin in the revisions directory so it can be restored later if required.
 * Gets activated by the upgrader_package_options filter.
 *
 * @param $options
 *
 * @return mixed
 */
function wppfm_store_reverse_plugin_version( $options ) {
	$plugin_main_file = WPPFM_PLUGIN_NAME . '/' . WPPFM_PLUGIN_NAME . '.php';
	if ( isset( $options['hook_extra']['plugin'] ) && isset( $options['destination'] ) && $plugin_main_file === $options['hook_extra']['plugin'] ){
		$plugin_main_file_path = WPPFM_PLUGIN_DIR . WPPFM_PLUGIN_NAME . '.php';

		if ( ! class_exists( 'PclZip' ) ) {
			/** @noinspection PhpIncludeInspection */
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}

		if ( file_exists( $plugin_main_file_path ) && class_exists( 'PclZip' ) ) {
			$plugin_data = get_plugin_data( $plugin_main_file_path );
			$wp_filesystem = wppfm_get_wp_filesystem();
			$zip_file_name = WPPFM_PLUGIN_VERSION_ID . '-' . $plugin_data['Version'] . '-' . WPPFM_PLUGIN_NAME . '.zip';

			// Create the reversions storage folder if it does not yet exist.
			if ( ! is_dir( WPPFM_REVISIONS_DIR ) ) {
				$wp_filesystem->mkdir( WPPFM_REVISIONS_DIR );
			}

			$plugin_dir_path = plugin_dir_path( $plugin_main_file_path );

			if ( copy_dir( $plugin_dir_path, WPPFM_REVISIONS_DIR . '/' . WPPFM_PLUGIN_NAME ) ) {
				$zip = new PclZip( WPPFM_REVISIONS_DIR . '/' . $zip_file_name );

				if ( $zip->create( WPPFM_REVISIONS_DIR . '/' . WPPFM_PLUGIN_NAME, PCLZIP_OPT_REMOVE_PATH, WPPFM_REVISIONS_DIR ) ) {
					$wp_filesystem->delete( WPPFM_REVISIONS_DIR . '/' . WPPFM_PLUGIN_NAME, true );
				}
			}
		}
	}

	return $options;
}

add_filter( 'upgrader_package_options', 'wppfm_store_reverse_plugin_version', 10, 4 );

function wppfm_restore_older_version() {
	if ( isset( $_POST['nonce'] )
		 && isset( $_POST['versionTitle'] )
		 && wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ),'wppfm_reverse_older_version_of_plugin_nonce' )
		 && current_user_can( 'manage_options' ) ) {

		$to_restore_plugin_name = $_POST['versionTitle'];
		$path_to_active_plugin = WP_PLUGIN_DIR . '/' . WPPFM_PLUGIN_NAME;
		$path_to_reversion_source_folder = WPPFM_REVISIONS_DIR . '/' . $to_restore_plugin_name;
		$path_to_temp_copy_of_active_plugin_folder = WP_PLUGIN_DIR . '/' . WPPFM_PLUGIN_NAME . '-origin-remove';
		$path_to_restore_plugin = WPPFM_REVISIONS_DIR . '/' . WPPFM_PLUGIN_NAME;

		$wp_filesystem = wppfm_get_wp_filesystem();

		// Unzip the selected version of the plugin in the revision source folder.
		$unzip_result = unzip_file( $path_to_reversion_source_folder, WPPFM_REVISIONS_DIR );

		if ( ! $unzip_result ) {
			die();
		}

		// Delete the zip file after unzipping it.
		wp_delete_file( $path_to_reversion_source_folder );

		// Rename the current plugin directory to allow the selected version to be copied to the plugins' folder.
		$rename_current = $wp_filesystem->move( $path_to_active_plugin, $path_to_temp_copy_of_active_plugin_folder );

		// Move the restored plugin in the revisions directory to the original plugin directory.
		$move_restored = $wp_filesystem->move( $path_to_restore_plugin, $path_to_active_plugin );

		// If the renaming and moving of the plugin folders was successful, deactivate the plugin that is to be replaced.
		if ( $rename_current && $move_restored && ! wp_next_scheduled('wppfm_remove_old_folder_event') ) {
			wp_schedule_single_event(time() + 5, 'wppfm_remove_old_folder_event');
		}
	}
}

add_action( 'wp_ajax_wppfm_reverse_older_version_of_plugin', 'wppfm_restore_older_version' );

/**
 * Adds the styling for the reverse action element to the plugin description on the Plugins page.
 *
 * @return void CSS code that styles the reverse action element.
 */
function wppfm_reverse_action_element_css() {
	global $pagenow;
	if ( $pagenow && 'plugins.php' === sanitize_text_field( $pagenow ) ){
		?>
		<!--suppress CssUnusedSymbol -->
		<style id="wppfm-reverse-plugin-css">
            .wppfm-reverse-plugin-wrapper {
                position:relative
            }
            .wppfm-plugin-reverse-versions {
                display:none;
                position:absolute;
			<?php echo is_rtl() ? 'right' : 'left'; ?>:0;
                top:0
            }
            .wppfm-reverse-plugin-wrapper:hover .wppfm-plugin-reverse-versions {
                background:#fff;
                display:block;
                margin-top:15px;
                min-width:200px;
                min-width:max-content;
                padding:10px 10px;
                z-index:9
            }
            .wppfm-reverse-plugin-wrapper:hover .wppfm-plugin-reverse-versions a {
                background-position:-9999px -9999px;
                background-repeat:no-repeat;
                background-size:16px 16px;
                display:block;
                margin-bottom:5px;
            }
		</style>
		<?php
	}
}

add_action( 'admin_head', 'wppfm_reverse_action_element_css' );

/**
 * Adds the reverse action element to the plugin description on the Plugins page.
 *
 * @return void The HTML code that adds the reverse action element.
 */
function wppfm_add_reverse_js_function_to_plugins_page() {
	global $pagenow;
	if ( $pagenow && 'plugins.php' === sanitize_text_field( $pagenow ) ){
		?>
		<script id="wppfm-reverse-plugin-js" type="text/javascript">
					document.addEventListener('DOMContentLoaded', function() {
						var actions = document.querySelectorAll('.wppfm-reverse-plugin-action');
						actions.forEach(function(action) {
							action.addEventListener('click', function(e) {
								e.preventDefault();
								var nonce = '<?php echo wp_create_nonce('wppfm_reverse_older_version_of_plugin_nonce'); ?>';
								var versionTitle = action.getAttribute('data-version-title');
								var data = new FormData();
								data.append('action', 'wppfm_reverse_older_version_of_plugin');
								data.append('nonce', nonce);
								data.append('versionTitle', versionTitle);
								var xhr = new XMLHttpRequest();
								xhr.open('POST', ajaxurl, true);
								xhr.onload = function() {
									if (xhr.status === 200) {
										window.location.reload();
									} else {
										alert( esc_html__( 'Plugin could not be reversed to a previous version!', 'wp-product-feed-manager' ) );
									}
								};
								xhr.send(data);
							});
						});
					});
		</script>
		<?php
	}
}

add_action( 'admin_footer', 'wppfm_add_reverse_js_function_to_plugins_page' );

function wppfm_remove_old_version_folder() {
	wppfm_write_log_file( 'wppfm_remove_old_folder_event started' );
	$path_to_temp_copy_of_active_plugin_folder = WP_PLUGIN_DIR . '/' . WPPFM_PLUGIN_NAME . '-origin-remove';

	if ( is_dir( $path_to_temp_copy_of_active_plugin_folder ) ) {
		$wp_filesystem = wppfm_get_wp_filesystem();
		if ( ! $wp_filesystem->delete( $path_to_temp_copy_of_active_plugin_folder, true ) ) {
			wppfm_write_log_file( 'Failed to delete the folder: ' . $path_to_temp_copy_of_active_plugin_folder );
		}
	} else {
		wppfm_write_log_file( 'Folder does not exist: ' . $path_to_temp_copy_of_active_plugin_folder );
	}
}

add_action( 'wppfm_remove_old_folder_event', 'wppfm_remove_old_version_folder' );
