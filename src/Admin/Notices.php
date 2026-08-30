<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Admin;

defined('ABSPATH') || exit;

/**
 * Surfaces delivery failures in the WordPress admin.
 *
 * Failures happen in a queue worker, where there is nobody to tell, so they are
 * parked in an option and shown to the next shop manager who loads an admin screen.
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
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $notices = self::all();

        if ($notices === []) {
            return;
        }

        self::clear();

        foreach ($notices as $notice) {
            $productId = isset($notice['product_id']) ? (int) $notice['product_id'] : 0;
            $message   = isset($notice['message']) && is_string($notice['message']) ? $notice['message'] : '';
            $title     = $productId > 0 ? get_the_title($productId) : '';

            echo '<div class="notice notice-error is-dismissible"><p>';

            if (is_string($title) && $title !== '') {
                printf(
                    /* translators: 1: product name, 2: the reason the delivery failed. */
                    esc_html__('FoPost could not post %1$s: %2$s', 'fopost-woocommerce'),
                    '<strong>' . esc_html($title) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
                    esc_html($message)
                );
            } else {
                printf(
                    /* translators: %s: the reason the delivery failed. */
                    esc_html__('FoPost could not send a product post: %s', 'fopost-woocommerce'),
                    esc_html($message)
                );
            }

            echo '</p></div>';
        }
    }
}
