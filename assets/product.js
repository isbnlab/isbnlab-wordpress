/* WooCommerce product screen: the ISBNLab book data box. */
( function () {
	'use strict';

	var config = window.isbnlabProduct;
	var input = document.getElementById( 'isbnlab-isbn' );
	var lookupButton = document.getElementById( 'isbnlab-lookup' );
	var applyButton = document.getElementById( 'isbnlab-apply' );
	var message = document.getElementById( 'isbnlab-message' );
	var preview = document.getElementById( 'isbnlab-preview' );
	var panel = document.getElementById( 'isbnlab-apply-panel' );

	if ( ! config || ! input || ! lookupButton ) {
		return;
	}

	var foundIsbn = '';

	function say( text, isError ) {
		message.textContent = text || '';
		message.classList.toggle( 'is-error', !! isError );
	}

	function post( action, extra ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		body.append( 'post_id', String( config.postId ) );
		Object.keys( extra ).forEach( function ( key ) {
			[].concat( extra[ key ] ).forEach( function ( value ) {
				body.append( key, value );
			} );
		} );

		return fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( response ) {
			return response.json();
		} );
	}

	function lookup() {
		var isbn = input.value.trim();
		if ( ! isbn ) {
			input.focus();
			return;
		}

		lookupButton.disabled = true;
		preview.hidden = true;
		panel.hidden = true;
		say( config.looking );

		post( 'isbnlab_preview', { isbn: isbn } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					say( ( json && json.data && json.data.message ) || config.failed, true );
					return;
				}
				foundIsbn = json.data.isbn;
				// The HTML is built and escaped by the server (ISBNLab_WooCommerce::render_preview).
				preview.innerHTML = json.data.html;
				preview.hidden = false;
				panel.hidden = false;
				say( '' );
			} )
			.catch( function () {
				say( config.failed, true );
			} )
			.then( function () {
				lookupButton.disabled = false;
			} );
	}

	function apply() {
		var fields = Array.prototype.map.call(
			panel.querySelectorAll( 'input[name="isbnlab_fields[]"]:checked' ),
			function ( box ) {
				return box.value;
			}
		);

		if ( ! fields.length ) {
			say( config.nothing, true );
			return;
		}

		applyButton.disabled = true;
		lookupButton.disabled = true;
		say( config.applying );

		post( 'isbnlab_apply', { isbn: foundIsbn, 'fields[]': fields } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					say( ( json && json.data && json.data.message ) || config.failed, true );
					applyButton.disabled = false;
					lookupButton.disabled = false;
					return;
				}
				// Any notes are shown as an admin notice after the reload (ISBNLab_WooCommerce::show_apply_notes).
				// The product was saved on the server; stop the editor asking about unsaved changes.
				if ( window.jQuery ) {
					window.jQuery( window ).off( 'beforeunload' );
				}
				window.onbeforeunload = null;
				var url = new URL( window.location.href );
				if ( url.pathname.indexOf( 'post-new.php' ) !== -1 ) {
					url = new URL( 'post.php', url );
					url.searchParams.set( 'post', String( config.postId ) );
					url.searchParams.set( 'action', 'edit' );
				}
				window.location.href = url.toString();
			} )
			.catch( function () {
				say( config.failed, true );
				applyButton.disabled = false;
				lookupButton.disabled = false;
			} );
	}

	lookupButton.addEventListener( 'click', lookup );
	input.addEventListener( 'keydown', function ( event ) {
		// Enter would submit the whole product form.
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			lookup();
		}
	} );
	if ( applyButton ) {
		applyButton.addEventListener( 'click', apply );
	}
} )();
