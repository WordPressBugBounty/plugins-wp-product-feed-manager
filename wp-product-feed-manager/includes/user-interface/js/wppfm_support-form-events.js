/* global wppfm_support_form_vars */
jQuery( function() {

	const authorizationUsername = "listing_manager";
	const authorizationPassword = "rxgbwedYS0XqF1AvkHNPbC06";
	// noinspection JSCheckFunctionSignatures
	const base64Auth = btoa(`${authorizationUsername}:${authorizationPassword}`);  // Encode to base64.
	const apiURL = "https://wpmarketingrobot.com/wp-json/fluent-crm/v2/subscribers/";

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

	/**
	 * Reacts on a click on the close button of the popup window. Closes any open popup window.
	 *
	 * @since 3.11.0 - Added the Google Shopping Checklist popup.
	 */
	jQuery( '.wppfm-popup__close-button' ).on(
			'click',
			function() {
				jQuery( '#wppfm-channel-info-popup' ).hide();
				jQuery( '#wppfm-google-shopping-checklist-popup' ).hide();
			}
	)

	/**
	 * Reacts on the checkbox event of the "Accept EULA" button on the License page.
	 */
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
		wppfm_showWorkingSpinner();

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
			url: apiURL,  // The URL of the API.
			data: params,
			headers: {
				"Authorization": "Basic " + base64Auth
			}
		})
		.done( function() {
			jQuery( '#wppfm-google-shopping-checklist-popup' ).show();
			wppfm_hideWorkingSpinner();
		})
		.fail( function( xhr ) {
			wppfm_handleSubscriberFail( xhr, email )
		});
	}

	/**
	 * Calls the FluentCRM API to get a subscriber id based on email address.
	 *
	 * @param {string} email The email address to search for.
	 *
	 * @since 3.11.0
	 */
	function wppfm_getSubscriberId( email ) {

		let params = {
			get_by_email: email
		}

		return new Promise((resolve, reject) => {
			jQuery.ajax({
				method: "GET",
				url: `${apiURL}0`,  // The URL of the API.
				data: params,
				headers: {
					"Authorization": "Basic " + base64Auth
				}
			}).done(function(result) {
				resolve( result.subscriber.id );
			}).fail(function(xhr) {
				reject(xhr);
			});
		});
	}

	/**
	 * Calls the FluentCRM API to update a subscriber tags so it includes the EBook tag.
	 *
	 * @param {number} subscriberId The subscriber id to update.
	 *
	 * @since 3.11.0
	 */
	function wppfm_updateSubscriberTagsToEBook( subscriberId ) {

		let params = {
			attach_tags: [11], // tag 11 = "Ebook".
		}

		return new Promise((resolve, reject) => {
			jQuery.ajax({
				method: "PUT",
				url: `${apiURL}${subscriberId}/`,  // The URL of the API.
				data: params,
				headers: {
					"Authorization": "Basic " + base64Auth
				}
			}).done(function(result) {
				resolve( result );
			}).fail(function(xhr) {
				reject(xhr);
			});
		});
	}

	/**
	 * Handles subscription fail message.
	 *
	 * @param {string} xhr JSON string with information about the error.
	 * @param {string} email The email address that was used for the subscription.
	 *
	 * @since 3.11.0 - Completely reworked the function to handle updating the user tags if the user already existed.
	 */
	function wppfm_handleSubscriberFail( xhr, email ) {
		let errorMessage = JSON.parse( xhr.responseText );
		let emailMessage = errorMessage.hasOwnProperty('email') ? errorMessage.email : null;

		if ( emailMessage && emailMessage.hasOwnProperty( 'unique' ) ) { // E-mail is not unique.
			// If the email is already registered, we still want to send the EBook so we need to update the tags.
			wppfm_getSubscriberId( email ).then( ( subscriberId ) => {
				wppfm_updateSubscriberTagsToEBook( subscriberId ).then( ( result ) => {
					if ( 'Subscriber successfully updated' === result.message ) {
						jQuery( '#wppfm-google-shopping-checklist-popup' ).show();
						wppfm_hideWorkingSpinner();
					} else {
						wppfm_handleSubscribeErrorMessage( result );
					}
				});
			})
			.catch( ( xhr ) => {
				wppfm_handleSubscribeErrorMessage( xhr );
			});

		} else {
			wppfm_handleSubscribeErrorMessage( xhr );
		}
	}

	/**
	 * Shows the error message on the support page.
	 *
	 * @param {string} xhr The xhr object containing the error message.
	 *
	 * @since 3.11.0
	 */
	function wppfm_handleSubscribeErrorMessage( xhr ) {
		wppfm_showErrorMessage( wppfm_support_form_vars.signup_failed )
		console.log( JSON.parse( xhr.responseText ) );
		wppfm_hideWorkingSpinner();
	}

	/**
	 * Gets the user names from the data storage element.
	 *
	 * @since 3.11.0
	 * @returns {object} containing keyed values for username, firstname and lastname.
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
