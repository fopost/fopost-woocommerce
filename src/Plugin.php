<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

use Fopost\WooCommerce\Admin\LogPage;
use Fopost\WooCommerce\Admin\Notices;
use Fopost\WooCommerce\Admin\ProductMetaBox;
use Fopost\WooCommerce\Admin\SettingsTab;

/**
 * Main plugin class. Wires the WooCommerce triggers, the queue worker and the admin screens.
 */
final class Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

    private function __construct()
    {
        // Singleton, use Plugin::instance().
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        if (! self::wooCommerceActive()) {
            add_action('admin_notices', [self::class, 'renderMissingWooCommerceNotice']);

            return;
        }

        (new Triggers())->register();

        // The queue worker. Runs in an Action Scheduler request, never in the request that published the product.
        add_action(Scheduler::HOOK, [Publisher::class, 'handle'], 10, 2);

        if (is_admin()) {
            add_filter('woocommerce_get_settings_pages', [SettingsTab::class, 'register']);
            (new ProductMetaBox())->register();
            (new LogPage())->register();
            (new Notices())->register();
        }
    }

    public static function wooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function renderMissingWooCommerceNotice(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('FoPost for WooCommerce needs WooCommerce 8.0 or newer to be installed and active.', 'fopost-for-woocommerce');
        echo '</p></div>';
    }
}
