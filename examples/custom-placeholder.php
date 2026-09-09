<?php

/**
 * Add your own template placeholder.
 *
 * Drop this in your theme's functions.php or a small site plugin, then use
 * {stock_quantity} in any FoPost message template.
 *
 * @package Fopost\WooCommerce\Examples
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_filter(
    'fopost_wc_template_placeholders',
    static function (array $placeholders, WC_Product $product, string $trigger): array {
        $placeholders['{stock_quantity}'] = (string) ($product->get_stock_quantity() ?? '');

        return $placeholders;
    },
    10,
    3
);

/**
 * Skip products in one category, whatever the trigger.
 */
add_filter(
    'fopost_wc_should_post',
    static function (bool $should, int $product_id, string $trigger): bool {
        if (has_term('clearance', 'product_cat', $product_id)) {
            return false;
        }

        return $should;
    },
    10,
    3
);

/**
 * Append a hashtag to every message.
 */
add_filter(
    'fopost_wc_rendered_message',
    static function (string $message, string $template, WC_Product $product, string $trigger): string {
        return $message . "\n\n#newarrival";
    },
    10,
    4
);
