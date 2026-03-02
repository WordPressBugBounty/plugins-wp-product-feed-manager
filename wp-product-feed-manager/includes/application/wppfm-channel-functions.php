<?php

/**
 * WP Product Channel Functions.
 *
 * @package WP Product Feed Manager/Application/Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns an array with the channel_id, category_name and description_name of a specific channel.
 *
 * @param string $channel_id the id of the channel for which the data is requested.
 *
 * @return array with channel_id, category_name and description_name.
 */
function wppfm_channel_file_text_data( $channel_id ) {
	switch ( $channel_id ) {
		case '1': // google
		case '13': // avantlink
		case '16': // facebook
		case '30': // Snapchat
		case '31': // Pinterest
		case '34': // X Shopping Manager
		case '35': // Instagram Shopping
		case '36': // WhatsApp Business
		case '37': // TikTok Catalog
		case '38': // Atalanda
		case '39': // Reddit
			$category_name    = 'google_product_category';
			$description_name = 'description';
			break;

		case '2': // bing
			$category_name    = 'BingCategory';
			$description_name = 'Description';
			break;

		case '3': // beslis
			$category_name    = 'Categorie';
			$description_name = 'Omschrijving';
			break;

		case '4': // pricegrabber
			$category_name    = 'Categorization';
			$description_name = 'Detailed Description';
			break;

		case '5': // shopping
			$category_name    = 'Category';
			$description_name = 'Product_Description';
			break;

		case '6': // amazon
		case '7': // connexity
		case '9': // nextag
			$category_name    = 'Category';
			$description_name = 'Description';
			break;

		case '10': // kieskeurig
			$category_name    = 'productgroep';
			$description_name = 'productomschrijving';
			break;

		case '11': // vergelijk
			$category_name    = 'Category';
			$description_name = 'ProductDescription';
			break;

		case '12': // koopjespakker
			$category_name    = 'category';
			$description_name = 'description';
			break;

		case '14': // zbozi
		case '24': // Heureka
			$category_name    = 'CATEGORYTEXT';
			$description_name = 'DESCRIPTION';
			break;

		case '15': // comcon
		case '17': // Bol.com
		case '27': // Galaxus Product Stock Pricing
		case '28': // Galaxus Product Properties
			$category_name    = '';
			$description_name = '';
			break;

		case '18': // Adtraction
		case '22': // Converto
		case '29': // Vivino
		case '32': // Vivino XML
		case '33': // Idealo XML
			$category_name    = '';
			$description_name = 'description';
			break;

		case '19': // Ricardo
			$category_name    = 'CategoryNr';
			$description_name = 'Descriptions[0].ProductDescription';
			break;

		case '20': // eBay
			$category_name    = 'condition';
			$description_name = 'productDescription';
			break;

		case '21': // Shopzilla
			$category_name    = 'Category ID';
			$description_name = 'Description';
			break;

		case '23': // Idealo
			$category_name    = '';
			$description_name = 'Produktbeschreibung';
			break;

		case '25': // Pepperjam
			$category_name    = 'category_network';
			$description_name = 'description_long';
			break;

		case '26': // Galaxus Product Data
			$category_name    = 'Category';
			$description_name = 'ProductDescription_';
			break;

		case '40': // ChatGPT
		case '996': // marketingrobot TSV
		case '997': // marketingrobot TXT
		case '998': // marketingrobot CSV
		case '999': // marketingrobot
			$category_name    = 'product_category';
			$description_name = 'description';
			break;

		default:
			$category_name    = 'google_product_category';
			$description_name = 'description';
			break;
	}

	return array(
		'channel_id'       => $channel_id,
		'category_name'    => $category_name,
		'description_name' => $description_name,
	);
}

/**
 * Returns the type of file the channel outputs. Default XML.
 *
 * @param string $channel_id the channel id for which the feed type is requested.
 *
 * @return string with the file type, XML is default.
 */
function wppfm_get_file_type( $channel_id ) {
	switch ( $channel_id ) {
		case '2': // bing
		case '4': // pricegrabber
		case '6': // amazon
		case '7': // connexity
		case '9': // nextag
		case '12': // koopjespakker
		case '21': // shopzilla
		case '25': // pepperjam
		case '29': // Vivino
		case '997': // Custom TXT Feed
			return 'txt';

		case '15': // comcon
		case '17': // bol.com
		case '19': // Ricardo.ch
		case '22': // Converto
		case '23': // Idealo
		case '26': // Galaxus Product Data
		case '27': // Galaxus Product Stock Pricing
		case '28': // Galaxus Product Properties
		case '34': // X Shopping Manager
		case '39': // Reddit
		case '998': // Custom CSV Feed
			return 'csv';

		case '996': // Custom TSV Feed
			return 'tsv';

		default:
			return 'xml';
	}
}

/**
 * Some channels use different csv separators. Only required for csv feeds.
 *
 * @param string $channel_id the channel id for which the csv separator is requested.
 *
 * @return string with the correct csv separator, comma is default.
 */
function wppfm_get_correct_csv_separator( $channel_id ) {
	switch ( $channel_id ) {
		case '15': // comcon
		case '26': // Galaxus Product Data
		case '27': // Galaxus Product Stock Pricing
		case '28': // Galaxus Product Properties
			return ';';

		case '29': // Vivino
			return '|';

		default:
			return ',';
	}
}

/**
 * Some channels use different txt separators. Only required for txt feeds.
 *
 * @param string $channel_id the channel id for which the txt separator is requested.
 *
 * @return string with the correct txt separator, TAB is default.
 */
function wppfm_get_correct_txt_separator( $channel_id ) {
	switch ( $channel_id ) {
		case '29':
			return '|'; // Vivino

		default:
			return 'TAB'; // Results in a tab separated feed.
	}
}

/**
 * Some channels use a different separator for the header line.
 *
 * @param string $channel_id the channel id for which the header separator is requested.
 *
 * @return string with the correct csv header separator.
 */
function wppfm_get_correct_csv_header_separator( $channel_id ) {
	switch ( $channel_id ) {
		case '22': // Converto
		case '29': // Vivino
			return '|';

		default:
			return wppfm_get_correct_csv_separator( $channel_id );
	}
}

/**
 * returns true if the channel uses the categories from the web-shop.
 *
 * @param string $channel_id the channel id for which this request is valid.
 *
 * @return boolean true if the channel uses its own categories. True is default.
 */
function wppfm_channel_uses_own_category( $channel_id ) {
	switch ( $channel_id ) {
		case '15': // comcon
		case '17': // bol.com
		case '22': // converto
		case '23': // idealo
		case '25': // pepperjam
		case '26': // Galaxus Product Data
		case '27': // Galaxus Product Stock Pricing
		case '28': // Galaxus Product Properties
		case '29': // Vivino
		case '32': // Vivino XML
		case '33': // Idealo XML
			return false;

		default:
			return true;
	}
}

/**
 * Returns the text that has to be used as node name for every product in the XML file.
 *
 * @param string $channel the channel id for which to return the node name.
 *
 * @return string containing the product node name. Default is product.
 */
function wppfm_product_node_name( $channel ) {
	switch ( $channel ) {
		case '1': // Google
		case '13': // Avantlink
		case '16': // Facebook
		case '31': // Pinterest
		case '34': // X Shopping Manager
		case '35': // Instagram Shopping
		case '36': // WhatsApp Business
		case '37': // TikTok Catalog
		case '38': // Atalanda
			return 'item';

		case '5': // Shopping
		case '11': // Vergelijk
		case '20': // Ebay
			return 'Product';

		case '14': // Zbozi
		case '24': // Heureka
			return 'SHOPITEM';

		default:
			return 'product';
	}
}

/**
 * Returns a pre-tag when the channel requires it.
 *
 * @param string $channel the channel id for which to return the pre-tag.
 *
 * @return string with the pre-tag. No pre-tag (empty string) is default.
 */
function wppfm_get_node_pre_tag( $channel ) {
	switch ( $channel ) {
		case '1': // Google
		case '16': // Facebook
		case '34': // X Shopping Manager
		case '35': // Instagram Shopping
		case '36': // WhatsApp Business
		case '37': // TikTok Catalog
		case '38': // Atalanda
			return 'g:';

		default:
			return '';
	}
}

/**
 * Returns true if the channel wants all attributes to be added to the feed even when there is no data for it.
 *
 * @param string $channel the channel id for which the request is valid.
 *
 * @return bool true if the channel requires even empty attributes. False is default.
 */
function wppfm_channel_requires_all_attributes_in_feed( $channel ) {
	switch ( $channel ) {
		case '26': // Galaxus Product Data
			return true;

		default:
			return false;
	}
}

/**
 * Returns true if the channel does not allow double quotes on empty attributes in the feed, but just an empty string.
 *
 * @param string $channel the channel id for which the request is valid.
 *
 * @return bool true if the channel requires no quotes. False is default.
 */
function wppfm_channel_requires_no_quotes_on_empty_attributes( $channel ) {
	switch ( $channel ) {
		case '26': // Galaxus Product Data
			return true;

		default:
			return false;
	}
}

/**
 * Returns true if a channel is a custom channel.
 *
 * @param string $channel channel name or channel id for the request.
 *
 * @return boolean returns true if the channel is a custom channel, false is default.
 */
function wppfm_channel_is_custom_channel( $channel ) {
	switch ( $channel ) {
		case '996': // Custom TSV Feed
		case '997': // Custom TXT Feed
		case '998': // Custom CSV Feed
		case '999': // Custom XML Feed
		case 'marketingrobot_tsv': // Custom TSV Feed
		case 'marketingrobot_txt': // Custom TXT Feed
		case 'marketingrobot_csv': // Custom CSV Feed
		case 'marketingrobot': // Custom XML Feed
			return true;

		default:
			return false;
	}
}

/**
 * Checks if the current plugin version supports the selected channel.
 *
 * @param string $channel the channel id for which to perform the check.
 *
 * @since 1.8.0.
 * @return boolean true if the channel is supported by the current plugin version.
 */
function wppfm_plugin_version_supports_channel( $channel ) {
	$supported_channels = array(
		'google',
		'bing',
		'beslis',
		'pricegrabber',
		'shopping',
		'amazon',
		'connexity',
		'nextag',
		'kieskeurig',
		'vergelijk',
		'koopjespakker',
		'avantlink',
		'zbozi',
		'comcon',
		'facebook',
		'marketingrobot_tsv',
		'marketingrobot_txt',
		'marketingrobot_csv',
		'marketingrobot',
		'bol',
		'adtraction',
		'ricardo',
		'ebay',
		'shopzilla',
		'converto',
		'idealo',
		'heureka',
		'pepperjam',
		'galaxus_data',
		'galaxus_properties',
		'galaxus_stock_pricing',
		'vivino',
		'snapchat',
		'pinterest',
		'vivino_xml',
		'idealo_xml',
		'x_shopping_manager',
		'instagram_shopping',
		'whatsapp_business',
		'tiktok_catalog',
		'atalanda',
		'reddit',
		'chatgpt',
	);

	return in_array( $channel, $supported_channels, true );
}

/**
 * returns the channel specific class-feed.php class name.
 *
 * @param string $channel_id the channel id for which to get the channel class name.
 *
 * @return string containing the correct class name.
 */
function wppfm_get_correct_channel_class_name( $channel_id ) {
	if ( ! $channel_id ) {
		wppfm_write_log_file( sprintf( 'Error 3821 - Could not identify the selected channel id. Given channel = %s', $channel_id ) );
		return false;
	}

	$channel_base_class = new WPPFM_Channel();
	$channel_short_name = $channel_base_class->get_channel_short_name( $channel_id );

	$channel_class = 'WPPFM_' . ucfirst( $channel_short_name ) . '_Feed_Class';

	$file_path = WPPFM_CHANNEL_DATA_DIR . '/' . $channel_short_name . '/class-feed.php';
	$real_file_path = realpath( $file_path );

	if ( ! class_exists( $channel_class ) && file_exists( $file_path ) && $real_file_path && wppfm_plugin_version_supports_channel( $channel_short_name ) ) {
		require_once $file_path; // nosemgrep: audit.php.lang.security.file.inclusion-arg
	}

	return $channel_class;
}

/**
 * Checks if the channel used by a specific feed, is installed.
 *
 * @param string $feed_id the feed id to check
 *
 * @since 2.20.0
 * @return bool true if the channel is used by the specified feed.
 */
function wppfm_verify_feeds_channel_is_installed( $feed_id ) {
	if ( ! $feed_id ) {
		return true;
	}

	$channel_class = new WPPFM_Channel();

	$channels          = $channel_class->get_installed_channel_names();
	$feed_channel_name = $channel_class->get_channel_short_name_from_feed_id( $feed_id );

	return in_array( $feed_channel_name, $channels, true );
}

/**
 * Automatically updates installed channels if the installed version is not the latest version.
 *
 * @param array $installed_channels An array of objects representing the installed channels. Each object should have the properties "installed_version" and "version".
 *
 * @since 3.7.0.
 */
function wppfm_auto_update_installed_channels( $installed_channels ) {
	foreach( $installed_channels as $channel ) {
		$latest_version = (float) $channel->installed_version >= (float) $channel->version;

		if ( ! $latest_version ) {
			$nonce = wp_create_nonce( 'update-channel-nonce' );
			$channel_class = new WPPFM_Channel();
			$channel_class->update_channel( $channel->short_name, $channel->dir_code, $nonce );
		}
	}
}
