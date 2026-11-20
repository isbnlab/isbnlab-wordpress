<?php
/**
 * ISBN validation and conversion, done locally so that an invalid ISBN never costs an API call.
 *
 * @package ISBNLab
 */

defined( 'ABSPATH' ) || exit;

/**
 * ISBN-10 and ISBN-13 helpers. No WordPress functions, so it can be tested on its own.
 */
final class ISBNLab_Isbn {

	/**
	 * Strip spaces, hyphens and other separators, and upper-case a trailing x.
	 *
	 * @param string $isbn What the user typed.
	 * @return string
	 */
	public static function clean( $isbn ) {
		return strtoupper( (string) preg_replace( '/[^0-9Xx]/', '', (string) $isbn ) );
	}

	/**
	 * Whether the value is a valid ISBN-10 or ISBN-13 (checksum included).
	 *
	 * @param string $isbn Any formatting.
	 * @return bool
	 */
	public static function is_valid( $isbn ) {
		$isbn = self::clean( $isbn );

		if ( 10 === strlen( $isbn ) ) {
			return self::is_valid_10( $isbn );
		}

		if ( 13 === strlen( $isbn ) ) {
			return self::is_valid_13( $isbn );
		}

		return false;
	}

	/**
	 * The ISBN-13 for a valid ISBN-10 or ISBN-13, or null when the value is not a valid ISBN.
	 *
	 * @param string $isbn Any formatting.
	 * @return string|null
	 */
	public static function to_13( $isbn ) {
		$isbn = self::clean( $isbn );

		if ( 13 === strlen( $isbn ) ) {
			return self::is_valid_13( $isbn ) ? $isbn : null;
		}

		if ( 10 !== strlen( $isbn ) || ! self::is_valid_10( $isbn ) ) {
			return null;
		}

		$body = '978' . substr( $isbn, 0, 9 );

		return $body . self::check_digit_13( $body );
	}

	/**
	 * Checksum test for a cleaned ISBN-10.
	 *
	 * @param string $isbn Ten characters.
	 * @return bool
	 */
	private static function is_valid_10( $isbn ) {
		if ( ! preg_match( '/^[0-9]{9}[0-9X]$/', $isbn ) ) {
			return false;
		}

		$sum = 0;
		for ( $i = 0; $i < 10; $i++ ) {
			$digit = ( 'X' === $isbn[ $i ] ) ? 10 : (int) $isbn[ $i ];
			$sum  += $digit * ( 10 - $i );
		}

		return 0 === $sum % 11;
	}

	/**
	 * Checksum test for a cleaned ISBN-13 (978 or 979 prefix).
	 *
	 * @param string $isbn Thirteen digits.
	 * @return bool
	 */
	private static function is_valid_13( $isbn ) {
		if ( ! preg_match( '/^97[89][0-9]{10}$/', $isbn ) ) {
			return false;
		}

		return self::check_digit_13( substr( $isbn, 0, 12 ) ) === $isbn[12];
	}

	/**
	 * EAN-13 check digit for twelve digits.
	 *
	 * @param string $body Twelve digits.
	 * @return string
	 */
	private static function check_digit_13( $body ) {
		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$sum += (int) $body[ $i ] * ( 0 === $i % 2 ? 1 : 3 );
		}

		return (string) ( ( 10 - $sum % 10 ) % 10 );
	}
}
