let _totalNumberOfProductsForTheFeed = 0;

function wppfm_initiateProgressBar( nrProductsForFeed ) {
	if ( 100 > nrProductsForFeed ) { // Only show the bar when there are more than 100 products to process.
		return;
	}

	_totalNumberOfProductsForTheFeed = nrProductsForFeed;
	const innerProgressBar = jQuery('#wppfm-progress-bar__bar');
	innerProgressBar.css( 'width', '5%' );
	innerProgressBar.css( 'background-color', '#536936' );
	jQuery('#wppfm-progress-bar').show();
}

function wppfm_updateProgressBar( nrOfProductsProcessed ) {
	const progressPercentage = (nrOfProductsProcessed / _totalNumberOfProductsForTheFeed) * 100;
  jQuery('#wppfm-progress-bar__bar').css('width', `${progressPercentage}%`);
}

function wppfm_closeProgressBar() {
	jQuery('#wppfm-progress-bar').hide();
}

function wppfm_switchToAlertProgressBar() {
	jQuery('#wppfm-progress-bar__bar').css( 'background-color', '#ee4951' );
}