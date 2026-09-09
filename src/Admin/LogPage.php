<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Admin;

defined('ABSPATH') || exit;

use Fopost\WooCommerce\Log;
use WP_Query;

/**
 * WooCommerce, FoPost Activity: what was posted, for which product, and what came back.
 */
final class LogPage
{
    public const MENU_SLUG = 'fopost-woocommerce-activity';

    /** Products scanned per page. */
    private const PER_PAGE = 20;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 60);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('FoPost Activity', 'fopost-for-woocommerce'),
            __('FoPost Activity', 'fopost-for-woocommerce'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You are not allowed to view this page.', 'fopost-for-woocommerce'), 403);
        }

        $paged = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.

        $query = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $paged,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_key'       => Log::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed lookup over a small set of products the plugin has touched.
            'no_found_rows'  => false,
        ]);

        echo '<div class="wrap"><h1>' . esc_html__('FoPost Activity', 'fopost-for-woocommerce') . '</h1>';

        if (! $query->have_posts()) {
            echo '<p>' . esc_html__('Nothing has been posted yet.', 'fopost-for-woocommerce') . '</p></div>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Product', 'fopost-for-woocommerce') . '</th>';
        echo '<th>' . esc_html__('When', 'fopost-for-woocommerce') . '</th>';
        echo '<th>' . esc_html__('Trigger', 'fopost-for-woocommerce') . '</th>';
        echo '<th>' . esc_html__('Result', 'fopost-for-woocommerce') . '</th>';
        echo '<th>' . esc_html__('FoPost Post ID', 'fopost-for-woocommerce') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($query->posts as $product) {
            $productId = (int) $product->ID;

            foreach (Log::forProduct($productId) as $entry) {
                self::renderRow($productId, $entry);
            }
        }

        echo '</tbody></table>';

        $links = paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'total'     => (int) $query->max_num_pages,
            'current'   => $paged,
            'type'      => 'plain',
        ]);

        if (is_string($links) && $links !== '') {
            echo '<p class="tablenav-pages">' . wp_kses_post($links) . '</p>';
        }

        echo '</div>';

        wp_reset_postdata();
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function renderRow(int $productId, array $entry): void
    {
        $status  = isset($entry['status']) ? (string) $entry['status'] : '';
        $time    = isset($entry['time']) ? (int) $entry['time'] : 0;
        $trigger = isset($entry['trigger']) ? (string) $entry['trigger'] : '';
        $postId  = isset($entry['post_id']) ? (string) $entry['post_id'] : '';
        $message = isset($entry['message']) ? (string) $entry['message'] : '';
        $labels  = SettingsTab::triggerLabels();

        echo '<tr>';

        printf(
            '<td><a href="%1$s">%2$s</a></td>',
            esc_url((string) get_edit_post_link($productId)),
            esc_html((string) get_the_title($productId))
        );

        echo '<td>' . esc_html($time > 0 ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $time) : '') . '</td>';
        echo '<td>' . esc_html($labels[$trigger] ?? $trigger) . '</td>';

        if ($status === Log::STATUS_SUCCESS) {
            echo '<td>' . esc_html__('Sent', 'fopost-for-woocommerce') . '</td>';
        } else {
            echo '<td><strong>' . esc_html__('Failed', 'fopost-for-woocommerce') . '</strong><br /><span class="description">' . esc_html($message) . '</span></td>';
        }

        echo '<td><code>' . esc_html($postId) . '</code></td>';
        echo '</tr>';
    }
}
