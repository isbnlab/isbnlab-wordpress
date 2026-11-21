<?php
/**
 * Must-use plugin for bin/smoke-test only: answers ISBNLab API and cover requests from the
 * fixtures, so the smoke test needs no token and makes no network calls to isbnlab.com.
 * Every answered request is counted in the option isbnlab_fake_calls.
 *
 * @package ISBNLab
 */

add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {
		if ( 0 === strpos( $url, 'https://covers.isbnlab.com/' ) ) {
			update_option( 'isbnlab_fake_cover_calls', (int) get_option( 'isbnlab_fake_cover_calls', 0 ) + 1 );
			$image = imagecreatetruecolor( 40, 60 );
			ob_start();
			imagejpeg( $image );
			$jpeg = ob_get_clean();
			if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
				file_put_contents( $args['filename'], $jpeg );
			}

			return array(
				'headers'  => array( 'content-type' => 'image/jpeg' ),
				'body'     => empty( $args['stream'] ) ? $jpeg : '',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => isset( $args['filename'] ) ? $args['filename'] : null,
			);
		}

		if ( 0 === strpos( $url, 'https://www.isbnlab.com/book/example/' ) ) {
			update_option( 'isbnlab_fake_example_calls', (int) get_option( 'isbnlab_fake_example_calls', 0 ) + 1 );
			if ( isset( $args['headers']['Authorization'] ) ) {
				update_option( 'isbnlab_fake_example_token_sent', 1 );
			}
			$down = (bool) get_option( 'isbnlab_fake_example_down' );

			return array(
				'headers'  => array( 'content-type' => 'application/json' ),
				'body'     => $down ? '' : file_get_contents( '/fixtures/book.json' ),
				'response' => $down ? array( 'code' => 503, 'message' => 'Unavailable' ) : array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		if ( 0 !== strpos( $url, 'https://www.isbnlab.com/api/' ) ) {
			return $preempt;
		}

		update_option( 'isbnlab_fake_calls', (int) get_option( 'isbnlab_fake_calls', 0 ) + 1 );

		$auth = isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '';
		if ( 'Bearer smoke-token' !== $auth ) {
			return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 401, 'message' => 'Unauthorized' ), 'cookies' => array(), 'filename' => null );
		}

		// The first request for this ISBN is rate limited, as the API's per-second limit does.
		if ( false !== strpos( $url, '/api/book/isbn/9780306406157' ) && ! get_option( 'isbnlab_fake_limited' ) ) {
			update_option( 'isbnlab_fake_limited', 1 );
			return array( 'headers' => array( 'retry-after' => '1' ), 'body' => '{}', 'response' => array( 'code' => 429, 'message' => 'Too Many Requests' ), 'cookies' => array(), 'filename' => null );
		}

		$fixtures = '/fixtures/';
		$routes   = array(
			'/api/book/isbn/9780765302304'           => 'book.json',
			'/api/book/isbn/9780000000002'           => 'book-sparse.json',
			'/api/book/isbn/9780306406157'           => 'book.json',
			'/api/isbn/isbn-lookup/9780765302304'    => 'book-free.json',
		);
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( ! isset( $routes[ $path ] ) ) {
			return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 404, 'message' => 'Not Found' ), 'cookies' => array(), 'filename' => null );
		}

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => file_get_contents( $fixtures . $routes[ $path ] ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
