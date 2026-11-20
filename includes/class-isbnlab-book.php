<?php
/**
 * A book lookup response, flattened into the plain values the plugin shows and saves.
 *
 * @package ISBNLab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of one API book. Every getter copes with missing or null fields, since many
 * books have only some of them. No WordPress functions, so it can be tested on its own.
 */
final class ISBNLab_Book {

	/**
	 * The decoded JSON response.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param array $data Decoded response of GET /api/book/isbn/{isbn}.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * The decoded response, unchanged.
	 *
	 * @return array
	 */
	public function raw() {
		return $this->data;
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return $this->string( 'title' );
	}

	/**
	 * Subtitle.
	 *
	 * @return string
	 */
	public function subtitle() {
		return $this->string( 'subtitle' );
	}

	/**
	 * Title and subtitle joined with a colon, or just the title.
	 *
	 * @return string
	 */
	public function full_title() {
		$subtitle = $this->subtitle();

		return '' === $subtitle ? $this->title() : $this->title() . ': ' . $subtitle;
	}

	/**
	 * ISBN-13.
	 *
	 * @return string
	 */
	public function isbn13() {
		return $this->string( 'isbn13' );
	}

	/**
	 * ISBN-10.
	 *
	 * @return string
	 */
	public function isbn10() {
		return $this->string( 'isbn10' );
	}

	/**
	 * Description, as plain text with line breaks.
	 *
	 * @return string
	 */
	public function description() {
		return $this->string( 'description' );
	}

	/**
	 * Author names.
	 *
	 * @return string[]
	 */
	public function authors() {
		return $this->names( 'authors' );
	}

	/**
	 * Publisher names.
	 *
	 * @return string[]
	 */
	public function publishers() {
		return $this->names( 'publishers' );
	}

	/**
	 * BISAC genre names, such as "FICTION / Fantasy / Epic".
	 *
	 * @return string[]
	 */
	public function genres() {
		return $this->names( 'genres' );
	}

	/**
	 * Subject names.
	 *
	 * @return string[]
	 */
	public function subjects() {
		return $this->names( 'subjects' );
	}

	/**
	 * Page count, or 0 when unknown.
	 *
	 * @return int
	 */
	public function pages() {
		return isset( $this->data['numberOfPages'] ) ? (int) $this->data['numberOfPages'] : 0;
	}

	/**
	 * Format, such as "Hardcover".
	 *
	 * @return string
	 */
	public function format() {
		return $this->string( 'physicalFormat' );
	}

	/**
	 * Language name, such as "English".
	 *
	 * @return string
	 */
	public function language() {
		return $this->string( 'language' );
	}

	/**
	 * Two-letter language code, such as "en".
	 *
	 * @return string
	 */
	public function language_code() {
		return $this->string( 'languageCode' );
	}

	/**
	 * Publication date as Y-m-d, Y-m or Y, as precise as the API knows it, or '' when unknown.
	 *
	 * @return string
	 */
	public function publish_date() {
		$date = $this->string( 'publishDate' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $date, $m ) ) {
			return '';
		}

		switch ( $this->string( 'publishDatePrecision' ) ) {
			case 'year':
				return $m[1];
			case 'month':
				return $m[1] . '-' . $m[2];
			case 'day':
				return $m[1] . '-' . $m[2] . '-' . $m[3];
		}

		// Without a precision, a 1 January date is usually just a year.
		return ( '01' === $m[2] && '01' === $m[3] ) ? $m[1] : $m[1] . '-' . $m[2] . '-' . $m[3];
	}

	/**
	 * Year of publication, or '' when unknown.
	 *
	 * @return string
	 */
	public function publish_year() {
		return substr( $this->publish_date(), 0, 4 );
	}

	/**
	 * URL of the first cover image, or ''.
	 *
	 * @return string
	 */
	public function cover_url() {
		$covers = isset( $this->data['covers'] ) && is_array( $this->data['covers'] ) ? $this->data['covers'] : array();
		foreach ( $covers as $url ) {
			if ( is_string( $url ) && 0 === strpos( $url, 'https://' ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Series name and number, such as "Wheel of time #12", or ''.
	 *
	 * @return string
	 */
	public function series() {
		$series = isset( $this->data['series'] ) ? $this->data['series'] : null;

		if ( is_array( $series ) && isset( $series['name'] ) && is_string( $series['name'] ) ) {
			$number = isset( $series['number'] ) && is_numeric( $series['number'] ) ? ' #' . $series['number'] : '';

			return $series['name'] . $number;
		}

		// Some books still carry a plain list of series names.
		if ( is_array( $series ) && isset( $series[0] ) && is_string( $series[0] ) ) {
			return $series[0];
		}

		return '';
	}

	/**
	 * Average rating out of 5 and the number of ratings, or null when there are none.
	 *
	 * @return array{average: float, count: int}|null
	 */
	public function rating() {
		$summary = isset( $this->data['ratings']['summary'] ) ? $this->data['ratings']['summary'] : null;

		if ( ! is_array( $summary ) || empty( $summary['count'] ) || ! isset( $summary['average'] ) ) {
			return null;
		}

		return array(
			'average' => (float) $summary['average'],
			'count'   => (int) $summary['count'],
		);
	}

	/**
	 * The book's page on isbnlab.com, or ''.
	 *
	 * @return string
	 */
	public function link() {
		$link = $this->string( 'link' );

		return 0 === strpos( $link, 'https://www.isbnlab.com/' ) ? $link : '';
	}

	/**
	 * The fields shown in the import preview and saved as product attributes, in display
	 * order: label => value. Empty values are left out.
	 *
	 * @return array<string, string>
	 */
	public function details() {
		$details = array(
			'author'    => implode( ', ', $this->authors() ),
			'publisher' => implode( ', ', $this->publishers() ),
			'published' => $this->publish_date(),
			'format'    => $this->format(),
			'pages'     => $this->pages() > 0 ? (string) $this->pages() : '',
			'language'  => $this->language(),
			'series'    => $this->series(),
			'isbn13'    => $this->isbn13(),
			'isbn10'    => $this->isbn10(),
		);

		return array_filter(
			$details,
			function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * Schema.org Book data for JSON-LD.
	 *
	 * @return array
	 */
	public function schema() {
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Book',
			'name'     => $this->title(),
		);

		$optional = array(
			'alternativeHeadline' => $this->subtitle(),
			'isbn'                => $this->isbn13(),
			'numberOfPages'       => $this->pages() > 0 ? $this->pages() : '',
			'bookFormat'          => $this->schema_format(),
			'inLanguage'          => $this->language_code(),
			'datePublished'       => $this->publish_date(),
			'image'               => $this->cover_url(),
			'description'         => $this->description(),
			'url'                 => $this->link(),
		);

		foreach ( $optional as $key => $value ) {
			if ( '' !== $value ) {
				$schema[ $key ] = $value;
			}
		}

		$people = array();
		foreach ( $this->authors() as $name ) {
			$people[] = array(
				'@type' => 'Person',
				'name'  => $name,
			);
		}
		if ( $people ) {
			$schema['author'] = $people;
		}

		$publishers = $this->publishers();
		if ( $publishers ) {
			$schema['publisher'] = array(
				'@type' => 'Organization',
				'name'  => $publishers[0],
			);
		}

		$rating = $this->rating();
		if ( $rating ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating['average'],
				'ratingCount' => $rating['count'],
				'bestRating'  => 5,
			);
		}

		return $schema;
	}

	/**
	 * Schema.org BookFormatType for the format, or ''.
	 *
	 * @return string
	 */
	private function schema_format() {
		$format = strtolower( $this->format() );

		if ( '' === $format ) {
			return '';
		}
		if ( false !== strpos( $format, 'hardcover' ) || false !== strpos( $format, 'hardback' ) ) {
			return 'https://schema.org/Hardcover';
		}
		if ( false !== strpos( $format, 'paperback' ) || false !== strpos( $format, 'mass market' ) ) {
			return 'https://schema.org/Paperback';
		}
		if ( false !== strpos( $format, 'audio' ) || false !== strpos( $format, 'mp3' ) ) {
			return 'https://schema.org/AudiobookFormat';
		}
		if ( false !== strpos( $format, 'ebook' ) || false !== strpos( $format, 'kindle' ) || false !== strpos( $format, 'epub' ) ) {
			return 'https://schema.org/EBook';
		}

		return '';
	}

	/**
	 * A top-level string field, or ''.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	private function string( $key ) {
		if ( ! isset( $this->data[ $key ] ) || ! is_scalar( $this->data[ $key ] ) ) {
			return '';
		}

		return trim( (string) $this->data[ $key ] );
	}

	/**
	 * The names in a list of linked records.
	 *
	 * @param string $key Field name.
	 * @return string[]
	 */
	private function names( $key ) {
		$names = array();
		$list  = isset( $this->data[ $key ] ) && is_array( $this->data[ $key ] ) ? $this->data[ $key ] : array();

		foreach ( $list as $item ) {
			if ( is_array( $item ) && isset( $item['name'] ) && is_string( $item['name'] ) && '' !== trim( $item['name'] ) ) {
				$names[] = trim( $item['name'] );
			}
		}

		return array_values( array_unique( $names ) );
	}
}
