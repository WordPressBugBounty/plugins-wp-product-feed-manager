const publisherFaviconElement = jQuery('#wpprfm-publisher-favicon');

jQuery('#wpprfm-publisher-name').on(
		'focusout',
		function() {
			wppfm_mainInputChanged(false);
		},
);

jQuery('#wpprfm-aggregator-name').on(
		'focusout',
		function() {
			wppfm_setGoogleFeedTitle(jQuery('#wpprfm-aggregator-name').val());
		},
);

publisherFaviconElement.on(
		'focusout',
		function() {
			if (wpprfm_validFIconUrl(publisherFaviconElement.val())) {
				wpprfm_setPublisherFavicon(publisherFaviconElement.val());
			} else {
				publisherFaviconElement.val('');
			}
		},
);

jQuery('#wpprfm-generate-review-feed-button-top').on(
		'click',
		function() {
			wpprfm_startReviewFeedGeneration();
		},
);

jQuery('#wpprfm-generate-review-feed-button-bottom').on(
		'click',
		function() {
			wpprfm_startReviewFeedGeneration();
		},
);

jQuery('#wpprfm-save-review-feed-button-top').on(
		'click',
		function() {
			wpprfm_saveFeedData();
		},
);

jQuery('#wpprfm-save-review-feed-button-bottom').on(
		'click',
		function() {
			wpprfm_saveFeedData();
		},
);
