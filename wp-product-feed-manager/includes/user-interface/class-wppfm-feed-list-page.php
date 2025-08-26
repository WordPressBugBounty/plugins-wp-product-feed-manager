<?php

/**
 * WPPFM Product Feed Manager Page Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Feed_List_Page' ) ) :

	/**
	 * Feed List Form Class.
	 *
	 * @since 3.2.0
	 */
	class WPPFM_Feed_List_Page {

		private $_list_table;

		function __construct() {

			wppfm_check_db_version();

			$this->_list_table = new WPPFM_List_Table();

			$this->prepare_feed_list();
		}

		/**
		 * Generates the Feed_List page.
		 *
		 * @since 3.2.0
		 */
		public function display() {
			$this->add_data_storage();
			$this->feed_list_page();
		}

		/**
		 * Generates the main part of the Feed_List page.
		 */
		private function feed_list_page() {
			// Feed List Page Header with Add New Feed button.
			echo '<div class="wppfm-page__title" id="wppfm-product-feed-list-title"><h1>' . esc_html__( 'Product Feed List', 'wp-product-feed-manager' ) . '</h1></div>
			<div class="wppfm-button-wrapper">
			<a href="admin.php?page=wppfm-feed-editor-page" class="wppfm-button wppfm-blue-button" id="wppfm-add-new-feed-button"><i class="wppfm-button-icon wppfm-icon-plus"></i>' . esc_html__( 'Add New Feed', 'wp-product-feed-manager' ) . '</a>
			</div>';

			$this->weblog_teaser();

			// Feed List Table.
			echo '<div class="wppfm-page-layout__main" id="wppfm-product-feed-list-table">';
			$this->list_content();
			echo '</div>';
		}

		/**
		 * Stores data in the DOM for the Feed List Table.
		 */
		private function add_data_storage() {
			$sortable_columns = $this->get_sortable_columns();
			$feeds_in_queue   = get_site_option( 'wppfm_feed_queue', array() );

			echo
				'<div id="wppfm-feed-list-page-data-storage" class="wppfm-data-storage-element" 
					data-wppfm-sort-column="none"
					data-wppfm-sort-direction="none" 
					data-wppfm-sortable-columns="' . esc_html( implode( '-', $sortable_columns ) ) . '"
					data-wppfm-feeds-in-queue="' . esc_html( implode( ',', $feeds_in_queue ) ) . '"
					data-wppfm-plugin-version-id="' . esc_html( WPPFM_PLUGIN_VERSION_ID ) . '" 
					data-wppfm-plugin-version-nr="' . esc_attr( WPPFM_VERSION_NUM ) . '"
					data-wppfm-plugin-distributor="' . esc_attr( WPPFM_PLUGIN_DISTRIBUTOR ) . '">
				</div>';
		}

		/**
		 * Prepares the list table.
		 */
		private function prepare_feed_list() {
			$this->_list_table->set_table_id( 'wppfm-feed-list' );

			$list_columns = array(
				'col_feed_name'        => __( 'Name', 'wp-product-feed-manager' ),
				'col_feed_url'         => __( 'Url', 'wp-product-feed-manager' ),
				'col_feed_last_change' => __( 'Updated', 'wp-product-feed-manager' ),
				'col_feed_items'       => __( 'Items', 'wp-product-feed-manager' ),
			);

			$list_columns['col_feed_type'] = __( 'Type', 'wp-product-feed-manager' );

			$list_columns['col_feed_status']  = __( 'Status', 'wp-product-feed-manager' );
			$list_columns['col_feed_actions'] = __( 'Actions', 'wp-product-feed-manager' );

			// Set the column names.
			$this->_list_table->set_column_titles( $list_columns );
		}

		/**
		 * Activates the HTML for the main body top.
		 */
		private function list_content() {
			$this->_list_table->get_feed_list_table();
		}

		/**
		 * Stores which columns in the Feed List will be sortable.
		 *
		 * @return string[] with the sortable column data.
		 */
		private function get_sortable_columns() {
			return array(
				'name'    => '1',
				'updated' => '3',
				'items'   => '4',
				'type'    => '5',
			);
		}

		/**
		 * Returns a clickable teaser for the latest weblog.
		 *
		 * @since 3.14.0.
		 */
		private function weblog_teaser() {
			$latest_blog_option  = get_option( 'wppfm_latest_weblogs', array() );

			if( empty( $latest_blog_option ) ) {
				return;
			}

			$latest_blog  = $latest_blog_option[0];
			$article_date = date( 'dmy', strtotime( $latest_blog['date'] ) );
			$article_id   = $latest_blog['id'];
			$utm_params   = '?utm_source=pl_article_ad&utm_medium=textlink&utm_campaign=plugin_article&utm_id=ARCO.' . $article_date . '&utm_content=' . $article_id;
			$href_url     = $latest_blog['url'] . $utm_params;

			if (! empty( $latest_blog['title'] ) && ! empty( $latest_blog['url'] ) && ! empty( $latest_blog['image_url'] )) {
				echo '<a href="' . esc_url( $href_url ) . '" target="_blank">
				<figure class="wppfm-weblog-teaser">
					<img decoding="async" width="422" height="149" fetchpriority="high" class="wppfm-weblog-teaser-figure" src="' . esc_url( $latest_blog['image_url'] ) . '" title="' . esc_attr( $latest_blog['title'] ) . '" alt="new blog post">
					<figcaption class="wppfm-weblog-teaser-caption">' . esc_html__('New blog post! Discover pro tips to get more out of your web shop!', 'wp-product-feed-manager') . '</figcaption>
					</figure>
				</a>';
			}
		}
	}

	// end of WPPFM_Feed_List_Page class

endif;
