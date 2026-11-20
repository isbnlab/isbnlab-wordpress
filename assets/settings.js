/* Settings > ISBNLab: the Test connection button. */
( function () {
	'use strict';

	var button = document.getElementById( 'isbnlab-test-connection' );
	var result = document.getElementById( 'isbnlab-test-result' );
	var config = window.isbnlabSettings;

	if ( ! button || ! result || ! config ) {
		return;
	}

	button.addEventListener( 'click', function () {
		var body = new URLSearchParams();
		body.append( 'action', 'isbnlab_test_connection' );
		body.append( 'nonce', config.nonce );

		button.disabled = true;
		result.textContent = config.testing;

		fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				result.textContent = ( json && json.data && json.data.message ) || config.failed;
				result.style.color = json && json.success ? '#00a32a' : '#d63638';
			} )
			.catch( function () {
				result.textContent = config.failed;
				result.style.color = '#d63638';
			} )
			.then( function () {
				button.disabled = false;
			} );
	} );
} )();
