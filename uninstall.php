<?php
/**
 * Removes the plugin's settings and cached lookups when it is deleted. Products keep the data
 * that was copied into them.
 *
 * @package ISBNLab
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'isbnlab_settings' );

global $wpdb;

// Cached lookups are transients named isbnlab_book_<isbn13>; there is no API to list them.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_isbnlab_book_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_isbnlab_book_' ) . '%'
	)
);
