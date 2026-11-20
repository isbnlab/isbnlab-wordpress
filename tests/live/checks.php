<?php
/**
 * Run by bin/live-test with `wp eval-file`: the plugin against the real API. The token comes
 * from the ISBNLAB_API_TOKEN environment variable and is never saved.
 *
 * @package ISBNLab
 */

$failures = 0;

$check = function ( $label, $ok, $detail = '' ) use ( &$failures ) {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . ( '' !== $detail ? "  ({$detail})" : '' ) . "\n";
	if ( ! $ok ) {
		++$failures;
	}
};

$token = (string) getenv( 'ISBNLAB_API_TOKEN' );
if ( '' === $token ) {
	echo "Set ISBNLAB_API_TOKEN.\n";
	exit( 1 );
}

// Hand the token to the plugin for this run only, through the option filter, so it is not stored.
// Both filters, since the option_ one only runs once the option exists.
$with_token = function ( $value ) use ( $token ) {
	$value          = is_array( $value ) ? $value : array();
	$value['token'] = $token;
	return $value;
};
add_filter( 'option_' . ISBNLab_Settings::OPTION, $with_token );
add_filter( 'default_option_' . ISBNLab_Settings::OPTION, $with_token );

$err = function ( $result ) {
	return is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '';
};

$client = new ISBNLab_Client( $token, 0 );

$result = $client->test_connection();
$check( 'test connection', true === $result, $err( $result ) );

$bad = ( new ISBNLab_Client( 'ilp_not_a_real_token', 0 ) )->get_book( '9780765302304' );
$check( 'wrong token is unauthorized', is_wp_error( $bad ) && 'isbnlab_unauthorized' === $bad->get_error_code(), $err( $bad ) );

$book = $client->get_book( '0765302306' );
$check( 'lookup by ISBN-10', $book instanceof ISBNLab_Book, $err( $book ) );
if ( ! $book instanceof ISBNLab_Book ) {
	exit( 1 );
}
$check( 'title', 'The Gathering Storm' === $book->title(), $book->title() );
$check( 'authors', in_array( 'Robert Jordan', $book->authors(), true ), implode( ', ', $book->authors() ) );
$check( 'isbn13', '9780765302304' === $book->isbn13() );
$check( 'cover url', 0 === strpos( $book->cover_url(), 'https://' ), $book->cover_url() );
$check( 'link', '' !== $book->link(), $book->link() );
echo '     details: ' . wp_json_encode( $book->details(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";

foreach ( array( '9780439708180', '9780143127741', '9781250301697' ) as $isbn ) {
	$other = $client->get_book( $isbn );
	$check( "lookup {$isbn}", $other instanceof ISBNLab_Book && '' !== $other->title(), $other instanceof ISBNLab_Book ? $other->full_title() . ' / ' . implode( ', ', $other->authors() ) : $err( $other ) );
}

$missing = $client->get_book( '9790000000001' );
$check( 'unknown ISBN is not found', is_wp_error( $missing ) && 'isbnlab_not_found' === $missing->get_error_code(), $err( $missing ) );

// A real product, with the real cover download.
$product = new WC_Product_Simple();
$product->set_name( 'live placeholder' );
$product->save();
$notes   = ISBNLab_WooCommerce::apply( $product, $book, array( 'title', 'description', 'cover', 'attributes', 'gtin' ) );
$product = wc_get_product( $product->get_id() );
$check( 'apply: no notes', array() === $notes, implode( ' | ', $notes ) );
$check( 'apply: name', 'The Gathering Storm' === $product->get_name() );
$check( 'apply: description', '' !== $product->get_description() );
$check( 'apply: cover downloaded', $product->get_image_id() > 0 );
if ( $product->get_image_id() ) {
	$file = get_attached_file( $product->get_image_id() );
	$size = $file && file_exists( $file ) ? getimagesize( $file ) : false;
	$check( 'apply: cover is an image', is_array( $size ), $size ? $size[0] . 'x' . $size[1] . ' ' . $size['mime'] : '' );
}
$check( 'apply: GTIN', '9780765302304' === $product->get_global_unique_id() );

// As an editor, so an error would show in the output rather than render nothing.
wp_set_current_user( 1 );
$html = do_shortcode( '[isbnlab_book isbn="9780765302304"]' );
$check( 'shortcode renders', false !== strpos( $html, 'isbnlab-book__title' ), wp_strip_all_tags( substr( $html, 0, 200 ) ) );

echo $failures ? "{$failures} failed\n" : "all passed\n";
exit( $failures ? 1 : 0 );
