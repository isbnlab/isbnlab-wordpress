<?php
/**
 * Demo content for WordPress Playground, run by playground/blueprint.json once WooCommerce and
 * the plugin are active. Uses only the bundled example book, so it works with no API token and
 * no network access. Not part of the plugin zip (see .gitattributes).
 *
 * @package ISBNLab
 */

require_once '/wordpress/wp-load.php';

// A ready-to-browse store: no WooCommerce setup wizard, no "coming soon" page.
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_task_list_hidden', 'yes' );
// WooCommerce sends the first admin visit within 30 seconds of activation to its setup wizard.
delete_transient( '_wc_activation_redirect' );

$book = ISBNLab_Client::example_book();

// A product filled from the example ISBN, as the product box does.
$product = new WC_Product_Simple();
$product->set_name( 'Example' );
$product->set_regular_price( '29.99' );
$product->set_status( 'publish' );
$product->save();
ISBNLab_WooCommerce::apply( $product, $book, array( 'title', 'description', 'cover', 'attributes', 'gtin' ) );
$product = wc_get_product( $product->get_id() );
$product->set_status( 'publish' );
$product->save();

$admin = admin_url();
$links = array(
	array( $admin . 'post-new.php?post_type=product', 'Fill a new product from an ISBN', 'Open Products > Add New and use the ISBNLab book data box on the right. Without a token, try 978-0-7653-0230-4.' ),
	array( get_permalink( $product->get_id() ), 'See a product filled by the plugin', 'Name, description, cover, attributes and the GTIN/ISBN field all came from one ISBN.' ),
	array( $admin . 'options-general.php?page=isbnlab', 'Add your free API token', 'Then any ISBN works, in the product box, the block and the shortcode.' ),
);

$list = '';
foreach ( $links as $link ) {
	$list .= sprintf( '<li><a href="%s">%s</a>: %s</li>', esc_url( $link[0] ), esc_html( $link[1] ), esc_html( $link[2] ) );
}

$content = <<<HTML
<!-- wp:paragraph -->
<p>This is a live WordPress, running in your browser, with the <strong>ISBNLab Book Data</strong> plugin and WooCommerce. You are logged in as an administrator; nothing you change here is kept.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>{$list}</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>The ISBNLab Book block</h2>
<!-- /wp:heading -->

<!-- wp:isbnlab/book {"isbn":"9780765302304"} /-->

<!-- wp:heading -->
<h2>The shortcode, without details or description</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><code>&#91;isbnlab_book isbn="0765302306" details="no" description="no"&#93;</code></p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[isbnlab_book isbn="0765302306" details="no" description="no"]
<!-- /wp:shortcode -->

<!-- wp:paragraph -->
<p>Without an API token the plugin shows only this example book. <a href="https://www.isbnlab.com/register">Create a free ISBNLab account</a> for a token that looks up any of more than 100 million editions.</p>
<!-- /wp:paragraph -->
HTML;

$page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'ISBNLab Book Data demo',
		'post_name'    => 'isbnlab-demo',
		'post_content' => $content,
	)
);

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page );
