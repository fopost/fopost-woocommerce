# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## What This Is

**FoPost for WooCommerce** is a WordPress plugin (WP.org slug `fopost-woocommerce`) that posts a
shop's products to social media through the hosted [FoPost](https://fopost.com) service. A
WooCommerce product event queues an Action Scheduler job; that job renders a message template and
sends it to the FoPost API using the official [`fopost/sdk`](https://github.com/fopost/fopost-php)
PHP SDK.

| Property | Value |
|---|---|
| **Type** | WordPress plugin, WooCommerce add-on |
| **WordPress.org slug** | `fopost-woocommerce` |
| **Text Domain** | `fopost-woocommerce` |
| **PHP namespace** | `Fopost\WooCommerce\` |
| **PHP version** | 8.1+ (strict types required) |
| **Requires** | WordPress 6.0+, WooCommerce 8.0+ |
| **Runtime dependency** | `fopost/sdk` ^0.1 (Composer, bundled in the zip) |
| **License** | GPL-2.0-or-later |

## Brand Rules

- The product is **FoPost** (`fopost.com`). Never write "OwlStack", retired Aug 2026.
- Never write an email address or a `mailto:` link. Support is https://fopost.com/contact and
  GitHub issues. This includes the generated `.pot` header, which is why `Last-Translator` carries
  a URL and not an address.
- Never name AI providers or models, hosting, infrastructure, or any person. The author is the
  brand, and the legal entity is Porter Bridge, LLC.
- Do not use em-dashes or en-dashes in prose.

## Text Domain Rules

The text domain is **`fopost-woocommerce`**, matching the plugin folder name inside
`wp-content/plugins/` and the WP.org slug. WordPress Plugin Check requires the match.

```php
__('Settings', 'fopost-woocommerce')
esc_html_e('Post Now', 'fopost-woocommerce')
```

The rule across the FoPost WordPress repos is *text domain equals the WP.org slug, never the repo
name*. In `fopost-wp` the repo is `fopost-wp` and the slug is `fopost`, so its domain is `fopost`.
In `fopost-social-wp` the slug is `fopost-social`. Here the repo name and the slug happen to be the
same string, so the domain is `fopost-woocommerce`. Using a bare `fopost` here would fail Plugin
Check. Anything else as the second argument to `__()`, `_e()`, `esc_html__()`, `esc_html_e()` or
`_n()` is a bug.

## Dependency Decision

**This add-on does not depend on the `fopost-wp` connector plugin.** That was considered and
rejected on evidence:

- `fopost-wp` is an *inbound* connector. It stores `hash('sha256', $token)` of a site token that
  FoPost presents back on `fopost/v1` REST routes. There is no FoPost API key anywhere in it.
- It has no outbound HTTP client of any kind, and no `X-API-Key` anywhere.
- `grep -rn "apply_filters\|do_action" fopost-wp/src` returns nothing. There is no action, filter,
  or public client accessor another plugin could reuse.
- Its `AGENTS.md` states the plugin is the connector "and nothing else" and forbids adding social
  publishing to it.

So there is nothing to reuse and no credential to share. This plugin therefore stands alone on the
PHP SDK with its own API key, stored in `fopost_wc_api_key`. `Requires Plugins:` names `woocommerce`
only. If `fopost-wp` ever grows an outbound client, revisit this and delete the duplicate store.

## Parent Dependency

`fopost/sdk` resolves from Packagist (`"fopost/sdk": "^0.1"`). There is no `repositories` block in
`composer.json` and none should be added back; the source lives in the sibling `fopost-php` repo.

## Architecture

```
fopost-woocommerce.php     # Plugin header, constants, HPOS declaration, bootstrap
uninstall.php              # Data removal on delete
src/
├── Plugin.php             # Singleton, wires triggers, the queue worker and the admin screens
├── Activator.php          # Seeds default templates and toggles
├── Deactivator.php        # Cancels every queued job
├── Uninstaller.php        # Removes options, product meta, transients
├── Settings.php           # The option store and the sanitizer every write goes through
├── Template.php           # Placeholder renderer, filterable
├── Triggers.php           # The three WooCommerce events, each decides whether to queue
├── Scheduler.php          # Action Scheduler wrapper: enqueue, dedupe, cancel
├── ProductState.php       # Per-product opt-out, message override, last seen sale/stock state
├── Publisher.php          # The queue worker: render, send, record. Never throws
├── Log.php                # The delivery trail, in product meta
├── Api/
│   ├── ClientFactory.php  # Builds an SDK Client from the settings
│   ├── WpTransport.php    # SDK Transport over wp_remote_request
│   ├── PostGateway.php    # The one call Publisher makes, so failures are testable
│   ├── SdkPostGateway.php # PostGateway backed by the SDK
│   └── Directory.php      # Cached workspace and account lookups for the settings screen
└── Admin/
    ├── SettingsTab.php    # WC_Settings_Page under WooCommerce, Settings, FoPost
    ├── ProductMetaBox.php # The FoPost box on a product, plus the Post Now admin-post handler
    ├── LogPage.php        # WooCommerce, FoPost Activity
    └── Notices.php        # Failures parked in an option, shown on the next admin screen
```

**How a post happens.** A WooCommerce event reaches `Triggers`, which checks the trigger toggle, the
connection, and the product's opt-out, then calls `Scheduler::enqueue()`. Nothing else happens in
that request. Later, Action Scheduler fires `fopost_wc_publish_product`, `Publisher::handle()` runs,
`Template::render()` builds the message, and `SdkPostGateway` creates the post and publishes it.
The outcome, success or failure, is written to `Log`.

## Design Decisions Worth Keeping

- **Action Scheduler, not `wp_schedule_single_event`.** WooCommerce bundles it, it retries, and its
  queue is visible to the shop owner. Publishing a product must never wait on a network call.
- **The log lives in product meta, not a custom table.** The trail is short, capped at 20 entries,
  always read per product, and WordPress deletes it with the product. That keeps the plugin free of
  schema migrations and of direct SQL. A custom table would only pay for itself if the log were
  queried across products by something other than "show me the recent ones".
- **`WpTransport` replaces the SDK's cURL transport.** A plugin must use the WordPress HTTP API so
  the site's proxy, timeout and certificate settings apply, and so hosts that filter `WP_Http` see
  the request.
- **`Publisher` depends on `PostGateway`, not on `Client`.** That is what makes the failure path
  testable offline.
- **The API key is never rendered back into the form.** The field shows a hint and saves only a
  non-empty submission; a separate checkbox clears the stored key.
- **Opt-out is enforced at enqueue time**, not at send time, so a queued job runs even if the
  product is opted out afterwards. `Post Now` is an explicit user action and bypasses the toggles.

## Security Invariants

1. **Every admin write checks a capability and a nonce.** `manage_woocommerce` plus
   `check_admin_referer()` on the settings tab and the Post Now handler, `wp_verify_nonce()` plus
   `manage_woocommerce` and `edit_post` on the product meta box.
2. **Nothing is written to an option this plugin does not own.** `Settings::update()` intersects
   against `Settings::optionNames()`, and `SettingsTab::save()` further restricts to the fields of
   the section on screen, so saving one section can never clobber another.
3. **Every stored value passes `Settings::sanitize()`.** Unknown keys are dropped, identifiers are
   stripped to identifier characters, checkboxes only ever store `yes` or `no`, and templates are
   length capped.
4. **No product data reaches a social network as markup.** `Template::text()` strips tags, decodes
   entities, and strips again.
5. **The queue worker never throws.** A failed delivery is logged and surfaced as an admin notice.
   A fatal in an Action Scheduler job would poison the store's queue.
6. **No direct SQL outside `Uninstaller`**, where there is no API for a `LIKE` sweep of meta keys.

## Option and Meta Keys

| Key | Meaning |
|---|---|
| `fopost_wc_api_key` | The FoPost API key |
| `fopost_wc_workspace_id` | Which workspace posts go to |
| `fopost_wc_accounts` | Which connected accounts posts go to |
| `fopost_wc_attach_image` | Whether the featured image rides along |
| `fopost_wc_trigger_{published,on_sale,back_in_stock}` | Per-trigger switch |
| `fopost_wc_template_{published,on_sale,back_in_stock}` | Per-trigger message |
| `fopost_wc_notices` | Failures waiting to be shown |
| `fopost_wc_db_version` | Schema marker |
| `_fopost_wc_disabled` | Product meta: never post this product |
| `_fopost_wc_message` | Product meta: message override |
| `_fopost_wc_log` | Product meta: the delivery trail |
| `_fopost_wc_stock_status` | Product meta: last seen stock status, for edge detection |
| `_fopost_wc_on_sale` | Product meta: last seen sale state, for edge detection |

Never rename a key without a migration that reads the old value and writes the new one.

## API Contract

The SDK owns the transport, but the shape matters when reading its code:

- Base URL `https://api.fopost.com/v1`, overridable through the `fopost_wc_api_base_url` filter
- Auth is the header `X-API-Key`, not `Authorization: Bearer`
- Success bodies are wrapped in `{"data": ...}`; errors are `{"error": "<code>", "message": "..."}`
- The SDK retries 429 honoring `Retry-After`, capped at 60s. Action Scheduler retries everything else
- Publishing is create then publish, and publish returns when delivery is **queued**, not live

## Commands

```bash
composer install
composer test                              # phpunit, fully offline
composer lint                              # phpcs against phpcs.xml.dist
php -d xdebug.mode=off vendor/bin/phpcs
php -d xdebug.mode=off vendor/bin/phpcbf   # autofix
make build                                 # distributable zip in dist/
make version-check                         # header and readme.txt agree
make version-bump V=x.y.z
```

Regenerate the translation template after touching a user-facing string:

```bash
xgettext --language=PHP --from-code=UTF-8 --no-wrap \
  --keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_html_e \
  --keyword=esc_attr__ --keyword=esc_attr_e --keyword=_x:1,2c --keyword=_n:1,2 \
  --add-comments=translators: -o languages/fopost-woocommerce.pot \
  fopost-woocommerce.php uninstall.php $(find src -name '*.php' | sort)
```

Then restore the header block (no email addresses) as it stands in the committed file.

## Testing

PHPUnit with hand written WordPress, WooCommerce and Action Scheduler stubs in
`tests/bootstrap.php`, mirroring how `fopost-wp` tests. No Brain Monkey, no WP_Mock, no database,
no network. `tests/TestCase.php` resets every stubbed store between tests and offers `connect()`,
`product()` and `queue()` helpers.

What is covered, and what is worth covering: the template renderer substituting every placeholder
and never emitting markup, a product publish queueing exactly one action, an opted-out product
queueing none, the settings sanitizer refusing bad input, and an API failure being recorded rather
than thrown into the request. Add a case when something can fail silently and expensively, not for
layout or copy.

## Conventions

- Every PHP file starts with `declare(strict_types=1);` and `defined('ABSPATH') || exit;`
- Follow `phpcs.xml.dist`: WordPress security, I18n, DB and PHP sniffs on a PSR-style codebase.
  Do not run the raw `--standard=WordPress` ruleset, this codebase intentionally does not follow its
  formatting rules
- `phpcs:ignore` only with a reason on the same line
- Options, hooks, transients and functions use the `fopost_wc_` prefix; constants use `FOPOST_WC_`;
  product meta uses `_fopost_wc_`
- Comments are short and explain a why, never narrate obvious code

## Releasing

`make version-bump V=x.y.z` (it moves the plugin header, `FOPOST_WC_VERSION` and the readme
`Stable tag` together), `make version-check`, `make lint`, `make test`, then tag `vx.y.z` and push.
`.github/workflows/release.yml` builds the zip with production dependencies only and attaches it to
a GitHub release.

**WordPress.org hosting requires a one-time manual plugin review submission.** The listing does not
exist until a human uploads `make build`'s zip to https://wordpress.org/plugins/developers/add/ and
the review team approves it, which creates the SVN repository.

Once the SVN repository exists, `make` drives it:

| Target | What it does |
| :--- | :--- |
| `make svn-checkout` | One-time checkout into `.svn-wp/` (gitignored) |
| `make svn-sync` | Production build rsynced into `trunk`, `.distignore` respected |
| `make svn-diff` | Review what would be committed |
| `make svn-tag` | Stage `tags/$(VERSION)` as a local copy of trunk |
| `make svn-push` | Commit trunk and the tag in **one** revision |
| `make svn-assets` | Push `wp-assets/` to the SVN `assets/` branch |
| `make release` | lint → test → sync → tag → push |

Trunk and the tag must land in a single revision: WordPress.org reads trunk's `Stable tag` on
commit and keeps the previous version if that tag does not exist yet. `WP_ORG_SVN_USERNAME` in the
environment supplies `--username`; leave it unset to be prompted.

`wp-assets/` holds the directory artwork and is not tracked in git: `banner-1544x500.png`,
`banner-772x250.png`, `icon-128x128.png`, `icon-256x256.png`, and `screenshot-1.png` through
`screenshot-4.png` matching the readme's Screenshots section.

The commented-out `wp-deploy` job in `release.yml` is the alternative to `make release`; it needs
the repository secrets `WP_ORG_SVN_USERNAME` and `WP_ORG_SVN_PASSWORD` (the same names `fopost-wp`
uses). Uncomment it at that point.

## Git

Conventional Commits, atomic, one logical change each. No trailer blocks. Branch
`feature/<description>` off a fresh `main`. Push the branch and hand over the compare link, never
run `gh pr create`.
