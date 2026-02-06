<?php

/**
 * WPPFM List Table Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_List_Table' ) ) :

	/**
	 * List Table Class.
	 */
	class WPPFM_List_Table {

		private $_column_titles = array();
		private $_table_id;
		private $_table_list;

		/** @noinspection PhpVoidFunctionResultUsedInspection */
		public function __construct() {
			$queries = new WPPFM_Queries();

			$this->_table_id        = '';
			$this->_table_list      = $queries->get_feeds_list();

			add_option( 'wp_enqueue_scripts', WPPFM_i18n_Scripts::wppfm_feed_settings_i18n() );
			add_option( 'wp_enqueue_scripts', WPPFM_i18n_Scripts::wppfm_list_table_i18n() );
		}

		/**
		 * Sets the column titles.
		 *
		 * @param array $titles of strings containing the column titles.
		 */
		public function set_column_titles( $titles ) {
			if ( ! empty( $titles ) ) {
				$this->_column_titles = $titles;
			}
		}

		/**
		 * Sets the table id.
		 *
		 * @param string $id requested DOM id to set as table id.
		 */
		public function set_table_id( $id ) {
			if ( $id !== $this->_table_id ) {
				$this->_table_id = $id;
			}
		}

		/**
		 * Renders the feed list table.
		 */
		public function get_feed_list_table() {
			echo '<table class="wppfm-table wppfm-large-table tablepress widefat fixed posts" id="wppfm-feed-list-table">';
			$this->table_header();
			$this->table_body();
			$this->table_footer();
			echo '</table>';
		}

		/**
		 * Renders the feed list table header.
		 */
		private function table_header() {
			$sortable_columns = $this->get_sortable_columns();
			$counter          = 1;

			echo '<thead><tr>';

			foreach ( $this->_column_titles as $title ) {
				$on_click_code   = $this->is_sortable_column( $counter, $sortable_columns) ? ' onclick=wppfm_sortOnColumn(' . esc_js( $counter ) . ')' : '';
				$sortable_header = $this->is_sortable_column( $counter, $sortable_columns) ? $this->sortable_title( $title ) : $title;
				$sortable_class  = $this->is_sortable_column( $counter, $sortable_columns) ? ' sortable desc' : '';

				echo '<th id="wppfm-feed-list-table-header-column-' . esc_attr( strtolower( $title ) ) . '"' . esc_attr( $on_click_code ) . ' class="wppfm-manage-column wppfm-column-name' . esc_attr( $sortable_class ) . '">' . wp_kses( $sortable_header, true ) . '</th>';
				$counter++;
			}

			echo '</tr></thead>';
		}

		/**
		 * Checks if a specified column is sortable or not.
		 *
		 * @param string $column_id        the id of the column to check.
		 * @param array  $sortable_columns a list with columns that should be sortable.
		 *
		 * @return bool true if the column is sortable.
		 */
		private function is_sortable_column( $column_id, $sortable_columns ) {
			return in_array( $column_id, $sortable_columns);
		}

		/**
		 * Renders the Feed List table footer.
		 */
		private function table_footer() {
			echo '<tfoot><tr>';

			foreach ( $this->_column_titles as $title ) {
				echo '<th>' . esc_html( $title ) . '</th>';
			}

			echo '</tr></tfoot>';
		}

		/**
		 * Returns the HTML to make a column sortable.
		 *
		 * @param string $title the title of the column that is sortable.
		 *
		 * @return string with the HTML used to make a column sortable.
		 */
		private function sortable_title( $title ) {
			$html  = '<a href="#"><span>' . esc_html( $title ) . '</span><span class="sorting-indicators">';
			$html .= '<span class="sorting-indicator asc" aria-hidden="true"></span>';
			$html .= '<span class="sorting-indicator desc" aria-hidden="true"></span>';
			$html .= '</span></a>';

			return $html;
		}

		/**
		 * Renders the List Table body.
		 */
		private function table_body() {
			$nr_products = '';
			$feed_types  = wppfm_list_feed_type_text();

			$channel_class = new WPPFM_Channel();

			echo '<tbody id="' . esc_attr( $this->_table_id ) . '">';

			foreach ( $this->_table_list as $list_item ) {
				$feed_ready_status = ( 'on_hold' === $list_item->status || 'ok' === strtolower( $list_item->status ) );
				$feed_type         = '1' === $list_item->feed_type_id ? $channel_class->get_channel_name( $list_item->channel_id ) . ' ' . __( 'Feed', 'wp-product-feed-manager' ) : $feed_types[ $list_item->feed_type_id ];

				if ( $feed_ready_status ) {
					$nr_products = $list_item->products;
				} elseif ( 'processing' === $list_item->status ) {
					$nr_products = __( 'Processing the feed, please wait...', 'wp-product-feed-manager' );
				} elseif ( 'failed_processing' === $list_item->status || 'in_processing_queue' === $list_item->status ) {
					$nr_products = __( 'Unknown', 'wp-product-feed-manager' );
				}

				echo '<tr id="wppfm-feed-row" class="wppfm-feed-row">
					<td id="title-' . esc_attr( $list_item->product_feed_id ) . '">' . esc_html( $list_item->title ) . '</td>
					<td id="url">' . esc_url( $list_item->url ) . '</td>
					<td id="updated-' . esc_attr( $list_item->product_feed_id ) . '">' . esc_html( $list_item->updated ) . '</td>
					<td id="products-' . esc_attr( $list_item->product_feed_id ) . '">' . esc_html( $nr_products ) . '</td>
					<td id="type-' . esc_attr( $list_item->product_feed_id ) . '">' . esc_html( $feed_type ) . '</td>
					<td id="feed-status-' . esc_attr( $list_item->product_feed_id ) . '" style="color:' . esc_attr( $list_item->color ) . '"><strong>';
				echo esc_attr( $this->list_status_text( $list_item->status ) );
				echo '</strong></td>
					<td id="wppfm-feed-list-actions-for-feed-' . esc_attr( $list_item->product_feed_id ) . '">';

				if ( $feed_ready_status ) {
					$this->feed_ready_action_links( $list_item->product_feed_id, $list_item->url, $list_item->status, $list_item->title, $feed_types[ $list_item->feed_type_id ] );
				} else {
					$this->feed_not_ready_action_links( $list_item->product_feed_id, $list_item->url, $list_item->title, $feed_types[ $list_item->feed_type_id ] );
				}

				echo '</td></tr>';
			}

			echo '</tbody>';
		}

		/**
		 * Returns a status text based on the actual status code of a feed.
		 *
		 * @param string $status the status code of the feed.
		 *
		 * @return string|null the feed status text (Unknown = default).
		 */
		private function list_status_text( $status ) {

			switch ( $status ) {
				case 'OK': // sometimes the status is stored in capital letters
				case 'ok':
					return __( 'Ready (auto)', 'wp-product-feed-manager' );

				case 'on_hold':
					return __( 'Ready (manual)', 'wp-product-feed-manager' );

				case 'processing':
					return __( 'Processing', 'wp-product-feed-manager' );

				case 'in_processing_queue':
					return __( 'In processing queue', 'wp-product-feed-manager' );

				case 'has_errors':
					return __( 'Has errors', 'wp-product-feed-manager' );

				case 'failed_processing':
					return __( 'Failed processing', 'wp-product-feed-manager' );

				default:
					return __( 'Unknown', 'wp-product-feed-manager' );
			}
		}

		/**
		 * Defines which columns in the Feed List will be sortable.
		 *
		 * @since 2.38.0
		 * @return string[]
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
		 * Generates the code for the Action buttons used in the feed list row where the feed is in ready mode.
		 * This function is the PHP equal for the feedReadyActions() function in the wppfm_feed-list.js file.
		 *
		 * @param string $feed_id
		 * @param string $feed_url
		 * @param string $status
		 * @param string $title
		 * @param string $feed_type
		 *
		 * @noinspection BadExpressionStatementJS
		 * @noinspection JSVoidFunctionReturnValueUsed
		 * @noinspection CommaExpressionJS
		 */
		private function feed_ready_action_links( $feed_id, $feed_url, $status, $title, $feed_type ) {
			$file_exists    = 'No feed generated' !== $feed_url;
			$url_strings    = explode( '/', $feed_url );
			$file_name      = stripos( $feed_url, '/' ) ? end( $url_strings ) : $title;
			$change_status  = 'ok' === strtolower( $status ) ? __( 'Auto-off', 'wp-product-feed-manager' ) : __( 'Auto-on', 'wp-product-feed-manager' );
			$feed_type_link = wppfm_convert_string_with_spaces_to_lower_case_string_with_dashes( $feed_type );
			$action_id      = wppfm_convert_string_with_spaces_to_lower_case_string_with_dashes( $title );

			echo '<strong><a href="javascript:void(0);" id="wppfm-edit-' . esc_attr( $action_id ) . '-action" onclick="parent.location=\'admin.php?page=wppfm-feed-editor-page&feed-type=' . esc_attr( $feed_type_link ) . '&id=' . esc_attr( $feed_id ) . '\'">' . esc_html__( 'Edit', 'wp-product-feed-manager' ) . '</a>';
			echo $file_exists ? ' | <a href="javascript:void(0);" id="wppfm-view-' . esc_html( $action_id ) . '-action" onclick="wppfm_viewFeed(\'' . esc_url( $feed_url ) . '\')">' . esc_html__( 'View', 'wp-product-feed-manager' ) . '</a>' : '';
			echo ' | <a href="javascript:void(0);" id="wppfm-delete-' . esc_attr( $action_id ) . '-action" onclick="wppfm_deleteSpecificFeed(' . esc_html( $feed_id ) . ', \'' . esc_html( $file_name ) . '\')">' . esc_html__( 'Delete', 'wp-product-feed-manager' ) . '</a>';
			echo $file_exists && 'Google Merchant Promotions Feed' !== $feed_type ? '<a href="javascript:void(0);" id="wppfm-deactivate-' . esc_attr( $action_id ) . '-action" onclick="wppfm_deactivateFeed(' . esc_attr( $feed_id ) . ')" id="feed-status-switch-' . esc_attr( $feed_id ) . '"> | ' . esc_html( $change_status ) . '</a>' : '';
			echo ' | <a href="javascript:void(0);" id="wppfm-duplicate-' . esc_attr( $action_id ) . '-action" onclick="wppfm_duplicateFeed(' . esc_attr( $feed_id ) . ', \'' . esc_html( $title ) . '\')">' . esc_html__( 'Duplicate', 'wp-product-feed-manager' ) . '</a>';
			echo 'Product Feed' === $feed_type ? ' | <a href="javascript:void(0);" id="wppfm-regenerate-' . esc_attr( $action_id ) . '-action" onclick="wppfm_regenerateFeed(' . esc_attr( $feed_id ) . ')">' . esc_html__( 'Regenerate', 'wp-product-feed-manager' ) . '</a></strong>' : '';
		}

		/**
		 * Generates the code for the Action buttons used in the feed list row where the feed is in processing or error mode.
		 * This function is the PHP equal for the feedNotReadyActions() function in the wppfm_feed-list.js file.
		 *
		 * @param string $feed_id
		 * @param string $feed_url
		 * @param string $title
		 * @param string $feed_type
		 *
		 * @noinspection BadExpressionStatementJS
		 * @noinspection JSVoidFunctionReturnValueUsed
		 * @noinspection CommaExpressionJS
		 */
		private function feed_not_ready_action_links( $feed_id, $feed_url, $title, $feed_type ) {
			if ( stripos( $feed_url, '/' ) ) {
				$url_array = explode( '/', $feed_url );
				$file_name = end( $url_array );
			} else {
				$file_name = $title;
			}

			$feed_type_link = wppfm_convert_string_with_spaces_to_lower_case_string_with_dashes( $feed_type );
			$action_id      = wppfm_convert_string_with_spaces_to_lower_case_string_with_dashes( $title );

			echo '<strong><a href="javascript:void(0);" id="wppfm-edit-' . esc_attr( $action_id ) . '-action" onclick="parent.location=\'admin.php?page=wppfm-feed-editor-page&feed-type=' . esc_attr( $feed_type_link ) . '&id=' . esc_attr( $feed_id ) . '\'">' . esc_html__( 'Edit', 'wp-product-feed-manager' ) . '</a>';
			echo ' | <a href="javascript:void(0);" id="wppfm-delete-' . esc_attr( $action_id ) . '-action" onclick="wppfm_deleteSpecificFeed(' . esc_attr( $feed_id ) . ', \'' . esc_attr( $file_name ) . '\')">' . esc_html__( 'Delete', 'wp-product-feed-manager' ) . '</a>';
			echo 'Product Feed' === $feed_type ? ' | <a href="javascript:void(0);" id="wppfm-regenerate-' . esc_attr( $action_id ) . '-action" onclick="wppfm_regenerateFeed(' . esc_attr( $feed_id ) . ')">' . esc_html__( 'Regenerate', 'wp-product-feed-manager' ) . '</a></strong>' : '';
			$this->feed_status_checker_script( $feed_id );
		}

		/**
		 * Returns a script that is placed on rows of feeds that are still processing or waiting in the queue. This script then runs every 10 seconds and checks the status
		 * of that specific feed generation processes. It is responsible for showing the correct status of this feed in the feed list.
		 *
		 * @param string $feed_id
		 *
		 * @noinspection JSCheckFunctionSignatures
		 */
		private function feed_status_checker_script( $feed_id ) {
			echo '<script type="text/javascript">var wppfmStatusCheck_' . esc_attr( $feed_id ) . ' = null;
				(function(){ wppfmStatusCheck_' . esc_attr( $feed_id ) . ' = window.setInterval( wppfm_checkAndSetStatus_' . esc_attr( $feed_id ) . ', 10000, ' . esc_attr( $feed_id ) . ' ) })();
				function wppfm_checkAndSetStatus_' . esc_attr( $feed_id ) . '( feedId ) {
				  wppfm_getCurrentFeedStatus( feedId, function( result ) {
				    var data = JSON.parse( result );
				    wppfm_resetFeedStatus( data );
				    if( data["status_id"] !== "3" && data["status_id"] !== "4" ) {
				      window.clearInterval( wppfmStatusCheck_' . esc_attr( $feed_id ) . ' );
	  				  wppfmRemoveFromQueueString( feedId );
				    }
				  } );
				}
				</script>';
		}
	}


	// end of WPPFM_List_Table class

endif;
