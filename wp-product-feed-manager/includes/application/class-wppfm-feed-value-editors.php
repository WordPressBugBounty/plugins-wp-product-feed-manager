<?php

/**
 * WP Product Feed Value Editors Class.
 *
 * @package WP Product Feed Manager/Application/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WPPFM_Feed_Value_Editors' ) ) :

	/**
	 * Feed Value Editors Class.
	 */
	class WPPFM_Feed_Value_Editors {

		/**
		 * Handles the overwrite value editing.
		 *
		 * @param string[] $condition query string describing the overwritten query.
		 *
		 * @return string containing the overwritten result.
		 */
		public function overwrite_value( $condition ) {
			return $condition[2];
		}

		/**
		 * Handles the replacement value editing.
		 *
		 * @param string[] $condition     array with query strings describing the replacement query.
		 * @param string   $current_value the current value.
		 *
		 * @return string containing the replacement result.
		 */
		public function replace_value( $condition, $current_value ) {
			return str_replace( $condition[2], $condition[3], $current_value );
		}

		/**
		 * Converts an attribute element to a child element.
		 *
		 * @param string[] $element_name  array with the names of the attribute element.
		 * @param string   $current_value the current value.
		 *
		 * @return string containing the conversion result.
		 */
		public function convert_to_child_element( $element_name, $current_value ) {
			return "!sub:$element_name[2]|$current_value";
		}

		/**
		 * Handles the remove value editing.
		 *
		 * @param string[] $condition     array with query strings describing the remove value query.
		 * @param string   $current_value the current value.
		 *
		 * @return string containing the remove value result.
		 */
		public function remove_value( $condition, $current_value ) {
			return str_replace( $condition[2], '', $current_value );
		}

		/**
		 * Handles the adding of a prefix editing.
		 *
		 * @param string[] $condition     array with query strings describing the add prefix query.
		 * @param string   $current_value the current value.
		 *
		 * @return string containing the replacement result.
		 */
		public function add_prefix_value( $condition, $current_value ) {
			return $condition[2] . $current_value;
		}

		/**
		 * Handles the adding of a suffix editing.
		 *
		 * @param string[] $condition     array with query strings describing the added suffix query.
		 * @param string   $current_value the current value.
		 *
		 * @return string containing the replacement result.
		 */
		public function add_suffix_value( $condition, $current_value ) {
			return $current_value . $condition[2];
		}

		/**
		 * Performs a strip_tags action on a value.
		 *
		 * @param string $current_value the current value.
		 *
		 * @return string the resulting value.
		 */
		public function strip_tags_from_value( $current_value ) {
			return wp_strip_all_tags( $current_value );
		}

		/**
		 * Performs an html_entity_decode action on a value.
		 *
		 * @since 2.34.0
		 *
		 * @param string $current_value the current value.
		 *
		 * @return string the resulting value.
		 */
		public function html_entity_decode_value( $current_value ) {
			return html_entity_decode( $current_value, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401 );
		}

		/**
		 * Performs an html_entity_encode action on a value.
		 * 
		 * Uses multiple encoding functions to ensure thorough encoding of special characters,
		 * HTML entities, and other problematic characters.
		 *
		 * @since 3.16.0
		 *
		 * @param string $current_value the current value.
		 *
		 * @return string the resulting value.
		 */
		public function html_entity_encode_value( $current_value ) {
			// First convert all HTML special chars
			$encoded = htmlspecialchars($current_value, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401, 'UTF-8', true);
			
			// Then convert remaining special characters to HTML entities
			$encoded = htmlentities($encoded, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401, 'UTF-8', true);
			
			// Finally use ENT_COMPAT to convert any remaining characters
			return mb_convert_encoding($encoded, 'HTML-ENTITIES', 'UTF-8');
		}

		/**
		 * Limits the number of characters in a value.
		 *
		 * @param string[] $condition     array with query strings describing the limit characters query. This parameter contains the requested max number of characters.
		 * @param string   $current_value the current value.
		 *
		 * @return string the current value limited to the given max number of characters.
		 */
		public function limit_characters_value( $condition, $current_value ) {
			return substr( $current_value, 0, $condition[2] );
		}

		/**
		 * Performs a recalculation action on a given value.
		 *
		 * @param string[]     $condition              array with query strings describing the recalculating query.
		 * @param string       $current_value          the current value.
		 * @param string       $combination_string     a string containing combination values.
		 * @param array|string $combined_data_elements an array with combined data elements or an empty string to be used in recalculation queries.
		 * @param string       $feed_language          selected Language in WPML add-on, leave empty if no exchange rate correction is required.
		 * @param string       $feed_currency          selected currency in WOOCS add-on, leave empty if no correction is required.
		 *
		 * @return string the result of the recalculation.
		 */
		public function recalculate_value( $condition, $current_value, $combination_string, $combined_data_elements, $feed_language, $feed_currency ) {
			if ( ! $combination_string ) {
				$values           = $this->make_recalculate_inputs( $current_value, $condition[3] );
				$calculated_value = $this->recalculate( $condition[2], floatval( $values['main_val'] ), floatval( $values['sub_val'] ) );

				return $this->is_money_value( $current_value ) ? wppfm_prep_money_values( $calculated_value, $feed_language, $feed_currency ) : $calculated_value;

			} else {
				if ( count( $combined_data_elements ) > 1 ) {
					$combined_string_values = array();

					foreach ( $combined_data_elements as $element ) {
						$values = $this->make_recalculate_inputs( $element, $condition[3] );

						$reg_match = '/[0-9.,]/'; // only numbers and decimals

						$calculated_value = preg_match( $reg_match, $values['main_val'] ) && preg_match( $reg_match, $values['sub_val'] ) ?
							$this->recalculate( $condition[2], floatval( $values['main_val'] ), floatval( $values['sub_val'] ) ) : $values['main_val'];

						$end_value = $this->is_money_value( $element ) ? wppfm_prep_money_values( $calculated_value, $feed_language, $feed_currency ) : $calculated_value;

						$combined_string_values[] = $end_value;
					}

					return $this->make_combined_result_string( $combined_string_values, $combination_string );
				} else {
					return '';
				}
			}
		}

		/**
		 * Combines values based on a combination string, like combining a currency value with its currency symbol.
		 *
		 * @param array $values              an array containing the values to combine.
		 * @param string $combination_string a string containing combination values.
		 *
		 * @return string containing the combined result.
		 */
		private function make_combined_result_string( $values, $combination_string ) {
			$separators    = $this->combination_separators();
			$result_string = $values[0];

			$combinations = explode( '|', $combination_string );

			for ( $i = 1; $i < count( $combinations ); $i ++ ) {
				$sep            = explode( '#', $combinations[ $i ] );
				$result_string .= $separators[ (int) $sep[0] ];
				$result_string .= $values[ $i ];
			}

			return $result_string;
		}

		/**
		 * Returns an array with possible combination separators as shown in the combined source fields selection.
		 *
		 * @return string[] with the separators.
		 */
		public function combination_separators() {
			return array(
				'',
				' ',
				', ',
				'. ',
				'; ',
				':',
				' - ',
				'/',
				'\\',
				'||',
				'_',
				'>', // @since 2.42.0
			); // should correspond with wppfm_getCombinedSeparatorList()
		}

		/**
		 * Retracts the recalculation main and sub value from the $current_value and $current_sub_value variables.
		 *
		 * @param string $current_value     containing the main value.
		 * @param string $current_sub_value containing the sub value.
		 *
		 * @return array with the main and sub value.
		 */
		private function make_recalculate_inputs( $current_value, $current_sub_value ) {
			if ( ! preg_match( '/[a-zA-Z]/', $current_value ) ) { // Only remove the commas if the current value has no letters.
				$main_value = wppfm_number_format_parse( $current_value );
			} else {
				$main_value = $current_value;
			}

			$sub_value = wppfm_number_format_parse( $current_sub_value );

			return array(
				'main_val' => $main_value,
				'sub_val'  => $sub_value,
			);
		}

		/**
		 * Turns a meta-value into a money value if required.
		 *
		 * @param object $meta_data     containing the meta data.
		 * @param string $feed_language the language of the feed.
		 * @param string $feed_currency the currency of the feed.
		 *
		 * @return mixed|string the result value.
		 */
		public function prep_meta_values( $meta_data, $feed_language, $feed_currency ) {
			$result = $meta_data->meta_value;

			if ( wppfm_meta_key_is_money( $meta_data->meta_key ) ) {
				$result = wppfm_prep_money_values( $result, $feed_language, $feed_currency );
			}

			return is_string( $result ) ? trim( $result ) : $result;
		}

		/**
		 * Checks is a certain value could be a money value or not.
		 *
		 * @param int $value or string $value.
		 *
		 * @since 2.28.0 Switched to the formal wc functions to get the separator and number of decimal values.
		 * @since 3.9.0 Found that the prices that are read using WooCommerce functions, always have a point as decimal separator even
		 *  when the WC Price Decimal Separator is set to a comma.
		 *  This means the $last_pos fails to identify a price formated number when the user has a comma set as the WC Decimal Separator.
		 *  So now a fixed point is set in the $last_pos assignment instead of the wc_get_price_decimal_separator function.
		 *
		 * @return boolean
		 */
		public function is_money_value( $value ) {
			// Replace a comma separator with a period, so it can be recognized as numeric.
			$possible_number = wppfm_number_format_parse( $value );

			// if it's not a number, it cannot be a money value.
			if ( ! is_numeric( $possible_number ) ) {
				return false;
			}

			$last_pos = strrpos( (string) $value, '.' );

			if ( ! $last_pos ) { // Has no decimal separator.
				return false;
			}

			$value_length = strlen( (string) $value );

			$actual_decimals = $value_length - $last_pos - 1;

			return wc_get_price_decimals() === $actual_decimals;
		}

		private function recalculate( $math, $main_value, $sub_value ) {
			$result = 0;

			if ( is_numeric( $main_value ) && is_numeric( $sub_value ) ) {
				switch ( $math ) {
					case 'add':
						$result = $main_value + $sub_value;
						break;

					case 'subtract':
						$result = $main_value - $sub_value;
						break;

					case 'multiply':
						$result = $main_value * $sub_value;
						break;

					case 'divide':
						$result = 0 !== $sub_value ? $main_value / $sub_value : 0;
						break;
				}
			}

			return $result;
		}
	}


	// End of WPPFM_Feed_Value_Editors class

endif;
