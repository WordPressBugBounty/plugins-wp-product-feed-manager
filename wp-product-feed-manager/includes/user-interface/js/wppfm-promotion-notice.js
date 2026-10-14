( function() {

	/**
	 * Hides the promotion banner without persisting dismissal (until the next page load).
	 */
	jQuery( '#wppfm-dismiss-promotion-notice' ).on(
		'click',
		function( event ) {
			event.preventDefault();
			jQuery( 'div#wppfm-discount-promotion-notice' ).fadeOut( 300 );
		}
	);

	/**
	 * Permanently dismisses the promotion for this plugin edition.
	 */
	jQuery( '#wppfm-dismiss-promotion-notice-link' ).on(
		'click',
		function( event ) {
			event.preventDefault();
			jQuery( 'div#wppfm-discount-promotion-notice' ).fadeOut( 300 );

			if ( typeof ajax_object === 'undefined' ) {
				return;
			}

			var promotionEdition = ( typeof wppfm_promotion_notice !== 'undefined' && wppfm_promotion_notice.promotion_edition )
				? wppfm_promotion_notice.promotion_edition
				: 'free';

			jQuery.post(
				ajax_object.ajax_url,
				{
					action: 'wppfm-ajax-cancel-promotion-notice',
					cancelPromotionNonce: ajax_object.cancelPromotionNonce,
					promotionEdition: promotionEdition,
				}
			);
		}
	);

} )();
