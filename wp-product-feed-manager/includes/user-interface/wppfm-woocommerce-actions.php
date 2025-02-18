<?php

/**
 * @package WP Product Feed Manager/User Interface/Functions.
 * @since 3.11.0.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a custom Product Feed tab to the Product Data on the WooCommerce product edit page. Triggered by the 'woocommerce_product_data_tabs' filter.
 */
function wppfm_product_feed_tab( $tabs ) {
	$tabs['wppfm_product_feed'] = array(
		'label'    => esc_html__( 'Product Feed', 'wp-product-feed-manager' ),
		'target'   => 'wppfm_product_feed_tab',
		'priority' => 55,
	);

	return $tabs;
}

add_filter( 'woocommerce_product_data_tabs', 'wppfm_product_feed_tab' );

/**
 * Renders the Product Feed tab content. Triggered by the 'woocommerce_product_data_panels' action.
 *
 * @since 3.12.0. - Added a nonce field to the checkbox form.
 */
function wppfm_render_product_feed_tab() {
	// The Product Feed tab content
	?>
	<div id="wppfm_product_feed_tab" class="panel woocommerce_options_panel">
		<?php
		woocommerce_wp_checkbox( array(
			'id'            => 'wppfm-exclude-from-feed-checkbox',
			'name'          => 'wppfm_exclude_from_feed',
			'wrapper_class' => 'show_if_simple show_if_variable',
			'value'         => get_post_meta( get_the_ID(), 'wppfm_exclude_from_feed', true ),
			'label'         => esc_html__( 'Exclude from Feed', 'wp-product-feed-manager' ),
			'description'   => esc_html__( 'Activate this option to mark this product for exclusion. Use the Feed Manager Product Filter to remove marked products from feeds', 'wp-product-feed-manager' ),
			'default'  		=> '0',
			'desc_tip'    	=> false,
		) );

		// Adding a nonce field to the form
		wp_nonce_field( 'wppfm_exclude_from_feed_nonce_action', 'wppfm_exclude_from_feed_nonce' );

		do_action( 'wppfm_woocommerce_product_feed_panel' );
		?>
	</div>
	<?php
}

add_action( 'woocommerce_product_data_panels', 'wppfm_render_product_feed_tab' );

/**
 * Saves the checkbox value for products. Triggered by the 'woocommerce_process_product_meta' action.
 *
 * @param int $post_id the ID of the product.
 *
 * @since 3.12.0. - Added a nonce verification check.
 */
function wppfm_save_custom_product_feed_exclusion_checkbox( $post_id ) {
	if ( wp_verify_nonce( filter_input( INPUT_POST, 'wppfm_exclude_from_feed_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm_exclude_from_feed_nonce_action' ) ) {
		$exclude_product = isset( $_POST['wppfm_exclude_from_feed'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, 'wppfm_exclude_from_feed', $exclude_product );
	}
}

add_action( 'woocommerce_process_product_meta', 'wppfm_save_custom_product_feed_exclusion_checkbox' );

/**
 * Adds a checkbox to the WooCommerce product variation edit page in the option row. Triggered by the 'woocommerce_variation_options' action.
 *
 * @param int     $loop           iteration count of the variations.
 * @param array   $variation_data the variation data.
 * @param WP_POST $variation      the variation object.
 *
 * @since 3.12.0. - Added a nonce field to the checkbox form.
 * @noinspection PhpUnusedParameterInspection*/
function wppfm_add_custom_select_to_variations( $loop, $variation_data, $variation ) {
	$exclude_variation_checked = get_post_meta( $variation->ID, 'wppfm_exclude_from_feed', true );

	?><label class="tips" data-tip="<?php esc_attr_e( 'Activate this option to mark this product variation for exclusion. Use the Feed Manager Product Filter to remove marked products from feeds', 'wp-product-feed-manager' ); ?>">
		<?php esc_html_e( 'Exclude from Feed', 'wp-product-feed-manager' ); ?>
		<input type="checkbox" class="checkbox variable_exclude_from_feed" name="wppfm_exclude_from_feed[<?php esc_attr( $loop ) ?>]" <?php checked( $exclude_variation_checked, 'yes' ); ?> />
		<?php wp_nonce_field( 'wppfm_exclude_from_feed_nonce_action', 'wppfm_exclude_from_feed_nonce' ); ?>
	</label><?php
}

add_action( 'woocommerce_variation_options', 'wppfm_add_custom_select_to_variations', 10, 3 );

/**
 * Saves the checkbox value for variations. Triggered by the 'woocommerce_save_product_variation' action.
 *
 * @param string $variation_id the ID of the variation.
 * @param int    $loop         iteration count of the variations.
 *
 * @since 3.12.0. - Added a nonce check.
 */
function wppfm_save_custom_variation_feed_exclusion_checkbox( $variation_id, $loop ) {
	if ( wp_verify_nonce( filter_input( INPUT_POST, 'wppfm_exclude_from_feed_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), 'wppfm_exclude_from_feed_nonce_action' ) ) {
		$exclude_variation = isset( $_POST['wppfm_exclude_from_feed'][0] ) ? 'yes' : 'no';
		update_post_meta( $variation_id, 'wppfm_exclude_from_feed', $exclude_variation );
	}
}

add_action( 'woocommerce_save_product_variation', 'wppfm_save_custom_variation_feed_exclusion_checkbox', 10, 2 );
