<?php
/**
 * Plugin Name:       ISBNLab Book Data
 * Plugin URI:        https://github.com/isbnlab/isbnlab-wordpress
 * Description:       Fill WooCommerce products from an ISBN and show books on your site with the ISBNLab Book block or the [isbnlab_book] shortcode, using your ISBNLab API token.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            ISBNLab
 * Author URI:        https://www.isbnlab.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       isbnlab
 * WC requires at least: 8.0
 * WC tested up to:   11.3
 *
 * @package ISBNLab
 */

defined( 'ABSPATH' ) || exit;

define( 'ISBNLAB_VERSION', '0.1.0' );
define( 'ISBNLAB_FILE', __FILE__ );
define( 'ISBNLAB_DIR', plugin_dir_path( __FILE__ ) );
define( 'ISBNLAB_URL', plugin_dir_url( __FILE__ ) );

require_once ISBNLAB_DIR . 'includes/class-isbnlab-isbn.php';
require_once ISBNLAB_DIR . 'includes/class-isbnlab-book.php';
require_once ISBNLAB_DIR . 'includes/class-isbnlab-client.php';
require_once ISBNLAB_DIR . 'includes/class-isbnlab-settings.php';
require_once ISBNLAB_DIR . 'includes/class-isbnlab-woocommerce.php';
require_once ISBNLAB_DIR . 'includes/class-isbnlab-display.php';

ISBNLab_Settings::init();
ISBNLab_Display::init();

add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'WooCommerce' ) ) {
			ISBNLab_WooCommerce::init();
		}
	}
);

// The plugin only touches products through WooCommerce's CRUD classes, so it works with HPOS.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ISBNLAB_FILE, true );
		}
	}
);
