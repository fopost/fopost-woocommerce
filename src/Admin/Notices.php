<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Admin;

defined('ABSPATH') || exit;

/**
 * Surfaces delivery failures in the WordPress admin.
 *
 * Failures happen in a queue worker, where there is nobody to tell, so they are
 * parked in an option and shown once, on a WooCommerce screen, as a single notice
 * pointing at the activity log. Never on unrelated admin pages.
 */
final class Notices
{
    public const OPTION_KEY = 'fopost_wc_notices';

    /** Notices kept. The oldest fall off. */
    private const LIMIT = 5;

    public function register(): void
    {
        add_action('admin_notices', [self::class, 'render']);
    }

    public static function add(int $productId, string $message): void
    {
        $notices = self::all();

        array_unshift($notices, [
            'product_id' => $productId,
            'message'    => mb_substr($message, 0, 300),
            'time'       => time(),
        ]);

        update_option(self::OPTION_KEY, array_slice($notices, 0, self::LIMIT), false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $notices = get_option(self::OPTION_KEY, []);

        if (! is_array($notices)) {
            return [];
        }

        return array_values(array_filter($notices, 'is_array'));
    }

    public static function clear(): void
    {
        delete_option(self::OPTION_KEY);
    }

    public static function render(): void
    {
        if (! current_user_can('manage_woocommerce') || ! self::onWooCommerceScreen()) {
            return;
        }

        $notices = self::all();

        if ($notices === []) {
            return;
        }

        self::clear();

        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html(self::summary($notices));
        echo ' <a href="' . esc_url(self::logUrl()) . '">';
        echo esc_html__('View FoPost Activity', 'fopost-for-woocommerce');
        echo '</a></p></div>';
    }

    /** Only WooCommerce's own screens, so the rest of the dashboard stays untouched. */
    private static function onWooCommerceScreen(): bool
    {
        if (! function_exists('wc_get_screen_ids') || ! function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();

        if ($screen === null) {
            return false;
        }

        return in_array($screen->id, wc_get_screen_ids(), true);
    }

    /**
     * @param array<int, array<string, mixed>> $notices
     */
    private static function summary(array $notices): string
    {
        if (count($notices) > 1) {
            return sprintf(
                /* translators: %d: how many product posts failed. */
                _n(
                    'FoPost could not send %d product post.',
                    'FoPost could not send %d product posts.',
                    count($notices),
                    'fopost-for-woocommerce'
                ),
                count($notices)
            );
        }

        $notice    = $notices[0];
        $productId = isset($notice['product_id']) ? (int) $notice['product_id'] : 0;
        $message   = isset($notice['message']) && is_string($notice['message']) ? $notice['message'] : '';
        $title     = $productId > 0 ? get_the_title($productId) : '';

        if (is_string($title) && $title !== '') {
            return sprintf(
                /* translators: 1: product name, 2: the reason the delivery failed. */
                __('FoPost could not post %1$s: %2$s', 'fopost-for-woocommerce'),
                $title,
                $message
            );
        }

        return sprintf(
            /* translators: %s: the reason the delivery failed. */
            __('FoPost could not send a product post: %s', 'fopost-for-woocommerce'),
            $message
        );
    }

    private static function logUrl(): string
    {
        return admin_url('admin.php?page=' . LogPage::MENU_SLUG);
    }
}
