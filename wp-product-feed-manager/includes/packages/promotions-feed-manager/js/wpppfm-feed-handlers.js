//noinspection JSUnusedGlobalSymbols,JSUnusedLocalSymbols
function wpppfm_requiredDataIsFilledIn( promotionNr = 0 ) {
	var couponCodeRequired = jQuery( '#wpppfm-offer-type-input-field-' + promotionNr ).val();

	if ( 'generic_code' === couponCodeRequired ) {
		couponCodeRequired = jQuery( '#wpppfm-generic-redemption-code-input-field-' + promotionNr ).val();
	}

	return jQuery( '#wppfm-feed-file-name' ).val() &&
		jQuery( '#wpppfm-promotion-id-input-field-' + promotionNr ).val() &&
		jQuery( '#wpppfm-product-applicability-input-field-' + promotionNr ).val() &&
		couponCodeRequired &&
		jQuery( '#wpppfm-long-title-input-field-' + promotionNr ).val() &&
		jQuery( '#wpppfm-promotion-effective-start-date-input-field-' + promotionNr ).val() &&
		//jQuery( '#wpppfm-promotion-effective-end-date-input-field-' + promotionNr ).val() &&
		jQuery( '#wpppfm-redemption-channel-input-field-' + promotionNr ).val()
}

function wpppfm_makePromotionDatesString( startDate, endDate ) {
	return startDate + '/' + endDate;
}

function wpppfm_merchantPromotionsFeedSelected() {
	// now clear the product feed form and place the correct Review Feed elements
	wpppfm_initializeMerchantPromotionsFeedForm( wppfm_getFileNameFromForm() );
}
