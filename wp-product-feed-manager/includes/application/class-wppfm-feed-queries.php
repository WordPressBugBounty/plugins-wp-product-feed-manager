<?php

/**
 * WP Feed Queries Class.
 *
 * @package WP Product Feed Manager/Application/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WPPFM_Feed_Queries' ) ) :


	/**
	 * Feed Queries Class.
	 *
	 * Contains the attribute query functions.
	 */
	class WPPFM_Feed_Queries {

		/**
		 * Checks if the attribute query includes the specified query value.
		 *
		 * @param array  $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function includes_query( $query, $value ) {
			return ! ( $query[3] && strpos( strtolower( $value ), strtolower( trim( $query[3] ) ) ) !== false );
		}

		/**
		 * Checks if the attribute query does not include the specified query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function does_not_include_query( $query, $value ) {
			return ! ( $query[3] && strpos( strtolower( $value ), strtolower( trim( $query[3] ) ) ) === false );
		}

		/**
		 * Checks if the attribute value is equal to a specified query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_equal_to_query( $query, $value ) {
			return ! ( strtolower( $value ) === strtolower( trim( $query[3] ) ) );
		}

		/**
		 * Checks if the attribute value is not equal to a specified query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_not_equal_to_query( $query, $value ) {
			return ! ( strtolower( $value ) !== strtolower( trim( $query[3] ) ) );
		}

		/**
		 * Checks if the attribute value is empty.
		 *
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_empty( $value ) {
			if ( ! is_array( $value ) ) {
				$value = trim( $value );
			}

			return ! empty( $value );
		}

		/**
		 * Checks if the attribute value is not empty.
		 *
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_not_empty_query( $value ) {
			if ( ! is_array( $value ) ) {
				$value = trim( $value );
			}

			return empty( $value );
		}

		/**
		 * Checks if the attribute value starts with the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function starts_with_query( $query, $value ) {
			if ( ! empty( $value ) && strrpos( strtolower( $value ), strtolower( trim( $query[3] ) ), - strlen( $value ) ) !== false ) {
				return false;
			} else {
				return true;
			}
		}

		/**
		 * Checks if the attribute value does not start with the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function does_not_start_with_query( $query, $value ) {
			if ( empty( $value ) || strrpos( strtolower( $value ), strtolower( trim( $query[3] ) ), - strlen( $value ) ) === false ) {
				return false;
			} else {
				return true;
			}
		}

		/**
		 * Checks if the attribute value ends with the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function ends_with_query( $query, $value ) {
			$search_string = trim( $query[3] );
			$value_length  = strlen( $value );

			if ( ! empty( $value ) && ( $value_length - strlen( $search_string ) ) >= 0 && strpos( $value, $search_string, $value_length ) !== false ) {
				return false;
			} else {
				return true;
			}
		}

		/**
		 * Checks if the attribute value does not end with the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function does_not_end_with_query( $query, $value ) {
			$search_string = trim( $query[3] );
			$value_length  = strlen( $value );

			if ( ! empty( $value ) && ( $value_length - strlen( $search_string ) ) >= 0 && strpos( $value, $search_string, $value_length ) !== false ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Checks if the attribute value is truthy.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true when the attribute value is truthy.
		 */
		public function is_truthy_query( $query, $value ) {
			return $this->attribute_value_is_truthy( $value );
		}

		/**
		 * Checks if the attribute value is falsy.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true when the attribute value is falsy.
		 */
		public function is_falsy_query( $query, $value ) {
			return ! $this->attribute_value_is_truthy( $value );
		}

		/**
		 * Checks if the attribute value is greater the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_greater_than_query( $query, $value ) {
			$data_nr      = $this->convert_to_us_notation( trim( $value ) );
			$condition_nr = $this->convert_to_us_notation( trim( $query[3] ) );

			if ( is_numeric( $data_nr ) && is_numeric( $condition_nr ) ) {
				return ! ( (float) $data_nr > (float) $condition_nr );
			} else {
				return true;
			}
		}

		/**
		 * Checks if the attribute value is greater or equal to the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_greater_or_equal_to_query( $query, $value ) {
			$data_nr      = $this->convert_to_us_notation( trim( $value ) );
			$condition_nr = $this->convert_to_us_notation( trim( $query[3] ) );

			if ( is_numeric( $data_nr ) && is_numeric( trim( $condition_nr ) ) ) {
				return ! ( (float) $data_nr >= (float) $condition_nr );
			} else {
				return true;
			}
		}

		/**
		 * Checks if the attribute value is smaller than the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_smaller_than_query( $query, $value ) {
			$data_nr      = $this->convert_to_us_notation( trim( $value ) );
			$condition_nr = $this->convert_to_us_notation( trim( $query[3] ) );

			if ( is_numeric( $data_nr ) && is_numeric( $condition_nr ) ) {
				return ! ( (float) $data_nr < (float) $condition_nr );
			} else {
				return true;
			}
		}

		/**
		 * Checks if the attribute value is smaller or equal to the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_smaller_or_equal_to_query( $query, $value ) {
			$data_nr      = $this->convert_to_us_notation( trim( $value ) );
			$condition_nr = $this->convert_to_us_notation( trim( $query[3] ) );

			if ( is_numeric( $data_nr ) && is_numeric( $condition_nr ) ) {
				return ! ( (float) $data_nr <= (float) $condition_nr );
			} else {
				return true;
			}
		}

		/**
		 * Checks if the attribute value is between the query value.
		 *
		 * @param array $query An array with the query part of the attribute string. Element 3 contains the lower query value and element 5 the higher query value.
		 * @param string $value The attribute value to check the query against.
		 *
		 * @return bool true if the query is true.
		 */
		public function is_between_query( $query, $value ) {
			$data_nr           = $this->convert_to_us_notation( trim( $value ) );
			$condition_nr_low  = $this->convert_to_us_notation( trim( $query[3] ) );
			$condition_nr_high = $this->convert_to_us_notation( trim( $query[5] ) );

			if ( is_numeric( $data_nr ) && is_numeric( $condition_nr_low ) && is_numeric( $condition_nr_high ) ) {
				if ( (float) $data_nr > (float) $condition_nr_low && (float) $data_nr < (float) $condition_nr_high ) {
					return false;
				} else {
					return true;
				}
			} else {
				return true;
			}
		}

		/**
		 * Resolves whether a feed attribute value should be treated as truthy.
		 *
		 * Uses explicit boolean strings (true/false, yes/no, on/off, 1/0) so feed fields like
		 * "_product_is_variation" with literal "false" are not misread as truthy non-empty strings.
		 * Other non-empty strings remain truthy, matching typical PHP string semantics.
		 *
		 * @param mixed $value The attribute value to evaluate.
		 *
		 * @return bool True when the value should be considered truthy for filter conditions.
		 */
		private function attribute_value_is_truthy( $value ) {
			if ( is_bool( $value ) ) {
				return $value;
			}

			if ( is_array( $value ) ) {
				return ! empty( $value );
			}

			if ( is_int( $value ) || is_float( $value ) ) {
				return 0.0 !== (float) $value;
			}

			if ( null === $value ) {
				return false;
			}

			if ( ! is_string( $value ) ) {
				return (bool) $value;
			}

			$normalized = strtolower( trim( $value ) );

			if ( '' === $normalized ) {
				return false;
			}

			// Match common feed / form boolean strings explicitly (incl. literal "false").
			$explicit_truthy = array( '1', 'true', 'on', 'yes' );
			$explicit_falsy  = array( '0', 'false', 'off', 'no', 'n/a', 'null' );

			if ( in_array( $normalized, $explicit_truthy, true ) ) {
				return true;
			}

			if ( in_array( $normalized, $explicit_falsy, true ) ) {
				return false;
			}

			$boolean_value = filter_var( $normalized, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

			if ( null !== $boolean_value ) {
				return $boolean_value;
			}

			// Non-boolean strings: truthy when non-empty.
			return true;
		}

		/**
		 * Converts a current value to a US notation.
		 *
		 * @param string $current_value containing the money value to be converted.
		 *
		 * @return string containing the converted string.
		 */
		private function convert_to_us_notation( $current_value ) {
			// @since 2.28.0 Switched to the formal wc functions to get the separator and number of decimal values.
			$decimal_sep   = wc_get_price_decimal_separator();
			$thousands_sep = wc_get_price_thousand_separator();

			if ( ! preg_match( '/[a-zA-Z]/', $current_value ) ) { // Only remove the commas if the current value has no letters.
				if ( $this->already_us_notation( $current_value ) ) {
					// Some values like the Weight can already be in the US notation, so don't change them.
					return $current_value;
				}

				$no_thousands_sep = str_replace( $thousands_sep, '', $current_value );

				return ',' === $decimal_sep ? str_replace( ',', '.', $no_thousands_sep ) : $no_thousands_sep;
			} else {
				return $current_value;
			}
		}

		/**
		 * Checks if a numeric value is already in the US notation.
		 *
		 * @param string $value numeric value to be checked.
		 * @since 2.25.0
		 *
		 * @return bool true if the value is already in US notation.
		 */
		private function already_us_notation( $value ) {
			return strpos( $value, '.' ) && ! strpos( $value, ',' );
		}
	}


	// end of WPPFM_Feed_Queries_Class

endif;
