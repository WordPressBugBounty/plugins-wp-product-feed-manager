<?php

/**
 * WP Product Processing Support Trait.
 *
 * @package WP Product Feed Manager/Application/Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WPPFM_Processing_Support {

	protected $_selected_number;
	/** @var string|null */
	private $_temp_tax_country = null;
	/** @var string|null */
	private $_temp_currency = null;

    

	/**
	 * Returns the correct category for this specific product, based on the selection in the Category Mapping table.
	 * This function supports the Yoast Primary Category.
	 *
	 * @param string $id               the id of the product.
	 * @param string $main_category    the selected Default Category.
	 * @param object $category_mapping containing the selected categories from the Category Mapping table.
	 *
	 * @return string
	 */
	protected function get_mapped_category( $id, $main_category, $category_mapping ) {
		$result                 = false;
		$support_class          = new WPPFM_Feed_Support();
		$yoast_primary_category = WPPFM_Taxonomies::get_primary_cat( $id );
		$yoast_cat_is_selected  = $yoast_primary_category ? $support_class->selected_category_id( $yoast_primary_category[0]->term_id, $category_mapping ) : false;

		$product_categories = $yoast_primary_category && false !== $yoast_cat_is_selected ? $yoast_primary_category :
			wp_get_post_terms( $id, 'product_cat', array( 'taxonomy' => 'product_cat' ) ); // get the categories from a specific product in the shop

		if ( $product_categories && ! is_wp_error( $product_categories ) ) {
			// Loop through each category.
			foreach ( $product_categories as $category ) {
				// Check if this category is selected in the category mapping.
				$shop_category_id = $support_class->selected_category_id( $category->term_id, $category_mapping );

				// Only add this product when at least one of the categories is selected in the category mapping.
				if ( false !== $shop_category_id ) {
					//phpcs: ignore
					switch ( $category_mapping[ $shop_category_id ]->feedCategories ) {
						case 'wp_mainCategory':
							$result = $main_category;
							break;

						case 'wp_ownCategory':
							$result = WPPFM_Taxonomies::get_shop_categories( $id, ' > ' );
							break;

						default:
							$result = $category_mapping[ $shop_category_id ]->feedCategories;
					}

					// Found a selected category so now return the result.
					return $result; // Fixed ticket #1117.

				} else { // If this product was not selected in the category mapping, it is possible it has been filtered in, so map it to the default category.
					$result = $main_category;
				}
			}
		} else {
			if ( is_wp_error( $product_categories ) ) {
				wppfm_handle_wp_errors_response(
					$product_categories,
					sprintf(
						/* translators: %s: link to the support page */
						__(
							'2131 - Please try to refresh the page and open a support ticket at %s if the issue persists.',
							'wp-product-feed-manager'
						),
						WPPFM_SUPPORT_PAGE_URL
					)
				);
			}

			return false;
		}

		return $result;
	}

	/**
	 * Checks if this product has been filtered out of the feed, based on a filter selection.
	 *
	 * @param string $feed_filter_strings the feed filter string.
	 * @param array  $product_data        an array with product data.
	 *
	 * @return boolean true if the product is filtered out.
	 */
	protected function is_product_filtered( $feed_filter_strings, $product_data ) {
		if ( $feed_filter_strings ) {
			return $this->filter_result( json_decode( $feed_filter_strings[0]['meta_value'] ), $product_data );
		} else {
			return false;
		}
	}

	/**
	 * Gets the parent ids of a specific product.
	 *
	 * @param string $product_id the product id for which to look for parent ids.
	 *
	 * @return array with the parent ids.
	 */
	protected function get_product_parent_ids( $product_id ) {
		$queries_class = new WPPFM_Queries();

		$query_result = $queries_class->get_product_parents( $product_id );
		$ids          = array();

		foreach ( $query_result as $result ) {
			$ids[] = $result['ID'];
		}

		return $ids;
	}

	/**
	 * Extracts the column names of the selected sources, from a string that describes the selections for a specific feed attribute.
	 *
	 * @param string $value_string a string containing the source, condition and change value parameters.
	 *
	 * @return array with the source column names.
	 */
	protected function get_source_columns_from_attribute_value( $value_string ) {
		$source_columns = array();

		$value_object = json_decode( $value_string );

		if ( $value_object && property_exists( $value_object, 'm' ) ) {
			foreach ( $value_object->m as $source ) {
				// TODO: I guess I should further reduce the "if" loops by combining them more then now
				if ( is_object( $source ) && property_exists( $source, 's' ) ) {
					if ( property_exists( $source->s, 'source' ) ) {
						if ( 'combined' !== $source->s->source ) {
							$source_columns[] = $source->s->source;
						} else {
							if ( property_exists( $source->s, 'f' ) ) {
								$source_columns = array_merge( $source_columns, $this->get_combined_sources_from_combined_string( $source->s->f ) );
							}
						}
					}
				}
			}
		}

		return $source_columns;
	}

	/**
	 * Extracts the column names of the selected conditions, from a string that describes the selections for a specific feed attribute.
	 *
	 * @param string $value_string a string containing the source, condition and change value parameters.
	 *
	 * @return array with the condition column names.
	 */
	protected function get_condition_columns_from_attribute_value( $value_string ) {
		$condition_columns = array();

		$value_object = json_decode( $value_string );

		if ( $value_object && property_exists( $value_object, 'm' ) ) {
			foreach ( $value_object->m as $source ) {
				if ( is_object( $source ) && property_exists( $source, 'c' ) ) {
					for ( $i = 0; $i < count( $source->c ); $i ++ ) {
						$condition_columns[] = $this->get_names_from_string( $source->c[ $i ]->{$i + 1} );
					}
				}
			}
		}

		return $condition_columns;
	}

	/**
	 * Extracts the column names of the selected queries, from a string that describes the selections for a specific feed attribute.
	 *
	 * @param string $value_string a string containing the source, condition and change value parameters.
	 *
	 * @return array with the query column names.
	 */
	protected function get_queries_columns_from_attribute_value( $value_string ) {
		$query_columns = array();

		$value_object = json_decode( $value_string );

		if ( $value_object && property_exists( $value_object, 'v' ) ) {
			foreach ( $value_object->v as $changed_value ) {
				if ( property_exists( $changed_value, 'q' ) ) {
					for ( $i = 0; $i < count( $changed_value->q ); $i ++ ) {
						$query_columns[] = $this->get_names_from_string( $changed_value->q[ $i ]->{$i + 1} );
					}
				}
			}
		}

		return $query_columns;
	}

	/**
	 * Extract a column name from a string.
	 *
	 * @param string $string containing the column name.
	 *
	 * @return string with the column name.
	 */
	protected function get_names_from_string( $string ) {
		$condition_string_array = explode( '#', $string );

		return $condition_string_array[1];
	}

	/**
	 * Split the combined string into single combination items.
	 *
	 * @param string $combined_string the combined string.
	 *
	 * @return array containing the combination items.
	 */
	public function get_combined_sources_from_combined_string( $combined_string ) {
		$result                = array();
		$combined_string_array = explode( '|', $combined_string );

		$result[] = $combined_string_array[0];

		for ( $i = 1; $i < count( $combined_string_array ); $i ++ ) {
			$a = explode( '#', $combined_string_array[ $i ] );
			if ( array_key_exists( 1, $a ) ) {
				$result[] = $a[1];
			}
		}

		return $result;
	}

	/**
	 * Gets the meta-data element of a specific attribute from the attribute's list.
	 *
	 * @param string   $attribute  the feed attribute name.
	 * @param stdClass $attributes the attribute's list.
	 *
	 * @return stdClass attribute class with metadata from the attribute.
	 */
	protected function get_meta_data_from_specific_attribute( $attribute, $attributes ) {
		$i = 0;

		while ( true ) {
			if ( $attributes[ $i ]->fieldName !== $attribute ) {
				$i ++;
				if ( $i > 1000 ) {
					break;
				}
			} else {
				return $attributes[ $i ];
			}
		}

		return new stdClass();
	}

	/**
	 * Generate the value of a field based on what the user has selected in filters, combined data, static data, e.g.,
	 *
	 * @param array    $product_data             contains the product data.
	 * @param stdClass $attribute_meta_data      the meta-data of the product attribute.
	 * @param string   $main_category_feed_title the main category title.
	 * @param string   $row_category             the processed Default Category for this product.
	 * @param string   $feed_language            selected language for the feed.
	 * @param string   $feed_currency            selected currency for the feed.
	 * @param array    $relation_table           a table containing the relation between attribute names and their db field names.
	 *
	 * @return array returns a key=>value array of a specific product field where the key contains the field name and the value the field value.
	 */
	protected function process_product_field( $product_data, $attribute_meta_data, $main_category_feed_title, $row_category, $feed_language, $feed_currency, $relation_table ) {

		//@noinspection PhpVariableNameInspection
		$product_object[ $attribute_meta_data->fieldName ] = $this->get_correct_field_value(
			$attribute_meta_data,
			$product_data,
			$main_category_feed_title,
			$row_category,
			$feed_language,
			$feed_currency,
			$relation_table
		);

		return $product_object;
	}

	/**
	 * Processes a single field of a single field in the feed.
	 *
	 * @param stdClass $field_meta_data          containing the meta-data of the field.
	 * @param array    $product_data             contains the product data.
	 * @param string   $main_category_feed_title main category title.
	 * @param string   $row_category             the processed Default Category for this product.
	 * @param string   $feed_language            selected language for the feed.
	 * @param string   $feed_currency            selected currency for the feed.
	 * @param array    $relation_table           a table containing the relation between attribute names and their db field names.
	 *
	 * @return string containing the end value for the field.
	 */
	protected function get_correct_field_value( $field_meta_data, $product_data, $main_category_feed_title, $row_category, $feed_language, $feed_currency, $relation_table ) {
		$this->_selected_number = 0;

		// Do not process category strings, but only fields that are requested.
		//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( property_exists( $field_meta_data, 'fieldName' )
			&& $field_meta_data->fieldName !== $main_category_feed_title
			&& $this->meta_data_contains_category_data( $field_meta_data ) === false ) {

			$value_object = property_exists( $field_meta_data, 'value' ) && '' !== $field_meta_data->value ? json_decode( $field_meta_data->value ) : new stdClass();

			if ( property_exists( $field_meta_data, 'value' )
				&& '' !== $field_meta_data->value
				&& property_exists( $value_object, 'm' ) ) { // seems to be something we need to work on

				//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$advised_source = property_exists( $field_meta_data, 'advisedSource' ) ? $field_meta_data->advisedSource : '';

				// Get the end value depending on the filter settings.
				$end_row_value = $this->get_correct_end_row_value( $value_object->m, $product_data, $advised_source );

			} else { // No queries, edit values or alternative sources for this field.

				//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( property_exists( $field_meta_data, 'advisedSource' ) && '' !== $field_meta_data->advisedSource ) {
					//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$db_title = $field_meta_data->advisedSource;
				} else {
					$support_class = new WPPFM_Feed_Support();
					//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$source_title = $field_meta_data->fieldName;
					$db_title     = $support_class->get_db_column_title( $source_title, $relation_table );
				}

				$end_row_value = array_key_exists( $db_title, $product_data ) ? $product_data[ $db_title ] : '';
			}

			// Change value if requested.
			if ( property_exists( $field_meta_data, 'value' ) && '' !== $field_meta_data->value && property_exists( $value_object, 'v' ) ) {
				$pos = $this->_selected_number;

				if ( property_exists( $value_object, 'm' ) && property_exists( $value_object->m[ $pos ], 's' ) ) {
					$combination_string = property_exists( $value_object->m[ $pos ]->s, 'f' ) ? $value_object->m[ $pos ]->s->f : false;
					$is_money           = property_exists( $value_object->m[ $pos ]->s, 'source' ) && wppfm_meta_key_is_money( $value_object->m[ $pos ]->s->source );
				} else {
					$combination_string = false;
					$is_money           = false;
				}

				$row_value     = ! $is_money ? $end_row_value : wppfm_prep_money_values( $end_row_value . $feed_language, $feed_currency );
				$end_row_value = $this->get_edited_end_row_value( $value_object->v, $row_value, $product_data, $combination_string, $feed_language, $feed_currency );
			}
		} else {
			$end_row_value = $row_category;
		}

		return $end_row_value;
	}

	/**
	 * Processes the selected source data of an attribute to get the end value for the attribute row that goes into the feed.
	 *
	 * @param object $source_selections object with a string that describes the source selection.
	 * @param array  $product_data      main product data.
	 * @param string $advised_source    the advised source for this attribute. Empty of no advised source is active.
	 *
	 * @return string the end row value.
	 */
	private function get_correct_end_row_value( $source_selections, $product_data, $advised_source ) {
		$end_row_value = '';
		$nr_values     = count( (array)$source_selections ); // added @since 1.9.4
		$value_counter = 1; // added @since 1.9.4

		foreach ( $source_selections as $source_selection ) {
			if ( true === $this->get_filter_status( $source_selection, $product_data ) ) {

				$end_row_value = $this->get_row_source_data( $source_selection, $product_data, $advised_source );
				break;
			} else {
				// No "or else" value seems to be selected.
				if ( $value_counter >= $nr_values ) {
					return $end_row_value;
				} // added @since 1.9.4

				$this->_selected_number ++;
			}

			$value_counter ++; // added @since 1.9.4
		}

		// Not found a condition that was correct, so let's take the "for all other products" data to fetch the correct row_value.
		if ( '' === $end_row_value ) {
			$end_row_value = $this->get_row_source_data( end( $source_selections ), $product_data, $advised_source );
		}

		return $end_row_value;
	}

	/**
	 * Removes links from the post-content and post-excerpts in a product data array.
	 *
	 * @param array $product_data reference to the product data.
	 *
	 * @since 2.6.0.
	 */
	protected function remove_links_from_product_data_description( &$product_data ) {
		$pattern     = '#<a.*?>(.*?)</a>#i'; // link pattern
		$replacement = '\1';

		if ( array_key_exists( 'post_content', $product_data ) ) {
			$product_data['post_content'] = preg_replace( $pattern, $replacement, $product_data['post_content'] );
		}

		if ( array_key_exists( 'post_excerpt', $product_data ) ) {
			$product_data['post_excerpt'] = preg_replace( $pattern, $replacement, $product_data['post_excerpt'] );
		}
	}

	/**
	 * Checks if the filter on this attribute excludes the product from the feed.
	 *
	 * @param object $source_selection containing the source title and a filter string.
	 * @param array  $product_data     with the product data.
	 *
	 * @return bool true if the filter does not exclude the product.
	 */
	private function get_filter_status( $source_selection, $product_data ) {
		if ( ! empty( $source_selection ) && property_exists( $source_selection, 'c' ) ) {
			// Check if the query is true for this field.
			return $this->filter_result( $source_selection->c, $product_data );
		} else {
			// Apparently there is no condition, so the result is always true.
			return true;
		}
	}

	/**
	 * Handles an array with conditions and checks if they are true for a specific product.
	 *
	 * @param array $conditions   the array with conditions.
	 * @param array $product_data the product data.
	 *
	 * @return bool true if the condition is true for the specific product.
	 */
	private function filter_result( $conditions, $product_data ) {
		$query_results = array();
		$support_class = new WPPFM_Feed_Support();

		foreach ( $conditions as $condition ) {
			$condition_string = $support_class->get_query_string_from_query_object( $condition );

			$query_split = explode( '#', $condition_string );

			$row_result = $support_class->check_query_result_on_specific_row( $query_split, $product_data ) === true ? 'false' : 'true';

			$query_results[] = $query_split[0] . '#' . $row_result;
		}

		return $this->combined_filter_result( $query_results );
	}

	/**
	 * Receives an array with condition results and generates a single end result based on the "and" or "or"
	 * connection between the conditions.
	 *
	 * @param array $results
	 *
	 * @return bool returns true if the combined filter (with "and" or "or" conditions) is true. False if not.
	 */
	private function combined_filter_result( $results ) {
		$and_results = array();
		$or_results  = array();

		if ( count( $results ) > 0 ) {
			foreach ( $results as $query_result ) {
				$result_split = explode( '#', $query_result );

				if ( '2' === $result_split[0] ) {
					$or_results[] = $and_results; // store the current "and" result for processing as "or" result

					$and_results = array(); // clear the "and" array
				}

				$and_result = $result_split[1]; // === 'false' ? 'false' : 'true';

				$and_results[] = $and_result;
			}

			if ( count( $and_results ) > 0 ) {
				$or_results[] = $and_results;
			}

			$end_result = false;

			if ( count( $or_results ) > 0 ) {

				foreach ( $or_results as $or_result ) {
					$a = true;

					foreach ( $or_result as $and_array ) {
						if ( 'false' === $and_array ) {
							$a = false;
						}
					}

					if ( $a ) {
						$end_result = true;
					}
				}
			}
		} else {
			$end_result = false;
		}

		return $end_result;
	}

	/**
	 * Reads the source data of a row from the product data.
	 *
	 * @param object $filter         contains the filter data.
	 * @param array  $product_data   with the product data.
	 * @param string $advised_source advised source if applicable.
	 *
	 * @return string with the source data.
	 */
	private function get_row_source_data( $filter, $product_data, $advised_source ) {
		$row_source_data = '';

		if ( ! empty( $filter ) && property_exists( $filter, 's' ) ) {
			if ( property_exists( $filter->s, 'static' ) ) {
				$row_source_data = $filter->s->static;
			} elseif ( property_exists( $filter->s, 'source' ) ) {
				if ( 'combined' !== $filter->s->source ) {
					$row_source_data = array_key_exists( $filter->s->source, $product_data ) ? $product_data[ $filter->s->source ] : '';
				} else {
					$row_source_data = $this->generate_combined_source_output( $filter->s->f, $product_data );
				}
			}
		} else {
			// return the advised source data
			if ( '' !== $advised_source ) {
				$row_source_data = array_key_exists( $advised_source, $product_data ) ? $product_data[ $advised_source ] : '';
			}
		}

		return $row_source_data;
	}

	/**
	 * Returns the end result of a combined string source selector.
	 *
	 * @param string $combined_sources a string with the selected combined sources.
	 * @param array  $product_data     the product data.
	 *
	 * @return string containing the output of the combined source selector.
	 */
	private function generate_combined_source_output( $combined_sources, $product_data ) {
		$source_selectors_array = explode( '|', $combined_sources ); // Split the combined source string in an array containing every single source.
		$values_class           = new WPPFM_Feed_Value_Editors();
		$separators             = $values_class->combination_separators(); // Array with all possible separators.

		// If one of the row results is an array, the final output needs to be an array.
		$result_is_array = $this->check_if_any_source_has_array_data( $source_selectors_array, $product_data );
		$result          = $result_is_array ? array() : '';

		if ( ! $result_is_array ) {
			$result = $this->combine_the_outputs( $source_selectors_array, $separators, $product_data, false );
		} else {
			for ( $i = 0; $i < count( $result_is_array ); $i ++ ) {
				$combined_string = $this->combine_the_outputs( $source_selectors_array, $separators, $product_data, $i );
				$result[]        = $combined_string;
			}
		}

		return $result;
	}

	/**
	 * Gets the keys from the $sources string (separated by a #), and it looks if any of these keys
	 * are linked to an array in the $data_row.
	 *
	 * @param array $sources      with the source strings.
	 * @param array $product_data the product data.
	 *
	 * @return array|bool from the data_row or false if no array data is found.
	 */
	private function check_if_any_source_has_array_data( $sources, $product_data ) {
		foreach ( $sources as $source ) {
			$split_source = explode( '#', $source );

			if ( count( $split_source ) > 1 && 'static' === $split_source[1] ) {
				$last_key = 'static';
			} elseif ( 'static' === $split_source[0] ) {
				$last_key = 'static';
			} else {
				$last_key = array_pop( $split_source );
			}

			if ( array_key_exists( $last_key, $product_data ) && is_array( $product_data[ $last_key ] ) ) {
				return $product_data[ $last_key ];
			}
		}

		return false;
	}

	protected function meta_data_contains_category_data( $meta_data ) {
		if ( ! property_exists( $meta_data, 'value' ) || empty( $meta_data->value ) ) {
			return false;
		}

		$meta_obj = json_decode( $meta_data->value );

		return property_exists( $meta_obj, 't' );
	}

	/**
	 * Returns an end value from a source that has an edit value input.
	 *
	 * @param array  $change_parameters  the change parameters including filter parameters if set.
	 * @param string $original_output    the output before the change.
	 * @param array  $product_data       the product data.
	 * @param bool   $combination_string true if the source is a combined string.
	 * @param string $feed_language      the language of the feed.
	 * @param string $feed_currency      the feed currency.
	 *
	 * @return string the end value.
	 */
	private function get_edited_end_row_value( $change_parameters, $original_output, $product_data, $combination_string, $feed_language, $feed_currency ) {
		$result_is_filtered = false;
		$support_class      = new WPPFM_Feed_Support();
		$final_output       = '';

		// Loop through the given change input rules.
		for ( $i = 0; $i < count( $change_parameters ); $i ++ ) {
			if ( property_exists( $change_parameters[ $i ], 'q' ) ) {
				$filter_result = $this->filter_result( $change_parameters[ $i ]->q, $product_data );
			} else {
				$filter_result = true;
			}

			// Only change the value if the filter is true.
			if ( true === $filter_result ) {
				$combined_data_elements = $combination_string ? $this->get_combined_elements( $product_data, $combination_string ) : '';
				$final_output           = $support_class->edit_value(
					$original_output,
					$change_parameters[ $i ]->{$i + 1},
					$combination_string,
					$combined_data_elements,
					$feed_language,
					$feed_currency
				);

				$original_output = $final_output; // Set the new output as the original output for the next change rule.
				$result_is_filtered = true;
			}
		}

		// If the rules are all filtered out, the original output needs to be returned.
		if ( false === $result_is_filtered ) {
			$final_output = $original_output;
		}

		return $final_output;
	}

	/**
	 * Returns an array with the elements of a combined source selector.
	 *
	 * @param array  $product_data       the product data.
	 * @param string $combination_string the combination data.
	 *
	 * @return array with the combined elements.
	 */
	private function get_combined_elements( $product_data, $combination_string ) {
		$result         = array();
		$found_all_data = true;

		$combination_elements = explode( '|', $combination_string );

		if ( false === strpos( $combination_elements[0], 'static#' ) ) {
			if ( array_key_exists( $combination_elements[0], $product_data ) ) {
				$result[] = $product_data[ $combination_elements[0] ];
			} else {
				$found_all_data = false;
			}
		} else {
			$element  = explode( '#', $combination_elements[0] );
			$result[] = $element[1];
		}

		for ( $i = 1; $i <= count( $combination_elements ) - 1; $i ++ ) {
			$pos      = strpos( $combination_elements[ $i ], '#' );
			$selector = substr( $combination_elements[ $i ], ( false !== $pos ? $pos + 1 : 0 ) );

			if ( substr( $selector, 0, 7 ) === 'static#' ) {
				$selector = explode( '#', $selector );
				$result[] = $selector[1];
			} elseif ( array_key_exists( $selector, $product_data ) ) {
				$result[] = $product_data[ $selector ];
			} else {
				//array_push( $result, $selector );
				$found_all_data = false;
			}
		}

		if ( $found_all_data ) {
			return $result;
		} else {
			$message = sprintf( 'Missing the data for one or both combined elements of the combination %s in the product with id %s.', $combination_string, $product_data['ID'] );
			do_action( 'wppfm_feed_generation_message', $this->_feed_data->feedId, $message );
			return array();
		}
	}

	/**
	 * Creates a single string with the output from a combined source selection.
	 *
	 * @param array    $source_selectors_array contains strings with the combined source selection elements.
	 * @param array    $separators             contains all possible separators for a combined source.
	 * @param array    $product_data           the product data.
	 * @param int|bool $array_pos              the position in the array, if an array is selected as a combined element. False if this is not the case.
	 *
	 * @return string contains the combined output of a combined source selection.
	 */
	private function combine_the_outputs( $source_selectors_array, $separators, $product_data, $array_pos ) {
		$combined_string = '';

		foreach ( $source_selectors_array as $source ) {
			$split_source = explode( '#', $source );

			// Get the separator.
			$separators_id = count( $split_source ) > 1 && 'static' !== $split_source[0] ? $split_source[0] : 0;
			$sep           = $separators[ $separators_id ];

			$data_key = count( $split_source ) > 1 && 'static' !== $split_source[0] ? $split_source[1] : $split_source[0];

			if ( ( array_key_exists( $data_key, $product_data ) && $product_data[ $data_key ] ) || 'static' === $data_key ) {
				if ( 'static' !== $data_key && ! is_array( $product_data[ $data_key ] ) ) { // Not static and no array.
					$combined_string .= $sep;
					$combined_string .= $product_data[ $data_key ];
				} elseif ( 'static' === $data_key ) { // Static inputs.
					$static_string    = count( $split_source ) > 2 ? $split_source[2] : $split_source[1];
					$combined_string .= $sep . $static_string;
				} else { // Array inputs.
					$input_array      = $product_data[ $data_key ][ $array_pos ];
					$combined_string .= $sep . $input_array;
				}
			}
		}

		return $combined_string;
	}

	/**
	 * Generates an array with the relations between the WooCommerce fields and the channel fields.
	 *
	 * @return array with the relations.
	 */
	public function channel_to_woocommerce_field_relations() {
		$relations = array();

		foreach ( $this->_feed->attributes as $attribute ) {

			// Get the source name except for the category_mapping field.
			if ( 'category_mapping' !== $attribute->fieldName ) {
				$source = $this->get_source_from_attribute( $attribute );
			}

			if ( ! empty( $source ) ) {
				// Correct Google product category source.
				if ( 'google_product_category' === $attribute->fieldName ) {
					$source = 'google_product_category';
				}

				// Correct Google identifier exists source.
				if ( 'identifier_exists' === $attribute->fieldName ) {
					$source = 'identifier_exists';
				}

				// Fill the relation array.
				$a           = array(
					'field' => $attribute->fieldName,
					'db'    => $source,
				);
				$relations[] = $a;
			}
		}

		if ( empty( $relations ) ) {
			wppfm_write_log_file( 'Function get_channel_to_woocommerce_field_relations returned zero relations.' );
		}

		return $relations;
	}

	/**
	 * Extract the source name from the attribute string.
	 *
	 * @param object $attribute the attribute.
	 *
	 * @return string with the source.
	 */
	private function get_source_from_attribute( $attribute ) {

		$value_source = property_exists( $attribute, 'value' ) ? $this->get_source_from_attribute_value( $attribute->value ) : '';

		if ( ! empty( $value_source ) ) {
			$source = $value_source;
		} elseif ( property_exists( $attribute, 'advisedSource' ) && '' !== $attribute->advisedSource ) {
			$source = $attribute->advisedSource;
		} else {
			$source = $attribute->fieldName;
		}

		return $source;
	}

	/**
	 * Extract the source value from the attribute string.
	 *
	 * @param string $value attribute string.
	 *
	 * @return string the source value.
	 */
	private function get_source_from_attribute_value( $value ) {
		$source = '';

		if ( $value ) {
			$value_string = $this->get_source_string( $value );

			$value_object = json_decode( $value_string );

			if ( is_object( $value_object ) && property_exists( $value_object, 'source' ) ) {
				$source = $value_object->source;
			}
		}

		return $source;
	}

	/**
	 * Extracts the source string from a value string.
	 *
	 * @param string $value_string the value string.
	 *
	 * @return string the source string.
	 */
	private function get_source_string( $value_string ) {
		$source_string = '';

		if ( ! empty( $value_string ) ) {
			$value_object = json_decode( $value_string );

			if ( $value_object && is_object( $value_object ) && property_exists( $value_object, 'm' )
				&& ! empty( $value_object->m[0] )
				&& property_exists( $value_object->m[0], 's' ) ) {
				$source_string = wp_json_encode( $value_object->m[0]->s );
			}
		}

		return $source_string;
	}

	/**
	 * Generates an XML string of one product including its variations.
	 *
	 * @param array  $product_placeholder contains the product data.
	 * @param string $category_name       field name of the category.
	 * @param string $description_name    field name of the description.
	 *
	 * @return string an XML string for the feed.
	 */
	protected function convert_data_to_xml( $product_placeholder, $category_name, $description_name, $channel ) {
		return $product_placeholder ? $this->make_xml_string_row( $product_placeholder, $category_name, $description_name, $channel ) : '';
	}

	/**
	 * Generates an XML string for one product.
	 *
	 * @param   array  $product_placeholder contains all product data.
	 * @param   string $category_name       selected category name.
	 * @param   string $description_name    the name of the description.
	 * @param   string $channel             contains the channel id.
	 *
	 * @return string an XML string for the feed.
	 * @noinspection PhpUndefinedMethodInspection
	 */
	private function make_xml_string_row( $product_placeholder, $category_name, $description_name, $channel ) {
		$product_node_name              = function_exists( 'wppfm_product_node_name' ) ? wppfm_product_node_name( $channel ) : 'item';
		$node_pre_tag_name              = function_exists( 'wppfm_get_node_pre_tag' ) ? wppfm_get_node_pre_tag( $channel ) : 'g:';
		$product_node                   = apply_filters( 'wppfm_xml_product_node_name', $product_node_name, $channel );
		$node_pre_tag                   = apply_filters( 'wppfm_xml_product_pre_tag_name', $node_pre_tag_name, $channel );
		// _channel_class functions are defined in the channel-specific Channel Class. But if that specific function does not exist, the function in this file will be used.
		$attributes_with_sub_attributes = apply_filters( 'wppfm_attributes_with_sub_attributes', $this->_channel_class->keys_that_have_sub_tags() );
		$attributes_repeated_fields     = apply_filters( 'wppfm_attributes_that_are_repeatable', $this->_channel_class->keys_that_can_be_used_more_than_once() );
		$sub_keys_for_subs_attributes   = apply_filters( 'wppfm_keys_for_sub_attributes', $this->_channel_class->sub_keys_for_sub_tags() );

		$this->_channel_class->add_xml_sub_tags( $product_placeholder, $sub_keys_for_subs_attributes, $attributes_with_sub_attributes, $node_pre_tag );
		$xml_string = "<$product_node>";

		// For each product value item.
		foreach ( $product_placeholder as $key => $value ) {
			if ( ! is_array( $value ) ) {
				$xml_string .= $this->make_xml_string( $key, $value, $category_name, $description_name, $node_pre_tag, $attributes_with_sub_attributes, $attributes_repeated_fields );
			} else {
				$xml_string .= $this->make_xml_string_from_array( $key, $value, $node_pre_tag, $attributes_with_sub_attributes, $attributes_repeated_fields, $channel );
			}
		}

		$xml_string .= "</$product_node>";

		return $xml_string;
	}

	/**
	 * Generates a csv string of one product including its variations.
	 *
	 * @param array  $product_placeholder contains the product data.
	 * @param array  $active_fields       contains all the active fields.
	 * @param string $csv_separator       with the csv separator for this csv file.
	 *
	 * @return string a csv string for the feed.
	 */
	protected function convert_data_to_csv( $product_placeholder, $active_fields, $csv_separator ) {
		if ( $product_placeholder ) {
			if ( count( $product_placeholder ) > count( $active_fields ) ) {
				$support_class = new WPPFM_Feed_Support();
				$support_class->correct_active_fields_list( $active_fields );
			}

			// The first row in a csv file should contain the index, the following rows the data.
			return $this->make_comma_separated_string_from_data_array( $product_placeholder, $active_fields, $this->_feed_data->channel, $csv_separator );
		} else {
			return '';
		}
	}

	/**
	 * Generates a tab separated string for a tsv file.
	 *
	 * @param array $product_placeholder contains the product data.
	 *
	 * @return string a tsv string for the feed.
	 */
	protected function convert_data_to_tsv( $product_placeholder ) {
		if ( $product_placeholder ) {
			return $this->make_feed_string_from_product_placeholder( $product_placeholder, "\t" );
		} else {
			return '';
		}
	}

	/**
	 * Generates a txt string for a txt file.
	 *
	 * @param array  $product_placeholder contains the product data.
	 * @param string $separator           the txt separator.
	 *
	 * @return string a txt string for the feed.
	 */
	protected function convert_data_to_txt( $product_placeholder, $separator ) {
		if ( $product_placeholder ) {
			return $this->make_feed_string_from_product_placeholder( $product_placeholder, $separator );
		} else {
			return '';
		}
	}

	/**
	 * Takes one row data and converts it to a tab delimited string.
	 *
	 * @param array  $product_placeholder contains the product data.
	 * @param string $separator           the separator.
	 *
	 * @return string with the feed string.
	 */
	protected function make_feed_string_from_product_placeholder( $product_placeholder, $separator ) {
		$row_string = '';

		foreach ( $product_placeholder as $row_item ) {
			$a_row_item     = ! is_array( $row_item ) ? preg_replace( "/[\r\n]/", "", $row_item ) : implode( ', ', $row_item );
			$clean_row_item = wp_strip_all_tags( $a_row_item );
			$row_string    .= $clean_row_item;

			'TAB' === $separator ? $row_string .= "\t" : $row_string .= $separator;
		}

		$row = 'TAB' === $separator ? trim( $row_string ) : trim( $row_string, $separator ); // removes the separator at the end of the line

		return $row . "\r\n";
	}

	/**
	 * Takes the data for one row and converts it to a comma-separated string that fits into the feed.
	 *
	 * @param   array   $row_data       Array with the attribute name => attribute data.
	 * @param   array   $active_fields  Array containing the attributes that are active and need to go into the feed.
	 * @param   string  $channel        Channel id.
	 * @param   string  $separator      Requested data separator (default ,).
	 *
	 * @return string comma separated string with row data.
	 */
	private function make_comma_separated_string_from_data_array( $row_data, $active_fields, $channel, $separator = ',' ) {
		$row_string = '';

		$quotes_not_allowed = wppfm_channel_requires_no_quotes_on_empty_attributes( $channel );

		// @since 2.11.0 allows choosing another separator for array data.
		$separator_for_arrays = apply_filters( 'wppfm_separator_for_arrays_in_csv_feed', '|' );

		// Loop through the active attributes.
		foreach ( $active_fields as $row_item ) {
			if ( array_key_exists( $row_item, $row_data ) ) {
				$clean_row_item = ! is_array( $row_data[ $row_item ] ) ? preg_replace( "/[\r\n]/", '', $row_data[ $row_item ] ) : implode( $separator_for_arrays, $row_data[ $row_item ] );
			} else {
				$clean_row_item = '';
			}

			$quotes = $quotes_not_allowed && '' === $clean_row_item ? '' : '"';

			$remove_double_quotes_from_string = str_replace( '"', "'", $clean_row_item );
			$row_string                      .= $quotes . $remove_double_quotes_from_string . $quotes . $separator;
		}

		$row = rtrim( $row_string, $separator ); // Removes the comma at the end of the line.

		return $row . "\r\n";
	}

	/**
	 * Generates the header string for a csv or tsv file.
	 *
	 * @param array  $active_fields array with the active fields.
	 * @param string $separator     the separator to use for the header.
	 *
	 * @return string with the header.
	 */
	protected function make_custom_header_string( $active_fields, $separator ) {
		$header = implode( $separator, $active_fields );

		return $header . "\r\n";
	}

	/**
	 * Make an array of product element strings.
	 *
	 * @param string $key                  the node id.
	 * @param array  $value                the array containing the values for the XML string.
	 * @param string $google_node_pre_tag  the Google node tag to use.
	 * @param array  $tags_with_sub_tags   an array with attributes that have sub tags.
	 * @param array  $tags_repeated_fields an array with attributes that can be used more than once.
	 * @param string $channel              the channel id.
	 *
	 * @return string an XML string from an array of product elements.
	 * @noinspection PhpUndefinedMethodInspection
	 */
	private function make_xml_string_from_array( $key, $value, $google_node_pre_tag, $tags_with_sub_tags, $tags_repeated_fields, $channel ) {
		$xml_strings = '';

		for ( $i = 0; $i < count( $value ); $i ++ ) {
			$xml_key      = 'Extra_Afbeeldingen' === $key ? 'Extra_Image_' . ( $i + 1 ) : $key; // Required for Beslist.nl
			$xml_strings .= $this->make_xml_string( $xml_key, $value[ $i ], '', '', $google_node_pre_tag, $tags_with_sub_tags, $tags_repeated_fields );
		}

		// Specific for the Atalanda channel option key.
		if ( '38' === $channel && 'option' === $key ) {
			$xml_strings = '<atalanda:options>' . str_replace( 'g:', 'atalanda:', $xml_strings ) . '</atalanda:options>';
		}

		return $xml_strings;
	}

	/**
	 * Generates an XML node.
	 *
	 * Returns an XML node for a product tag and uses the product data to make the node.
	 *
	 * @param string $key                   note id.
	 * @param string $xml_value             note value.
	 * @param string $category_name         category name.
	 * @param string $description_name      description name.
	 * @param string $google_node_pre_tag   pre node tag.
	 * @param array  $tags_with_sub_tags    array with tags that have a sub tag construction.
	 * @param array  $tags_repeated_fields  array with tags that are allowed to be placed in the feed more than once
	 *
	 * @since 1.1.0
	 * @since 2.34.0. Added a new line break at the end of each XML row to make a more readable XML feed and prevent large text lines.
	 * @return string Node string in xml format eg. <id>43</id>.
	 */
	private function make_xml_string( $key, $xml_value, $category_name, $description_name, $google_node_pre_tag, $tags_with_sub_tags, $tags_repeated_fields ) {
		$xml_string     = '';
		$key            = str_replace( ' ', '_', $key ); // @since 2.40.0
		$repeated_field = in_array( $key, $tags_repeated_fields, true );
		$subtag_sep     = apply_filters( 'wppfm_sub_tag_separator', '||' );

		if ( substr( $xml_value, 0, 5 ) === '!sub:' ) {
			$sub_array = explode( '|', $xml_value );
			$sa        = $sub_array[0];
			$st        = explode( ':', $sa );
			$sub_tag   = $st[1];
			$xml_value = "<$google_node_pre_tag$sub_tag>$sub_array[1]</$google_node_pre_tag$sub_tag>";
		}

		if ( $repeated_field && ! is_array( $xml_value ) ) {
			$xml_value = explode( $subtag_sep, $xml_value );
		}

		// Keys to be added in a CDATA bracket to the XML feed.
		$cdata_keys = apply_filters ( 'wppfm_cdata_keys', array(
			$category_name,
			$description_name,
			'title'
		) );

		if ( ! is_array( $xml_value ) && ! in_array( $key, $tags_with_sub_tags, true ) ) {
			if ( in_array( $key, $cdata_keys, true ) ) {
				$xml_value = $this->convert_to_character_data_string( $xml_value ); // Put in a ![CDATA[...]] bracket.
			} else {
				$xml_value = $this->convert_to_xml_value( $xml_value );
			}
		}

		if ( '' !== $key ) {
			if ( is_array( $xml_value ) && $repeated_field ) {
				foreach ( $xml_value as $value_item ) {
					$xml_string .= $this->add_xml_string( $key, $value_item, $google_node_pre_tag );
				}
			} else {
				$xml_string = $this->add_xml_string( $key, $xml_value, $google_node_pre_tag );
			}
		}

		return $xml_string . "\r\n";
	}

	/**
	 * Generates a single XML line string.
	 *
	 * @param string $key                 the key to use.
	 * @param string $xml_value           the value to use
	 * @param string $google_node_pre_tag the node pre tag to use.
	 *
	 * @since 1.9.0.
	 * @since 2.13.0 Added the wppfm_xml_element_attribute filter.
	 * @since 2.38.0 Removed a code part that would replace a - character by a _ character as the - character is not recommended for an XML file. But the Vivino XML channel requires the use of an _ in some of their keys.
	 * @since 3.8.0 Added the wppfm_xml_key_prefix_per_attribute filter to allow different XML key prefixes in one feed, different per attribute.
	 * @since 1.9.0
	 * @return string with a single XML line.
	 */
	private function add_xml_string( $key, $xml_value, $google_node_pre_tag ) {
		//$not_allowed_characters = array( ' ', '-' );
		//$clean_key              = str_replace( $not_allowed_characters, '_', $key );

		//@since 3.8.0
		$google_node_pre_tag = apply_filters( 'wppfm_xml_key_prefix_per_attribute', $google_node_pre_tag, $key, $xml_value );

		// @since 2.13.0
		$element_attribute        = apply_filters( 'wppfm_xml_element_attribute', '', $key, $xml_value );
		$element_attribute_string = '' !== $element_attribute ? ' ' . $element_attribute : '';

		//return "<$google_node_pre_tag$clean_key$element_attribute_string>$xml_value</$google_node_pre_tag$clean_key>";
		return "<$google_node_pre_tag$key$element_attribute_string>$xml_value</$google_node_pre_tag$key>";
	}

	/**
	 * Converts an ordinary XML string into a CDATA string as long as it's not only a numeric value.
	 *
	 * @param string $string XML string to convert.
	 *
	 * @since 2.34.0. Added an utf8 check and rewritten the CDATA string rule.
	 * @since 3.5.0. Replaced the deprecated utf8_encode with mb_convert_encoding.
	 * @return string the CDATA string.
	 */
	protected function convert_to_character_data_string( $string ) {
		if ( is_numeric( $string ) ) {
			return $string;
		}

		if ( mb_detect_encoding( $string, 'UTF-8' ) ) { // string contains non-UTF-8 characters so they should be removed
			$string = mb_convert_encoding( $string, 'UTF-8', mb_detect_encoding( $string ));
		}

		return '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $string ) . ']]>';
	}

	/**
	 * Can be overridden by a channel-specific function in its class-feed.php.
	 *
	 * @param   $product_placeholder    array   Pointer to the product data.
	 * @param   $sub_keys_for_subs      array   Array with the tags that can be placed in the feed as a sub tag (e.g. <loyalty_points><ratio>).
	 * @param   $tags_repeated_fields   array   Array with tags of fields that can have more than one instance in the feed.
	 * @param   $node_pre_tag           string  The channel dependant pre tag (eg. g: for Google Feeds).
	 *
	 * @since 1.9.0
	 * @since 2.37.0. Added the extra limit parameter to the explode function so that it can work with attributes that have an - in their name, like attributes used in the Vivino XML channel.
	 * @since 3.8.0. Added extra code that splits up sub-attributes if more than one sub-attribute is available in the $sub_tags variable.
	 * @return  array   The product with the correct XML tags.
	 */
	public function add_xml_sub_tags( &$product_placeholder, $sub_keys_for_subs, $tags_repeated_fields, $node_pre_tag ) {
		$sub_tags = array_intersect_key( $product_placeholder, array_flip( $sub_keys_for_subs ) );

		if ( count( $sub_tags ) < 1 ) {
			return $product_placeholder;
		}

		$subtag_sep = apply_filters( 'wppfm_sub_tag_separator', '||' );
		$tags_value = array();
		$main_tag   = '';

		foreach ( $sub_tags as $key => $value ) {
			$split = explode( '-', $key, 2 );

			// @since 3.8.0
			// For each main tag, the $tags_value needs to be reset in order to get a separate main element in the feed
			$main_tag_changed = '' !== $main_tag && $main_tag !== $split[0];
			if ( $main_tag_changed ) {
				$tags_value = array();
			}

			$main_tag = $split[0];

			if ( in_array( $split[0], $tags_repeated_fields, true ) ) {
				$tags_counter = 0;
				$value_array  = is_array( $value ) ? $value : explode( $subtag_sep, $value );

				foreach ( $value_array as $sub_value ) {
					$prev_string                 = array_key_exists( $tags_counter, $tags_value ) ? $tags_value[ $tags_counter ] : '';
					$tags_value[ $tags_counter ] = $prev_string . '<' . $node_pre_tag . $split[1] . '>' . $sub_value . '</' . $node_pre_tag . $split[1] . '>';
					$tags_counter ++;
				}
			} else {
				$tags_value  = array_key_exists( $split[0], $product_placeholder ) ? $product_placeholder[ $split[0] ] : '';
				$tags_value .= '<' . $node_pre_tag . $split[1] . '>' . $value . '</' . $node_pre_tag . $split[1] . '>';
			}

			unset( $product_placeholder[ $key ] );
			$product_placeholder[ $split[0] ] = $tags_value;
		}

		return $product_placeholder;
	}

	/**
	 * Can be overridden by a channel-specific function in its class-feed.php. This version returns an empty array.
	 *
	 * @since 1.9.0
	 *
	 * @return array empty array.
	 */
	public function keys_that_have_sub_tags() {
		return array();
	}

	/**
	 * Can be overridden by a channel-specific function in its class-feed.php. This version returns and empty array.
	 *
	 * @since 2.1.0
	 *
	 * @return array empty array.
	 */
	public function sub_keys_for_sub_tags() {
		return array();
	}

	/**
	 * Can be overridden by a channel-specific function in its class-feed.php. this version returns an empty array.
	 *
	 * @since 1.9.0
	 *
	 * @return array empty array.
	 */
	public function keys_that_can_be_used_more_than_once() {
		return array();
	}

	/**
	 * Replaces certain characters to get a valid XML value.
	 *
	 * @param string $value_string the original value.
	 *
	 * @return string converted string.
	 */
	public function convert_to_xml_value( $value_string ) {
		$string_without_tags = wp_strip_all_tags( $value_string );
		$prep_string         = str_replace(
			array(
				'&amp;',
				'&lt;',
				'&gt;',
				'&apos;',
				'&quot;',
				'&nbsp;',
			),
			array(
				'&',
				'<',
				'>',
				'\'',
				'"',
				'nbsp;',
			),
			$string_without_tags
		);

		return str_replace(
			array(
				'&',
				'<',
				'>',
				'\'',
				'"',
				'nbsp;',
				'`',
			),
			array(
				'&amp;',
				'&lt;',
				'&gt;',
				'&apos;',
				'&quot;',
				' ',
				'',
			),
			$prep_string
		);
	}

	/**
	 * Returns the translated attachment URL (full size) for a given attachment ID and language.
	 * Falls back to the original ID if no translation exists. Also normalizes protocol to https when needed.
	 *
	 * @param int    $attachment_id     Original attachment ID.
	 * @param string $selected_language Target language code.
	 *
	 * @since 3.16.0
	 * @return string Attachment URL or empty string.
	 */
	private function get_attachment_url_translated( $attachment_id, $selected_language ) {
		if ( ! $attachment_id ) {
			return '';
		}

		if ( has_filter( 'wpml_object_id' ) && is_plugin_active( 'wpml-media-translation/plugin.php' ) ) {
			$translated_id = apply_filters( 'wpml_object_id', $attachment_id, 'attachment', true, $selected_language );
			$attachment_id = $translated_id ? $translated_id : $attachment_id;
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'full' );

		if ( ! $url ) {
			return '';
		}

		// Normalize to https if site is served over SSL.
		if ( is_ssl() ) {
			$url = str_replace( 'http://', 'https://', $url );
		}

		return $url;
	}

	/**
	 * Get formal WooCommerce custom fields data.
	 *
	 * @param string $id                the product id.
	 * @param string $parent_product_id the products parent id.
	 * @param string $field             the field data.
	 *
	 * @since 2.0.9. added the $parent_product_id parameter.
	 * @return string with formal WooCommerce field data.
	 */
	protected function get_custom_field_data( $id, $parent_product_id, $field ) {
		$custom_string = '';
		$taxonomy      = 'pa_' . $field;
		$custom_values = get_the_terms( $id, $taxonomy );

		if ( ! $custom_values && 0 !== $parent_product_id ) {
			$custom_values = get_the_terms( $parent_product_id, $taxonomy );
		}

		if ( $custom_values ) {
			foreach ( $custom_values as $custom_value ) {
				$custom_string .= $custom_value->name . ', ';
			}
		}

		return $custom_string ? substr( $custom_string, 0, - 2 ) : '';
	}

	/**
	 * Handles third party custom field data.
	 *
	 * @param string $product_id        the product id.
	 * @param string $parent_product_id the products parent id.
	 * @param string $field the field data.
	 *
	 * @since 1.6.0
	 * @since 2.0.9. added the $parent_product_id parameter.
	 * @since 3.1.0. supports the ACF plugin.
	 * @return string with the correct field data.
	 */
	protected function get_third_party_custom_field_data( $product_id, $parent_product_id, $field ) {
		$result        = '';
		$product_brand = '';

		// YITH Brands plugin.
		if ( get_option( 'yith_wcbr_brands_label' ) === $field ) { // YITH Brands plugin active
			if ( has_term( '', 'yith_product_brand', $product_id ) ) {
				$product_brand = get_the_terms( $product_id, 'yith_product_brand' );
			}

			if ( ! $product_brand && 0 !== $parent_product_id && has_term( '', 'yith_product_brand', $parent_product_id ) ) {
				$product_brand = get_the_terms( $parent_product_id, 'yith_product_brand' );
			}

			if ( $product_brand && ! is_wp_error( $product_brand ) ) {
				foreach ( $product_brand as $brand ) {
					$result .= $brand->name . ', ';
				}
			}
		}

		// WooCommerce Brands plugin.
		if ( in_array( 'woocommerce-brands/woocommerce-brands.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {

			if ( has_term( '', 'product_brand', $product_id ) ) {
				$product_brand = get_the_terms( $product_id, 'product_brand' );
			}

			if ( ! $product_brand && 0 !== $parent_product_id && has_term( '', 'product_brand', $parent_product_id ) ) {
				$product_brand = get_the_terms( $parent_product_id, 'product_brand' );
			}

			if ( $product_brand && ! is_wp_error( $product_brand ) ) {
				foreach ( $product_brand as $brand ) {
					$result .= $brand->name . ', ';
				}
			} elseif ( is_wp_error( $product_brand ) ) {
				do_action( 'wppfm_feed_generation_warning', $product_id, $product_brand ); // @since 2.3.0
			}
		}

		// Advanced Custom Fields (ACF) plugin.
		// Handles the ACF custom fields.
		// @since 3.1.0.
		if ( function_exists( 'acf_get_field' ) && function_exists( 'acf_get_value' ) && function_exists( 'acf_format_value' ) ) {
			$field_array = acf_get_field( $field );

			if ( $field_array ) {
				$field_value = acf_get_value( $product_id, $field_array );
				$format_data = acf_format_value( $field_value, $product_id, $field_array );

				// $field_array['type'] contains the Field Type
				// $field_array['return_format'] contains the Return Format

				if ( ! is_array( $format_data ) ) {
					if ( $format_data instanceof WP_Term ) {
						$format_data = $format_data->name;
					}

					// @since 3.4.0. handling the ACF True / False field type.
					if ( is_bool( $format_data ) ) {
						$format_data = $format_data ? 'true' : 'false';
					}

					$result = $format_data && is_string( $format_data ) ? $format_data . ', ' : '';
				} else {
					$data_string = '';
					$separator   = 'additional_image' === $field ? '||' : ', '; // for the additional_image attribute, use || as a separator
					$separator   = apply_filters( 'wppfm_acf_array_value_separator', $separator );

					foreach ( $format_data as $data ) {
						if ( $data instanceof WP_Term ) {
							$data = $data->name;
						}

						$data_string .= $data && is_string( $data ) ? $data . $separator : '';
					}

					$result = $data_string;
				}
			}
		}

		return $result ? substr( $result, 0, - 2 ) : '';
	}

	/**
	 * Adds a single key with value to the active feed file.
	 *
	 * @param string $key   the key.
	 * @param string $value the value.
	 */
	protected function write_single_general_xml_string_to_current_file( $key, $value ) {
		$general_xml_string = sprintf( '<%s>%s</%s>', $key, $value, $key );
		wppfm_append_line_to_file( $this->_feed_file_path, $general_xml_string, true );
	}

	/**
	 * Handles attributes that use their own procedures to ge the correct output value.
	 *
	 * @param object          $product            contains all the product data.
	 * @param WC_Product|null $woocommerce_product Optional. Already loaded WooCommerce product object to avoid redundant loading.
	 *
	 * @noinspection PhpPossiblePolymorphicInvocationInspection
	 */
	protected function handle_procedural_attributes( $product, $woocommerce_product = null ) {
		// Use provided WC_Product object if available, otherwise load it
		if ( null === $woocommerce_product ) {
			$woocommerce_product = wc_get_product( $product->ID );
		}

		$feed_id = $this->_feed_data->feedId;

		if ( false === $woocommerce_product ) {
			$msg = sprintf( 'Failed to get the WooCommerce products procedural data from product %s.', $product->ID );
			do_action( 'wppfm_feed_generation_warning', $feed_id, $msg ); // @since 2.3.0
			return;
		}

		$active_field_names = $this->_pre_data['column_names'];
		$selected_language  = $this->_feed_data->language;
		$selected_currency  = $this->_feed_data->currency;

		$woocommerce_parent_id      = $woocommerce_product->get_parent_id();
		$woocommerce_product_parent = $woocommerce_product->is_type( 'variable' ) || $woocommerce_product->is_type( 'variation' ) ? wc_get_product( $woocommerce_parent_id ) : null;

		$price_context = get_option( 'wppfm_omit_price_filters', false ) ? 'view' : 'feed'; // $since 3.12.0.

		if ( false === $woocommerce_product_parent || null === $woocommerce_product_parent ) {
			// This product has no parent id, so it is possible this is the main of a variable product,
			// so to make sure the general variation data like min_variation_price are available, copy the product
			// in the parent product.
			$woocommerce_product_parent = $woocommerce_product;
		}

		// @since 2.36.0.
		if ( in_array( '_regular_price', $active_field_names, true ) ) {
			if ( $woocommerce_product->is_type( 'variable' ) ) {
				$product->_regular_price = wppfm_prep_money_values( $woocommerce_product->get_variation_regular_price( 'max', true ), $selected_language, $selected_currency );
			} else {
				$product->_regular_price = wppfm_prep_money_values( $woocommerce_product->get_regular_price( $price_context ), $selected_language, $selected_currency );
			}
		}

		// @since 2.36.0.
		// @since 2.40.0. Fixed the fact that the formal wc get_variation_sale_price function returns the regular price when no sale price is set for a variation.
		if ( in_array( '_sale_price', $active_field_names, true ) ) {
			if ( $woocommerce_product->is_type( 'variable' ) ) {
				$product->_sale_price = wppfm_prep_money_values( $this->get_variation_sale_price( $woocommerce_product, 'max' ), $selected_language, $selected_currency );
			} else {
				$product->_sale_price = wppfm_prep_money_values( $woocommerce_product->get_sale_price( $price_context ), $selected_language, $selected_currency );
			}
		}

		if ( in_array( 'shipping_class', $active_field_names, true ) ) {
			// Get the shipping class.
			$shipping_class = $woocommerce_product->get_shipping_class();

			// If the shipping class in the product was empty and the product has a parent, then check if the parent has a shipping class.
			$product->shipping_class = ! $shipping_class && $woocommerce_product_parent ? $woocommerce_product_parent->get_shipping_class() : $shipping_class;
		}

		if ( in_array( 'permalink', $active_field_names, true ) ) {
			$permalink = get_permalink( $product->ID );
			if ( false === $permalink && 0 !== $woocommerce_parent_id ) {
				$permalink = get_permalink( $woocommerce_parent_id );
			}

			// WPML support.
			$permalink = has_filter( 'wppfm_get_wpml_permalink' )
				? apply_filters( 'wppfm_get_wpml_permalink', $permalink, $selected_language ) : $permalink;

			// WOOCS support since @2.29.0.
			$permalink = has_filter( 'wppfm_get_woocs_currency' )
				? apply_filters( 'wppfm_woocs_product_permalink', $permalink, $selected_currency ) : $permalink;

			// Translatepress support since @2.36.0.
			$permalink = has_filter( 'wppfm_get_transpress_permalink' )
				? apply_filters( 'wppfm_get_transpress_permalink', $permalink, $selected_language ) : $permalink;

			// @since 3.7.0 - Add Google Analytics parameters to the product permalink.
			// @since 3.11.0 - Added processing Google Analytics URL shortcodes.
			if ( $this->_feed_data->google_analytics ) {
				$permalink = $this->add_google_analytics_data_to_product_url( $permalink, $product->ID );
			}

			$product->permalink = $permalink;
		}

		// @since 3.16.0 - Removed the usage of the wppfm_get_wpml_permalink filter. Refactored the code to use the get_attachment_url_translated function.
		if ( in_array( 'attachment_url', $active_field_names, true ) ) {
			// Resolve the product's thumbnail URL (translated if applicable), fallback to parent.
			$attachment_url = $this->get_attachment_url_translated( get_post_thumbnail_id( $product->ID ), $selected_language );

			if ( ! $attachment_url && 0 !== $woocommerce_parent_id ) {
				$attachment_url = $this->get_attachment_url_translated( get_post_thumbnail_id( $woocommerce_parent_id ), $selected_language );
			}

			$product->attachment_url = $attachment_url;
		}

		/**
		 * Source for the products main image, even for variable products it returns the image of the parent (main) product
		 * @since 3.4.0.
		 * @since 3.16.0 - Removed the usage of the wppfm_get_wpml_permalink filter. Refactored the code to use the get_attachment_url_translated function.
		 */
		if ( in_array( 'product_main_image_url', $active_field_names, true ) ) {
			$main_product_id  = 0 !== $woocommerce_parent_id ? $woocommerce_parent_id : $product->ID;
			$main_image_url   = $this->get_attachment_url_translated( get_post_thumbnail_id( $main_product_id ), $selected_language );
			$product->product_main_image_url = $main_image_url;
		}

		if ( in_array( 'product_cat', $active_field_names, true ) ) {
			$product->product_cat = WPPFM_Taxonomies::get_shop_categories( $product->ID );
			if ( '' === $product->product_cat && 0 !== $woocommerce_parent_id ) {
				$product->product_cat = WPPFM_Taxonomies::get_shop_categories( $woocommerce_parent_id );
			}
		}

		if ( in_array( 'product_cat_string', $active_field_names, true ) ) {
			$product->product_cat_string = WPPFM_Taxonomies::make_shop_taxonomies_string( $product->ID );
			if ( '' === $product->product_cat_string && 0 !== $woocommerce_parent_id ) {
				$product->product_cat_string = WPPFM_Taxonomies::make_shop_taxonomies_string( $woocommerce_parent_id );
			}
		}

		if ( in_array( 'last_update', $active_field_names, true ) ) {
			$product->last_update = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) );
		}

		if ( in_array( '_wp_attachement_metadata', $active_field_names, true ) ) {
			$product_id                        = 0 === $woocommerce_parent_id ? $product->ID : $woocommerce_parent_id;
			$product->_wp_attachement_metadata = $this->get_product_image_gallery( $product_id, $selected_language );
		}

		if ( in_array( 'product_tags', $active_field_names, true ) ) {
			// @since 2.41.0 - Corrected the code such that it also gives the tags of the parent.
			$product_id            = 0 === $woocommerce_parent_id ? $product->ID : $woocommerce_parent_id;
			$product->product_tags = $this->get_product_tags( $product_id );
		}

		if ( in_array( 'wc_currency', $active_field_names, true ) ) {
			// WPML support.
			$product->wc_currency = has_filter( 'wppfm_get_translated_currency' )
				? apply_filters( 'wppfm_get_translated_currency', get_woocommerce_currency(), $selected_language ) : get_woocommerce_currency();
		}

		if ( $woocommerce_product_parent && ( $woocommerce_product_parent->is_type( 'variable' ) || $woocommerce_product_parent->is_type( 'variation' ) ) ) {
			if ( in_array( '_min_variation_price', $active_field_names, true ) ) {
				$product->_min_variation_price = wppfm_prep_money_values( $woocommerce_product_parent->get_variation_price(), $selected_language, $selected_currency );
			}

			if ( in_array( '_max_variation_price', $active_field_names, true ) ) {
				$product->_max_variation_price = wppfm_prep_money_values( $woocommerce_product_parent->get_variation_price( 'max' ), $selected_language, $selected_currency );
			}

			if ( in_array( '_min_variation_regular_price', $active_field_names, true ) ) {
				$product->_min_variation_regular_price = wppfm_prep_money_values( $woocommerce_product_parent->get_variation_regular_price(), $selected_language, $selected_currency );
			}

			if ( in_array( '_max_variation_regular_price', $active_field_names, true ) ) {
				$product->_max_variation_regular_price = wppfm_prep_money_values( $woocommerce_product_parent->get_variation_regular_price( 'max' ), $selected_language, $selected_currency );
			}

			// @since 2.40.0. - Fixed the fact that the formal wc get_variation_sale_price function returns the regular price when no sale price is set for a variation.
			if ( in_array( '_min_variation_sale_price', $active_field_names, true ) ) {
				$product->_min_variation_sale_price = wppfm_prep_money_values( $this->get_variation_sale_price( $woocommerce_product_parent ), $selected_language, $selected_currency );
			}

			// @since 2.40.0. - Fixed the fact that the formal wc get_variation_sale_price function returns the regular price when no sale price is set for a variation.
			if ( in_array( '_max_variation_sale_price', $active_field_names, true ) ) {
				$product->_max_variation_sale_price = wppfm_prep_money_values( $this->get_variation_sale_price( $woocommerce_product_parent, 'max' ), $selected_language, $selected_currency );
			}

			if ( in_array( 'item_group_id', $active_field_names, true ) ) {
				$product->item_group_id = $this->get_item_group_id( $woocommerce_parent_id );
			}
		} else {
			// @since 2.37.0. - Added code to handle min and max variation prices for non-variation products.
			if ( in_array( '_min_variation_price', $active_field_names, true ) ) {
				$product->_min_variation_price = wppfm_prep_money_values( $woocommerce_product->get_regular_price( $price_context ), $selected_language, $selected_currency );
			}

			if ( in_array( '_max_variation_price', $active_field_names, true ) ) {
				$product->_max_variation_price = wppfm_prep_money_values( $woocommerce_product->get_regular_price( $price_context ), $selected_language, $selected_currency );
			}

			if ( in_array( '_min_variation_regular_price', $active_field_names, true ) ) {
				$product->_min_variation_regular_price = wppfm_prep_money_values( $woocommerce_product->get_regular_price( $price_context ), $selected_language, $selected_currency );
			}

			if ( in_array( '_max_variation_regular_price', $active_field_names, true ) ) {
				$product->_max_variation_regular_price = wppfm_prep_money_values( $woocommerce_product->get_regular_price( $price_context ), $selected_language, $selected_currency );
			}

			if ( in_array( '_min_variation_sale_price', $active_field_names, true ) ) {
				$product->_min_variation_sale_price = wppfm_prep_money_values( $woocommerce_product->get_sale_price( $price_context ), $selected_language, $selected_currency );
			}

			if ( in_array( '_max_variation_sale_price', $active_field_names, true ) ) {
				$product->_max_variation_sale_price = wppfm_prep_money_values( $woocommerce_product->get_sale_price( $price_context ), $selected_language, $selected_currency );
			}

			if ( ! $woocommerce_product_parent->is_type( 'simple' ) && ! $woocommerce_product_parent->is_type( 'grouped' )
				&& ! $woocommerce_product_parent->is_type( 'virtual' ) && ! $woocommerce_product_parent->is_type( 'downloadable' )
				&& ! $woocommerce_product_parent->is_type( 'external' ) ) {
				$msg = sprintf(
					'Product type of product %s could not be identified. The products shows as type %s',
					$product->ID,
					function_exists( 'get_product_type' ) ? get_product_type( $product->ID ) : 'unknown'
				);
				do_action( 'wppfm_feed_generation_warning', $feed_id, $msg ); // @since 2.3.0
			}
		}

		if ( in_array( '_stock', $active_field_names, true ) ) {
			$product->_stock = $woocommerce_product->get_stock_quantity();
		}

		// @since 2.1.4.
		if ( in_array( 'empty', $active_field_names, true ) ) {
			$product->empty = '';
		}

		// @since 2.2.0.
		if ( in_array( 'product_type', $active_field_names, true ) ) {
			$product->product_type = $woocommerce_product->get_type();
		}

		// @since 2.2.0.
		if ( in_array( 'product_variation_title_without_attributes', $active_field_names, true ) ) {
			$product_title = get_post_field( 'post_title', $product->ID );

			if ( false !== strpos( $product_title, ' - ' ) ) { // Assuming that the woocommerce_product_variation_title_attributes_separator is ' - '.
				$title_parts   = explode( ' - ', $product_title );
				$product_title = $title_parts[0];
			}

			$product->product_variation_title_without_attributes = $product_title;
		}

		// @since 2.21.0.
		if ( in_array( '_variation_parent_id', $active_field_names, true ) ) {
			$product->_variation_parent_id = $woocommerce_parent_id;
		}

		// @since 2.21.0.
		if ( in_array( '_product_parent_id', $active_field_names, true ) ) {
			$product->_product_parent_id = $woocommerce_parent_id ?: '0';
		}

		// @since 2.21.0.
		if ( in_array( '_max_group_price', $active_field_names, true ) && $woocommerce_product_parent->is_type( 'grouped' ) ) {
			$product->_max_group_price = $this->get_group_price( $woocommerce_product_parent, 'max' );
		}

		// @since 2.21.0.
		if ( in_array( '_min_group_price', $active_field_names, true ) && $woocommerce_product_parent->is_type( 'grouped' ) ) {
			$product->_min_group_price = $this->get_group_price( $woocommerce_product_parent );
		}

		// @since 2.26.0.
		// @since 2.36.0.- Changed the way the regular price is fetched for variation products.
		if ( in_array( '_regular_price_with_tax', $active_field_names, true ) ) {
			if ( $woocommerce_product->is_type( 'variable' ) ) {
				$regular_price = $woocommerce_product->get_variation_regular_price( 'max' );
			} else {
				$regular_price = $woocommerce_product->get_regular_price( $price_context );
			}
			$feed_country_code               = $this->_feed_data->country;
			$localized                       = $this->get_localized_price_for_country( $woocommerce_product, $regular_price, $feed_country_code, $selected_currency );
			$product->_regular_price_with_tax = wppfm_prep_money_values( $localized, $selected_language, $selected_currency );
		}

		// @since 2.26.0.
		// @since 2.36.0.- Changed the way the regular price is fetched for variation products.
		if ( in_array( '_regular_price_without_tax', $active_field_names, true ) ) {
			if ( $woocommerce_product->is_type( 'variable' ) ) {
				$regular_price = $woocommerce_product->get_variation_regular_price( 'max' );
			} else {
				$regular_price = $woocommerce_product->get_regular_price( $price_context );
			}
			$feed_country_code                  = $this->_feed_data->country;
			$price                               = $this->get_localized_price_ex_tax_for_country( $woocommerce_product, $regular_price, $feed_country_code, $selected_currency );
			if ( '' !== $price && $price !== null ) {
				$product->_regular_price_without_tax = wppfm_prep_money_values( $price, $selected_language, $selected_currency );
			}
		}

		// @since 2.26.0.
		// @since 2.36.0.- Changed the way the sale price is fetched for variation products
		// @since 2.37.0.- Added a check if the sale price is empty because the wc_get_price_including_tax function will return an unwanted regular price if the sale price is empty
		// @since 2.40.0.- Fixed the fact that the formal wc get_variation_sale_price function returns the regular price when no sale price is set for a variation.
		if ( in_array( '_sale_price_with_tax', $active_field_names, true ) ) {
			if ( $woocommerce_product->is_type( 'variable' ) ) {
				$sale_price = $this->get_variation_sale_price( $woocommerce_product, 'max' );
			} else {
				$sale_price = $woocommerce_product->get_sale_price( $price_context );
			}

			if ( $sale_price ) {
				$feed_country_code            = $this->_feed_data->country;
				$localized                    = $this->get_localized_price_for_country( $woocommerce_product, $sale_price, $feed_country_code, $selected_currency );
				$product->_sale_price_with_tax = wppfm_prep_money_values( $localized, $selected_language, $selected_currency );
			}
		}

		// @since 2.26.0.
		// @since 2.36.0.- Changed the way the sale price is fetched for variation products
		// @since 2.37.0.- Added a check if the sale price is empty because the wc_get_price_including_tax function will return an unwanted regular price if the sale price is empty
		// @since 2.40.0.- Fixed the fact that the formal wc get_variation_sale_price function returns the regular price when no sale price is set for a variation.
		if ( in_array( '_sale_price_without_tax', $active_field_names, true ) ) {
			if ( $woocommerce_product->is_type( 'variable' ) ) {
				$sale_price = $this->get_variation_sale_price( $woocommerce_product, 'max' );
			} else {
				$sale_price = $woocommerce_product->get_sale_price( $price_context );
			}

			if ( $sale_price ) {
				$feed_country_code               = $this->_feed_data->country;
				$price                            = $this->get_localized_price_ex_tax_for_country( $woocommerce_product, $sale_price, $feed_country_code, $selected_currency );
				if ( '' !== $price && $price !== null ) {
					$product->_sale_price_without_tax = wppfm_prep_money_values( $price, $selected_language, $selected_currency );
				}
			}
		}

		// @since 2.28.0.
		if ( in_array( '_product_parent_description', $active_field_names, true ) ) {
			$product->_product_parent_description = $woocommerce_product_parent->get_description( 'feed' );
		}

		// @since 2.28.0.
		if ( in_array( '_woocs_currency', $active_field_names, true ) ) {
			// WOOCS support
			$product->_woocs_currency = has_filter( 'wppfm_get_woocs_currency' )
				? apply_filters( 'wppfm_get_woocs_currency', $selected_currency ) : get_woocommerce_currency();
		}

		// @since 3.15.0.
		if ( in_array( '_low_stock_amount', $active_field_names, true ) ) {
			$low_stock_amount = wc_get_low_stock_amount( $woocommerce_product );
			if ( $low_stock_amount ) {
				$product->_low_stock_amount = $low_stock_amount;
			}
		}

		$woocommerce_product = null;
	}

	/**
	 * Get the item group id of a variation product.
	 *
	 * @param string $woocommerce_parent_id the parent product id.
	 *
	 * @since 3.11.0.
	 * @since 3.11.1 - To fix tickets #4302, #4311 and #4312, added a check if the parent product is a variation product.
	 * @return string the item group id.
	 */
	private function get_item_group_id( $woocommerce_parent_id ) {
		$parent = wc_get_product( $woocommerce_parent_id );

		// Ensure the product is either a variable product or variation.
		if ( ! $parent || ! ( $parent->is_type( 'variable' ) || $parent->is_type( 'variation' ) ) ) {
			return '';
		}

		$parent_sku = $parent->get_sku();
		$product_id = $parent->get_id();

		if ( $parent_sku ) {
			return $parent_sku; // Best practise.
		}

		if ( $product_id ) {
			return 'GID' . $product_id;
		}

		return '';
	}

	/**
	 * Get all the gallery image urls.
	 *
	 * @param string $product_id        the product id.
	 * @param string $selected_language selected language, if applicable.
	 *
	 * @since 3.16.0 - Removed the usage of the wppfm_get_wpml_permalink filter. Refactored the code to use the get_attachment_url_translated function.
	 * @return array|string an array with image urls or an empty string if none are found.
	 */
	private function get_product_image_gallery( $product_id, $selected_language ) {
		$image_urls    = array();
		$images        = 1;
		$max_nr_images = 10;

		$product        = wc_get_product( $product_id );
		$attachment_ids = $product->get_gallery_image_ids();

		foreach ( $attachment_ids as $attachment_id ) {
			$url = $this->get_attachment_url_translated( $attachment_id, $selected_language );
			if ( $url ) {
				$image_urls[] = $url;
				$images ++;
			}

			if ( $images > $max_nr_images ) {
				break;
			}
		}

		return ! empty( $image_urls ) ? $image_urls : '';
	}

	/**
	 * Adds Google Analytics parameters to the product permalink.
	 *
	 * @param string $permalink  the original permalink.
	 * @param string $product_id the product id.
	 *
	 * @since 3.11.0
	 * @return string modified permalink with Google Analytics parameters if they are added.
	 */
	private function add_google_analytics_data_to_product_url( $permalink, $product_id ) {
		// Generate Google Analytics parameters with shortcodes replaced
		$parameters = $this->get_google_analytics_parameters( $product_id );

		// Add the parameters to the permalink
		return add_query_arg( $parameters, $permalink );
	}

	/**
	 * Gets Google Analytics parameters for a product.
	 *
	 * @param string $product_id the product id.
	 *
	 * @since 3.11.0
	 * @return array Google Analytics parameters with shortcodes replaced.
	 */
	private function get_google_analytics_parameters( $product_id ) {
		$google_analytics_data_holder = $this->_feed_data;

		$parameters = array(
			'utm_id'              => $google_analytics_data_holder->utm_id,
			'utm_source'          => $google_analytics_data_holder->utm_source,
			'utm_medium'          => $google_analytics_data_holder->utm_medium,
			'utm_campaign'        => $google_analytics_data_holder->utm_campaign,
			'utm_source_platform' => $google_analytics_data_holder->utm_source_platform,
			'utm_term'            => $google_analytics_data_holder->utm_term,
			'utm_content'         => $google_analytics_data_holder->utm_content,
		);

		// Replace shortcodes in each Google Analytics parameter value.
		foreach ( $parameters as $key => $value ) {
			if ( ! is_null( $value ) && $value !== '' ) {
				$parameters[ $key ] = $this->replace_shortcodes( $value, $product_id );
			}
		}

		// Filter out empty parameters.
		return array_filter( $parameters, function( $value ) {
			return ! is_null( $value ) && $value !== '';
		});
	}

	/**
	 * Replaces shortcodes in a text with their actual values.
	 *
	 * @param string $text       the text containing shortcodes.
	 * @param string $product_id the product id.
	 *
	 * @since 3.11.0
	 * @return string the text with shortcodes replaced.
	 */
	private function replace_shortcodes( $text, $product_id ) {
		// Check for shortcodes in the text before proceeding.
		if ( ! $this->has_shortcode( $text ) ) {
			return $text; // No shortcodes found, return text as-is.
		}

		// Prepare only the shortcodes that are found in the text
		$shortcodes = array(
			'[product-id]'       => strpos($text, '[product-id]') !== false ? $product_id : '',
			'[product-sku]'      => strpos($text, '[product-sku]') !== false ? get_post_meta( $product_id, '_sku', true ) : '',
			'[product-title]'    => strpos($text, '[product-title]') !== false ? get_the_title( $product_id ) : '',
			'[product-group-id]' => strpos($text, '[product-group-id]') !== false ? $this->get_item_group_id( $product_id ) : '',
		);

		// Replace shortcodes in the text
		foreach ( $shortcodes as $shortcode => $value ) {
			$text = str_replace( $shortcode, $value, $text );
		}

		return $text;
	}

	/**
	 * Checks if a text contains any of the supported shortcodes.
	 *
	 * @param string $text the text to check.
	 *
	 * @since 3.11.0
	 * @return bool true if any of the shortcodes are found, false otherwise.
	 */
	private function has_shortcode( $text ) {
		$shortcodes = array(
			'[product-id]',
			'[product-sku]',
			'[product-title]',
			'[product-group-id]',
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( strpos( $text, $shortcode ) !== false ) {
				return true;
			}
		}

		return true;
	}

	/**
	 * Gets the product tags.
	 *
	 * @param string $product_id the product id.
	 *
	 * @return string comma separated string containing the product tags.
	 */
	private function get_product_tags( $product_id ) {
		$product_tags_string = '';
		$product_tag_values  = get_the_terms( $product_id, 'product_tag' );
		$post_tag_values     = get_the_tags( $product_id );

		if ( $product_tag_values ) {
			foreach ( $product_tag_values as $product_tag ) {
				$product_tags_string .= $product_tag->name . ', ';
			}
		}

		if ( $post_tag_values ) {
			foreach ( $post_tag_values as $post_tag ) {
				$product_tags_string .= $post_tag->name . ', ';
			}
		}

		return $product_tags_string ? substr( $product_tags_string, 0, - 2 ) : '';
	}

	/**
	 * Returns the lowest or highest price of the products in a grouped product.
	 *
	 * @param WC_Product $woocommerce_product_parent grouped product data.
	 * @param string     $min_max                    min (lowest) or max (highest) price, default min.
	 *
	 * @return string The highest or lowest price as a string.
	 */
	private function get_group_price( $woocommerce_product_parent, $min_max = 'min' ) {
		$children       = $woocommerce_product_parent->get_children();
		$product_prices = array();

		foreach ( $children as $value ) {
			$product          = wc_get_product( $value );
			$product_prices[] = $product->get_price();
		}

		return 'min' === $min_max ? min( $product_prices ) : max( $product_prices );
	}

	/**
	 * Returns a localized product price converted to the feed currency and calculated with taxes for the feed country.
	 *
	 * This emulates the frontend price shown to a visitor from the target country, including product tax class rules.
	 *
	 * @param WC_Product $product            WooCommerce product.
	 * @param float      $raw_price          Base price (regular/sale) before conversion.
	 * @param string     $feed_country_code  ISO country code for the feed target country (e.g. 'DE').
	 * @param string     $feed_currency      Target feed currency (e.g. 'EUR').
	 *
	 * @since 3.17.0
	 * @return float Localized gross price
	 */
private function get_localized_price_for_country( $product, $raw_price, $feed_country_code, $feed_currency ) {
    if ( '' === $raw_price || null === $raw_price ) {
        return '';
    }
    // 1) Convert to feed currency via WPML Multi-currency when available
    $converted_price   = $raw_price;
    $resolved_currency = $feed_currency ? $feed_currency : get_woocommerce_currency();
    if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
        $converted_price = apply_filters( 'wcml_raw_price_amount', $raw_price, $resolved_currency );
    }


		// 2) Calculate price including taxes while forcing the taxable address to the feed's country (no globals mutation).
		$this->_temp_tax_country = $feed_country_code;
		add_filter( 'woocommerce_customer_taxable_address', array( $this, 'override_tax_address' ), 999 );
        try {
            $localized_price = wc_get_price_including_tax( $product, array( 'price' => $converted_price ) );
        } catch ( \Throwable $e ) {
            $localized_price = (float) $converted_price; // fallback to converted price
        }
		remove_filter( 'woocommerce_customer_taxable_address', array( $this, 'override_tax_address' ), 999 );
		$this->_temp_tax_country = null;

		return apply_filters( 'wppfm_get_localized_product_price', $localized_price, $product, $feed_country_code, $feed_currency );
	}

	/**
	 * Returns a localized product price excluding taxes for the feed country, converted to the feed currency.
	 *
	 * When prices are entered inclusive of tax, WooCommerce requires the correct tax location to strip tax properly.
	 *
	 * @param WC_Product $product            WooCommerce product.
	 * @param float      $raw_price          Base price (regular/sale) before conversion.
	 * @param string     $feed_country_code  ISO country code for the feed target country (e.g. 'DE').
	 * @param string     $feed_currency      Target feed currency (e.g. 'EUR').
	 *
	 * @since 3.17.0
	 * @return float Localized net price (excluding tax)
	 */
private function get_localized_price_ex_tax_for_country( $product, $raw_price, $feed_country_code, $feed_currency ) {
    // 1) Compute net price in store currency using WooCommerce tax logic and target country
    if ( '' === $raw_price || null === $raw_price ) {
        return '';
    }

    $this->_temp_tax_country = $feed_country_code;
    add_filter( 'woocommerce_customer_taxable_address', array( $this, 'override_tax_address' ), 999 );
    try {
        $is_taxable     = method_exists( $product, 'is_taxable' ) ? $product->is_taxable() : true;
        $prices_include = function_exists( 'wc_prices_include_tax' ) ? wc_prices_include_tax() : false;
        $tax_class      = method_exists( $product, 'get_tax_class' ) ? $product->get_tax_class() : '';
        $price_param    = is_numeric( $raw_price ) ? (float) $raw_price : (float) $product->get_price();

        // One-time explicit tax calculation to compute net if needed
        $calc_total_tax = '';
        $calc_taxes     = array();
        $sum_tax        = 0.0;
        if ( class_exists( 'WC_Tax' ) ) {
            $rates_for_calc = WC_Tax::get_rates( $tax_class );
            $calc_taxes     = WC_Tax::calc_tax( (float) $price_param, $rates_for_calc, $prices_include );
            $sum_tax        = is_array( $calc_taxes ) ? array_sum( array_map( 'floatval', $calc_taxes ) ) : 0.0;
            $calc_total_tax = sprintf( 'tax_sum:%s', $sum_tax );
        }

        // Two reference calculations
        $net_no_arg   = wc_get_price_excluding_tax( $product );
        $net_with_arg = wc_get_price_excluding_tax( $product, array( 'price' => $price_param ) );

        if ( $is_taxable && $prices_include ) {
            // Prefer explicit inclusive-tax calculation when it yields a positive tax sum
            if ( $sum_tax > 0 ) {
                $net_by_calc = max( 0.0, (float) $price_param - (float) $sum_tax );
                $net_store_currency = $net_by_calc;
            } else {
                // Fallback: choose the lowest positive candidate from WooCommerce helpers
                $candidates = array_filter( array( (float) $net_no_arg, (float) $net_with_arg ), function( $v ) { return $v > 0; } );
                $net_store_currency = ! empty( $candidates ) ? min( $candidates ) : (float) $price_param;
            }
        } else {
            $net_store_currency = $net_no_arg;
        }
    } catch ( \Throwable $e ) {
        $net_store_currency = (float) $raw_price; // fallback
    }
    remove_filter( 'woocommerce_customer_taxable_address', array( $this, 'override_tax_address' ), 999 );
    $this->_temp_tax_country = null;

    // 2) Convert net to feed currency via WPML (if active) using a scoped client currency override
    $resolved_currency = $feed_currency ? $feed_currency : get_woocommerce_currency();

    $this->_temp_currency = $resolved_currency;
    if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
        add_filter( 'wcml_client_currency', array( $this, 'override_client_currency' ), 999 );
    }

    $net_converted = defined( 'ICL_SITEPRESS_VERSION' )
        ? apply_filters( 'wcml_raw_price_amount', $net_store_currency, $resolved_currency )
        : $net_store_currency;

    if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
        remove_filter( 'wcml_client_currency', array( $this, 'override_client_currency' ), 999 );
    }
    $this->_temp_currency = null;

    return apply_filters( 'wppfm_get_localized_product_price_ex_tax', $net_converted, $product, $feed_country_code, $resolved_currency );
}

	/**
	 * Forces the taxable address to the feed country while calculating prices.
	 *
	 * @param array $address [ country, state, postcode, city ]
	 * @return array
	 */
	public function override_tax_address( $address ) {
		if ( $this->_temp_tax_country ) {
			return array( $this->_temp_tax_country, '', '', '' );
		}
		return $address;
	}

	/**
	 * Forces the client currency for WPML Multi-currency while calculating prices.
	 *
	 * @param string $currency
	 * @return string
	 */
	public function override_client_currency( $currency ) {
		return $this->_temp_currency ? $this->_temp_currency : $currency;
	}

	/**
	 * Fixes the fact that the formal wc get_variation_sale_price function returns the regular price when no sale price is set for a variation.
	 *
	 * @param object $wc_product the product data.
	 * @param string $min_or_max min (lowest) or max (highest) price, default min.
	 *
	 * @since 2.40.0
	 * @return mixed|string
	 */
	private function get_variation_sale_price( $wc_product, $min_or_max = 'min' ) {
		$variation_sale_price = $wc_product->get_variation_sale_price( $min_or_max );
		return $variation_sale_price < $wc_product->get_variation_regular_price( $min_or_max ) ? $variation_sale_price : '';
	}
}
