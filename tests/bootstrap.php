<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Test stubs intentionally mimic WordPress, WooCommerce and Action Scheduler functions.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test stubs mirror core implementations.

/**
 * WordPress, WooCommerce and Action Scheduler stubs for offline unit testing.
 *
 * The parent plugin (fopost/wordpress) tests the same way: hand written stubs
 * rather than a mocking framework, so the suite runs with nothing but PHPUnit.
 *
 * @package Fopost\WooCommerce\Tests
 */

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

require_once __DIR__ . '/../vendor/autoload.php';

/** Reset every stubbed store between tests. */
function fopost_wc_test_reset(): void
{
    $GLOBALS['fopost_wc_test_options']    = [];
    $GLOBALS['fopost_wc_test_meta']       = [];
    $GLOBALS['fopost_wc_test_transients'] = [];
    $GLOBALS['fopost_wc_test_queue']      = [];
    $GLOBALS['fopost_wc_test_products']   = [];
    $GLOBALS['fopost_wc_test_terms']      = [];
}

fopost_wc_test_reset();

// ── Options ──────────────────────────────────────────────────────────────────

if (! function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['fopost_wc_test_options'][$option] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $option, mixed $value, string|bool $autoload = 'yes'): bool
    {
        $GLOBALS['fopost_wc_test_options'][$option] = $value;

        return true;
    }
}

if (! function_exists('add_option')) {
    function add_option(string $option, mixed $value = '', string $deprecated = '', string|bool $autoload = 'yes'): bool
    {
        if (array_key_exists($option, $GLOBALS['fopost_wc_test_options'])) {
            return false;
        }

        $GLOBALS['fopost_wc_test_options'][$option] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        unset($GLOBALS['fopost_wc_test_options'][$option]);

        return true;
    }
}

// ── Transients ───────────────────────────────────────────────────────────────

if (! function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        return $GLOBALS['fopost_wc_test_transients'][$key] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $ttl = 0): bool
    {
        $GLOBALS['fopost_wc_test_transients'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset($GLOBALS['fopost_wc_test_transients'][$key]);

        return true;
    }
}

// ── Post meta ────────────────────────────────────────────────────────────────

if (! function_exists('get_post_meta')) {
    function get_post_meta(int $postId, string $key = '', bool $single = false): mixed
    {
        $value = $GLOBALS['fopost_wc_test_meta'][$postId][$key] ?? '';

        return $single ? $value : ($value === '' ? [] : [$value]);
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta(int $postId, string $key, mixed $value): bool
    {
        $GLOBALS['fopost_wc_test_meta'][$postId][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_post_meta')) {
    function delete_post_meta(int $postId, string $key, mixed $value = ''): bool
    {
        unset($GLOBALS['fopost_wc_test_meta'][$postId][$key]);

        return true;
    }
}

// ── Hooks ────────────────────────────────────────────────────────────────────

if (! function_exists('add_action')) {
    function add_action(string $hook, mixed $callback, int $priority = 10, int $args = 1): bool
    {
        return true;
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook, mixed $callback, int $priority = 10, int $args = 1): bool
    {
        return true;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
        // No-op stub.
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

// ── Sanitizing and escaping ──────────────────────────────────────────────────

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $removeBreaks = false): string
    {
        $text = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);
        $text = strip_tags($text);

        if ($removeBreaks) {
            $text = (string) preg_replace('/[\r\n\t ]+/', ' ', $text);
        }

        return trim($text);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('absint')) {
    function absint(mixed $maybeint): int
    {
        return abs((int) $maybeint);
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1): mixed
    {
        return parse_url($url, $component);
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return false;
    }
}

// ── Posts and terms ──────────────────────────────────────────────────────────

if (! function_exists('get_permalink')) {
    function get_permalink(int $postId): string
    {
        return 'https://shop.example/product/' . $postId;
    }
}

if (! function_exists('wp_get_post_terms')) {
    function wp_get_post_terms(int $postId, string $taxonomy = '', array $args = []): array
    {
        return $GLOBALS['fopost_wc_test_terms'][$postId] ?? [];
    }
}

if (! function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url(int $attachmentId, string $size = 'thumbnail'): string|false
    {
        return $attachmentId > 0 ? 'https://shop.example/uploads/' . $attachmentId . '.jpg' : false;
    }
}

// ── WooCommerce ──────────────────────────────────────────────────────────────

if (! class_exists('WC_Product')) {
    /** Minimal stand in for the WooCommerce product object. */
    class WC_Product
    {
        /** @param array<string, mixed> $props */
        public function __construct(private array $props = [])
        {
        }

        public function get_id(): int
        {
            return (int) ($this->props['id'] ?? 0);
        }

        public function get_name(): string
        {
            return (string) ($this->props['name'] ?? '');
        }

        public function get_price(): string
        {
            return (string) ($this->props['price'] ?? '');
        }

        public function get_regular_price(): string
        {
            return (string) ($this->props['regular_price'] ?? '');
        }

        public function get_sale_price(): string
        {
            return (string) ($this->props['sale_price'] ?? '');
        }

        public function get_short_description(): string
        {
            return (string) ($this->props['short_description'] ?? '');
        }

        public function get_sku(): string
        {
            return (string) ($this->props['sku'] ?? '');
        }

        public function get_image_id(): int
        {
            return (int) ($this->props['image_id'] ?? 0);
        }

        public function is_on_sale(): bool
        {
            return (bool) ($this->props['on_sale'] ?? false);
        }
    }
}

if (! class_exists('WP_Post')) {
    /** Minimal stand in for a WordPress post. */
    class WP_Post
    {
        public int $ID = 0;
        public string $post_type = 'post'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

        public function __construct(int $id = 0, string $type = 'post')
        {
            $this->ID = $id;
            $this->post_type = $type;
        }
    }
}

if (! function_exists('wc_get_product')) {
    function wc_get_product(int $productId): mixed
    {
        return $GLOBALS['fopost_wc_test_products'][$productId] ?? null;
    }
}

if (! function_exists('wc_price')) {
    function wc_price(float $amount): string
    {
        return '<span class="amount">&#36;' . number_format($amount, 2) . '</span>';
    }
}

// ── Action Scheduler ─────────────────────────────────────────────────────────

if (! function_exists('as_enqueue_async_action')) {
    function as_enqueue_async_action(string $hook, array $args = [], string $group = ''): int
    {
        $GLOBALS['fopost_wc_test_queue'][] = ['hook' => $hook, 'args' => $args, 'group' => $group];

        return count($GLOBALS['fopost_wc_test_queue']);
    }
}

if (! function_exists('as_has_scheduled_action')) {
    function as_has_scheduled_action(string $hook, array $args = [], string $group = ''): bool
    {
        foreach ($GLOBALS['fopost_wc_test_queue'] as $job) {
            if ($job['hook'] === $hook && $job['args'] === $args && $job['group'] === $group) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('as_unschedule_all_actions')) {
    function as_unschedule_all_actions(string $hook, array $args = [], string $group = ''): void
    {
        $GLOBALS['fopost_wc_test_queue'] = [];
    }
}
