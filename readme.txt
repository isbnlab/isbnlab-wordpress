=== ISBNLab Book Data ===
Contributors: isbnlab
Tags: isbn, books, woocommerce, book metadata, bookstore
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fill WooCommerce products from an ISBN, and show any book on your site, with data from the ISBNLab book metadata API.

== Description ==

Type an ISBN and get the book's title, subtitle, authors, publisher, publication date, format, page count, language, series, description and cover, from a catalog of more than 100 million editions.

**For WooCommerce stores**

* An **ISBNLab book data** box on the product screen. Enter an ISBN-10 or ISBN-13, click **Look up**, check the preview, then choose what to fill in:
  * product name (with or without the subtitle)
  * description
  * cover, downloaded into your media library as the product image
  * attributes: author, publisher, published, format, pages, language, series, ISBN-13 and ISBN-10
  * the ISBN-13 in WooCommerce's GTIN/ISBN field (WooCommerce 9.2 or later)
  * the ISBN-13 as the SKU
* Nothing changes until you click **Apply to product**, and attributes you added yourself are kept.

**For any site**

* The **ISBNLab Book** block: enter an ISBN and it shows the cover, title, authors, details and description. Turn each part on or off in the block settings.
* The `[isbnlab_book isbn="9780765302304"]` shortcode, with `cover="no"`, `details="no"` and `description="no"` options.
* Each book adds schema.org `Book` structured data, so search engines know it is a book.
* Lookups are cached (24 hours by default), so page views do not use up your daily calls.

**Try it before you sign up:** with no token, the plugin works with one example book, ISBN 978-0-7653-0230-4, so you can see the product box, the block and the shortcode first. Any other ISBN needs a token.

**For every other book you need an ISBNLab account.** The Free plan includes 1,000 lookups a day. [Create an account](https://www.isbnlab.com/register) and see [pricing](https://www.isbnlab.com/pricing).

== External services ==

This plugin gets book data from the ISBNLab API at https://www.isbnlab.com, run by RINC Productions, LLC. Without an API token it makes no requests: the example book (ISBN 978-0-7653-0230-4) and its cover are bundled in the plugin.

* **What is sent, and when:** the ISBN being looked up and your API token, when someone clicks **Look up** or **Apply to product** on a product, when a page shows a book block or shortcode whose lookup is not already cached, and when you click **Test connection** in the settings. Requests come from your web server, not from visitors' browsers. No visitor data and no other information about your site is sent, apart from the plugin and WordPress version numbers in the user agent.
* **Cover images:** book cards load cover images from covers.isbnlab.com in visitors' browsers. A product's cover is downloaded once into your media library.
* **Links:** if you turn on the "Book data from ISBNLab" link in the settings, book cards link to the book's page on www.isbnlab.com. It is off by default.

ISBNLab [terms of service](https://www.isbnlab.com/terms-of-use) and [privacy policy](https://www.isbnlab.com/privacy-policy).

== Installation ==

1. Install and activate the plugin.
2. Go to **Settings > ISBNLab**, paste your API token and save. Or put `define( 'ISBNLAB_API_TOKEN', '...' );` in wp-config.php to keep it out of the database.
3. Click **Test connection**.
4. Open a WooCommerce product, or add the ISBNLab Book block to a page.

== Frequently Asked Questions ==

= Can I try it without an account? =

Yes. Until you add a token, the example ISBN 978-0-7653-0230-4 works everywhere in the plugin, using data bundled with it. Other ISBNs ask for a token.

= Does it need WooCommerce? =

No. The block and shortcode work on any site. The product box appears when WooCommerce is active.

= Which plan do I need? =

Every plan includes book lookups by ISBN. Higher plans include more lookups a day; see [pricing](https://www.isbnlab.com/pricing).

= What happens when a book is not found? =

The product box says so. A block or shortcode shows the reason to logged-in editors and nothing to visitors.

= Can I change the book card? =

Use the `isbnlab_book_card_options` filter to change what is shown and `isbnlab_book_card_html` to change the HTML. The card's CSS classes start with `isbnlab-book`.

= What is removed when I delete the plugin? =

Its settings and cached lookups. Products keep the data that was copied into them.

== Screenshots ==

1. The ISBNLab book data box on a WooCommerce product.
2. The ISBNLab Book block.
3. Settings > ISBNLab.

== Changelog ==

= 0.1.0 =
* First release: WooCommerce product box, ISBNLab Book block, `[isbnlab_book]` shortcode, settings page.
