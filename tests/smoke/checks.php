<?php
/**
 * Run by bin/smoke-test with `wp eval-file` inside a WordPress with WooCommerce and the fake API.
 *
 * @package ISBNLab
 */

$failures = 0;

$check = function ( $label, $ok ) use ( &$failures ) {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		++$failures;
	}
};

update_option( 'isbnlab_fake_calls', 0 );

// Settings.
$check( 'no token gives an error', is_wp_error( ( new ISBNLab_Client( '' ) )->get_book( '9780765302304' ) ) );
$check( 'wrong token gives unauthorized', 'isbnlab_unauthorized' === ( new ISBNLab_Client( 'wrong', 0 ) )->get_book( '9780765302304' )->get_error_code() );
update_option( 'isbnlab_settings', ISBNLab_Settings::sanitize( array( 'token' => 'smoke-token', 'cache_hours' => '24' ) ) );
$check( 'token saved', 'smoke-token' === ISBNLab_Settings::token() );
update_option( 'isbnlab_settings', ISBNLab_Settings::sanitize( array( 'token' => '', 'cache_hours' => '12' ) ) );
$check( 'empty token field keeps the token', 'smoke-token' === ISBNLab_Settings::token() && 12 === ISBNLab_Settings::cache_hours() );
$check( 'test connection', true === ISBNLab_Client::from_settings()->test_connection() );

// Client and cache.
delete_transient( 'isbnlab_book_9780765302304' );
update_option( 'isbnlab_fake_calls', 0 );
$client = ISBNLab_Client::from_settings();
$book   = $client->get_book( '0-7653-0230-6' );
$check( 'lookup by ISBN-10', $book instanceof ISBNLab_Book && 'The Gathering Storm' === $book->title() );
$client->get_book( '9780765302304' );
$check( 'second lookup is cached', 1 === (int) get_option( 'isbnlab_fake_calls' ) );
$check( 'invalid ISBN never calls the API', 'isbnlab_invalid_isbn' === $client->get_book( '123' )->get_error_code() && 1 === (int) get_option( 'isbnlab_fake_calls' ) );
$check( 'unknown ISBN is not found', 'isbnlab_not_found' === $client->get_book( '9781234567897' )->get_error_code() );
$client->get_book( '9781234567897' );
$check( 'not found is cached too', 2 === (int) get_option( 'isbnlab_fake_calls' ) );

// WooCommerce product.
$product = new WC_Product_Simple();
$product->set_name( 'placeholder' );
$product->set_status( 'auto-draft' );
$product->set_attributes(
	array(
		'condition' => ( function () {
			$a = new WC_Product_Attribute();
			$a->set_name( 'Condition' );
			$a->set_options( array( 'Used' ) );
			$a->set_visible( true );
			return $a;
		} )(),
	)
);
$product->save();

$notes   = ISBNLab_WooCommerce::apply( $product, $book, array_keys( array_filter( wp_list_pluck( ISBNLab_WooCommerce::fields(), 'default' ) ) ) );
$product = wc_get_product( $product->get_id() );
$names   = array_map(
	function ( $a ) {
		return $a->get_name();
	},
	array_values( $product->get_attributes() )
);

$check( 'apply: no notes', array() === $notes );
$check( 'apply: name', 'The Gathering Storm' === $product->get_name() );
$check( 'apply: description', false !== strpos( $product->get_description(), 'Tarmon Gai' ) );
$check( 'apply: auto-draft becomes draft', 'draft' === $product->get_status() );
$check( 'apply: own attribute kept', in_array( 'Condition', $names, true ) );
$check( 'apply: book attributes', in_array( 'Author', $names, true ) && in_array( 'Pages', $names, true ) );
$check( 'apply: author value', 'Robert Jordan, Brandon Sanderson' === $product->get_attribute( 'Author' ) );
$check( 'apply: GTIN', ! method_exists( $product, 'get_global_unique_id' ) || '9780765302304' === $product->get_global_unique_id() );
$check( 'apply: SKU untouched by default', '' === $product->get_sku() );
$check( 'apply: cover is the product image', $product->get_image_id() > 0 );
$image_id = $product->get_image_id();

ISBNLab_WooCommerce::apply( $product, $book, array( 'title', 'subtitle', 'cover', 'attributes', 'sku' ) );
$product = wc_get_product( $product->get_id() );
$check( 'reapply: subtitle in name', 'The Gathering Storm: Book Twelve of the Wheel of Time' === $product->get_name() );
$check( 'reapply: cover reused, not downloaded again', $image_id === $product->get_image_id() );
$check( 'reapply: no duplicate attributes', count( $names ) === count( $product->get_attributes() ) );
$check( 'reapply: SKU', '9780765302304' === $product->get_sku() );

$other = new WC_Product_Simple();
$other->set_name( 'other' );
$other->save();
$notes = ISBNLab_WooCommerce::apply( $other, $book, array( 'sku' ) );
$check( 'duplicate SKU is reported, not fatal', 1 === count( $notes ) );

// Shortcode and block.
$html = do_shortcode( '[isbnlab_book isbn="9780765302304"]' );
$check( 'shortcode: card', false !== strpos( $html, 'class="isbnlab-book"' ) && false !== strpos( $html, 'The Gathering Storm' ) );
$check( 'shortcode: JSON-LD', false !== strpos( $html, '"@type":"Book"' ) );
$check( 'shortcode: no credit link by default', false === strpos( $html, 'Book data from ISBNLab' ) );
$check( 'shortcode: options', false === strpos( do_shortcode( '[isbnlab_book isbn="9780765302304" cover="no" description="no"]' ), 'isbnlab-book__cover' ) );
$check( 'shortcode: visitors see nothing on error', '' === do_shortcode( '[isbnlab_book isbn="9781234567897"]' ) );
wp_set_current_user( 1 );
$check( 'shortcode: editors see the error', false !== strpos( do_shortcode( '[isbnlab_book isbn="9781234567897"]' ), 'No book with that ISBN' ) );
wp_set_current_user( 0 );

$block = do_blocks( '<!-- wp:isbnlab/book {"isbn":"9780000000002","showDescription":false} /-->' );
$check( 'block: sparse book renders', false !== strpos( $block, 'A Sparse Book' ) && false !== strpos( $block, 'wp-block-isbnlab-book' ) );
$check( 'block: registered', WP_Block_Type_Registry::get_instance()->is_registered( 'isbnlab/book' ) );

// Publish a page for the HTTP checks in bin/smoke-test.
$page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Smoke',
		'post_name'    => 'smoke',
		'post_content' => '<!-- wp:isbnlab/book {"isbn":"9780765302304"} /-->' . "\n\n[isbnlab_book isbn=\"0765302306\" details=\"no\"]",
	)
);
$check( 'page created', $page > 0 );

echo $failures ? "{$failures} failed\n" : "all passed\n";
if ( $failures ) {
	exit( 1 );
}
