# ISBNLab Book Data for WordPress

A WordPress plugin for the [ISBNLab book metadata API](https://www.isbnlab.com/api-information). Paste your ISBNLab API token, then:

- **WooCommerce:** fill a product from an ISBN. The ISBNLab box on the product screen looks up the book, shows a preview, and fills in the name, description, cover image, attributes (author, publisher, format, pages, …), the GTIN/ISBN field and, if you want, the SKU.
- **Any site:** show a book with the **ISBNLab Book** block or `[isbnlab_book isbn="9780765302304"]`. Each card includes schema.org `Book` structured data.

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

## Tests

Everything runs in Docker:

```bash
bin/test          # php -l and tests/unit.php on PHP 7.4 and 8.4
bin/smoke-test    # a throwaway WordPress + WooCommerce, the plugin, tests/smoke/checks.php,
                  # a front-end page check, then the WordPress.org Plugin Check
KEEP=1 bin/smoke-test   # same, and leave the site at http://localhost:8089 (admin / admin)
```

The smoke test never calls isbnlab.com: `tests/smoke/fake-api.php` answers API and cover requests from `tests/fixtures` (copied from isbnlab-php's fixtures, which are real responses).

## Licence

GPL-2.0-or-later, like WordPress (see `LICENSE`). Copyright (c) 2026 RINC Productions, LLC (ISBNLab).
