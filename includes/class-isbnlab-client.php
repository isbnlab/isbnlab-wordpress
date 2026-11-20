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
	 * How long a "not found" answer is remembered, so a missing ISBN on a busy page is not
	 * looked up on every view.
	 */
	const NOT_FOUND_TTL = HOUR_IN_SECONDS;

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
					'Authorization' => 'Bearer ' . $this->token,
					'Accept'        => 'application/json',
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
				$retry = (int) wp_remote_retrieve_header( $response, 'retry-after' );
				$message = $retry > 0
					/* translators: %d: seconds to wait. */
					? sprintf( __( 'ISBNLab rate limit reached. Try again in %d seconds.', 'isbnlab' ), $retry )
					: __( 'ISBNLab rate limit reached for your plan. Try again later.', 'isbnlab' );

				return new WP_Error( 'isbnlab_rate_limited', $message, array( 'status' => $status ) );
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
