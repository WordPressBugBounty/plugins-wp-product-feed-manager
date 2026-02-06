<?php

/**
 * WPPFM Product Feed Form Control Class.
 *
 * @package WP Product Feed Manager/User Interface/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPFM_Feed_Form_Control' ) ) :

	/**
	 * WPPFM Feed Form Control Class.
	 */
	class WPPFM_Feed_Form_Control {

		/**
		 * Renders a source selector.
		 */
		public static function source_selector() {
			$data_class = new WPPFM_Data();
			$sources    = $data_class->get_sources();

			if ( ! empty( $sources ) ) {
				echo '<select id="wppfm-sources">
					<option value="0">' . esc_html__( 'Select your product source', 'wp-product-feed-manager' ) . '</option>';

				if ( count( $sources ) > 1 ) {
					foreach ( $sources as $source ) {
						echo '<option value="' . esc_attr( $source['source_id'] ) . '">' . esc_html( $source['name'] ) . '</option>';
					}
				} else {
					echo '<option value="' . esc_attr( $sources[0]['source_id'] ) . '" selected>' . esc_html( $sources[0]['name'] ) . '</option>';
				}

				echo '</select>';
			}
		}

		/**
		 * Renders a channel selector.
		 */
		public static function channel_selector() {
			$data_class = new WPPFM_Data();
			$channels   = $data_class->get_channels();

			if ( ! empty( $channels ) ) {
				echo '<div id="selected-merchant"></div>
					<select class="wppfm-main-input-selector" id="wppfm-merchants-selector" style="display:initial;">
					<option value="0">' . esc_html__( '-- Select your merchant --', 'wp-product-feed-manager' ) . '</option>';

				foreach ( $channels as $channel ) {
					echo '<option value="' . esc_attr( $channel['channel_id'] ) . '">' . esc_html( $channel['name'] ) . '</option>';
				}

				echo '</select>';
			} else {
				echo esc_html__( 'You first need to install a channel before you can add a feed. Open the Manage Channels page and install at least one channel.', 'wp-product-feed-manager' );
			}
		}

		/**
		 * Renders a feed type selector.
		 *
		 * @since 2.38.0.
		 */
		public static function feed_type_selector( $preselected ) {
			$data_class = new WPPFM_Data();
			$feed_types = $data_class->get_google_support_feed_types();

			if ( ! empty( $feed_types ) ) {
				echo '<div id="wppfm-selected-google-feed-type"></div>
					<select class="wppfm-main-input-selector wppfm-feed-types-selector" id="wppfm-feed-types-selector">
					<option value="' . esc_attr( $feed_types[0]['feed_type_id'] ) . '">' . esc_html( $feed_types[0]['name'] ) . '</option>
					<optgroup label="Supplemental Feeds">';

				foreach ( $feed_types as $feed_type ) {
					$disabled = $feed_type['disabled'] ? ' disabled="disabled"' : '';
					if ( 'supplemental' === $feed_type['group'] ) {
						if ( $feed_type['feed_type_id'] === $preselected ) {
							echo '<option value="' . esc_attr( $feed_type['feed_type_id'] ) . '" selected>' . esc_html( $feed_type['name'] ) . '</option>';
						} else {
							echo '<option value="' . esc_attr( $feed_type['feed_type_id'] ) . '"' . esc_attr( $disabled ) . '>' . esc_html( $feed_type['name'] ) . '</option>';
						}
					}
				}

				echo '</optgroup></select>';
			}
		}

		/**
		 * Renders a feed business type selector.
		 */
		public static function feed_business_type_selector() {
			$business_types = array( 'Education', 'Flights', 'Hotels and rentals', 'Jobs', 'Local deals', 'Real estate', 'Travel', 'Custom' );

			echo '<select class="wppfm-main-input-selector wppfm-feed-business-types-selector" id="wppfm-feed-drm-types-selector">
				<option value="0">' . esc_html__( '-- Select your business type --', 'wp-product-feed-manager' ) . '</option>';

			foreach ( $business_types as $business_type ) {
				echo '<option value="' . esc_attr( $business_type ) . '">' . esc_html( $business_type ) . '</option>';
			}

			echo '</select>';
		}

		/**
		 * Renders a country selector.
		 */
		public static function country_selector() {
			$data_class = new WPPFM_Data();
			$countries  = $data_class->get_countries();

			if ( ! empty( $countries ) ) {
				echo '<select class="wppfm-main-input-selector wppfm-countries-selector" id="wppfm-countries-selector" disabled>
					<option value="0">' . esc_html__( '-- Select your target country --', 'wp-product-feed-manager' ) . '</option>';

				foreach ( $countries as $country ) {
					echo '<option value="' . esc_attr( $country['name_short'] ) . '">' . esc_html( $country['name'] ) . '</option>';
				}

				echo '</select>';
			}
		}

		/**
		 * Renders a schedule selector.
		 */
		public static function schedule_selector() {
			echo '<span id="wppfm-update-day-wrapper" style="display:initial">' . esc_html__( 'Every', 'wp-product-feed-manager' ) . '
				<input type="text" class="small-text" name="days-interval" id="days-interval" value="1" style="width:30px;" /> ' . esc_html__( 'day(s) at', 'wp-product-feed-manager' ) . '</span>
				<span id="wppfm-update-every-day-wrapper" style="display:none">' . esc_html__( 'Every day at', 'wp-product-feed-manager' ) . '</span>
				<select id="update-schedule-hours" style="width:52px;height:35px;">' . wp_kses( self::hour_list(), array( 'option' => array( 'value' => array() ) ) ) . '</select>
				<select id="update-schedule-minutes" style="width:52px;height:35px;">' . wp_kses( self::minutes_list(), array( 'option' => array( 'value' => array() ) ) ) . '</select>
				<span id="wppfm-update-frequency-wrapper" style="display:initial">
				 ' . esc_html__( 'for', 'wp-product-feed-manager' ) . ' 
				 <select id="update-schedule-frequency" style="width:50px;height:35px;">' . wp_kses( self::frequency_list(), array( 'option' => array( 'value' => array() ) ) ) . '</select>
				  ' . esc_html__( 'time(s) a day', 'wp-product-feed-manager' ) . '
				</span>';
		}

		/**
		 * Renders an aggregation selector.
		 */
		public static function aggregation_selector() {
			echo '<input type="checkbox" name="aggregator-selector" id="aggregator">';
		}

		/**
		 * Renders a product variation selector.
		 */
		public static function product_variation_selector() {
			echo '<input type="checkbox" name="product-variations-selector" id="variations">';
		}

		/**
		 * Renders a Google feed title selector.
		 */
		public static function google_feed_title_selector() {
			echo '<input type="text" name="google-feed-title-selector" id="google-feed-title-selector" placeholder="uses File Name if left empty..." />';
		}

		/**
		 * Renders a Google feed description selector.
		 */
		public static function google_feed_description_selector() {
			echo '<input type="text" name="google-feed-description-selector" id="google-feed-description-selector" placeholder="uses a standard description if left empty..." />';
		}

		/**
		 * Renders a Google Analytics selector.
		 */
		public static function google_analytics_selector() {
			echo '<input type="checkbox" name="wppfm-google-analytics-selector" id="wppfm-google-analytics">';
		}

		/**
		 * Creates the HTML code with a list of 24 hours to be used in an hour selector.
		 *
		 * @return string option list with 24 hours in options.
		 */
		private static function hour_list() {
			$html = self::get_time_list(); // First ten hours.

			for ( $i = 10; $i < 24; $i ++ ) {
				$html .= '<option value="' . $i . '">' . $i . '</option>';
			}

			return $html;
		}

		/**
		 * Creates the HTML code with a list of 60 minutes to be used in a minute selector.
		 *
		 * @return string option list with 60 minutes in options.
		 */
		private static function minutes_list() {
			$html = self::get_time_list(); // First ten minutes.

			for ( $i = 10; $i < MINUTE_IN_SECONDS; $i ++ ) {
				$html .= '<option value="' . $i . '">' . $i . '</option>';
			}

			return $html;
		}

		/**
		 * Creates the HTML code for a list with feed update frequencies, to be used in a frequency selector.
		 *
		 * @return string with specific frequency options.
		 */
		private static function frequency_list() {
			$html_code  = '<option value="1">1</option>';
			$html_code .= '<option value="2">2</option>';
			$html_code .= '<option value="4">4</option>';
			$html_code .= '<option value="6">6</option>';
			$html_code .= '<option value="8">8</option>';
			$html_code .= '<option value="12">12</option>';
			$html_code .= '<option value="24">24</option>';

			return $html_code;
		}

		/**
		 * Creates the HTML code with a list of 00 to 09 option selectors.
		 *
		 * @return string with the option selectors.
		 */
		private static function get_time_list() {
			$html_code  = '<option value="00">00</option>';
			$html_code .= '<option value="01">01</option>';
			$html_code .= '<option value="02">02</option>';
			$html_code .= '<option value="03">03</option>';
			$html_code .= '<option value="04">04</option>';
			$html_code .= '<option value="05">05</option>';
			$html_code .= '<option value="06">06</option>';
			$html_code .= '<option value="07">07</option>';
			$html_code .= '<option value="08">08</option>';
			$html_code .= '<option value="09">09</option>';

			return $html_code;
		}
	}


	// end of WPPFM_Feed_Form_Control class

endif;
