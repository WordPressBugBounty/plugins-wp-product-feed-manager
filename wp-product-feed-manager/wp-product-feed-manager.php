<?php
/**
 * Plugin Name: WP Product Feed Manager
 * Plugin URI: https://www.wpmarketingrobot.com
 * Description: An easy-to-use WordPress plugin that generates and submits your product feeds to merchant centres.
 * Author: WP Marketing Robot
 * Author URI: https://www.wpmarketingrobot.com
 * Developer: Michel Jongbloed
 * Developer URI: https://www.wpmarketingrobot.com
 * Version: 2.14.0
 * Modified: 15-04-2025
 * WC requires at least: 8.4
 * WC tested up to: 9.8.1
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * You can read the GNU General Public License here <http://www.gnu.org/licenses/>.
 * Requires at least: 6.5
 * Requires Plugins: woocommerce
 * Tested up to: 6.7
 *
 * @package WordPress
 *
 * Text Domain: wp-product-feed-manager
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Product_Feed_Manager' ) ) :

	/**
	 * The Main WP_Product_Feed_Manager Class.
	 *
	 * @class WP_Product_Feed_Manager.
	 */
	final class WP_Product_Feed_Manager {

		/**
		 * Version number.
		 *
		 * @var string  Containing the version number of the plugin.
		 */
		public $version = '2.14.0';

		/**
		 * Author Name.
		 *
		 * @var string  Containing the author name.
		 */
		public $author = 'Michel Jongbloed';

		/**
		 * Force single instance.
		 *
		 * @var WP_Product_Feed_Manager Single instance.
		 */
		private static $instance = null;

		/**
		 * Returns the Singleton instance of this class.
		 *
		 * @static
		 * @access public
		 * @return  WP_Product_Feed_Manager Main instance.
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Cloning is not allowed.
		 *
		 * @since 0.9.1
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is not allowed', 'wp-product-feed-manager' ), '0.9.1' );
		}

		/**
		 * Unserializing instances of this class are not allowed.
		 *
		 * @since 0.9.1
		 */
		public function __wakeup() {
			_doing_it_wrong(
				__FUNCTION__,
				esc_html__( 'Unserializing instances of this class is not allowed', 'wp-product-feed-manager' ),
				'0.9.1'
			);
		}

		/**
		 * WP_Product_Feed_Manager Constructor.
		 *
		 * @since 0.9.1
		 */
		private function __construct() {
			// Set the constants to be used in this plugin.
			$this->define_constants();

			// Hooks.
			$this->hooks();

			// Includes.
			$this->includes();

			// Register my version.
			add_option( 'myplugin_version', WPPFM_VERSION_NUM );

			// Register my schedule.
			add_action( 'wppfm_feed_update_schedule', array( $this, 'activate_feed_update_schedules' ) );
			add_action( 'wp_ajax_dismiss_admin_notice', array( $this, 'dismiss_admin_notice' ) );

			// Set up localisation.
			// @since 3.11.0.- Changed from the plugins_loaded to the  after_setup_theme action to prevent a "Translation loading was triggered too early" error message.
			add_action( 'after_setup_theme', array( $this, 'load_text_domain' ) );

			// Declare compatibility with custom order tables.
			add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility_for_custom_order_tables' ) );

			do_action( 'wp_product_feed_manager_loaded' );
		}

		/**
		 * Defines a few important constants.
		 */
		private function define_constants() {
			// Store the name of the plugin.
			if ( ! defined( 'WPPFM_PLUGIN_NAME' ) ) {
				define( 'WPPFM_PLUGIN_NAME', 'wp-product-feed-manager' );
			}

			// Store the directory of the plugin.
			if ( ! defined( 'WPPFM_PLUGIN_DIR' ) ) {
				define( 'WPPFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Store the plugin constructor.
			if ( ! defined( 'WPPFM_PLUGIN_CONSTRUCTOR' ) ) {
				define( 'WPPFM_PLUGIN_CONSTRUCTOR', plugin_basename( __FILE__ ) );
			}

			// Store the url of the plugin.
			if ( ! defined( 'WPPFM_PLUGIN_URL' ) ) {
				define( 'WPPFM_PLUGIN_URL', plugins_url() . '/' . WPPFM_PLUGIN_NAME );
			}

			// Store the version of my plugin.
			if ( ! defined( 'WPPFM_VERSION_NUM' ) ) {
				define( 'WPPFM_VERSION_NUM', $this->version );
			}

			// Store the version id of my plugin.
			// @since 2.38.0.
			if ( ! defined( 'WPPFM_PLUGIN_VERSION_ID' ) ) {
				define( 'WPPFM_PLUGIN_VERSION_ID', 'free' );
			}

			// Store the plugin distributor.
			if ( ! defined( 'WPPFM_PLUGIN_DISTRIBUTOR' ) ) {
				define( 'WPPFM_PLUGIN_DISTRIBUTOR', 'wpmarketingrobot' );
			}

			// Store the transient alive time.
			if ( ! defined( 'WPPFM_TRANSIENT_LIVE' ) ) {
				define( 'WPPFM_TRANSIENT_LIVE', 20 * MINUTE_IN_SECONDS );
			}

			// Store the time before a feed gets a failed label.
			if ( ! defined( 'WPPFM_DELAY_FAILED_LABEL' ) ) {
				define( 'WPPFM_DELAY_FAILED_LABEL', MINUTE_IN_SECONDS );
			}

			// Store the url to wpmarketingrobot.com.
			if ( ! defined( 'WPPFM_EDD_SL_STORE_URL' ) ) {
				define( 'WPPFM_EDD_SL_STORE_URL', 'https://www.wpmarketingrobot.com/' );
			}

			// Store the link to the support page.
			if ( ! defined( 'WPPFM_SUPPORT_PAGE_URL' ) ) {
				define( 'WPPFM_SUPPORT_PAGE_URL', 'www.wpmarketingrobot.com/support/' );
			}

			// Store the plugin title.
			if ( ! defined( 'WPPFM_EDD_SL_ITEM_NAME' ) ) {
				define( 'WPPFM_EDD_SL_ITEM_NAME', 'WP Product Feed Manager' );
			}

			// Store the plugin title.
			if ( ! defined( 'WPPFM_MIN_REQUIRED_WC_VERSION' ) ) {
				define( 'WPPFM_MIN_REQUIRED_WC_VERSION', '3.0.0' );
			}

			// Store the base uploads' folder, should also work in a multi-site environment.
			if ( ! defined( 'WPPFM_UPLOADS_DIR' ) ) {
				$wp_upload_dir = wp_get_upload_dir(); // @since 2.10.0 switched from wp_upload_dir to wp_get_upload_dir.
				$upload_dir    = is_multisite() && defined( 'UPLOADS' ) ? UPLOADS : $wp_upload_dir['basedir'];

				if ( ! file_exists( $upload_dir ) && ! is_dir( $upload_dir ) ) {
					define( 'WPPFM_UPLOADS_DIR', $wp_upload_dir['basedir'] );
				} else {
					define( 'WPPFM_UPLOADS_DIR', $upload_dir );
				}
			}

			if ( ! defined( 'WPPFM_UPLOADS_URL' ) ) {
				$wp_upload_dir = wp_upload_dir();

				// Correct baseurl for https if required.
				if ( is_ssl() ) {
					$url = str_replace( 'http://', 'https://', $wp_upload_dir['baseurl'] );
				} else {
					$url = $wp_upload_dir['baseurl'];
				}

				// @since 2.11.1 Added the wppfm_corrected_uploads_url filter.
				define( 'WPPFM_UPLOADS_URL', apply_filters( 'wppfm_corrected_uploads_url', $url ) );
			}

			// Store the folder that contains the channels' data.
			if ( ! defined( 'WPPFM_CHANNEL_DATA_DIR' ) ) {
				define( 'WPPFM_CHANNEL_DATA_DIR', WPPFM_PLUGIN_DIR . 'includes/application' );
			}

			// Store the folder that contains the backup files.
			if ( ! defined( 'WPPFM_BACKUP_DIR' ) ) {
				define( 'WPPFM_BACKUP_DIR', WPPFM_UPLOADS_DIR . '/wppfm-backups' );
			}

			// Store the folder that contains the revision files.
			if ( ! defined( 'WPPFM_REVISIONS_DIR' ) ) {
				define( 'WPPFM_REVISIONS_DIR', WPPFM_UPLOADS_DIR . '/wppfm-revisions' );
			}

			// Store the folder that contains the feeds.
			if ( ! defined( 'WPPFM_FEEDS_DIR' ) ) {
				define( 'WPPFM_FEEDS_DIR', WPPFM_UPLOADS_DIR . '/wppfm-feeds' );
			}

			// Store the folder that contains the loggings.
			if ( ! defined( 'WPPFM_LOGGINGS_DIR' ) ) {
				define( 'WPPFM_LOGGINGS_DIR', WPPFM_UPLOADS_DIR . '/wppfm-logs' );
			}
		}

		/**
		 * Sets the activation and deactivation hooks.
		 */
		private function hooks() {
			// Register's the activation, deactivation and uninstall hooks.
			register_activation_hook( __FILE__, array( &$this, 'on_activation' ) );
			register_deactivation_hook( __FILE__, array( &$this, 'on_deactivation' ) );
			register_shutdown_function( array( $this, 'log_errors' ) );
		}

		/**
		 * Includes the required files.
		 *
		 * @noinspection PhpIncludeInspection*/
		private function includes() {
			// Include the WordPress pluggable.php file on forehand to prevent a "Call to undefined function wp_get_current_user()" error.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_admin() && isset( $_SERVER['PHP_SELF'] ) && basename( wp_unslash( $_SERVER['PHP_SELF'] ) ) === 'options-general.php' && 'email_template' === filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) {
				require_once ABSPATH . 'wp-includes/pluggable.php';
			}

			// To prevent a fatal error about not finding the is_plugin_active function.
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			// Include the admin menu and the includes file.
			require_once __DIR__ . '/includes/application/wppfm-feed-processing-support.php';
			require_once __DIR__ . '/includes/application/wppfm-feed-processor-functions.php';
//			require_once __DIR__ . '/includes/application/wppfm-plugin-reversion-functions.php';
			require_once __DIR__ . '/includes/user-interface/wppfm-admin-menu-functions.php';
			require_once __DIR__ . '/includes/user-interface/wppfm-admin-actions.php';
			require_once __DIR__ . '/includes/user-interface/wppfm-edit-feed-form-functions.php';
			require_once __DIR__ . '/includes/user-interface/wppfm-admin-filters.php';
			require_once __DIR__ . '/includes/data/wppfm-admin-functions.php';
			require_once __DIR__ . '/includes/data/wppfm-data-storage-functions.php';
			require_once __DIR__ . '/includes/user-interface/wppfm-messaging-functions.php';
			require_once __DIR__ . '/includes/user-interface/wppfm-url-functions.php';
			require_once __DIR__ . '/includes/user-interface/wppfm-woocommerce-actions.php';
			require_once __DIR__ . '/includes/wppfm-wpincludes.php';
			require_once __DIR__ . '/includes/packages/logger/wp-product-feed-manager-logger.php';

			if ( 'true' === get_option( 'wppfm_show_product_identifiers', 'false' ) ) {
				require_once __DIR__ . '/includes/user-interface/wppfm-product-identifiers.php'; // @since 2.10.0
			}

			// Include all required classes.
			include_classes();
			include_channels();

			// Include the integrated Product Review Feed Manager package.
			// @since 2.37.0
			require_once __DIR__ . '/includes/packages/review-feed-manager/wp-product-review-feed-manager.php';

			// Include the integrated Google Merchant Promotions Manager package.
			// @since 3.0.0
			require_once __DIR__ . '/includes/packages/promotions-feed-manager/wp-merchant-promotions-feed-manager.php';
		}

		/**
		 * Activate the feed update schedules.
		 */
		public function activate_feed_update_schedules() {
			require_once __DIR__ . '/includes/application/wppfm-cron-functions.php';
			wppfm_update_feeds();
		}

		/**
		 * Registers a dismiss notice action.
		 *
		 * @since 1.9.8
		 */
		public function dismiss_admin_notice() {
			if ( is_admin() ) {
				update_option( 'wppfm_license_notice_suppressed', true );
			}
		}

		/**
		 * Registers a dismiss notice action, declaring compatibility with the WooCommerce custom order tables feature.
		 *
		 * @since 2.36.0
		 * @noinspection PhpFullyQualifiedNameUsageInspection
		 */
		public function declare_compatibility_for_custom_order_tables() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
			}
		}

		/**
		 * Performs the required actions on activation of the plugin.
		 */
		public function on_activation() {
			// Add the required tables to the database.
			$wppfm_database = new WPPFM_Database_Management();
			$wppfm_database->make();

			wp_schedule_event( time(), 'hourly', 'wppfm_feed_update_schedule' );
		}

		/**
		 * Registers the text domain.
		 *
		 * @since 2.1.6
		 */
		public function load_text_domain() {
			load_plugin_textdomain( 'wp-product-feed-manager', false, WPPFM_PLUGIN_NAME . '/languages' );
		}

		/**
		 * Performs the required actions on deactivation of the plugin.
		 */
		public function on_deactivation() {
			// Stop the scheduled feed update actions.
			wp_clear_scheduled_hook( 'wppfm_feed_update_schedule' );
			// Remove all keyed option items from the option table and clears any stuck feed processing data.
			wppfm_clear_feed_process_data();
		}

		/**
		 * Gets triggered when the plugin quits and is used to fetch fatal errors.
		 *
		 * @since 1.10.0
		 */
		public function log_errors() {
			$error = error_get_last();

			if ( $error ) {
				// Load the messaging code if not already done yet.
				require_once __DIR__ . '/includes/user-interface/wppfm-messaging-functions.php';

				// Fetch fatal errors.
				if ( E_ERROR === $error['type'] ) {
					// Load the required classes if not already done yet.
					if ( ! class_exists( 'WPPFM_Feed_Controller' ) ) {
						require_once __DIR__ . '/includes/application/class-wppfm-feed-controller.php';
					}
					if ( ! class_exists( 'WPPFM_Db_Management' ) ) {
						require_once __DIR__ . '/includes/data/class-wppfm-db-management.php';
					}

					// Clear the feed queue.
					WPPFM_Feed_Controller::clear_feed_queue();

					// The background process clearly has stopped.
					WPPFM_Feed_Controller::set_feed_processing_flag();

					// Remove all keyed option items from the option table.
					WPPFM_Db_Management::clean_options_table();

					wppfm_write_log_file(
						sprintf(
							'PHP Fatal error: %s in file %s on line %s',
							$error['message'],
							$error['file'],
							$error['line']
						)
					);
				} elseif ( E_WARNING === $error['type'] ) {
					if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
						wppfm_write_log_file(
							sprintf(
								'PHP Warning: %s in file %s on line %s',
								$error['message'],
								$error['file'],
								$error['line']
							)
						);
					}
				}
			}
		}

	}

	// End of WP_Product_Feed_Manager class.

endif;

WP_Product_Feed_Manager::get_instance();
