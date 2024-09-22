jQuery( function() {

	jQuery( '#wpppfm-generate-merchant-promotions-feed-button-bottom').on(
		'click',
		function() {
			wpppfm_startPromotionsFeedGeneration();
		}
	);

	jQuery( '#wpppfm-save-merchant-promotions-feed-button-bottom').on(
		'click',
		function() {
			wpppfm_savePromotionsFeed();
		}
	);
} );
