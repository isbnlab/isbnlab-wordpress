<?php
/**
 * Plugin Name: ISBNLab demo
 * Description: The WordPress Playground demo's sample books, styles and notes. Copied to mu-plugins by playground/setup.php; not part of the plugin zip.
 *
 * @package ISBNLab
 */

/**
 * The bundled sample books, by ISBN-13.
 *
 * @return array
 */
function isbnlab_demo_books() {
	static $books = null;
	if ( null === $books ) {
		$books = array();
		$rows  = json_decode( (string) file_get_contents( WP_PLUGIN_DIR . '/isbnlab/playground/books/books.json' ), true );
		foreach ( (array) $rows as $row ) {
			$books[ $row['book']['isbn13'] ] = $row['book'];
		}
	}
	return $books;
}

// The sample books work everywhere in the plugin with no token and no network.
add_filter(
	'isbnlab_pre_get_book',
	function ( $data, $isbn13 ) {
		$books = isbnlab_demo_books();
		return isset( $books[ $isbn13 ] ) ? $books[ $isbn13 ] : $data;
	},
	10,
	2
);

// The covers are already small; generating sizes in the browser would only slow the demo down.
add_filter( 'woocommerce_resize_images', '__return_false' );
add_filter( 'woocommerce_background_image_regeneration', '__return_false' );

// All the sample books on one shop page.
add_filter(
	'loop_shop_per_page',
	function () {
		return 24;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$base = plugins_url( 'isbnlab/playground/' );
		wp_enqueue_style( 'isbnlab-demo', $base . 'demo.css', array(), ISBNLAB_VERSION );
		$font = get_theme_file_uri( 'assets/fonts/literata/Literata72pt-' );
		wp_add_inline_style(
			'isbnlab-demo',
			"@font-face{font-family:'Demo Serif';font-weight:500;font-display:swap;src:url('{$font}Medium.woff2') format('woff2')}" .
			"@font-face{font-family:'Demo Serif';font-weight:500;font-style:italic;font-display:swap;src:url('{$font}MediumItalic.woff2') format('woff2')}"
		);
		if ( is_front_page() ) {
			wp_enqueue_script( 'isbnlab-demo', $base . 'demo.js', array(), ISBNLAB_VERSION, true );
			wp_add_inline_script( 'isbnlab-demo', 'window.isbnlabDemo=' . wp_json_encode( get_option( 'isbnlab_demo_index', array() ) ) . ';', 'before' );
		}
	}
);

// "Create this product" on the demo page opens the product box with the ISBN looked up.
add_action(
	'admin_footer-post-new.php',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only prefills a field.
		$isbn = isset( $_GET['isbn'] ) ? ISBNLab_Isbn::to_13( sanitize_text_field( wp_unslash( $_GET['isbn'] ) ) ) : null;
		?>
		<script>
		( function () {
			var note = document.querySelector( '.isbnlab-no-token' );
			if ( note ) {
				note.textContent = 'This demo includes 25 sample books. Add a free token in Settings > ISBNLab to look up any ISBN.';
			}
			var isbn = <?php echo wp_json_encode( $isbn ); ?>;
			var input = document.getElementById( 'isbnlab-isbn' );
			var button = document.getElementById( 'isbnlab-lookup' );
			if ( isbn && input && button ) {
				input.value = isbn;
				button.click();
				input.closest( '.postbox' ).scrollIntoView( { block: 'center' } );
			}
		} )();
		</script>
		<?php
	}
);

add_action(
	'isbnlab_after_token_field',
	function () {
		?>
		<div class="notice notice-warning inline" style="max-width: 640px;">
			<p><strong>Using a real token in this demo?</strong>
			This WordPress runs in your browser, and its requests to ISBNLab pass through WordPress Playground's CORS proxy. When you are done, regenerate the token in your ISBNLab profile so this one stops working. A free token is quick to regenerate.</p>
		</div>
		<?php
	}
);
