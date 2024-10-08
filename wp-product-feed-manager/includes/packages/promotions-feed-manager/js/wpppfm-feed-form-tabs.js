// Reference wp-content/plugins/woocommerce/assets/js/admin/meta-boxes.js then Tabbed panels for more info.
jQuery(function() {

	jQuery( document.body )
	.on( 'wppfm-init-tabbed-panels', function () {
		jQuery( 'ul.wppfm-tabs' ).show();

		jQuery( 'ul.wppfm-tabs a' ).on( 'click', function ( e ) {
			e.preventDefault();
			var panel_wrap = jQuery( this ).closest( 'div.panel-wrap' );
			jQuery( 'ul.wppfm-tabs li', panel_wrap ).removeClass( 'active' );
			jQuery( this ).parent().addClass( 'active' );
			jQuery( 'div.wppfm-panel', panel_wrap ).hide();
			jQuery( jQuery( this ).attr( 'href' ) ).show();
		} );
		jQuery( 'div.panel-wrap' ).each( function () {
			// noinspection JSCheckFunctionSignatures
			jQuery( this ).find( 'ul.wppfm-tabs li' ).eq( 0 ).find( 'a' ).trigger( 'click' );
		} );

	} )
	.trigger( 'wppfm-init-tabbed-panels' );

});
