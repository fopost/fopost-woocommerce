# FoPost for WooCommerce

[![CI](https://github.com/fopost/fopost-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/fopost/fopost-woocommerce/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-96588a.svg)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)](https://www.php.net)

The official WooCommerce add-on for [FoPost](https://fopost.com). It posts your products to social
media when they are published, go on sale, or come back in stock.

## What it does

| Trigger | Fires when |
| :--- | :--- |
| Product published | A product moves into the published status for the first time |
| Product goes on sale | A price change puts the product on sale, and it was not before |
| Product back in stock | Stock status returns to `instock` after being out |

Each trigger is off by default, has its own message template, and can be switched independently.

## Install

```bash
composer install
```

Then copy the directory to `wp-content/plugins/fopost-woocommerce` and activate it. WooCommerce must
be active first. A release zip with production dependencies is attached to every GitHub release.

## Configure

**WooCommerce, Settings, FoPost**

1. Paste a FoPost API key. It is stored on your site and is only ever sent to FoPost.
2. Pick the workspace and the connected accounts product posts should go to.
3. In **Triggers and Messages**, switch on the events you want and edit their copy.

The key is never rendered back into the settings form. Leave the field blank to keep the key you
already saved, or tick **Remove the stored API key** to clear it.

## Message placeholders

| Placeholder | Value |
| :--- | :--- |
| `{product_name}` | The product title |
| `{price}` | The regular price, formatted in your shop's currency |
| `{sale_price}` | The sale price, when there is one |
| `{permalink}` | The product URL |
| `{short_description}` | The short description as plain text, trimmed to 280 characters |
| `{sku}` | The SKU |
| `{categories}` | The product categories, comma separated |

Every value is reduced to plain text before it is substituted, so markup in a product description
never reaches a social network.

## Filters

| Filter | Purpose |
| :--- | :--- |
| `fopost_wc_template_placeholders` | Add or change placeholders. Return plain text values |
| `fopost_wc_rendered_message` | Change the finished message |
| `fopost_wc_should_post` | Veto a post before it is queued |
| `fopost_wc_api_base_url` | Point a staging site at a different API |
| `fopost_wc_api_timeout` | Per-request timeout, in seconds |
| `fopost_wc_logged` | React to a recorded delivery attempt |

See [`examples/custom-placeholder.php`](examples/custom-placeholder.php) for working code.

## How it delivers

A trigger never calls the API. It queues an Action Scheduler job, which WooCommerce already runs, so
publishing a product is never slowed down by a network round trip and a failed delivery is retried.
The call itself goes through the [FoPost PHP SDK](https://github.com/fopost/fopost-php), whose
transport is swapped for the WordPress HTTP API so your host's proxy and certificate settings apply.

Every attempt is recorded against the product and listed under **WooCommerce, FoPost Activity** with
the FoPost post ID. Failures also surface as an admin notice.

## Development

```bash
composer install
composer test    # phpunit, fully offline
composer lint    # phpcs against the project ruleset
make build       # build the distributable zip
```

The test suite stubs WordPress, WooCommerce and Action Scheduler, so it needs no database and makes
no network calls.

## Support

- Documentation: https://fopost.com/docs
- Contact: https://fopost.com/contact
- Issues: https://github.com/fopost/fopost-woocommerce/issues

## License

GPL-2.0-or-later. Copyright (c) 2026 Porter Bridge, LLC.

WordPress.org requires a GPL-compatible license for hosted plugins, which is why this repository is
GPL rather than MIT like the FoPost SDKs.
