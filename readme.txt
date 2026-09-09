=== FoPost for WooCommerce ===
Contributors: alihesari
Donate link: https://fopost.com
Tags: woocommerce, social media, auto post, product marketing, scheduling
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.1.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Post your WooCommerce products to social media through FoPost when they are published, go on sale, or come back in stock.

== Description ==

FoPost for WooCommerce watches your shop and hands the moments worth announcing to [FoPost](https://fopost.com), which delivers them to your connected social accounts.

**Three triggers, each one optional**

* **Product published** when a product first goes live
* **Product goes on sale** the moment a sale price starts applying
* **Product back in stock** when stock returns after running out

Every trigger is off until you turn it on, and each one has its own message.

**Messages you write, with placeholders**

Write the copy once and let each product fill it in:

`{product_name}` `{price}` `{sale_price}` `{permalink}` `{short_description}` `{sku}` `{categories}`

The featured image goes along with the post, unless you turn that off. Developers can add their own placeholders with the `fopost_wc_template_placeholders` filter.

**Per-product control**

Every product gets a FoPost box on its edit screen: opt this one out entirely, write a message just for it, or press Post Now.

**It never slows down your shop**

Publishing a product does not wait on a network call. The work is handed to Action Scheduler, the queue that already runs your store, which retries a failed delivery and keeps a record you can inspect.

**A record of everything sent**

WooCommerce, FoPost Activity lists what was posted for which product, with the FoPost post ID. A delivery that fails is written down and raised as an admin notice rather than disappearing.

**What you need**

A FoPost account with an API key, at least one connected social account, and WooCommerce 8.0 or newer.

**Links**

* [FoPost](https://fopost.com) - the hosted publishing service this add-on posts through
* [Documentation](https://fopost.com/docs/sdks/woocommerce) - setup, placeholders, and the filters
* [Pricing](https://fopost.com/pricing) - what a FoPost account costs
* [Support](https://fopost.com/contact) - questions and problems

== Installation ==

1. Upload the plugin to `wp-content/plugins/fopost-for-woocommerce`, or install it through Plugins, Add New.
2. Activate the plugin. WooCommerce must be active first.
3. Go to **WooCommerce, Settings, FoPost** and paste your FoPost API key.
4. Save, then pick the workspace and the accounts your product posts should go to.
5. Open the **Triggers and Messages** section, switch on the events you want, and edit what each one says.

== Frequently Asked Questions ==

= Do I need a FoPost account? =

Yes. This plugin composes the post and hands it to FoPost, which owns the connections to the social networks.

= Do I need the FoPost connector plugin as well? =

No. This add-on talks to the FoPost API with its own API key. The separate FoPost plugin does the opposite job, letting FoPost publish into WordPress, and the two do not depend on each other.

= Will publishing a product feel slower? =

No. A trigger only queues a job. The API call happens later, in Action Scheduler, in a separate request.

= Can I stop one product from ever being posted? =

Yes. Tick **Never post this product** in the FoPost box on that product's edit screen.

= What happens if FoPost is unreachable? =

The attempt is recorded against the product as a failure, the reason appears as an admin notice, and Action Scheduler retries the job.

= Can I add my own placeholder? =

Yes. Use the `fopost_wc_template_placeholders` filter. There is a worked example in the plugin's `examples` folder.

= Is it compatible with High-Performance Order Storage? =

Yes. The plugin declares compatibility and never touches order storage at all.

== Screenshots ==

1. WooCommerce, Settings, FoPost: the connection, the workspace, and the accounts product posts go to.
2. Triggers and Messages: each product event with its own switch and its own copy.
3. The FoPost box on a product, with the opt-out, the custom message, and Post Now.
4. WooCommerce, FoPost Activity: what was sent, when, and the FoPost post ID.

== Third-Party Services ==

This plugin sends data to FoPost, a hosted publishing service at
[fopost.com](https://fopost.com). You need a FoPost account and an API key for
the plugin to do anything.

When a product you have enabled is published, goes on sale, or comes back in
stock, the plugin calls the FoPost API at `https://api.fopost.com` and sends the
post text rendered from your message template — which can include the product
name, short description, price and permalink — along with the product image and
the accounts you chose to post to. FoPost then delivers that post to those
social accounts. **Nothing is sent until you enter an API key and enable a
product event.**

Service terms: [Terms of Service](https://fopost.com/terms-of-service) &middot;
[Privacy Policy](https://fopost.com/privacy-policy) &middot;
[Data Processing Addendum](https://fopost.com/dpa).

== Changelog ==

= 0.1.0 =
* First release: product published, on sale, and back in stock triggers, each individually switchable.
* Message templates with product placeholders, plus filters for adding your own.
* Per-product opt-out, per-product message override, and a Post Now action.
* Queued delivery through Action Scheduler, an activity log, and admin notices on failure.

== Upgrade Notice ==

= 0.1.0 =
First release.
