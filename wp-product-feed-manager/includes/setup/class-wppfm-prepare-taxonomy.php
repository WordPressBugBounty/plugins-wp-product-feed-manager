<?php

/* * ******************************************************************
 * Version 1.0
 * Modified: 29-08-2015
 * Copyright 2015 Accentio. All rights reserved.
 * License: None
 * By: Michel Jongbloed
 * ****************************************************************** */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Prepare_Taxonomy' ) ) :

	/**
	 * The WPPFM_Prepare_Taxonomy_Class contains functions that can convert taxonomy files provided by channel providers
	 * to the format that WP Product Feed Manager requires as taxonomy files.
	 *
	 * @class WPPFM_Prepare_Taxonomy_Class
	 * @version dev
	 */
	class WPPFM_Prepare_Taxonomy {

		/**
		 * Reads a text file through the plugin WP_Filesystem wrapper and returns normalized lines.
		 *
		 * @param string $path Absolute file path.
		 *
		 * @return string[]
		 */
		private static function get_file_lines( $path ) {
			$wp_filesystem = wppfm_get_wp_filesystem();
			$contents      = $wp_filesystem->exists( $path ) ? $wp_filesystem->get_contents( $path ) : '';

			if ( empty( $contents ) ) {
				return array();
			}

			$lines = preg_split( "/\r\n|\n|\r/", $contents );

			// Remove the trailing split artifact for files that already end with a newline.
			if ( ! empty( $lines ) && '' === end( $lines ) ) {
				array_pop( $lines );
			}

			return array_map( 'strval', $lines );
		}

		/**
		 * Writes normalized lines to disk through WP_Filesystem.
		 *
		 * @param string   $path             Absolute file path.
		 * @param string[] $lines            Lines to write without trailing newlines.
		 * @param bool     $remove_duplicates Optional. Remove duplicate lines before writing.
		 *
		 * @return bool
		 */
		private static function put_file_lines( $path, $lines, $remove_duplicates = false ) {
			$wp_filesystem = wppfm_get_wp_filesystem();
			$lines         = array_map( 'strval', (array) $lines );

			if ( $remove_duplicates ) {
				$lines = array_values( array_unique( $lines ) );
			}

			$contents = empty( $lines ) ? '' : implode( "\r\n", $lines ) . "\r\n";

			return (bool) $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
		}

		public static function remove_merchant_rates_from_pricegrabber_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/pricegrabber/taxonomy.en-US.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/pricegrabber/taxonomy_new.en-US.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {

				if ( strpos( $line, ',$' ) !== false ) {
					$out[] = $line ? substr( $line, 0, strpos( $line, ',$' ) ) : '';
				} else {
					$out[] = $line;
				}
			}

			self::put_file_lines( $rpath, $out );
		}

		public static function prepare_amazone_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/amazon/taxonomy.en-US.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/amazon/taxonomy_new.en-US.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {

				if ( strpos( $line, '/' ) !== false ) {
					$out[] = str_replace( '/', ' > ', $line );
				} else {
					$out[] = $line;
				}
			}

			self::put_file_lines( $rpath, $out );
		}


		public static function prepare_bing_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/bing/taxonomy.en-US.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/bing/taxonomy_new.en-US.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {
				$out[] = substr( $line, strrpos( $line, ' - ' ) + 3 );
			}

			self::put_file_lines( $rpath, $out );
		}

		public static function prepare_vergelijk_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/vergelijk/taxonomy.nl-NL.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/vergelijk/taxonomy_new.nl-NL.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {

				$removed_tabs = str_replace( "\t", '|', $line );

				$explode = explode( '|', $removed_tabs );

				$category = isset( $explode[1] ) ? str_replace( '"', '', $explode[1] ) : '';
				$out[]    = $category . ' > ' . ( $explode[3] ?? '' ) . ' > ' . ( $explode[5] ?? '' );
			}

			self::put_file_lines( $rpath, $out, true );
		}

		public static function prepare_bol_content_category_file() {
			$path  = WPPFM_CHANNEL_DATA_DIR . '/bol_content/taxonomy.nl-NL.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/bol_content/taxonomy_new.nl-NL.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {
				$out[] = preg_replace( "/\t/", ' > ', $line );
			}

			self::put_file_lines( $rpath, $out, true );
		}

		public static function convert_kieskeurig_category_file() {

			$path    = WPPFM_CHANNEL_DATA_DIR . '/kieskeurig/taxonomy.nl-NL.txt';
			$rpath   = WPPFM_CHANNEL_DATA_DIR . '/kieskeurig/taxonomy_new.nl-NL.txt';
			$newline = '';
			$lines   = self::get_file_lines( $path );
			$out     = array();

			$r_1 = '';
			$r_2 = '';

			$c = 0;

			foreach ( $lines as $line ) {

				if ( $c < 2 ) { // remove the first two lines
					$newline = '';
					$c ++;
				} else {

					$line_cats = explode( "\t", $line );

					if ( '' !== $line_cats[0] ) {

						$r_1 = $line_cats[0];

						$newline = '';
					} elseif ( '' !== $line_cats[2] ) {

						$r_2 = $line_cats[2];

						$newline = '';
					} elseif ( '' !== $line_cats[5] ) {

						$cat_1 = '' === $line_cats[0] ? $r_1 : $line_cats[0];
						$cat_2 = '' === $line_cats[2] ? $r_2 : $line_cats[2];
						$cat_3 = $line_cats[5];

						$newline = $cat_1 . ' > ' . $cat_2 . ' > ' . $cat_3;
					}
				}

				$out[] = $newline;
			}

			self::put_file_lines( $rpath, $out );
		}

		public static function prepare_beslis_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/beslis/category_overview.xml';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/beslis/taxonomy-new.nl-NL.txt';
			$xml   = simplexml_load_file( $path );
			$lines = array();

			foreach ( $xml->categories->maincat as $main_cat ) {

				$newline = (string) $main_cat['name'][0];
				$lines[] = $newline;

				foreach ( $main_cat as $level_1 ) {

					$level_1_line = $newline . ' > ' . (string) $level_1['name'][0];
					$lines[]      = $level_1_line;

					foreach ( $level_1 as $level_2 ) {

						$level_2_line = $level_1_line . ' > ' . (string) $level_2['name'][0];
						$lines[]      = $level_2_line;
					}
				}
			}

			self::put_file_lines( $rpath, $lines );
		}

		public static function prepare_heureka_category_file() {
			$path       = WPPFM_CHANNEL_DATA_DIR . '/heureka/category_overview.xml';
			$rpath      = WPPFM_CHANNEL_DATA_DIR . '/heureka/taxonomy-new.cs-CZ.txt';
			$cat_prefix = 'Heureka.cz | ';
			$xml        = simplexml_load_file( $path );
			$lines      = array();

			foreach ( $xml->CATEGORY as $main_cat ) {
				$newline = $main_cat[0]->CATEGORY_NAME;

				if ( ! empty( $newline ) ) {
					$lines[] = (string) $newline;
				}

				foreach ( $main_cat as $level_1 ) {
					$level_1_string = substr( $level_1[0]->CATEGORY_FULLNAME, strlen( $cat_prefix ) );
					$level_1_line   = str_replace( ' | ', ' > ', $level_1_string );

					if ( ! empty( $level_1_line ) ) {
						$lines[] = $level_1_line;
					}

					foreach ( $level_1 as $level_2 ) {
						$level_2_string = substr( $level_2[0]->CATEGORY_FULLNAME, strlen( $cat_prefix ) );
						$level_2_line   = str_replace( ' | ', ' > ', $level_2_string );

						if ( ! empty( $level_2_line ) ) {
							$lines[] = $level_2_line;
						}

						foreach ( $level_2 as $level_3 ) {
							$level_3_string = substr( $level_3[0]->CATEGORY_FULLNAME, strlen( $cat_prefix ) );
							$level_3_line   = str_replace( ' | ', ' > ', $level_3_string );

							if ( ! empty( $level_3_line ) ) {
								$lines[] = $level_3_line;
							}

							foreach ( $level_3 as $level_4 ) {
								$level_4_string = substr( $level_4[0]->CATEGORY_FULLNAME, strlen( $cat_prefix ) );
								$level_4_line   = str_replace( ' | ', ' > ', $level_4_string );

								if ( ! empty( $level_4_line ) ) {
									$lines[] = $level_4_line;
								}

								foreach ( $level_4 as $level_5 ) {
									$level_5_string = substr( $level_5[0]->CATEGORY_FULLNAME, strlen( $cat_prefix ) );
									$level_5_line   = str_replace( ' | ', ' > ', $level_5_string );

									if ( ! empty( $level_5_line ) ) {
										$lines[] = $level_5_line;
									}

									foreach ( $level_5 as $level_6 ) {
										$level_6_string = substr( $level_6[0]->CATEGORY_FULLNAME, strlen( $cat_prefix ) );
										$level_6_line   = str_replace( ' | ', ' > ', $level_6_string );

										if ( ! empty( $level_6_line ) ) {
											$lines[] = $level_6_line;
										}
									}
								}
							}
						}
					}
				}
			}

			self::put_file_lines( $rpath, $lines );
		}

		public static function prepare_nextag_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/nextag/taxonomy.en-US.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/nextag/taxonomy_new.en-US.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {

				if ( strpos( $line, '/' ) !== false ) {
					$newline = str_replace( '/', '>', $line );
				} else {
					$newline = $line;
				}

				$out[] = trim( $newline );
			}

			self::put_file_lines( $rpath, $out );
		}

		public static function prepare_ricardo_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/ricardo/taxonomy_source.fr-CH.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/ricardo/taxonomy.fr-CH.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {
				$line = trim( $line );

				$first_tab_pos = strpos( $line, "\t" );
				// get the number
				$cat_nr = substr( $line, 0, $first_tab_pos );

				// remove the number
				$line = str_replace( $cat_nr . "\t", '', $line );

				if ( strpos( $line, "\t" ) !== false ) {

					$newline = str_replace( "\t", ' > ', $line );
				} else {

					$newline = $line;
				}

				$newline = trim( $newline, ' > ' );

				$out[] = trim( $newline . ' (' . $cat_nr . ')' );
			}

			self::put_file_lines( $rpath, $out );
		}

		public static function prepare_ebay_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/ebay/taxonomy_source.en-US.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/ebay/taxonomy.en-US.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {
				$line = trim( $line );

				$line_items = explode( ';', $line );

				if ( $line_items[0] && $line_items[1] ) {
					$out[] = trim( $line_items[1] . ' (' . $line_items[0] . ')' );
				}
			}

			self::put_file_lines( $rpath, $out );
		}

		public static function prepare_koopjespakker_category_file() {

			$path  = WPPFM_CHANNEL_DATA_DIR . '/koopjespakker/taxonomy.en-US.txt';
			$rpath = WPPFM_CHANNEL_DATA_DIR . '/koopjespakker/taxonomy_new.en-US.txt';
			$lines = self::get_file_lines( $path );
			$out   = array();

			foreach ( $lines as $line ) {

				$newline_1 = str_replace( '/', '>', $line );
				$newline_2 = str_replace( '|', '>', $newline_1 );

				$newline = trim( $newline_2 );

				if ( '' !== $newline && strpos( $line, 'concat' ) === false && strpos( $line, 'Options' ) === false ) {
					$out[] = $newline;
				}
			}

			self::put_file_lines( $rpath, $out );
		}

	}


	// end of WPPFM_Prepare_Taxonomy_Class

endif;
