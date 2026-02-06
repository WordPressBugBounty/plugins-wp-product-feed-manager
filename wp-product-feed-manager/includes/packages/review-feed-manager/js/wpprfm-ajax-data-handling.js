var wppfm_review_ajax;

function wpprfm_getFeedAttributes( feedId, callback ) {

	jQuery.post(
	wppfm_review_ajax.ajaxurl,
		{
			action: 'wppfm-rf-ajax-get-product-review-feed-attributes',
			feedId: feedId,
			reviewFeedGetAttributesNonce: wppfm_review_ajax.reviewFeedGetAttributesNonce,
		},
		function( response ) {

			callback( wppfm_validateResponse( response ) );
		}
	);
}
