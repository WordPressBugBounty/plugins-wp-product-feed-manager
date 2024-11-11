<?php

/**
 * WPPFM Category Selector Element Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Category_Selector_Element' ) ) :

	class WPPFM_Category_Selector_Element {

		/**
		 * Renders a category mapping table head code.
		 *
		 * @param string $mode displays a normal category selector or a category mapping selector when 'mapping' is given. Default = 'normal'.
		 */
		public static function category_selector_table_head( $mode = 'normal' ) {
			$mode_column = 'mapping' === $mode ? __( 'Feed Category', 'wp-product-feed-manager' ) : __( 'Description', 'wp-product-feed-manager' );

			echo '<thead class="wp-list-table widefat fixed striped"><tr>
				<td id="shop-category-selector" class="manage-column column-cb check-column" style="width:5%;">
				<label class="screen-reader-text" for="wppfm-categories-select-all">Select All</label>
				<input id="wppfm-categories-select-all" type="checkbox">
				</td>
				<th scope="row" class="manage-column column-name wppfm-col30w">' . esc_html__( 'Shop Category', 'wp-product-feed-manager' ) . '</th>
				<th scope="row" class="manage-column column-name wppfm-col55w">' . esc_html( $mode_column ) . '</th>
				<th scope="row" class="manage-column column-name wppfm-col10w">' . esc_html__( 'Products', 'wp-product-feed-manager' ) . '</th>
				</tr></thead>';
		}

		/**
		 * Renders a single row meant for the category mapping table.
		 *
		 * @param object $category          object containing data of the active category like term_id and name
		 * @param string $category_children a string with the children of the active category
		 * @param string $level_indicator   current active level
		 * @param string $mode              defines if the category mapping row should contain a description (normal) or a category mapping (mapping) column
		 */
		public static function category_mapping_row( $category, $category_children, $level_indicator, $mode ) {
			$category_row_class = 'mapping' === $mode ? 'wppfm-category-mapping-selector' : 'wppfm-category-selector';

			echo '<tr id="category-' . esc_attr( $category->term_id ) . '"><th class="check-column" scope="row" id="shop-category-selector">
				<input class="' . esc_attr( $category_row_class ) . '" data-children="' . esc_attr( $category_children ) . '" id="feed-selector-' . esc_attr( $category->term_id ) . '"
				type="checkbox" value="' . esc_attr( $category->term_id ) . '" title="Select ' . esc_attr( $category->name ) . '">
				</th><td id="shop-category" class="wppfm-col30w">' .
					esc_attr( $level_indicator ) . esc_attr( $category->name ) . '</td><td class="field-header wppfm-col55w"><div id="feed-category-' . esc_attr( $category->term_id ) . '"></div>';
			'mapping' === $mode ? self::category_mapping_selector( 'catmap', $category->term_id, false ) : self::category_description_data_item( $category->term_id );
			echo '</td><td class="category-count wppfm-col10w">' . esc_html( $category->category_count ) . '</td></tr>';
		}

		/**
		 * Renders a category input selector.
		 *
		 * @param string  $identifier    identifier for the selector
		 * @param string  $id            id of the selector
		 * @param boolean $start_visible should this selector start visible?
		 *
		 * @return void
		 */
		public static function category_mapping_selector( $identifier, $id, $start_visible ) {
			$display         = $start_visible ? 'initial' : 'none';
			$ident           = '-1' !== $id ? $identifier . '-' . $id : $identifier;
			$category_levels = apply_filters( 'wppfm_category_selector_level', 6 );

			echo '<div id="category-selector-' . esc_attr( $ident ) . '" style="display:' . esc_attr( $display ) . '">
				<div id="selected-categories"></div><select class="wppfm-main-input-selector wppfm-cat-selector" id="' . esc_attr( $ident ) . '_0" disabled></select>';

			for ( $i = 1; $i < $category_levels; $i ++ ) {
				echo '<select class="wppfm-main-input-selector wppfm-cat-selector" id="' . esc_attr( $ident ) . '_' . esc_attr( $i ) . '" style="display:none;"></select>';
			}

			echo '<div>';
		}

		/**
		 * Returns the code for the category description column.
		 *
		 * @param string $category_id
		 */
		private static function category_description_data_item( $category_id ) {
			$category_description = '' !== category_description( $category_id ) ? category_description( $category_id ) : '—';

			echo '<span aria-hidden="true">' . esc_html( $category_description ) . '</span>';
		}

		/**
		 * Renders a product filter selector.
		 */
		public static function product_filter_selector() {
			echo '<section class="wppfm-main-product-filter-wrapper" id="wppfm-main-product-filter-wrapper" style="display:none;">
					<div class="wppfm-main-product-filter-section__header" id="wppfm-main-product-filter-section-header"><h3>' . esc_html__( 'Product Filter', 'wp-product-feed-manager' ) . '</h3></div>
					<div class="wppfm-main-product-filter-section__body" id="wppfm-main-product-filter-section-body">
					</div>
				</section>';
		}
	}

	// end of WPPFM_Category_Selector_Element class

endif;
