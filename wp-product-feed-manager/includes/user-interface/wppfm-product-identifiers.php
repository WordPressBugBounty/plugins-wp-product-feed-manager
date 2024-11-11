<?php

/**
 * @package WP Product Feed Manager/User Interface/Functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds custom fields to the product inventory card that can be used in the feeds.
 */
function wppfm_create_gtin_wc_support_field() {

	// Add the Brand field.
	woocommerce_wp_text_input(
		array(
			'id'          => 'wppfm_product_brand',
			'label'       => 'Product brand',
			'class'       => 'wppfm_product_brand',
			'desc_tip'    => true,
			'description' => __( 'Brand name of the product. If the product has no brand name you can use the manufacturer or supplier name.', 'wp-product-feed-manager' ),
		)
	);

	// Add the GTIN field.
	woocommerce_wp_text_input(
		array(
			'id'          => 'wppfm_product_gtin',
			'label'       => 'Product GTIN',
			'class'       => 'wppfm_product_gtin',
			'desc_tip'    => true,
			'description' => __( 'GTIN refers to a products Global Trade Item Number. You can also use a UPC, EAN, JAN, ISBN or ITF-14 number here.', 'wp-product-feed-manager' ),
		)
	);

	// Add the MPN field.
	woocommerce_wp_text_input(
		array(
			'id'          => 'wppfm_product_mpn',
			'label'       => 'Product MPN',
			'class'       => 'wppfm_product_mpn',
			'desc_tip'    => true,
			'description' => __( 'Add your product\'s Manufacturer Part Number (MPN).', 'wp-product-feed-manager' ),
		)
	);
}

add_action( 'woocommerce_product_options_inventory_product_data', 'wppfm_create_gtin_wc_support_field' );

/**
 * Function for the woocommerce_process_product_meta action-hook. Saves the custom fields' data.
 *
 * @param mixed $post_id Post ID of the product.
 */
function wppfm_save_custom_fields( $post_id ) {
	$product = wc_get_product( $post_id );

	// Get the custom fields' data.
	$brand = sanitize_text_field( $_POST['wppfm_product_brand'] ) ?? '';
	$gtin  = sanitize_text_field( $_POST['wppfm_product_gtin'] ) ?? '';
	$mpn   = sanitize_text_field( $_POST['wppfm_product_mpn'] ) ?? '';

	// Save the custom fields' data.
	$product->update_meta_data( 'wppfm_product_brand', $brand );
	$product->update_meta_data( 'wppfm_product_gtin', $gtin );
	$product->update_meta_data( 'wppfm_product_mpn', $mpn );

	$product->save();
}

add_action( 'woocommerce_process_product_meta', 'wppfm_save_custom_fields' );

/**
 * Function for the woocommerce_variation_options action-hook. Adds custom fields to the product inventory card of the product variations.
 *
 * @param array  $loop
 * @param object $variation_data
 * @param object $variation
 *
 * @noinspection PhpUnusedParameterInspection*/
function wppfm_create_mpn_wc_variation_support_field( $loop, $variation_data, $variation ) {

	echo '<div class="options_group form-row form-row-full">';

	// Add the MPN text field to the variation cards.
	woocommerce_wp_text_input(
		array(
			'id'          => 'wppfm_product_mpn[' . $variation->ID . ']',
			'label'       => __( 'Product MPN', 'wp-product-feed-manager' ),
			'desc_tip'    => true,
			'description' => __( 'Add your product\'s Manufacturer Part Number (MPN).', 'wp-product-feed-manager' ),
			'value'       => get_post_meta( $variation->ID, 'wppfm_product_mpn', true ),
		)
	);

	// Add the GTIN text field to the variation cards.
	woocommerce_wp_text_input(
		array(
			'id'          => 'wppfm_product_gtin[' . $variation->ID . ']',
			'label'       => 'Product GTIN',
			'desc_tip'    => true,
			'description' => __( 'GTIN refers to a products Global Trade Item Number. You can also use a UPC, EAN, JAN, ISBN or ITF-14 number here.', 'wp-product-feed-manager' ),
			'value'       => get_post_meta( $variation->ID, 'wppfm_product_gtin', true ),
		)
	);

	echo '</div>';
}

add_action( 'woocommerce_variation_options', 'wppfm_create_mpn_wc_variation_support_field', 10, 3 );

/**
 * Function for the woocommerce_save_product_variation action-hook. Saves the custom fields data of the product variations.
 *
 * @param int $post_id
 */
function wppfm_save_variation_custom_fields( $post_id ) {

	// Get the variations mpn and gtin.
	$woocommerce_mpn_field  = sanitize_text_field( $_POST['wppfm_product_mpn'][ $post_id ] );
	$woocommerce_gtin_field = sanitize_text_field( $_POST['wppfm_product_gtin'][ $post_id ] );

	// Update.
	update_post_meta( $post_id, 'wppfm_product_mpn', $woocommerce_mpn_field );
	update_post_meta( $post_id, 'wppfm_product_gtin', $woocommerce_gtin_field );
}

add_action( 'woocommerce_save_product_variation', 'wppfm_save_variation_custom_fields', 10, 2 );

/**
 * Function for the woocommerce_product_quick_edit_start action-hook. Adds the custom fields to the product quick edit form.
 */
function wppfm_show_wc_quick_edit_custom_fields() {
	// Add the Brand field.
	?>
	<label>
		<span class="title"><?php esc_html_e('Brand', 'woocommerce'); ?></span>
		<span class="input-text-wrap">
            <input type="text" name="wppfm_product_brand" class="text wppfm_product_brand" value="">
        </span>
	</label>
	<br class="clear" />
	<?php

	// Add the GTIN field.
	?>
	<label>
		<span class="title"><?php esc_html_e('GTIN', 'woocommerce'); ?></span>
		<span class="input-text-wrap">
            <input type="text" name="wppfm_product_gtin" class="text wppfm_product_gtin" value="">
        </span>
	</label>
	<br class="clear" />
	<?php

	// Add the MPN field.
	?>
	<label>
		<span class="title"><?php esc_html_e('MPN', 'woocommerce'); ?></span>
		<span class="input-text-wrap">
            <input type="text" name="wppfm_product_mpn" class="text wppfm_product_mpn" value="">
        </span>
	</label>
	<br class="clear" />
	<?php
}

add_action( 'woocommerce_product_quick_edit_start', 'wppfm_show_wc_quick_edit_custom_fields' );

/**
 * Function for the woocommerce_product_quick_edit_save action-hook. Saves the custom fields data of the products quick edit form.
 *
 * @param object $product
 */
function wppfm_save_wc_quick_edit_custom_fields( $product ) {
	if ( function_exists('wppfm_create_gtin_wc_support_field' ) ) {
		// Get the custom fields' data.
		$brand = sanitize_text_field( $_POST['wppfm_product_brand'] ) ?? '';
		$gtin  = sanitize_text_field( $_POST['wppfm_product_gtin'] ) ?? '';
		$mpn   = sanitize_text_field( $_POST['wppfm_product_mpn'] ) ?? '';

		// Save the custom fields' data.
		$product->update_meta_data( 'wppfm_product_brand', $brand );
		$product->update_meta_data( 'wppfm_product_gtin', $gtin );
		$product->update_meta_data( 'wppfm_product_mpn', $mpn );

		$product->save();
	}
}

add_action( 'woocommerce_product_quick_edit_save', 'wppfm_save_wc_quick_edit_custom_fields' );