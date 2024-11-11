<?php

/**
 * WPPFM Category Wrapper Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Category_Wrapper' ) ) :

	abstract class WPPFM_Category_Wrapper {

		protected abstract function display();

		/**
		 * Returns a category mapping, containing all shop categories as rows.
		 *
		 * @param  string $mode displays a normal category selector or a category mapping selector when 'mapping' is given. Default = 'normal'.
		 */
		protected function category_table_content( $mode = 'normal' ) {
			$shop_categories = WPPFM_Taxonomies::get_shop_categories_list();

			$this->category_rows( $shop_categories, 0, $mode );
		}

		/**
		 * Returns a product filter element.
		 */
		protected function product_filter() {
			WPPFM_Category_Selector_Element::product_filter_selector();
		}

		/**
		 * Renders the category rows.
		 *
		 * @param $shop_categories
		 * @param $category_depth_level
		 * @param $mode
		 */
		private function category_rows( $shop_categories, $category_depth_level, $mode ) {
			$level_indicator = str_repeat( '— ', $category_depth_level );

			if ( $shop_categories ) {
				foreach ( $shop_categories as $category ) {
					$category_children = $this->get_sub_categories( $category );

					WPPFM_Category_Selector_Element::category_mapping_row( $category, $category_children, $level_indicator, $mode );

					if ( $category->children && count( (array) $category->children ) > 0 ) {
						self::category_rows( $category->children, $category_depth_level + 1, $mode );
					}
				}
			} else {
				echo esc_html__( 'No shop categories found.', 'wp-product-feed-manager' );
			}
		}

		/**
		 * Returns the ids of the subcategories of a specific category.
		 *
		 * @param WP_Term $category the main category to check for subcategories.
		 *
		 * @return string with the subcategories in a string like "[273, 272, 271]".
		 */
		private function get_sub_categories( $category ) {
			$array_string = '';

			if ( $category->children && count( (array) $category->children ) ) {
				$array_string .= '[';

				foreach ( $category->children as $child ) {
					$array_string .= $child->term_id . ', ';
				}

				$array_string  = substr( $array_string, 0, - 2 );
				$array_string .= ']';
			}

			return $array_string;
		}
	}

	// end of WPPFM_Category_Wrapper class

endif;
