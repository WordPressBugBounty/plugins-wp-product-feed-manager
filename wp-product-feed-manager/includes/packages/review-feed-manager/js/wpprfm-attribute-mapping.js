var _reviewFeedHolder;

function wpprfm_setDefaultReviewFeedAttributes( attributes ) {
	var defaultAttributeSettings = wpprfm_defaultAttributeSettings();
	var attributeId              = 0;

	_reviewFeedHolder.clearAllAttributes();

	for ( var attribute in attributes ) {

		var attributeTitle = attributes[ attribute ][ 'field_label' ];
		var active         = true;

		// deactivate if this attribute is not required and has no value
		if ( parseInt( attributes[ attribute ][ 'category_id' ] ) > 2 && ( '' === attributes[ attribute ][ 'value' ] || undefined === attributes[ attribute ][ 'value' ] ) ) {
			active = false;
		}

		if ( ! attributes[ attribute ][ 'value' ] ) {
			attributes[ attribute ][ 'value' ] = wpprfm_setPresetAttributes(attributeTitle);
		}

		_reviewFeedHolder.addAttribute( attributeId, attributeTitle, defaultAttributeSettings[ attributeTitle ], attributes[ attribute ][ 'value' ], attributes[ attribute ][ 'category_id' ], active, 0, 0, 0 );

		attributeId++;
	}
}

/**
 * Returns an array with advised (default) sources for the attributes.
 * This function should return the same result as the php wpprfm_get_woocommerce_to_review_feed_inputs() function
 *
 * @returns {object} default sources
 */
function wpprfm_defaultAttributeSettings() {
	return {
		'reviewer_name': 'comment_author',
		'review_timestamp': 'comment_date',
		'content': 'comment_content',
		'review_url': 'comment_url',
		'ratings_overall': 'rating',
		'ratings_overall_min': 'comment_rating_min',
		'ratings_overall_max': 'comment_rating_max',
		'product_url': 'permalink',
		'product_name': 'post_title',
		'reviewer_id': 'user_id',
		'reviewer_image_url': 'comment_author_url',
	};
}

/**
 * Returns a string with preset attributes.
 *
 * @param {string} attributeTitle
 * @returns {string} containing the correct attribute setting
 */
function wpprfm_setPresetAttributes( attributeTitle ) {
	switch( attributeTitle ) {
		case 'ratings_overall_min':
			return '{"m":[{"s":{"static":"0"}}]}';

		case 'ratings_overall_max':
			return '{"m":[{"s":{"static":"5"}}]}';

		case 'is_spam':
			return '{"m":[{"s":{"static":"false"}}]}';

		default:
			break;
	}
}
