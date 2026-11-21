/* The demo page's ISBN lookup, over the bundled sample books. */
( function () {
	'use strict';

	var data = window.isbnlabDemo;
	var form = document.getElementById( 'demo-lookup' );
	if ( ! data || ! form ) {
		return;
	}

	var input = form.querySelector( 'input' );
	var stage = document.getElementById( 'demo-stage' );
	var message = document.getElementById( 'demo-message' );

	function find( value ) {
		var isbn = value.replace( /[^0-9xX]/g, '' ).toUpperCase();
		return data.books.filter( function ( book ) {
			return book.isbn13 === isbn || book.isbn10 === isbn;
		} )[ 0 ];
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		node.className = className || '';
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	function show( book ) {
		var card = el( 'article', 'demo-result' );
		var cover = el( 'img', 'demo-result__cover' );
		cover.src = book.cover;
		cover.alt = 'Cover of ' + book.title;
		card.appendChild( cover );

		var body = el( 'div', 'demo-result__body' );
		body.appendChild( el( 'h2', '', book.title ) );
		body.appendChild( el( 'p', 'demo-result__author', book.authors ) );

		var list = el( 'dl' );
		book.details.forEach( function ( row ) {
			list.appendChild( el( 'dt', '', row[ 0 ] ) );
			list.appendChild( el( 'dd', '', row[ 1 ] ) );
		} );
		body.appendChild( list );

		var link = el( 'a', 'demo-button', book.url ? 'View the product' : 'Create this product' );
		link.href = book.url || data.addUrl + '&isbn=' + book.isbn13;
		body.appendChild( link );
		card.appendChild( body );

		stage.replaceChildren( card );
		stage.classList.add( 'has-result' );
	}

	function lookup( value ) {
		var book = find( value );
		message.textContent = '';
		if ( book ) {
			show( book );
		} else {
			message.textContent = 'That one is not in this demo’s 25 sample books. Pick one below, or add a free token to look up any ISBN.';
		}
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		lookup( input.value );
	} );

	document.querySelectorAll( '[data-demo-isbn]' ).forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			input.value = chip.getAttribute( 'data-demo-isbn' );
			lookup( input.value );
		} );
	} );
} )();
