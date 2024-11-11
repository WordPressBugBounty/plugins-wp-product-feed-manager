( function() {
	'use strict'

	const onPageReady = function() {
		wppfm_initialActivateSelect2SourceSelectors();
	}

	/**
	 * Activates Select2 source selectors.
	 *
	 * This function activates Select2 (ref: select2.org) on all the select elements whose class contains "wppfm-select2-selector".
	 *
	 * @return {void}
	 */
	function wppfm_initialActivateSelect2SourceSelectors() {
		wppfm_activateSelect2SourceSelectors()
	}

	jQuery( document ).ready( onPageReady );
} )()