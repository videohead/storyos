( function () {
	'use strict';

	const config = window.worldgraphDescriptSettings;
	const unsyncButton = document.querySelector( '[data-worldgraph-descript-confirm-unsync]' );

	if ( ! config || ! unsyncButton ) {
		return;
	}

	unsyncButton.addEventListener( 'click', function ( event ) {
		if ( ! window.confirm( config.i18n.confirmUnsync ) ) {
			event.preventDefault();
		}
	} );
}() );
