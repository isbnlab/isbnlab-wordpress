/* The ISBNLab Book block in the editor. Plain JavaScript on WordPress's own scripts, no build step. */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var blockEditor = wp.blockEditor;
	var components = wp.components;
	var ServerSideRender = wp.serverSideRender;

	function IsbnForm( props ) {
		var state = useState( props.isbn || '' );
		var value = state[ 0 ];
		var setValue = state[ 1 ];

		return el(
			'form',
			{
				className: 'isbnlab-book-form',
				onSubmit: function ( event ) {
					event.preventDefault();
					props.onSubmit( value.trim() );
				},
			},
			el( components.TextControl, {
				label: __( 'ISBN', 'isbnlab' ),
				hideLabelFromVision: true,
				placeholder: __( 'ISBN-10 or ISBN-13', 'isbnlab' ),
				value: value,
				onChange: setValue,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			el( components.Button, { variant: 'primary', type: 'submit', __next40pxDefaultSize: true }, __( 'Show book', 'isbnlab' ) )
		);
	}

	wp.blocks.registerBlockType( 'isbnlab/book', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = blockEditor.useBlockProps();

			var inspector = el(
				blockEditor.InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: __( 'Book', 'isbnlab' ) },
					el( components.TextControl, {
						label: __( 'ISBN', 'isbnlab' ),
						value: attributes.isbn,
						onChange: function ( isbn ) {
							setAttributes( { isbn: isbn.trim() } );
						},
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					} ),
					el( components.ToggleControl, {
						label: __( 'Show cover', 'isbnlab' ),
						checked: attributes.showCover,
						onChange: function ( on ) {
							setAttributes( { showCover: on } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					el( components.ToggleControl, {
						label: __( 'Show details', 'isbnlab' ),
						checked: attributes.showDetails,
						onChange: function ( on ) {
							setAttributes( { showDetails: on } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					el( components.ToggleControl, {
						label: __( 'Show description', 'isbnlab' ),
						checked: attributes.showDescription,
						onChange: function ( on ) {
							setAttributes( { showDescription: on } );
						},
						__nextHasNoMarginBottom: true,
					} )
				)
			);

			if ( ! attributes.isbn ) {
				return el(
					'div',
					blockProps,
					inspector,
					el(
						components.Placeholder,
						{
							icon: 'book-alt',
							label: __( 'ISBNLab Book', 'isbnlab' ),
							instructions: __( "Enter a book's ISBN to show its cover, title and details.", 'isbnlab' ),
						},
						el( IsbnForm, {
							isbn: attributes.isbn,
							onSubmit: function ( isbn ) {
								setAttributes( { isbn: isbn } );
							},
						} )
					)
				);
			}

			return el(
				'div',
				blockProps,
				inspector,
				el( ServerSideRender, {
					block: 'isbnlab/book',
					attributes: attributes,
					skipBlockSupportAttributes: true,
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
