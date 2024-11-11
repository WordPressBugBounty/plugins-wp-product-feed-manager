/* global wppfm_support_form_vars */
jQuery( function() {

	/**
	 * Reacts on the click button event of the "Sign Up Now" button on the Support page.
	 *
	 * Sends a given email to the subscribers (Lead) list on the WpMarketingRobot.com webserver.
	 */
	jQuery( '#wppfm-sign-up-button' ).on(
		'click',
		function() {
			let email = jQuery( '#wppfm-sign-up-mail-input' ).val();
			const wpUserName = wppfm_getWpUserNames();

			email = wppfm_sanitizeEmail( email );

			if ( ! email ) {
				//noinspection JSUnresolvedVariable
				wppfm_showErrorMessage( wppfm_support_form_vars.email_not_valid );
				return;
			}

			wppfm_sendSubscriber( wpUserName, email );
		}
	);

	jQuery( '.wppfm-popup__close-button' ).on(
			'click',
			function() {
				jQuery( '#wppfm-channel-info-popup' ).hide();
			}
	)

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

	/**
	 * Collects the correct user data
	 * and sends a subscription request to wpmarketingrobot.com.
	 *
	 * @param {object} wpUserName WordPress user names.
	 * @param {string} email      eMail the user entered.
	 *
	 * @since 3.11.0 - Added the first name, last name and Ebook tag to the registration.
	 */
	function wppfm_sendSubscriber( wpUserName, email ) {
		const authorizationUsername = "listing_manager";
		const authorizationPassword = "rxgbwedYS0XqF1AvkHNPbC06";
		// noinspection JSCheckFunctionSignatures
		const base64Auth = btoa(`${authorizationUsername}:${authorizationPassword}`);  // Encode to base64.

		let params = {
			first_name: wpUserName.firstName,
			last_name: wpUserName.lastName,
			email: email,
			status: 'subscribed',
			tags: [1,11], // tag 1 = "Google Manager Free", tag 11 = "Ebook".
			lists: [6] // list 6 = "Lead".
		}

		jQuery.ajax({
			method: "POST",
			url: "https://wpmarketingrobot.com/wp-json/fluent-crm/v2/subscribers/",  // The URL of the API.
			data: params,
			headers: {
				"Authorization": "Basic " + base64Auth
			}
		})
		.done( function() {
			//noinspection JSUnresolvedVariable
			wppfm_showSuccessMessage( wppfm_support_form_vars.chopping_checklist_send )
		})
		.fail( function( xhr ) {
			wppfm_handleSubscriberError( xhr )
		});
	}

	/**
	 * Handles subscription errors.
	 *
	 * @param {string} xhr JSON string with information about the error.
	 */
	function wppfm_handleSubscriberError( xhr ) {
		let errorMessage = JSON.parse( xhr.responseText );
		let emailMessage = errorMessage.hasOwnProperty('email') ? errorMessage.email : null;

		if ( emailMessage && emailMessage.hasOwnProperty( 'unique' ) ) { // E-mail is not unique.
			//noinspection JSUnresolvedVariable
			wppfm_showInfoMessage( wppfm_support_form_vars.email_already_registered )
		} else {
			//noinspection JSUnresolvedVariable
			wppfm_showErrorMessage( wppfm_support_form_vars.signup_failed )
			console.log( errorMessage );
		}
	}

	/**
	 * Gets the user names from the data storage element.
	 *
	 * @since 3.11.0
	 * @returns {object} containing keyed values for username,
	 * firstname and lastname.
	 */
	function wppfm_getWpUserNames() {
		const dataStorageElement = jQuery( '#wppfm-support-page-data-storage' );

		const wpUserName = dataStorageElement.data( 'wppfmUsername' );
		const firstName = dataStorageElement.data( 'wppfmUserFistName' ) || wpUserName;
		const lastName = dataStorageElement.data( 'wppfmUserLastName' ) || wpUserName;

		return {
			username: wpUserName,
			firstName: firstName,
			lastName: lastName
		}
	}
});
