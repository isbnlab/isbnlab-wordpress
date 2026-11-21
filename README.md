# ISBNLab Book Data for WordPress

A WordPress plugin for the [ISBNLab book metadata API](https://www.isbnlab.com/api-information). Paste your ISBNLab API token, then:

- **WooCommerce:** fill a product from an ISBN. The ISBNLab box on the product screen looks up the book, shows a preview, and fills in the name, description, cover image, attributes (author, publisher, format, pages, …), the GTIN/ISBN field and, if you want, the SKU.
- **Any site:** show a book with the **ISBNLab Book** block or `[isbnlab_book isbn="9780765302304"]`. Each card includes schema.org `Book` structured data.

**[Try it in your browser](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/isbnlab/isbnlab-wordpress/main/playground/blueprint.json)**: a live WordPress with WooCommerce and the plugin, running in WordPress Playground. No account needed: without a token the plugin works with one example book, ISBN 978-0-7653-0230-4 (bundled in `assets/example`, a copy of [this sample](https://www.isbnlab.com/book/example/9780765302304/the-gathering-storm-by-robert-jordan.json)). Paste a free token on Settings > ISBNLab to look up any ISBN.

`readme.txt` is the WordPress.org listing and the user documentation.

## Requirements

WordPress 6.3+, PHP 7.4+. WooCommerce 8.0+ for the product box (the GTIN field needs 9.2+). An ISBNLab account: [register](https://www.isbnlab.com/register); the Free plan has 1,000 calls a day.

## Install from source

```bash
bin/package                 # dist/isbnlab-<version>.zip, from the last commit
```

Upload the zip under Plugins > Add New > Upload Plugin, then go to Settings > ISBNLab. The token can also live in `wp-config.php`:

```php
define( 'ISBNLAB_API_TOKEN', 'ilp_...' );
```

## How it works

| File | What it does |
|---|---|
| `includes/class-isbnlab-client.php` | Calls the API with `wp_remote_get()` (no bundled HTTP library to clash with other plugins). Caches books in transients, and "not found" for an hour. |
| `includes/class-isbnlab-isbn.php` | Checks ISBN-10/13 checksums and converts 10 to 13 locally, so a typo never uses a call. |
| `includes/class-isbnlab-book.php` | Turns a `GET /api/book/isbn/{isbn}` response into plain values, coping with any missing field. |
| `includes/class-isbnlab-woocommerce.php` | The product box: "Look up" previews, "Apply to product" saves through WooCommerce's CRUD classes (HPOS-safe) and reloads. |
| `includes/class-isbnlab-display.php` | The block (`blocks/book`, server-rendered) and the shortcode. |
| `includes/class-isbnlab-settings.php` | Settings > ISBNLab. The saved token is never printed back into the page. |

There is no build step: the block's editor script is plain JavaScript on WordPress's own `wp.*` packages, with its dependencies listed by hand in `blocks/book/index.asset.php`.

The plugin calls only `GET /api/book/isbn/{isbn}` (lookups) and `GET /api/isbn/isbn-lookup/{isbn}` (Test connection), and sends `Accept: application/json` on every request.

## The Playground demo

`playground/blueprint.json` installs WooCommerce and this repository's `main` branch, then runs `playground/setup.php`, which builds a small store from the sample books in `playground/books` (refresh them with `ISBNLAB_API_TOKEN=... playground/fetch-books`) and makes the demo page. Both are left out of the plugin zip. WordPress.org's Live Preview button can use the same blueprint once it is copied to the listing's `assets/blueprints/blueprint.json`.

In Playground the plugin's API calls are made by the visitor's browser. Since www.isbnlab.com does not allow CORS from Playground, they go through Playground's CORS proxy (wordpress-playground-cors-proxy.net, run by the Playground project), which forwards the token only because the plugin sends `X-Cors-Proxy-Allowed-Request-Headers: Authorization`. So a token pasted in the demo passes through that proxy; use a test token there. The example book needs no request.

## Tests

Everything runs in Docker:

```bash
bin/test          # php -l and tests/unit.php on PHP 7.4 and 8.4
bin/smoke-test    # a throwaway WordPress + WooCommerce, the plugin, tests/smoke/checks.php,
                  # a front-end page check, then the WordPress.org Plugin Check
KEEP=1 bin/smoke-test   # same, and leave the site at http://localhost:8089 (admin / admin)
ISBNLAB_API_TOKEN=ilp_... bin/live-test   # the same site against the real API (about 8 calls)
```

`bin/live-test` passes the token only as an environment variable; never write it to a file.

The smoke test never calls isbnlab.com: `tests/smoke/fake-api.php` answers API and cover requests from `tests/fixtures` (copied from isbnlab-php's fixtures, which are real responses).

## Licence

GPL-2.0-or-later, like WordPress (see `LICENSE`). Copyright (c) 2026 RINC Productions, LLC (ISBNLab).
