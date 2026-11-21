<?php
/**
 * Demo content for WordPress Playground, run by playground/blueprint.json once WooCommerce and
 * the plugin are active. Uses the bundled sample books (playground/books, refreshed by
 * playground/fetch-books), so it needs no API token and no network. Not part of the plugin zip.
 *
 * @package ISBNLab
 */

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The page uses a form and inline styles, which the content filter removes when no user is logged in.
kses_remove_filters();

// A ready-to-browse store: no WooCommerce setup wizard, no "coming soon" page.
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_task_list_hidden', 'yes' );
update_option( 'woocommerce_thumbnail_cropping', 'uncropped' );
// WooCommerce sends the first admin visit within 30 seconds of activation to its setup wizard.
delete_transient( '_wc_activation_redirect' );

// The sample books, the demo's styles and the note under the token field.
wp_mkdir_p( WPMU_PLUGIN_DIR );
copy( __DIR__ . '/demo-plugin.php', WPMU_PLUGIN_DIR . '/isbnlab-demo.php' );
require_once WPMU_PLUGIN_DIR . '/isbnlab-demo.php';

// Nothing from the default install.
foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $old ) {
	if ( in_array( $old->post_name, array( 'hello-world', 'sample-page' ), true ) ) {
		wp_delete_post( $old->ID, true );
	}
}

// Importing covers without generating image sizes keeps the demo's start quick.
add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );

$shelves = array(
	'fantasy'         => 'Fantasy',
	'science-fiction' => 'Science fiction',
	'mystery'         => 'Mystery & thrillers',
	'classics'        => 'Classics',
	'nonfiction'      => 'Nonfiction',
	'kids'            => 'Children’s',
);
// Left out of the shop, so a visitor can create them from the ISBN.
$not_in_shop = array( '9780618260300', '9780553418026', '9780735211292' );

$rows = json_decode( (string) file_get_contents( __DIR__ . '/books/books.json' ), true );


$terms = array();
foreach ( $shelves as $slug => $name ) {
	$term           = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
	$terms[ $slug ] = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
}

/**
 * A believable demo price: the book data has none.
 *
 * @param string $format The book's format.
 * @return string
 */
$price_for = function ( $format ) {
	$format = strtolower( $format );
	if ( false !== strpos( $format, 'board' ) ) {
		return '9.99';
	}
	return false !== strpos( $format, 'hard' ) ? '27.99' : '16.99';
};

$index   = array();
$by_shelf = array_fill_keys( array_keys( $shelves ), array() );

foreach ( $rows as $row ) {
	$book  = new ISBNLab_Book( $row['book'] );
	$isbn  = $book->isbn13();
	$file  = __DIR__ . '/books/' . $isbn . '.jpg';

	$tmp = wp_tempnam( $isbn . '.jpg' );
	copy( $file, $tmp );
	$cover = media_handle_sideload( array( 'name' => $isbn . '.jpg', 'tmp_name' => $tmp ), 0, 'Cover of ' . $book->title() );
	if ( is_wp_error( $cover ) ) {
		wp_delete_file( $tmp );
		continue;
	}
	// The plugin reuses an attachment with this source instead of downloading the cover.
	update_post_meta( $cover, ISBNLab_WooCommerce::META_COVER, $book->cover_url() );
	update_post_meta( $cover, '_wp_attachment_image_alt', 'Cover of ' . $book->title() );

	$url = '';
	if ( ! in_array( $isbn, $not_in_shop, true ) ) {
		$product = new WC_Product_Simple();
		$product->set_name( $book->title() );
		$product->set_regular_price( $price_for( $book->format() ) );
		$product->set_status( 'publish' );
		$product->save();
		ISBNLab_WooCommerce::apply( $product, $book, array( 'title', 'description', 'cover', 'attributes', 'gtin' ) );
		$product = wc_get_product( $product->get_id() );
		$product->set_category_ids( array( $terms[ $row['shelf'] ] ) );
		$product->set_status( 'publish' );
		$product->save();
		$url = get_permalink( $product->get_id() );
	}

	$details = array();
	foreach ( array( 'Publisher' => 'publisher', 'Format' => 'format', 'Pages' => 'pages', 'Series' => 'series', 'ISBN-13' => 'isbn13' ) as $label => $key ) {
		$all = $book->details();
		if ( ! empty( $all[ $key ] ) ) {
			$details[] = array( $label, (string) $all[ $key ] );
		}
	}

	$entry = array(
		'isbn13'  => $isbn,
		'isbn10'  => $book->isbn10(),
		'title'   => $book->title(),
		'authors' => implode( ', ', $book->authors() ),
		'cover'   => (string) wp_get_attachment_url( $cover ),
		'url'     => $url,
		'details' => $details,
	);

	$index[ $isbn ] = $entry;
	if ( '' !== $url ) {
		$by_shelf[ $row['shelf'] ][] = $entry;
	}
}

$admin       = admin_url();
$new_product = $admin . 'post-new.php?post_type=product';
$settings    = $admin . 'options-general.php?page=isbnlab';
update_option( 'isbnlab_demo_index', array( 'books' => array_values( $index ), 'addUrl' => $new_product ), false );


// Fanned covers in the hero.
$fan = '';
foreach ( array( array( '9780374533557', -2, 2 ), array( '9780307588371', 2, 2 ), array( '9780765326355', -1, 1 ), array( '9780590353427', 1, 1 ), array( '9780765382030', 0, 0 ) ) as $item ) {
	$fan .= sprintf( '<img src="%s" alt="" style="--i:%d;--d:%d" />', esc_url( $index[ $item[0] ]['cover'] ), $item[1], $item[2] );
}

// Sample ISBNs to click, the first ones not yet in the shop.
$chips = '';
foreach ( array( '9780553418026', '9780618260300', '9780735211292', '9780441172719', '9780307588371' ) as $isbn ) {
	$chips .= sprintf( '<button type="button" data-demo-isbn="%s">%s</button>', esc_attr( $isbn ), esc_html( $index[ $isbn ]['title'] ) );
}

// The worked example.
$dune      = $index['9780441172719'];
$dune_rows = '';
foreach ( $dune['details'] as $row ) {
	$dune_rows .= '<dt>' . esc_html( $row[0] ) . '</dt><dd>' . esc_html( $row[1] ) . '</dd>';
}
$dune_cover  = esc_url( $dune['cover'] );
$dune_url    = esc_url( $dune['url'] );
$dune_title  = esc_html( $dune['title'] );
$dune_author = esc_html( $dune['authors'] );

// The shelves.
$shelf_html = '';
foreach ( $shelves as $slug => $name ) {
	$books = '';
	foreach ( $by_shelf[ $slug ] as $entry ) {
		$books .= sprintf( '<a href="%s" title="%s"><img src="%s" alt="%s" loading="lazy" /></a>', esc_url( $entry['url'] ), esc_attr( $entry['title'] ), esc_url( $entry['cover'] ), esc_attr( 'Cover of ' . $entry['title'] ) );
	}
	$shelf_html .= sprintf(
		'<div class="demo-shelf"><div class="demo-shelf__head"><h3>%s</h3><a href="%s">See all</a></div><div class="demo-shelf__books">%s</div></div>',
		esc_html( $name ),
		esc_url( (string) get_term_link( $terms[ $slug ], 'product_cat' ) ),
		$books
	);
}

$add_url  = esc_url( $new_product );
$set_url  = esc_url( $settings );
$shop_url = esc_url( get_permalink( wc_get_page_id( 'shop' ) ) );

$content = <<<HTML
<!-- wp:html -->
<section class="demo-band demo-hero alignfull"><div class="demo-wrap demo-hero__grid">
<div>
<p class="demo-eyebrow">ISBNLab Book Data for WordPress</p>
<h1>Type an ISBN. Get the whole book.</h1>
<p class="demo-hero__lede">Title, authors, cover, description and details, in your WooCommerce store or on any page. Try it here.</p>
<form id="demo-lookup"><input type="text" inputmode="numeric" autocomplete="off" aria-label="ISBN" placeholder="ISBN, such as 978-0-441-17271-9" /><button type="submit">Look up</button></form>
<p id="demo-message" role="status"></p>
<div class="demo-chips"><span>Try:</span>{$chips}</div>
</div>
<div id="demo-stage"><div class="demo-fan">{$fan}</div></div>
</div></section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="demo-band alignfull"><div class="demo-wrap">
<p class="demo-eyebrow">How it works</p>
<h2 class="demo-title">One ISBN in. A finished listing out.</h2>
<p class="demo-lede">No more typing up each book by hand. Paste the ISBN into the product screen, choose what to fill, and add your price.</p>
<div class="demo-flow">
<div class="demo-panel"><p class="demo-panel__label">ISBN</p><p class="demo-panel__isbn">978-0-441-17271-9</p><ul><li>Title</li><li>Description</li><li>Cover image</li><li>Attributes</li><li>GTIN / ISBN</li></ul></div>
<div class="demo-arrow" aria-hidden="true">→</div>
<div class="demo-listing"><img src="{$dune_cover}" alt="Cover of Dune" /><div><h3>{$dune_title}</h3><p class="demo-listing__by">{$dune_author}</p><dl>{$dune_rows}</dl><a class="demo-button" href="{$dune_url}">See this product</a></div></div>
</div>
</div></section>
<!-- /wp:html -->

<!-- wp:html -->
<section class="demo-band demo-shelves alignfull"><div class="demo-wrap">
<p class="demo-eyebrow">The demo store</p>
<h2 class="demo-title">Every book here was filled from its ISBN.</h2>
<p class="demo-lede" style="margin-bottom:0">Covers, descriptions and details came from ISBNLab. The prices are made up for the demo. <a href="{$shop_url}">Browse the shop</a>.</p>
<div class="demo-shelf-grid">{$shelf_html}</div>
</div></section>
<!-- /wp:html -->

<!-- wp:group {"align":"full","className":"demo-band","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull demo-band"><!-- wp:html -->
<div class="demo-wrap">
<p class="demo-eyebrow">Three ways to use it</p>
<h2 class="demo-title">In your store, or on any page.</h2>
<div class="demo-ways" style="margin-top:32px">
<div class="demo-way"><b>1</b><h3>WooCommerce products</h3><p>The ISBNLab box on the product screen fills name, description, cover, attributes and GTIN. <a href="{$add_url}">Try it</a>.</p></div>
<div class="demo-way"><b>2</b><h3>The Book block</h3><p>Add it to any post or page, type an ISBN, and choose what to show. It also adds schema.org data.</p></div>
<div class="demo-way"><b>3</b><h3>A shortcode</h3><p>For classic themes and page builders: <code>&#91;isbnlab_book isbn="9780765326355"&#93;</code></p></div>
</div>
</div>
<!-- /wp:html -->

<!-- wp:group {"className":"demo-live","layout":{"type":"default"}} -->
<div class="wp-block-group demo-live"><!-- wp:paragraph {"className":"demo-live__label"} -->
<p class="demo-live__label">This is the real block, rendering The Way of Kings:</p>
<!-- /wp:paragraph -->

<!-- wp:isbnlab/book {"isbn":"9780765326355","showDescription":false} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:html -->
<section class="demo-band demo-cta alignfull"><div class="demo-wrap">
<h2>Look up any of 100 million editions</h2>
<p>This demo holds 25 sample books. A free ISBNLab account gives you a token for 1,000 lookups a day.</p>
<a class="demo-button" href="https://www.isbnlab.com/register">Get a free token</a>
<a class="demo-button demo-button--ghost" href="{$set_url}">Add it to this demo</a>
</div></section>
<!-- /wp:html -->
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
		array( get_permalink( wc_get_page_id( 'shop' ) ), 'Shop' ),
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
wp_set_object_terms( $footer, get_stylesheet(), 'wp_theme' );
wp_set_object_terms( $footer, 'footer', 'wp_template_part_area' );
