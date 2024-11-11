/*global wppfm_channel_manager_form_vars */
/**
 * Activates the Feed Category selector in the Category Mapping element.
 *
 * @param {string} id the id of the category
 */
function wppfm_activateFeedCategoryMapping( id ) {
	var feedSelectorElement = jQuery( '#feed-selector-' + id );
	var children            = feedSelectorElement.attr( 'data-children' ) ? JSON.parse( feedSelectorElement.attr( 'data-children' ) ) : [];

	feedSelectorElement.prop( 'checked', true );

	wppfm_activateFeedCategorySelector( id );

	for ( var i = 0; i < children.length; i ++ ) {
		wppfm_activateFeedCategorySelector( children[ i ] );
	}
}

/**
 * Activates the Feed Category Description in the Category Mapping element.
 *
 * @param {string} id the id of the category.
 */
function wppfm_activateFeedCategorySelection( id ) {
	var feedSelectorElement = jQuery( '#feed-selector-' + id );
	var children            = feedSelectorElement.attr( 'data-children' ) ? JSON.parse( feedSelectorElement.attr( 'data-children' ) ) : [];

	feedSelectorElement.prop( 'checked', true );

	_feedHolder.activateCategory( id, true );

	for ( var i = 0; i < children.length; i ++ ) {
		wppfm_activateFeedCategorySelection( children[ i ] );
	}
}

/**
 * Selects all categories in the Category Mapping table.
 */
function wppfm_activateAllFeedCategoryMapping() {
	var tableType = 0 !== document.getElementsByClassName( 'wppfm-category-mapping-selector' ).length ? 'category_mapping_table' : 'category_selection_table';
	var idCollection = 'category_mapping_table' === tableType
		? document.getElementsByClassName( 'wppfm-category-mapping-selector' ) // Category mapping table.
		: document.getElementsByClassName( 'wppfm-category-selector' ); // Category selection table.

	for ( var j = 0; j < idCollection.length; j ++ ) {
		if ( 'category_mapping_table' === tableType ) {
			wppfm_activateFeedCategorySelector(idCollection[ j ].value);
		} else {
			wppfm_activateFeedCategorySelection(idCollection[ j ].value);
		}
	}
}

/**
 * Activates the Google Analytics tracking input fields.
 *
 * @param {boolean} activate if the GA is active, default true.
 */
function wppfm_activateGoogleAnalyticsTrackingInputs( activate = true ) {
	if ( activate ) {
		jQuery( '.wppfm-google-analytics-wrapper' ).show();
		jQuery( '.wppfm-google-analytics-input-wrapper' ).show();

		var utmCampaignInputElement = jQuery( '#wppfm-google-utm-campaign' );

		if ( '' === utmCampaignInputElement.val() && '' !== _feedHolder[ 'title' ] ) {
			utmCampaignInputElement.val( _feedHolder[ 'title' ] );
			wppfm_setGoogleUtmCampaign( _feedHolder[ 'title' ] );
		}

		// Set the default values if required.
		if ( ! _feedHolder[ 'utmSource'] ) {
			jQuery( '#wppfm-google-utm-source' ).val( 'Google Shopping' );
		}

		if ( ! _feedHolder[ 'utmMedium' ] ) {
			jQuery( '#wppfm-google-utm-medium' ).val( 'CPC' );
		}

		wppfm_fillFeedHolderWithGoogleAnalyticsTrackingData();
	} else {
		jQuery( '.wppfm-google-analytics-wrapper' ).hide();
		jQuery( '.wppfm-google-analytics-input-wrapper' ).hide();
	}
}

/**
 * Activates a category selector.
 *
 * @param {string} id the id of the selector.
 */
function wppfm_activateFeedCategorySelector( id ) {
	// Some channels use your own shop's categories.
	var usesOwnCategories   = wppfm_channelUsesOwnCategories( _feedHolder[ 'channel' ] );
	var feedCategoryText    = usesOwnCategories ? 'shopCategory' : 'default';
	var feedSelectorElement = jQuery( '#feed-selector-' + id );
	var feedCategoryElement = jQuery( '#feed-category-' + id );

	// Activate the category in the feedHolder.
	_feedHolder.activateCategory( id, usesOwnCategories );

	// Get the children of this selector if any.
	var children = feedSelectorElement.attr( 'data-children' ) ? JSON.parse( feedSelectorElement.attr( 'data-children' ) ) : [];

	if ( feedCategoryElement.html() === '' ) {
		feedCategoryElement.html( wppfm_mapToDefaultCategoryElement( id, feedCategoryText ) );
	}

	feedSelectorElement.prop( 'checked', true );

	for ( var i = 0; i < children.length; i ++ ) {
		wppfm_activateFeedCategorySelector( children[ i ] );
	}
}

/**
 * Deactivates a category selector.
 *
 * @param {string} id the id of the selector.
 */
function wppfm_deactivateFeedCategorySelection( id ) {
	var feedSelectorElement = jQuery( '#feed-selector-' + id );
	var children            = feedSelectorElement.attr( 'data-children' ) ? JSON.parse( feedSelectorElement.attr( 'data-children' ) ) : [];

	feedSelectorElement.prop( 'checked', false );

	_feedHolder.deactivateCategory( id );

	for ( var i = 0; i < children.length; i ++ ) {
		wppfm_deactivateFeedCategorySelection( children[ i ] );
	}
}

/**
 * Deactivates a feed category mapping element.
 *
 * @param {string} id the id of the selector.
 */
function wppfm_deactivateFeedCategoryMapping( id ) {
	var feedSelectorElement = jQuery( '#feed-selector-' + id );

	wppfm_deactivateFeedCategorySelector( id, true );

	var children = feedSelectorElement.attr( 'data-children' ) ? JSON.parse( feedSelectorElement.attr( 'data-children' ) ) : [];

	for ( var i = 0; i < children.length; i ++ ) {
		wppfm_deactivateFeedCategorySelector( children[ i ], false );
	}
}

/**
 * Deselects all categories in the Category Mapping table.
 */
function wppfm_deactivateAllFeedCategoryMapping() {
	var idCollection = 0 !== document.getElementsByClassName( 'wppfm-category-mapping-selector' ).length
		? document.getElementsByClassName( 'wppfm-category-mapping-selector' ) // Category mapping table.
		: document.getElementsByClassName( 'wppfm-category-selector' ); // Category selection table.

	for ( var j = 0; j < idCollection.length; j ++ ) {
		wppfm_deactivateFeedCategorySelector( idCollection[j].value, false );
	}
}

/**
 * Checks if a string contains special characters.
 *
 * @param {string} string the string to check.
 * @returns {boolean} returns true if the string contains the special characters,
 * false if not.
 */
function wppfm_contains_special_characters( string ) {
	var specialChars = '%^#<>\\{}[]\/~`@?:;=&';

	for ( var i = 0; i < specialChars.length; i ++ ) {
		if ( string.indexOf( specialChars[ i ] ) > - 1 ) {
			return true;
		}
	}

	return false;
}

/**
 * Deactivates a Feed Category selector and its children.
 *
 * @param {string} id the id of the category.
 * @param {boolean} parent true if the category has a parent, false if not.
 */
function wppfm_deactivateFeedCategorySelector( id, parent ) {
	var feedSelectorElement = jQuery( '#feed-selector-' + id );

	_feedHolder.deactivateCategory( id );

	jQuery( '#feed-category-' + id ).html( '' );
	jQuery( '#category-selector-catmap-' + id ).hide();

	feedSelectorElement.prop( 'checked', false );

	if ( ! parent ) {
		var children = feedSelectorElement.attr( 'data-children' ) ? JSON.parse( feedSelectorElement.attr( 'data-children' ) ) : [];
		for ( var i = 0; i < children.length; i ++ ) {
			wppfm_deactivateFeedCategorySelector( children[ i ], false );
		}
	}
}

/**
 * Shows and hides the category sublevel selectors depending on the selected level.
 *
 * @param {string} currentLevelId the level id.
 */
function wppfm_hideSubs( currentLevelId ) {

	// Identify the level from the level id.
	var level    = currentLevelId.match( /(\d+)$/ )[ 0 ];
	var idString = currentLevelId.substring( 0, currentLevelId.length - level.length );

	// Only show subfields that are at or before the selected level. Hide the rest.
	for ( var i = 7; i > level; i -- ) {
		var categorySubLevelSelector = jQuery( '#' + idString + i );
		categorySubLevelSelector.css( 'display', 'none' );
		categorySubLevelSelector.empty();
	}
}

/**
 * Replaces special HTML characters to HTML entities.
 *
 * @param {string} text string to escape.
 * @returns {string} with the result.
 */
function wppfm_escapeHtml( text ) {
	text = text || '';
	text = text.replace( /&([^#])(?![a-z1-4]{1,8};)/gi, '&#038;$1' );
	return text.replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
}

/**
 * Sanitizes a given input string by removing invalid characters.
 *
 * @param {string} fileName the original file name to be sanitized.
 * @since 3.3.0.
 * @return {string} the sanitized file name without any invalid characters.
 */
function wppfm_sanitizeInputString( fileName ) {
	return fileName.trim().replace(/[<>:"\/\\|?*]/g, '_');
}

//noinspection DuplicatedCode
/**
 * Cleans and validates an email address.
 *
 * @param {string} email the email address to sanitize and validate.
 * @return {string|boolean} the sanitized email address if it is valid, otherwise false.
 */
function wppfm_sanitizeEmail( email ) {
	// noinspection RegExpRedundantEscape
	const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
	if (re.test(String(email).toLowerCase())) {
		return email;
	} else {
		return false;
	}
}

//noinspection DuplicatedCode
/**
 * Takes a field string from a source input string and splits it up even when a pipe character
 * is used in a combined source input string
 *
 * @since 2.3.0
 * @param {string} fieldString the field string to handle.
 * @returns {array} with the separate strings.
 */
function wppfm_splitCombinedFieldElements( fieldString ) {
	if ( ! fieldString ) {
		return [];
	}

	var reg        = /\|[0-9]/; // Pipe splitter plus a number directly after it.
	var result     = [];
	var sliceStart = 0;
	var match;

	// Fetch the separate field strings and put them in the result array.
	while (( match = reg.exec(fieldString) ) !== null) {
		var ind = match.index;
		result.push(fieldString.substring(sliceStart, ind));
		fieldString = fieldString.slice(ind + 1);
	}

	// Then add the final field string to the result array.
	result.push( fieldString );

	return result;
}

/**
 * Shows the working spinner.
 */
function wppfm_showWorkingSpinner() {
	jQuery( '#wppfm-working-spinner' ).show();
	jQuery( 'body' ).css( 'cursor', 'wait' );
}

/**
 * Hides the working spinner.
 */
function wppfm_hideWorkingSpinner() {
	jQuery( '#wppfm-working-spinner' ).hide();
	jQuery( 'body' ).css( 'cursor', 'default' );
}

/**
 * Gets a button id.
 *
 * @param {string} feedType the feed type.
 * @returns {{save: string, generate: string}}
 */
function wppfm_getButtonIds( feedType ) {
	const isSpecialType = 'google-product-review-feed' === feedType || 'google-merchant-promotions-feed' === feedType;

	if( ! isSpecialType ) {
		return {
			generate: 'wppfm-generate-feed-button',
			save: 'wppfm-save-feed-button'
		}
	}

	if ( 'google-product-review-feed' === feedType ) {
		return {
			generate: 'wpprfm-generate-review-feed-button',
			save: 'wpprfm-save-review-feed-button'
		}
	}

	if ( 'google-merchant-promotions-feed' ) {
		return {
			generate: 'wpppfm-generate-merchant-promotions-feed-button',
			save: 'wpppfm-save-merchant-promotions-feed-button'
		}
	}
}

/**
 * Enables the feeds action buttons.
 *
 * @param {string} feedType the feed type, default product-feed.
 */
function wppfm_enableFeedActionButtons( feedType = 'product-feed' ) {
	let buttonIds = wppfm_getButtonIds( feedType );

	// Enable the Generate and Save button.
	jQuery( `#${buttonIds.generate}-top` ).removeClass( 'wppfm-disabled-button' ).blur();
	jQuery( `#${buttonIds.generate}-bottom` ).removeClass( 'wppfm-disabled-button' ).blur();
	jQuery( `#${buttonIds.save}-top` ).removeClass( 'wppfm-disabled-button' ).blur();
	jQuery( `#${buttonIds.save}-bottom` ).removeClass( 'wppfm-disabled-button' ).blur();

	if ( '' !== jQuery( '#wppfm-feed-editor-page-data-storage' ).data( 'wppfmFeedUrl' ) ) {
		wppfm_enableViewFeedButtons();
	}
}

/**
 * Disables the feeds action buttons.
 *
 * @param {string} feedType the feed type, default product-feed.
 */
function wppfm_disableFeedActionButtons( feedType = 'product-feed' ) {
	let buttonIds = wppfm_getButtonIds( feedType );

	// Enable the Generate and Save button.
	jQuery( `#${buttonIds.generate}-top` ).addClass( 'wppfm-disabled-button' );
	jQuery( `#${buttonIds.generate}-bottom` ).addClass( 'wppfm-disabled-button' );
	jQuery( `#${buttonIds.save}-top` ).addClass( 'wppfm-disabled-button' );
	jQuery( `#${buttonIds.save}-bottom` ).addClass( 'wppfm-disabled-button' );

	wppfm_disableViewFeedButtons();
}

/**
 * Enables the view feed buttons.
 */
function wppfm_enableViewFeedButtons() {
	jQuery('#wppfm-view-feed-button-top').removeClass( 'wppfm-disabled-button' ).blur();
	jQuery('#wppfm-view-feed-button-bottom').removeClass( 'wppfm-disabled-button' ).blur();
}

/**
 * Disables the view feed buttons.
 */
function wppfm_disableViewFeedButtons() {
	jQuery( '#wppfm-view-feed-button-top' ).addClass( 'wppfm-disabled-button' );
	jQuery( '#wppfm-view-feed-button-bottom' ).addClass( 'wppfm-disabled-button' );
}

/**
 * Converts a date time input value in the DD-MM-YYYY hh:mm format to an ISO date time string
 *
 * @since 2.40.0
 * @param dtInputValue
 * @returns string
 */
function wppfm_convertDtInputDateTimeToIsoDateTime( dtInputValue ) {
	const dateParts = dtInputValue.split(" "); // Splits the date and time.
	const dateElements = dateParts[0].split("-"); // Splits date into day, month and year.
	const timeElements = dateParts[1].split(":"); // Splits time into hours, minutes and seconds.

	return new Date( dateElements[2], dateElements[1], dateElements[0], timeElements[0], timeElements[1] ).toISOString();
}

/**
 * Converts an ISO date time string to a date time input value in the DD-MM-YYYY hh:mm format
 *
 * @since 2.40.0
 * @param isoDateTime
 * @returns string
 */
function wppfm_convertIsoDateTimeToDtInputDateTime( isoDateTime ) {
	const date = new Date( isoDateTime );

	const day = date.getDate();
	const month = date.getMonth();
	const year = date.getFullYear();
	const hours = (date.getHours() < 10 ? '0' : '') + date.getHours();
	const minutes = (date.getMinutes() < 10 ? '0' : '') + date.getMinutes();

	return `${day}-${month}-${year} ${hours}:${minutes}`;
}

/**
 * Activates a WordPress error notice with a specific message.
 *
 * @param {string} message with the error message.
 */
function wppfm_showErrorMessage( message ) {
	console.log(message);
	wppfm_hideAdminNotices();
	var errorMessageSelector = jQuery( '#wppfm-error-message' );
	errorMessageSelector.empty();
	errorMessageSelector.append( '<p>' + message + '</p>' );
	errorMessageSelector.show();
}

/**
 * Activates a WordPress info notice with a specific message.
 *
 * @param {string} message with the info message.
 *
 * @since 3.11.0.
 */
function wppfm_showInfoMessage( message ) {
	wppfm_hideAdminNotices();
	var infoMessageSelector = jQuery( '#wppfm-info-message' );
	infoMessageSelector.empty();
	infoMessageSelector.append( '<p>' + message + '</p>' );
	infoMessageSelector.show();
}

/**
 * Activates a WordPress success notice with a specific message.
 *
 * @param {string} message with the success message.
 */
function wppfm_showSuccessMessage( message ) {
	wppfm_hideAdminNotices();
	var successMessageSelector = jQuery( '#wppfm-success-message' );
	successMessageSelector.empty();
	successMessageSelector.append( '<p>' + message + '</p>' );
	successMessageSelector.show();
}

/**
 * Activates a WordPress warning notice with a specific message.
 *
 * @param {string} message with the warning message.
 */
function wppfm_showWarningMessage( message ) {
	wppfm_hideAdminNotices();
	var warningMessageSelector = jQuery( '#wppfm-warning-message' );
	warningMessageSelector.empty();
	warningMessageSelector.append( '<p>' + message + '</p>' );
	warningMessageSelector.show();
}

/**
 * Hides all plugin related admin notices.
 *
 * @since 3.11.0.
 */
function wppfm_hideAdminNotices() {
	jQuery( '#wppfm-error-message' ).hide();
	jQuery( '#wppfm-info-message' ).hide();
	jQuery( '#wppfm-success-message' ).hide();
	jQuery( '#wppfm-warning-message' ).hide();
}

/**
 * Fills and then shows the channel info popup
 *
 * @since 3.4.0
 * @param channel_short_name
 */
function wppfm_showChannelInfoPopup( channel_short_name ) {
	var channelInfoDataElement = jQuery( '#wppfm-' + channel_short_name + '-channel-data' );
	var name = channelInfoDataElement.data( 'channel-name' );
	var status = channelInfoDataElement.data( 'status' );
	var installedVersion = channelInfoDataElement.data( 'installed-version' );
	var version = channelInfoDataElement.data( 'version' );
	var infoLink = channelInfoDataElement.data( 'info-link' );
	var specificationsLink = channelInfoDataElement.data( 'specifications-link' );

	var installedVersionElement = jQuery( '#wppfm-channel-info-popup__installed-version' );
	var infoLinkElement = jQuery( '#wppfm-channel-info-popup__info-link' );
	var specificationsLinkElement = jQuery( '#wppfm-channel-info-popup__feed-specifications-link' );

	jQuery( '#wppfm-channel-info-popup__name' ).html( name );
	jQuery( '#wppfm-channel-info-popup__status' ).html( 'Status: ' + status );

	if ( 'installed' === status ) {
		installedVersionElement.html('Installed version: ' + installedVersion);
		installedVersionElement.show();
	} else {
		installedVersionElement.hide();
	}

	jQuery( '#wppfm-channel-info-popup__latest-version' ).html( 'Latest version: ' + version );

	if ( '' !== infoLink ) {
		infoLinkElement.html( '<a id="wppfm-channel-info-popup-feed-info-link" href="' + infoLink + '" target="_blank">More about selling on this channel</a>' );
		infoLinkElement.show();
	} else {
		infoLinkElement.hide();

	}

	if ( '' !== specificationsLink ) {
		specificationsLinkElement.html( '<a id="wppfm-channel-info-popup-feed-specifications-link" href="' + specificationsLink + '" target="_blank">Channels feed specifications</a>' );
		specificationsLinkElement.show();
	} else {
		specificationsLinkElement.hide();
	}

	jQuery( '#wppfm-channel-info-popup' ).show();
}