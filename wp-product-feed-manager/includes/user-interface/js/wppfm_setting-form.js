/*global wppfm_setting_form_vars */
/**
 * Normalizes an Ajax "true"/"false" response string to a strict boolean.
 *
 * @param {*} response Raw response from the settings Ajax endpoint.
 * @returns {boolean} True when the response indicates an enabled setting.
 */
function wppfm_isTrueResponse( response ) {
	return 'true' === String( response ).trim().toLowerCase();
}

/**
 * Persists a checkbox setting and synchronizes the control with the saved value.
 *
 * @param {string} checkboxSelector Checkbox selector.
 * @param {Function} updater Ajax updater function.
 * @param {string} settingLabel Label used in debug logs.
 * @returns {void}
 */
function wppfm_updateCheckboxSetting( checkboxSelector, updater, settingLabel ) {
	var checkboxElement = jQuery( checkboxSelector );
	var requestedState = checkboxElement.is( ':checked' );

	updater(
		requestedState,
		function( response ) {
			var storedState = wppfm_isTrueResponse( response );
			checkboxElement.prop( 'checked', storedState );
			console.log( settingLabel + ' changed to ' + ( storedState ? 'true' : 'false' ) );
		}
	);
}

function wppfm_auto_feed_fix_changed() {
	wppfm_updateCheckboxSetting( '#wppfm-auto-feed-fix-mode', wppfm_auto_feed_fix_mode, 'Auto feed fix setting' );
}

function wppfm_background_processing_mode_changed() {
	wppfm_updateCheckboxSetting( '#wppfm-background-processing-mode', wppfm_background_processing_mode, 'Background processing setting' );
}

function wppfm_feed_logger_status_changed() {
	wppfm_updateCheckboxSetting( '#wppfm-process-logging-mode', wppfm_feed_logger_status, 'Feed process logger status' );
}

function wppfm_show_product_identifiers_changed() {
	wppfm_updateCheckboxSetting( '#wppfm-product-identifiers', wppfm_show_pi_status, 'Show Product Identifiers setting' );
}

function wppfm_manual_channel_update_changed() {
	wppfm_updateCheckboxSetting( '#wppfm-manual-channel-update', wppfm_switch_to_manual_channel_update, 'Manual Channels Update setting' );
}

function wppfm_wpml_use_full_resolution_urls_changed() {
	wppfm_updateCheckboxSetting( '#wppfm-wpml-use-full-resolution-urls', wppfm_wpml_use_full_url_resolution, 'WPML Use full resolution URLs setting' );
}

function wppfm_omit_price_filters_changed() {
	wppfm_updateCheckboxSetting( '#wppfm-omit-price-filters', wppfm_omit_price_filters, 'Omit price filters setting' );
}


function wppfm_third_party_attributes_changed() {
	var thirdPartyAttributes = wppfm_sanitizeInputString( jQuery( '#wppfm-third-party-attr-keys' ).val() );
	wppfm_change_third_party_attribute_keywords(
		thirdPartyAttributes,
		function( response ) {
			console.log( 'Third party attributes changed to ' + response );
		}
	);
}

function wppfm_notice_mailaddress_changed() {
	var newNoticeEmail = wppfm_sanitizeNoticeRecipientList( jQuery( '#wppfm-notice-mailaddress' ).val() );
	if ( newNoticeEmail ) {
		wppfm_change_notice_mailaddress(
				newNoticeEmail,
				function( data ) {
					if ( ! data ) {
						return;
					}
					if ( data.test_sent && typeof wppfm_showSuccessMessage === 'function' ) {
						wppfm_showSuccessMessage( wppfm_setting_form_vars.test_email_sent );
					} else if ( data.email && ! data.test_sent && typeof wppfm_showWarningMessage === 'function' ) {
						wppfm_showWarningMessage( wppfm_setting_form_vars.test_email_failed );
					} else if ( data.email && typeof wppfm_showSuccessMessage === 'function' ) {
						wppfm_showSuccessMessage( wppfm_setting_form_vars.notice_recipient_saved );
					}
				}
		);
	} else {
		alert( wppfm_setting_form_vars.invalid_email_address );
	}
}

/**
 * Sanitizes and validates a comma-separated list of email addresses.
 *
 * @param {string} rawRecipients Raw recipient input.
 * @returns {string} Normalized comma-separated email list or an empty string.
 */
function wppfm_sanitizeNoticeRecipientList( rawRecipients ) {
	var recipients = String( rawRecipients || '' )
		.split( ',' )
		.map(
			function( recipient ) {
				return wppfm_sanitizeEmail( recipient.trim() );
			}
		)
		.filter(
			function( recipient ) {
				return recipient !== '';
			}
		);

	var uniqueRecipients = [];

	// Keep the original order while removing duplicates.
	recipients.forEach(
		function( recipient ) {
			if ( uniqueRecipients.indexOf( recipient ) === -1 ) {
				uniqueRecipients.push( recipient );
			}
		}
	);

	return uniqueRecipients.join( ',' );
}

function wppfm_clear_feed_process() {
	wppfm_showWorkingSpinner();
	wppfm_clear_feed_process_data(
		function( response ) {
			console.log( 'Clear feed process activated: ' + response );
			wppfm_hideWorkingSpinner();
		}
	);
}

function wppfm_reinitiate() {
	wppfm_showWorkingSpinner();
	wppfm_reinitiate_plugin(
		function( response ) {
			console.log( 'Re-initialization initiated ' + response );
			wppfm_hideWorkingSpinner();
		}
	);
}

function wppfm_backup() {
	var newFileName = wppfm_sanitizeInputString( jQuery( '#wppfm-backup-file-name' ).val() );

	if ( newFileName !== '' ) {
		jQuery( '#wppfm_backup-wrapper' ).hide();

		wppfm_initiateBackup(
				newFileName,
				function( response ) {
					wppfm_resetBackupsList();

					switch( response ) {
						case 'success':
							wppfm_showSuccessMessage( 'New backup file "' + newFileName + '" stored.' );
							break;
						case 'name_exists':
							wppfm_showWarningMessage( wppfm_setting_form_vars.backup_file_name_exists );
							break;
						case 'write_protected':
							wppfm_showErrorMessage( wppfm_setting_form_vars.write_protected_file );
							break;
						default:
							wppfm_showErrorMessage( wppfm_setting_form_vars.could_not_write_file.replace( '%backup_file_name%', newFileName ) );
							break;
					}
				}
		);
	} else {
		alert( wppfm_setting_form_vars.first_enter_file_name );
	}
}

function wppfm_deleteBackupFile( fileName ) {
	var userInput = confirm( wppfm_setting_form_vars.confirm_file_deletion.replace( '%backup_file_name%', fileName ) );

	if ( userInput === true ) {
		wppfm_showWorkingSpinner();

		wppfm_deleteBackup(
			fileName,
			function( response ) {

				if ( '1' === response ) {
					wppfm_showSuccessMessage( wppfm_setting_form_vars.file_deleted.replace( '%backup_file_name%', fileName ) );
					console.log( 'Backup file deleted ' + response );
					wppfm_resetBackupsList();
				}

				wppfm_hideWorkingSpinner();
			}
		);
	}
}

function wppfm_restoreBackupFile( fileName ) {
	var userInput = confirm( wppfm_setting_form_vars.confirm_file_restoring.replace( '%backup_file_name%', fileName ) );

	if ( userInput === true ) {
		wppfm_showWorkingSpinner();

		wppfm_restoreBackup(
			fileName,
			function( response ) {

				if ( '1' === response ) {
					wppfm_showSuccessMessage( wppfm_setting_form_vars.file_restored.replace( '%backup_file_name%', fileName ) );
					console.log( 'Backup file restored ' + response );
					wppfm_resetOptionSettings();
				}

				wppfm_hideWorkingSpinner();
			}
		);
	}
}

function wppfm_duplicateBackupFile( fileName ) {

	wppfm_showWorkingSpinner();

	wppfm_duplicateBackup(
		fileName,
		function( response ) {

			if ( '1' === response ) {
				wppfm_showSuccessMessage( wppfm_setting_form_vars.file_duplicated.replace( '%backup_file_name%', fileName ) );
				console.log( 'Backup file duplicated' + response );
				wppfm_resetBackupsList();
			}

			wppfm_hideWorkingSpinner();
		}
	);
}

/**
 * Exports the backup file to the user's download folder.
 *
 * @param {string} fileName the file name.
 */
function wppfm_exportBackupFile( fileName ) {
	const dataStorageElement = jQuery( '#wppfm-settings-page-data-storage' );
	const uploadDir = dataStorageElement.data( 'wppfmWpUploadsUrl' );
	const link = document.createElement('a');

	link.href = uploadDir + '/wppfm-backups/' + fileName;
	link.download = fileName; // Suggested file name for download.
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);

	alert( wppfm_setting_form_vars.how_to_import_backup_file );

	console.log( 'Exported the backup file' + fileName );
}
