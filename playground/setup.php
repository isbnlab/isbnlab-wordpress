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

// The note under the token field (regenerate a token used here).
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
}
copy( __DIR__ . '/demo-notes.php', WPMU_PLUGIN_DIR . '/isbnlab-demo-notes.php' );

// Nothing from the default install.
foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $old ) {
	if ( in_array( $old->post_name, array( 'hello-world', 'sample-page', 'privacy-policy' ), true ) ) {
		wp_delete_post( $old->ID, true );
	}
}

$book = ISBNLab_Client::example_book();

// A product filled from the example ISBN, as the product box does.
$product = new WC_Product_Simple();
$product->set_name( 'Example' );
$product->set_regular_price( '29.99' );
$product->set_status( 'publish' );
$product->save();
ISBNLab_WooCommerce::apply( $product, $book, array( 'title', 'description', 'cover', 'attributes', 'gtin' ) );
$product = wc_get_product( $product->get_id() );
$books   = wp_insert_term( 'Books', 'product_cat', array( 'slug' => 'books' ) );
if ( ! is_wp_error( $books ) ) {
	$product->set_category_ids( array( $books['term_id'] ) );
}
$product->set_status( 'publish' );
$product->save();

$admin       = admin_url();
$new_product = esc_url( $admin . 'post-new.php?post_type=product' );
$settings    = esc_url( $admin . 'options-general.php?page=isbnlab' );
$product_url = esc_url( get_permalink( $product->get_id() ) );
$shop_url    = esc_url( get_permalink( wc_get_page_id( 'shop' ) ) );
$cover_id    = (int) $product->get_image_id();
$cover_src   = esc_url( (string) wp_get_attachment_image_url( $cover_id, 'large' ) );

$content = <<<HTML
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"margin":{"top":"0"}}},"backgroundColor":"accent-5","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-accent-5-background-color has-background" style="margin-top:0;padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--50)"><!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"64%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:64%"><!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"1.4px"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="letter-spacing:1.4px;text-transform:uppercase">ISBNLab Book Data for WordPress</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-xx-large-font-size">Type an ISBN. Get the whole book.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Title, authors, cover, description, publisher, pages and series, for WooCommerce products and for any page. This is a live WordPress running in your browser, and you are logged in as its administrator.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$new_product}">Fill a product from an ISBN</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{$product_url}">See a filled product</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"36%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:36%"><!-- wp:image {"id":{$cover_id},"width":"280px","sizeSlug":"large","linkDestination":"custom","align":"center"} -->
<figure class="wp-block-image aligncenter size-large is-resized"><a href="{$product_url}"><img src="{$cover_src}" alt="The Gathering Storm, the example book" class="wp-image-{$cover_id}" style="width:280px"/></a></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained","wideSize":"1200px"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","align":"wide"} -->
<h2 class="wp-block-heading alignwide has-text-align-center">Three ways to use it</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns alignwide" style="padding-top:var(--wp--preset--spacing--40)"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">WooCommerce products</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>On Products &gt; Add New, the ISBNLab book data box fills the name, description, cover, attributes and GTIN from one ISBN. You set the price.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="{$new_product}">Try it with 978-0-7653-0230-4 →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">The ISBNLab Book block</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Add it to any post or page, type an ISBN, and choose whether to show the cover, the details and the description.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#isbnlab-block">See it below →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">A shortcode</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>For classic themes and page builders: <code>&#91;isbnlab_book isbn="9780765302304"&#93;</code>, with cover, details and description each switchable.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="#isbnlab-shortcode">See it below →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:heading {"anchor":"isbnlab-block"} -->
<h2 class="wp-block-heading" id="isbnlab-block">The block</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Cover, details and schema.org data for search engines, from the ISBN alone.</p>
<!-- /wp:paragraph -->

<!-- wp:isbnlab/book {"isbn":"9780765302304","showDescription":false} /-->

<!-- wp:heading {"anchor":"isbnlab-shortcode"} -->
<h2 class="wp-block-heading" id="isbnlab-shortcode">The shortcode, cover and title only</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><code>&#91;isbnlab_book isbn="0765302306" details="no" description="no"&#93;</code></p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[isbnlab_book isbn="0765302306" details="no" description="no"]
<!-- /wp:shortcode -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"margin":{"top":"var:preset|spacing|70"}}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-color has-contrast-background-color has-text-color has-background" style="margin-top:var(--wp--preset--spacing--70);padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","textColor":"base"} -->
<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color">Look up any of 100 million editions</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Without a token, the plugin shows only this example book. A free ISBNLab account gives you a token for 1,000 lookups a day.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"base","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-base-background-color has-text-color has-background wp-element-button" href="https://www.isbnlab.com/register">Get a free token</a></div>
<!-- /wp:button -->

<!-- wp:button {"textColor":"base","className":"is-style-outline","style":{"border":{"color":"var:preset|color|base"}}} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-base-color has-text-color has-border-color wp-element-button" style="border-color:var(--wp--preset--color--base)" href="{$settings}">Add it to this demo</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
HTML;

$page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Demo',
		'post_name'    => 'isbnlab-demo',
		'post_content' => $content,
	)
);
// Twenty Twenty-Five's template without the page title; the hero has its own heading.
update_post_meta( $page, '_wp_page_template', 'page-no-title' );

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page );

// Header menu. The theme's Navigation block uses the newest menu.
$menu = '';
foreach (
	array(
		array( home_url( '/' ), 'Demo' ),
		array( $shop_url, 'Shop' ),
		array( $new_product, 'Add a product' ),
		array( $settings, 'Settings' ),
	) as $item
) {
	$menu .= sprintf( '<!-- wp:navigation-link {"label":"%s","url":"%s","kind":"custom"} /-->', esc_attr( $item[1] ), esc_url( $item[0] ) );
}
wp_insert_post(
	array(
		'post_type'    => 'wp_navigation',
		'post_status'  => 'publish',
		'post_title'   => 'Demo menu',
		'post_content' => $menu,
	)
);

// A short footer in place of the theme's placeholder links.
$theme  = get_stylesheet();
$footer = wp_insert_post(
	array(
		'post_type'    => 'wp_template_part',
		'post_status'  => 'publish',
		'post_name'    => 'footer',
		'post_title'   => 'Footer',
		'post_content' => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">A demo of the ISBNLab Book Data plugin, running in WordPress Playground. Nothing you change here is kept. Book data from <a href="https://www.isbnlab.com/">ISBNLab</a>.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
	)
);
wp_set_object_terms( $footer, $theme, 'wp_theme' );
wp_set_object_terms( $footer, 'footer', 'wp_template_part_area' );
