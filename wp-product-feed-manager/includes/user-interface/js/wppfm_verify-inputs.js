function wppfm_sourceIsFilled( rowId, sourceLevel, conditionLevel ) {
	return 'select' !== jQuery( '#input-field-cntrl-' + rowId + '-' + sourceLevel + '-' + conditionLevel ).val();
}

function wppfm_changeValueIsFilled( rowId, sourceLevel, conditionLevel ) {
	var result              = false;
	var changeSelectorValue = jQuery( '#value-options-' + rowId + '-' + sourceLevel + '-' + conditionLevel ).val();
	var changeOptionsValue  = jQuery( '#value-options-input-' + rowId + '-' + sourceLevel + '-' + conditionLevel ).val();

	// @since 2.34.0.
	// The "change nothing", "strip tags" and "html entity decode" selections have no value to check.
	if ( '0' === changeSelectorValue || '8' === changeSelectorValue || '9' === changeSelectorValue ) {
		return true;
	}

	if ( changeOptionsValue ) {
		if ( '2' === changeSelectorValue ) { // replace
			result = ! ! jQuery( '#value-options-input-with-' + rowId + '-' + sourceLevel + '-' + conditionLevel ).val();
		} else {
			result = true;
		}
	}

	return result;
}

function wppfm_queryIsFilled( rowId, sourceLevel, queryLevel ) {
	var identString              = rowId + '-' + sourceLevel + '-' + queryLevel;
	var result                   = false;
	var querySourceSelectorValue = jQuery( '#value-options-input-field-cntrl-' + identString ).val();
	var querySelectorValue       = jQuery( '#value-query-condition-' + identString + '-0' ).val();
	var queryValue               = jQuery( '#value-options-condition-value-' + identString ).val();

	if ( sourceLevel < 0 ) {
		return true;  // there is no previous query so accept
	}

	if ( 'select' !== querySourceSelectorValue ) {
		if ( '4' !== querySelectorValue && '5' !== querySelectorValue ) {
			if ( '14' === querySelectorValue ) {
				result = ! ! (
					jQuery( '#value-options-condition-and-value-input-' + identString ).val() && queryValue
				);
			} else {
				result = ! ! queryValue;
			}
		} else {
			result = true;
		}
	}

	return result;
}

function wppfm_feedFilterIsFilled( feedId, filterLevel ) {
	var identifierString         = feedId + '-' + filterLevel;
	var result                   = false;
	var querySourceSelectorValue = jQuery( '#filter-source-control-' + identifierString ).val();
	var querySelectorValue       = jQuery( '#filter-options-control-' + identifierString ).val();
	var queryValue               = jQuery( '#filter-input-control-' + identifierString + '-1' ).val();

	if ( filterLevel < 0 ) {
		return true; // there is no previous filter so accept
	}

	if ( 'select' !== querySourceSelectorValue ) {
		if ( '4' !== querySelectorValue && '5' !== querySelectorValue ) {
			if ( '14' === querySelectorValue ) {
				result = ! ! (
					jQuery( '#filter-input-control-' + identifierString + '-2' ).val() && queryValue
				);
			} else {
				result = ! ! queryValue;
			}
		} else {
			result = true;
		}
	}

	return result;
}
