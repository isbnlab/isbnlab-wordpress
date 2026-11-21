<?php
/**
 * ISBNLab API client on the WordPress HTTP API, with results cached in transients.
 *
 * @package ISBNLab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calls https://www.isbnlab.com/api. Uses wp_remote_get() rather than bundling an HTTP library,
 * so it cannot clash with another plugin's copy of one.
 */
final class ISBNLab_Client {

	const BASE_URL = 'https://www.isbnlab.com';

	/**
	 * The book that works without a token, from the public EXAMPLE_URL.
	 */
	const EXAMPLE_ISBN = '9780765302304';

	/**
	 * Public sample of the API's book format. Needs no token.
	 */
	const EXAMPLE_URL = 'https://www.isbnlab.com/book/example/9780765302304/the-gathering-storm-by-robert-jordan.json';

	/**
	 * How long a "not found" answer is remembered, so a missing ISBN on a busy page is not
	 * looked up on every view.
	 */
	const NOT_FOUND_TTL = HOUR_IN_SECONDS;

	/**
	 * The longest Retry-After, in seconds, that a request waits out before trying once more.
	 */
	const MAX_RETRY_WAIT = 2;

	/**
	 * API token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Seconds to cache a book; 0 turns caching off.
	 *
	 * @var int
	 */
	private $cache_ttl;

	/**
	 * Constructor.
	 *
	 * @param string $token     API token.
	 * @param int    $cache_ttl Seconds to cache a book.
	 */
	public function __construct( $token, $cache_ttl = DAY_IN_SECONDS ) {
		$this->token     = (string) $token;
		$this->cache_ttl = max( 0, (int) $cache_ttl );
	}

	/**
	 * A client set up from the plugin's settings.
	 *
	 * @return self
	 */
	public static function from_settings() {
		return new self( ISBNLab_Settings::token(), ISBNLab_Settings::cache_hours() * HOUR_IN_SECONDS );
	}

	/**
	 * Look up a book by ISBN-10 or ISBN-13.
	 *
	 * @param string $isbn       Any formatting; hyphens and spaces are ignored.
	 * @param bool   $skip_cache Ask the API even when a cached copy exists.
	 * @return ISBNLab_Book|WP_Error
	 */
	public function get_book( $isbn, $skip_cache = false ) {
		$isbn13 = ISBNLab_Isbn::to_13( $isbn );
		if ( null === $isbn13 ) {
			return new WP_Error( 'isbnlab_invalid_isbn', __( 'That is not a valid ISBN-10 or ISBN-13.', 'isbnlab' ) );
		}

		/**
		 * Supplies a book without calling the API, such as from your own catalogue.
		 *
		 * @param array|null $data   Book data in the API's format, or null to look it up as usual.
		 * @param string     $isbn13 The ISBN-13.
		 */
		$supplied = apply_filters( 'isbnlab_pre_get_book', null, $isbn13 );
		if ( is_array( $supplied ) ) {
			return new ISBNLab_Book( $supplied );
		}

		// Without a token, the public example book still works, so the plugin can be tried first.
		if ( '' === $this->token ) {
			if ( self::EXAMPLE_ISBN === $isbn13 ) {
				return self::example_book();
			}

			return new WP_Error(
				'isbnlab_no_token',
				sprintf(
					/* translators: %s: the example ISBN. */
					__( 'Add your free ISBNLab API token under Settings > ISBNLab to look up any book. Until then, try the example ISBN %s.', 'isbnlab' ),
					'978-0-7653-0230-4'
				)
			);
		}

		$cache_key = 'isbnlab_book_' . $isbn13;

		if ( ! $skip_cache && $this->cache_ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return isset( $cached['not_found'] ) ? $this->not_found() : new ISBNLab_Book( $cached );
			}
		}

		$data = $this->request( '/api/book/isbn/' . $isbn13 );

		if ( is_wp_error( $data ) ) {
			if ( 'isbnlab_not_found' === $data->get_error_code() && $this->cache_ttl > 0 ) {
				set_transient( $cache_key, array( 'not_found' => true ), min( self::NOT_FOUND_TTL, $this->cache_ttl ) );
			}

			return $data;
		}

		if ( $this->cache_ttl > 0 ) {
			set_transient( $cache_key, $data, $this->cache_ttl );
		}

		return new ISBNLab_Book( $data );
	}

	/**
	 * The public example book, fetched with no token and cached for a day.
	 *
	 * @return ISBNLab_Book|WP_Error
	 */
	public static function example_book() {
		$cached = get_transient( 'isbnlab_book_example' );
		if ( is_array( $cached ) ) {
			return new ISBNLab_Book( $cached );
		}

		$response = wp_remote_get(
			self::EXAMPLE_URL,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => array( 'Accept' => 'application/json' ),
				'user-agent'  => 'isbnlab-wordpress/' . ISBNLAB_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
			)
		);

		$data = is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response )
			? null
			: json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['isbn13'] ) || self::EXAMPLE_ISBN !== $data['isbn13'] ) {
			return new WP_Error(
				'isbnlab_example_unavailable',
				__( 'The example book could not be loaded from ISBNLab. Try again, or add your free API token under Settings > ISBNLab.', 'isbnlab' )
			);
		}

		set_transient( 'isbnlab_book_example', $data, DAY_IN_SECONDS );

		return new ISBNLab_Book( $data );
	}

	/**
	 * Check the token with one ISBN check call.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		$result = $this->request( '/api/isbn/isbn-lookup/9780765302304' );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * GET a path and decode the JSON body.
	 *
	 * @param string $path Path starting with /api/.
	 * @return array|WP_Error
	 */
	private function request( $path ) {
		$result = $this->request_once( $path );

		// The API also limits calls per second. A page with several uncached books can hit that,
		// so wait out a short Retry-After once rather than show nothing.
		if ( is_wp_error( $result ) && 'isbnlab_rate_limited' === $result->get_error_code() ) {
			$data  = $result->get_error_data();
			$retry = isset( $data['retry_after'] ) ? (int) $data['retry_after'] : 0;

			if ( $retry > 0 && $retry <= self::MAX_RETRY_WAIT ) {
				sleep( $retry );
				$result = $this->request_once( $path );
			}
		}

		return $result;
	}

	/**
	 * One GET request, without the retry.
	 *
	 * @param string $path Path starting with /api/.
	 * @return array|WP_Error
	 */
	private function request_once( $path ) {
		if ( '' === $this->token ) {
			return new WP_Error(
				'isbnlab_no_token',
				__( 'Add your ISBNLab API token under Settings > ISBNLab.', 'isbnlab' )
			);
		}

		$response = wp_remote_get(
			self::BASE_URL . $path,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => array(
					'Authorization'                        => 'Bearer ' . $this->token,
					'Accept'                               => 'application/json',
					// Only for WordPress Playground, where requests can go through its CORS proxy,
					// which drops the Authorization header unless asked to keep it. Other
					// servers ignore it.
					'X-Cors-Proxy-Allowed-Request-Headers' => 'Authorization',
				),
				'user-agent'  => 'isbnlab-wordpress/' . ISBNLAB_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'isbnlab_http',
				/* translators: %s: the network error message. */
				sprintf( __( 'Could not reach ISBNLab: %s', 'isbnlab' ), $response->get_error_message() )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			return $this->error_for_status( $status, $response );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'isbnlab_bad_response', __( 'ISBNLab sent a response the plugin could not read.', 'isbnlab' ) );
		}

		return $data;
	}

	/**
	 * A readable error for a non-200 response.
	 *
	 * @param int   $status   HTTP status.
	 * @param array $response Response from wp_remote_get().
	 * @return WP_Error
	 */
	private function error_for_status( $status, $response ) {
		switch ( $status ) {
			case 401:
				return new WP_Error( 'isbnlab_unauthorized', __( 'ISBNLab did not accept the API token. Check it under Settings > ISBNLab.', 'isbnlab' ), array( 'status' => $status ) );
			case 403:
				return new WP_Error( 'isbnlab_forbidden', __( 'Your ISBNLab plan does not include this lookup.', 'isbnlab' ), array( 'status' => $status ) );
			case 404:
				return $this->not_found();
			case 429:
				$retry   = (int) wp_remote_retrieve_header( $response, 'retry-after' );
				$message = $retry > 0
					/* translators: %d: seconds to wait. */
					? sprintf( _n( 'ISBNLab rate limit reached. Try again in %d second.', 'ISBNLab rate limit reached. Try again in %d seconds.', $retry, 'isbnlab' ), $retry )
					: __( 'ISBNLab rate limit reached for your plan. Try again later.', 'isbnlab' );

				return new WP_Error(
					'isbnlab_rate_limited',
					$message,
					array(
						'status'      => $status,
						'retry_after' => $retry,
					)
				);
		}

		return new WP_Error(
			'isbnlab_http_status',
			/* translators: %d: HTTP status code. */
			sprintf( __( 'ISBNLab returned an error (HTTP %d).', 'isbnlab' ), $status ),
			array( 'status' => $status )
		);
	}

	/**
	 * The error for an ISBN the catalog does not have.
	 *
	 * @return WP_Error
	 */
	private function not_found() {
		return new WP_Error( 'isbnlab_not_found', __( 'No book with that ISBN was found.', 'isbnlab' ), array( 'status' => 404 ) );
	}
}
