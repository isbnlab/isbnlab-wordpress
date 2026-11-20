<?php
/**
 * Offline tests of the classes that need no WordPress: ISBN checks and the book mapping.
 * Run with bin/test (each supported PHP version, in Docker).
 *
 * @package ISBNLab
 */

define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../includes/class-isbnlab-isbn.php';
require __DIR__ . '/../includes/class-isbnlab-book.php';

$failures = 0;
$count    = 0;

function check( $label, $expected, $actual ) {
	global $failures, $count;
	++$count;
	if ( $expected !== $actual ) {
		++$failures;
		fwrite( STDERR, "FAIL {$label}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n" );
	}
}

function fixture( $name ) {
	return new ISBNLab_Book( json_decode( file_get_contents( __DIR__ . '/fixtures/' . $name ), true ) );
}

// ISBN checks.
check( 'valid ISBN-13', true, ISBNLab_Isbn::is_valid( '9780765302304' ) );
check( 'valid ISBN-13 with hyphens', true, ISBNLab_Isbn::is_valid( '978-0-7653-0230-4' ) );
check( 'valid ISBN-10', true, ISBNLab_Isbn::is_valid( '0765302306' ) );
check( 'valid ISBN-10 ending in x', true, ISBNLab_Isbn::is_valid( '035650395x' ) );
check( 'bad checksum', false, ISBNLab_Isbn::is_valid( '9780765302305' ) );
check( 'bad ISBN-10 checksum', false, ISBNLab_Isbn::is_valid( '0765302307' ) );
check( 'EAN that is not a book', false, ISBNLab_Isbn::is_valid( '4006381333931' ) );
check( 'too short', false, ISBNLab_Isbn::is_valid( '12345' ) );
check( 'empty', false, ISBNLab_Isbn::is_valid( '' ) );
check( 'X in the middle', false, ISBNLab_Isbn::is_valid( '07653X2306' ) );
check( '10 to 13', '9780765302304', ISBNLab_Isbn::to_13( '0-7653-0230-6' ) );
check( '10 with X to 13', '9780356503950', ISBNLab_Isbn::to_13( '035650395X' ) );
check( '13 stays 13', '9780765302304', ISBNLab_Isbn::to_13( '9780765302304' ) );
check( 'invalid gives null', null, ISBNLab_Isbn::to_13( '9780765302305' ) );
check( '979 prefix', true, ISBNLab_Isbn::is_valid( '9791032305690' ) );

// A full book.
$book = fixture( 'book.json' );
check( 'title', 'The Gathering Storm', $book->title() );
check( 'full title', 'The Gathering Storm: Book Twelve of the Wheel of Time', $book->full_title() );
check( 'authors', array( 'Robert Jordan', 'Brandon Sanderson' ), $book->authors() );
check( 'publishers', array( 'Tor/Forge' ), $book->publishers() );
check( 'pages', 784, $book->pages() );
check( 'year precision', '2009', $book->publish_date() );
check( 'cover', 'https://covers.isbnlab.com/9780765302304.jpg', $book->cover_url() );
check( 'series', 'Wheel of time #12', $book->series() );
check( 'link', 'https://www.isbnlab.com/book/9780765302304/the-gathering-storm', $book->link() );
check(
	'details',
	array(
		'author'    => 'Robert Jordan, Brandon Sanderson',
		'publisher' => 'Tor/Forge',
		'published' => '2009',
		'format'    => 'Hardcover',
		'pages'     => '784',
		'language'  => 'English',
		'series'    => 'Wheel of time #12',
		'isbn13'    => '9780765302304',
		'isbn10'    => '0765302306',
	),
	$book->details()
);

$schema = $book->schema();
check( 'schema type', 'Book', $schema['@type'] );
check( 'schema isbn', '9780765302304', $schema['isbn'] );
check( 'schema format', 'https://schema.org/Hardcover', $schema['bookFormat'] );
check( 'schema authors', 2, count( $schema['author'] ) );
check( 'schema publisher', 'Tor/Forge', $schema['publisher']['name'] );
check( 'schema rating', 4.5, $schema['aggregateRating']['ratingValue'] );
check( 'schema language', 'en', $schema['inLanguage'] );

// A book with almost nothing: every getter must cope.
$sparse = fixture( 'book-sparse.json' );
check( 'sparse title', 'A Sparse Book', $sparse->title() );
check( 'sparse full title', 'A Sparse Book', $sparse->full_title() );
check( 'sparse authors', array(), $sparse->authors() );
check( 'sparse pages', 0, $sparse->pages() );
check( 'sparse cover', '', $sparse->cover_url() );
check( 'sparse date', '', $sparse->publish_date() );
check( 'sparse legacy series', 'Legacy Series Name', $sparse->series() );
check( 'sparse rating', null, $sparse->rating() );
check( 'sparse details', array( 'series' => 'Legacy Series Name', 'isbn13' => '9780000000002' ), $sparse->details() );
check( 'sparse schema keys', array( '@context', '@type', 'name', 'isbn' ), array_keys( $sparse->schema() ) );

// Free-plan response.
$free = fixture( 'book-free.json' );
check( 'free title', 'The Gathering Storm', $free->title() );

// Dates without a precision.
$day = new ISBNLab_Book( array( 'publishDate' => '2015-06-02T00:00:00+00:00' ) );
check( 'day without precision', '2015-06-02', $day->publish_date() );
$jan = new ISBNLab_Book( array( 'publishDate' => '2015-01-01T00:00:00+00:00' ) );
check( '1 January without precision', '2015', $jan->publish_date() );
$month = new ISBNLab_Book( array( 'publishDate' => '2015-06-01T00:00:00+00:00', 'publishDatePrecision' => 'month' ) );
check( 'month precision', '2015-06', $month->publish_date() );

// Only https covers and isbnlab.com links are used.
$odd = new ISBNLab_Book( array( 'covers' => array( 'http://example.com/a.jpg', 'https://covers.isbnlab.com/b.jpg' ), 'link' => 'https://evil.example/x' ) );
check( 'skips http cover', 'https://covers.isbnlab.com/b.jpg', $odd->cover_url() );
check( 'ignores foreign link', '', $odd->link() );

echo "{$count} checks, {$failures} failed (PHP " . PHP_VERSION . ")\n";
exit( $failures > 0 ? 1 : 0 );
