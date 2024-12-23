const fileNameElement  = jQuery( '#wppfm-feed-file-name' );
const merchantsElement = jQuery( '#wppfm-merchants-selector' );
const googleFeedType   = jQuery( '#wppfm-feed-types-selector' );
const drmFeedType      = jQuery( '#wppfm-feed-drm-types-selector' );
const countriesElement = jQuery( '#wppfm-countries-selector' );
const level0Element    = jQuery( '#lvl_0' );

// monitor the four main feed settings and react when they change
fileNameElement.on(
	'focusout',
	function() {
		if ( '' !== fileNameElement.val() ) {
			// If nothing changed, exit without doing anything
			const dataStorageElement = jQuery( '#wppfm-feed-editor-page-data-storage' );
			let currentFileName = dataStorageElement.data( 'wppfmFeedData' ).feed_file_name;
			if ( fileNameElement.val() === currentFileName ) {
				wppfm_enableFeedActionButtons()
				return;
			} else {
				dataStorageElement.data( 'wppfmFeedData' ).feed_file_name = fileNameElement.val();
			}

			googleFeedType.prop( 'disabled', false );
			countriesElement.prop( 'disabled', false );
			level0Element.prop( 'disabled', false );
			if ( false === wppfm_validateFileName( fileNameElement.val() ) ) {
				fileNameElement.val( currentFileName );
			}

			if ( '0' !== merchantsElement.val() || '' !== jQuery( '#selected-merchant' ).text() ) {
				wppfm_showChannelInputs( merchantsElement.val(), true );
				wppfm_mainInputChanged( false );
			} else {
				wppfm_hideFeedFormMainInputs();
			}
		} else {
			googleFeedType.prop( 'disabled', true );
			countriesElement.prop( 'disabled', true );
			level0Element.prop( 'disabled', true );
		}
	}
);

fileNameElement.on(
	'keyup',
	function() {
		wppfm_disableFeedActionButtons();

		if ( '' !== fileNameElement.val() ) {
			googleFeedType.prop( 'disabled', false );
			countriesElement.prop( 'disabled', false );
			level0Element.prop( 'disabled', false );
		} else {
			googleFeedType.prop( 'disabled', true );
			countriesElement.prop( 'disabled', true );
			level0Element.prop( 'disabled', true );
		}
	}
);

merchantsElement.on(
	'change',
	function() {
		if ( '0' !== merchantsElement.val() && '' !== fileNameElement.val() ) {
			wppfm_showChannelInputs( merchantsElement.val(), true );
			wppfm_mainInputChanged( false );
		} else {
			wppfm_hideFeedFormMainInputs();
		}
	}
);

googleFeedType.on(
	'change',
	function() {
		const selectedGoogleFeedType = googleFeedType.val();
		wppfm_setGoogleFeedType( selectedGoogleFeedType );
		const currentFeedTypeForm = wppfm_getUrlParameter( 'feed-type' )

		if ( '1' === selectedGoogleFeedType && 'product-feed' === currentFeedTypeForm ) {
			wppfm_mainInputChanged( false );
		} else {
			wppfm_handleSupportFeedSelection(selectedGoogleFeedType);
		}
	}
)

drmFeedType.on(
	'change',
	function() {
		const selectedDrmFeedType = drmFeedType.val();
		wppfm_setDrmFeedTypeAttributes( selectedDrmFeedType );
		wppfm_setDrmBusinessType( selectedDrmFeedType );
	}
)

countriesElement.on(
	'change',
	function() {
		if ( '0' !== countriesElement.val() ) {
			level0Element.prop( 'disabled', false );
		}

		wppfm_mainInputChanged( false );
	}
);

jQuery( '#wppfm-feed-language-selector' ).on(
	'change',
	function() {
		wppfm_setGoogleFeedLanguage( jQuery( '#wppfm-feed-language-selector' ).val() );

		if ( wppfm_requiresLanguageInput ) {
			wppfm_mainInputChanged( false );
		}
	}
);

jQuery( '#wppfm-feed-currency-selector' ).on(
	'change',
	function() {
		wppfm_setGoogleFeedCurrency( jQuery( '#wppfm-feed-currency-selector' ).val() );
	}
);

jQuery( '#google-feed-title-selector' ).on(
	'change',
	function() {
		wppfm_setGoogleFeedTitle( jQuery( '#google-feed-title-selector' ).val() );
	}
);

jQuery( '#google-feed-description-selector' ).on(
	'change',
	function() {
		wppfm_setGoogleFeedDescription( jQuery( '#google-feed-description-selector' ).val() );
	}
);

jQuery( '#variations' ).on(
	'change',
	function() {
		wppfm_variationSelectionChanged();
	}
);

jQuery( '#aggregator' ).on(
	'change',
	function() {
		wppfm_aggregatorChanged();
		wppfm_drawAttributeMappingSection(); // reset the attribute mapping
	}
);

level0Element.on(
	'change',
	function() {
		wppfm_mainInputChanged( true );
	}
);

jQuery( '.wppfm-cat-selector' ).on(
	'change',
	function() {
		wppfm_nextCategory( this.id );
	}
);

jQuery( '#wppfm-google-utm-source' ).on(
		'change',
		function() {
			wppfm_setGoogleUtmSource( jQuery( '#wppfm-google-utm-source' ).val() );
		}
);

jQuery( '#wppfm-google-utm-medium' ).on(
		'change',
		function() {
			wppfm_setGoogleUtmMedium( jQuery( '#wppfm-google-utm-medium' ).val() );
		}
);

jQuery( '#wppfm-google-utm-id' ).on(
		'change',
		function() {
			wppfm_setGoogleUtmId( jQuery( '#wppfm-google-utm-id' ).val() );
		}
);

jQuery( '#wppfm-google-utm-campaign' ).on(
		'change',
		function() {
			wppfm_setGoogleUtmCampaign( jQuery( '#wppfm-google-utm-campaign' ).val() );
		}
);

jQuery( '#wppfm-google-utm-source-platform' ).on(
		'change',
		function() {
			wppfm_setGoogleUtmSourcePlatform( jQuery( '#wppfm-google-utm-source-platform' ).val() );
		}
);

jQuery( '#wppfm-google-utm-term' ).on(
		'change',
		function() {
			wppfm_setGoogleUtmTerm( jQuery( '#wppfm-google-utm-term' ).val() );
		}
);

jQuery( '#wppfm-google-utm-content' ).on(
		'change',
		function() {
			wppfm_setGoogleUtmContent( jQuery( '#wppfm-google-utm-content' ).val() );
		}
);

jQuery( '#wppfm-generate-feed-button-top' ).on(
	'click',
	function() {
		wppfm_generateFeed();
	}
);

jQuery( '#wppfm-generate-feed-button-bottom' ).on(
	'click',
	function() {
		wppfm_generateFeed();
	}
);

jQuery( '#wppfm-save-feed-button-top' ).on(
	'click',
	function() {
		wppfm_saveFeedData();
	}
);

jQuery( '#wppfm-view-feed-button-top' ).on(
	'click',
	function() {
		wppfm_viewFeed( jQuery( '#wppfm-feed-editor-page-data-storage' ).data( 'wppfmFeedUrl' ) );
	}
);

jQuery( '#wppfm-view-feed-button-bottom' ).on(
	'click',
	function() {
		wppfm_viewFeed( jQuery( '#wppfm-feed-editor-page-data-storage' ).data( 'wppfmFeedUrl' ) );
	}
);

jQuery( '#wppfm-save-feed-button-bottom' ).on(
	'click',
	function() {
		wppfm_saveFeedData();
	}
);

jQuery( '#days-interval' ).on(
	'change',
	function() {
		wppfm_saveUpdateSchedule();
	}
);

jQuery( '#update-schedule-hours' ).on(
	'change',
	function() {
		wppfm_saveUpdateSchedule();
	}
);

jQuery( '#update-schedule-minutes' ).on(
	'change',
	function() {
		wppfm_saveUpdateSchedule();
	}
);

jQuery( '#update-schedule-frequency' ).on(
	'change',
	function() {
		wppfm_saveUpdateSchedule();
	}
);

jQuery( '#wppfm-auto-feed-fix-mode' ).on(
	'change',
	function() {
		wppfm_auto_feed_fix_changed();
	}
);

jQuery( '#wppfm-background-processing-mode' ).on(
	'change',
	function() {
		wppfm_clear_feed_process();
		wppfm_background_processing_mode_changed();
	}
);

jQuery( '#wppfm-process-logging-mode' ).on(
	'change',
	function() {
		wppfm_feed_logger_status_changed();
	}
);

jQuery( '#wppfm-product-identifiers' ).on(
	'change',
	function() {
		wppfm_show_product_identifiers_changed();
	}
);

jQuery( '#wppfm-manual-channel-update' ).on(
	'change',
	function() {
		wppfm_manual_channel_update_changed();
	}
);

jQuery( '#wppfm-wpml-use-full-resolution-urls' ).on(
	'change',
	function() {
		wppfm_wpml_use_full_resolution_urls_changed();
	}
)

jQuery( '#wppfm-omit-price-filters' ).on(
	'change',
	function() {
		wppfm_omit_price_filters_changed();
	}
);

jQuery( '#wppfm-third-party-attr-keys' ).on(
	'focusout',
	function() {
		wppfm_third_party_attributes_changed();
	}
);

jQuery( '#wppfm-notice-mailaddress' ).on(
	'focusout',
	function() {
		wppfm_notice_mailaddress_changed();
	}
);

jQuery( '#wppfm-clear-feed-process-button' ).on(
	'click',
	function() {
		wppfm_clear_feed_process();
	}
);

jQuery( '#wppfm-reinitiate-plugin-button' ).on(
	'click',
	function() {
		wppfm_reinitiate();
	}
);

jQuery( '.wppfm-category-mapping-selector' ).on( // on activation of a category selector in the Category Mapping table
	'change',
	function() {
		if ( jQuery( this ).is( ':checked' ) ) {
			console.log( 'category ' + jQuery( this ).val() + ' selected' );
			wppfm_activateFeedCategoryMapping( jQuery( this ).val() );
		} else {
			console.log( 'category ' + jQuery( this ).val() + ' deselected' );
			wppfm_deactivateFeedCategoryMapping( jQuery( this ).val() );
		}
	}
);

jQuery( '.wppfm-category-selector' ).on( // on activation of a category selector in the Category Selector table
	'change',
	function() {
		if ( jQuery( this ).is( ':checked' ) ) {
			console.log( 'category ' + jQuery( this ).val() + ' selected' );
			wppfm_activateFeedCategorySelection( jQuery( this ).val() );
		} else {
			console.log( 'category ' + jQuery( this ).val() + ' deselected' );
			wppfm_deactivateFeedCategorySelection( jQuery( this ).val() );
		}
	}
);

jQuery( '#wppfm-categories-select-all' ).on( // on activation of the 'all' selector in the Category Mapping and Category Selector table
	'change',
	function() {
		if ( jQuery( this ).is( ':checked' ) ) {
			wppfm_activateAllFeedCategoryMapping();
		} else {
			wppfm_deactivateAllFeedCategoryMapping();
		}
	}
);

jQuery( '#wppfm-google-analytics' ).on(
		'change',
		function() {
		var show = false;
		wppfm_googleAnalyticsSelectionChanged();

		if ( jQuery( this ).is( ':checked' ) ) {
			show = true;
		}

		wppfm_activateGoogleAnalyticsTrackingInputs( show );
	}
);

jQuery( '#wppfm-accept-eula' ).on(
	'change',
	function() {
		if ( jQuery( this ).is( ':checked' ) ) {
			jQuery( '#wppfm-license-activate' ).prop( 'disabled', false );
		} else {
			jQuery( '#wppfm-license-activate' ).prop( 'disabled', true );
		}
	}
);

//jQuery( '.edit-output' ).click( function () { wppfm_editOutput( this.id ); } ); TODO: Check this out later. The this.id should get the id of the link but it doesn't.

jQuery( '#wppfm-prepare-backup' ).on(
	'click',
	function() {
		jQuery( '#wppfm-backup-file-name' ).val( '' );
		jQuery( '#wppfm-backup-wrapper' ).show();
	}
);

jQuery( '#wppfm-make-backup-button' ).on(
	'click',
	function() {
		jQuery( '#wppfm-backup-wrapper' ).hide();
		wppfm_backup();
	}
);

jQuery( '#wppfm-cancel-backup-button' ).on(
	'click',
	function() {
		jQuery( '#wppfm-backup-wrapper' ).hide();
	}
);

jQuery( '#wppfm-backup-file-name' ).on(
	'keyup',
	function() {
		if ( '' !== jQuery( '#wppfm-backup-file-name' ).val ) {
			jQuery( '#wppfm-make-backup-button' ).attr( 'disabled', false );
		}
	}
);

