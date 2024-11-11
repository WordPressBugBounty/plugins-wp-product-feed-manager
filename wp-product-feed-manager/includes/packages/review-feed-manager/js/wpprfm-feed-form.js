function wpprfm_initializeProductReviewFeedForm( productReviewFileName ) {
	// Clear the previous form.
	jQuery( '#wppfm-main-input-map' ).empty();

	wppfm_showWorkingSpinner();

	// Now add the correct elements for a product review feed.
	window.location = encodeURI( window.location.href + '&feed-type=google-product-review-feed&feed-name=' + productReviewFileName );
}

/**
 * Shows the category selector, product filter, and attribute mapping wrappers.
 */
function wpprfm_showCategoryAndAttributeMappingWrappers() {
	jQuery( '#category-map' ).show();

	// Show the attribute mapping.
	jQuery( '#fields-form' ).show();
}

/**
 * Hides the category selector, product filter, and attribute mapping wrappers.
 */
function wpprfm_hideCategoryAndAttributeMappingWrappers() {
	jQuery( '#category-map' ).hide();

	jQuery( '#wppfm-main-product-filter-wrapper' ).hide();

	// Show the attribute mapping.
	jQuery( '#fields-form' ).hide();
}

/**
 * Fills the fields on an edit feed form for an existing Google Review Feed
 */
function wpprfm_fillFeedFields( feedData ) {
	var isNew =_feedHolder[ 'feedId' ] === - 1;
	var schedule = feedData[ 'updateSchedule' ] ? feedData[ 'updateSchedule' ].split( ':' ) : [];

	wppfm_setMerchantSelector( isNew, _feedHolder[ 'channel' ] );
	wppfm_setGoogleFeedTypeSelector( isNew, _feedHolder[ 'feedType' ]);

	jQuery( '#wppfm-feed-file-name' ).val( feedData[ 'title' ] );
	jQuery( '#wpprfm-aggregator-name' ).val( feedData[ 'aggregatorName' ] );
	jQuery( '#wpprfm-publisher-name' ).val( feedData[ 'publisherName' ] );
	jQuery( '#wpprfm-publisher-favicon' ).val( feedData[ 'publisherFavicon' ] );
	jQuery( '#days-interval' ).val( schedule[ 0 ] );

	// Get the link to the update schedule selectors.
	var hrsSelector     = document.getElementById( 'update-schedule-hours' );
	var minutesSelector = document.getElementById( 'update-schedule-minutes' );
	var freqSelector    = document.getElementById( 'update-schedule-frequency' );

	// Set the values of the update schedule selectors.
	hrsSelector.value     = schedule[ 1 ];
	minutesSelector.value = schedule[ 2 ];
	freqSelector.value    = schedule[ 3 ] ? schedule[ 3 ] : '1'; // standard setting is once a day

	// Set the layout of the update schedule selectors.
	wppfm_setScheduleSelector( schedule[ 0 ], schedule[ 3 ] );
}

function wpprfm_validFIconUrl( publisherIconUrlValue ) {
	if ( '' === publisherIconUrlValue ) {
		return true;
	}

	if ( wppfm_validateUrl( publisherIconUrlValue ) ) {
		return true;
	} else {
		//noinspection JSUnresolvedVariable
		alert( wppfm_feed_settings_form_vars.invalid_url );
		return false;
	}
}

function wpprfm_fillSourcesList( customFields ) {
	_inputFields = wppfm_woocommerceSourceOptions();
	wppfm_addCustomFieldsToInputFields( _inputFields, customFields );

	_inputFields.sort( function( a, b ) {
			return (
				       '' + a.label
			       ).toUpperCase() < (
				       '' + b.label
			       ).toUpperCase() ? - 1 : 1;
		}
	);
}

/**
 * Gets the data from the feed data holder element that is stored in the feed HTML code and uses it to make a new _reviewFeedHolder that
 * will be used to fill the Review Feed form.
 */
function wpprfm_initiateReviewFeed() {
	var feedData = jQuery("#wppfm-feed-editor-page-data-storage").data("wppfmFeedData");

	if ( ! feedData ) { return; }

	// Make a new _reviewFeedHolder.
	if (feedData) {
		_reviewFeedHolder = new Feed(
			feedData[ 'feed_id' ],
			feedData[ 'feed_file_name' ],
			0,
			0,
			1,
			'',
			feedData[ 'category_mapping' ],
			feedData[ 'url' ],
			'1',
			'US', // Dummy data.
			'',
			'',
			feedData[ 'feed_title' ],
			feedData[ 'feed_description' ],
			feedData[ 'schedule' ],
			[],
			feedData[ 'status_id' ],
			'2'
		);

		// Add the Google Review Feed specific properties to the Feed object.
		_reviewFeedHolder[ 'aggregatorName' ] = feedData['aggregator_name'];
		_reviewFeedHolder[ 'publisherName' ] = feedData['publisher_name'];
		_reviewFeedHolder[ 'publisherFavicon' ] = feedData['publisher_favicon_url'];

		// Update the _feedHolder variable in the wppfm_feed-form.js file.
		wppfm_constructNewSpecialFeed( _reviewFeedHolder );

		wpprfm_setDefaultReviewFeedAttributes( JSON.parse( feedData[ 'attribute_data' ] ),
			feedData[ 'channel_id' ], 1);

		_reviewFeedHolder.setFeedFilter(feedData[ 'feed_filter' ]);

		_reviewFeedHolder[ 'sourceFields' ] = feedData[ 'source_fields' ];

		console.log( _reviewFeedHolder );
	}
}

/**
 * Saves the feed data to the db and starts the feed generation process.
 */
function wpprfm_initiateSaveAndGenerateReviewFeed() {

	wppfm_showWorkingSpinner();
	wppfm_disableFeedActionButtons( 'google-product-review-feed' );

	// Save the feed data to the database.
	wppfm_saveFeedToDb( _reviewFeedHolder, function( dbResult ) {

		var newFeed = _reviewFeedHolder[ 'feedId' ] === - 1;

		wpprfm_handleSaveReviewFeedToDbResult( dbResult, newFeed );

		// Convert the data to XML and save the code to a feed file.
		wppfm_updateFeedFile( _reviewFeedHolder[ 'feedId' ], function( xmlResult ) {

			wppfm_handleUpdateFeedFileActionResult( xmlResult );
			wppfm_hideWorkingSpinner();
		} );
	} );
}

/**
 * Handles the callback from saving the Review Feed data to the db.
 *
 * @param   {string|int}    dbResult    The callback result. Should contain the id of the feed.
 * @param   {boolean}       newFeed     Indicates if this is a new feed or an existing feed.
 */
function wpprfm_handleSaveReviewFeedToDbResult( dbResult, newFeed ) {

	// The wppfm_saveFeedToDb returns the entered feed id.
	if ( 0 === dbResult || '0' === dbResult ) {
		wppfm_handleSaveFeedToDbFailedAction();
	} else {

		// Insert the feed id in the _feed.
		_reviewFeedHolder[ 'feedId' ] = dbResult;

		if ( newFeed ) {
			// Reset the url to implement the feed id so the user can reset the form if he wants.
			wppfm_resetUrlForNewFeed( _reviewFeedHolder[ 'feedId' ], 'google-product-review-feed' );
			wppfm_storeFeedUrlInSourceData( _reviewFeedHolder[ 'url' ] );
		}
	}
}

/**
 * hook the document actions
 */
jQuery(function() {
	var feedId   = wppfm_getUrlParameter( 'id' );
	var feedName = wppfm_getUrlParameter( 'feed-name' );
	var feedType    = wppfm_getUrlParameter( 'feed-type' );

	if ( 'google-product-review-feed' !== feedType ) {
		return;
	}

	wppfm_showWorkingSpinner();

	if ( '' !== feedId ) {
		wpprfm_initiateReviewFeed();
		wpprfm_editExistingReviewFeed( feedId );
	} else if ( '' !== feedName && '' !== feedType ) { // New feed.
		jQuery( '#wppfm-feed-file-name' ).val( feedName );
		wppfm_setMerchantSelector( false, '1' );
		wppfm_setGoogleFeedTypeSelector( false, '2' );
	}

	jQuery( '#wppfm-feed-types-list-row').show();
	jQuery( '#wppfm-feed-types-selector').prop( 'disabled', false ).val( '2' );

	wppfm_hideWorkingSpinner()
} );
