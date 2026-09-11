<?php

/**
 * Plugin Name:       FoPost for WooCommerce
 * Plugin URI:        https://fopost.com/docs/sdks/woocommerce
 * Description:       Post your WooCommerce products to social media through FoPost when they are published, go on sale, or come back in stock.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   9.9
 * Author:            FoPost
 * Author URI:        https://fopost.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fopost-for-woocommerce
 * Domain Path:       /languages
 */

declare(strict_types=1);

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants.
define('FOPOST_WC_VERSION', '0.1.0');
define('FOPOST_WC_FILE', __FILE__);
define('FOPOST_WC_DIR', plugin_dir_path(__FILE__));
define('FOPOST_WC_URL', plugin_dir_url(__FILE__));
define('FOPOST_WC_BASENAME', plugin_basename(__FILE__));

// Require Composer autoloader. The released build always bundles it; a source
// checkout without `composer install` simply stays inert.
if (! file_exists(FOPOST_WC_DIR . 'vendor/autoload.php')) {
    return;
}

require_once FOPOST_WC_DIR . 'vendor/autoload.php';

// HPOS: this add-on stores nothing on orders, so it is compatible either way.
add_action('before_woocommerce_init', static function (): void {
    if (! class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', FOPOST_WC_FILE, true);
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', FOPOST_WC_FILE, true);
});

register_activation_hook(__FILE__, [\Fopost\WooCommerce\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\Fopost\WooCommerce\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', [\Fopost\WooCommerce\Plugin::instance(), 'boot']);
