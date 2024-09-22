var ajax_object;

( function() {

	jQuery( '#wppfm-dismiss-promotion-notice, #wppfm-dismiss-promotion-notice-link' ).on(
			'click',
			function() {
				jQuery( 'div#wppfm-discount-promotion-notice' ).slideUp();

				jQuery.post(
						ajax_object.ajax_url,
						{
							action: 'myajax-cancel-promotion-notice',
						},
						function( response ) {
							console.log(response);
						}
				);
			})

} )();
