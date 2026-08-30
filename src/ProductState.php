<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Per-product state: the shop manager's opt-out and message override, plus the
 * last known sale and stock state a trigger compares against.
 */
final class ProductState
{
    public const META_DISABLED     = '_fopost_wc_disabled';
    public const META_MESSAGE      = '_fopost_wc_message';
    public const META_STOCK_STATUS = '_fopost_wc_stock_status';
    public const META_ON_SALE      = '_fopost_wc_on_sale';

    public static function isOptedOut(int $productId): bool
    {
        return get_post_meta($productId, self::META_DISABLED, true) === 'yes';
    }

    public static function setOptedOut(int $productId, bool $optedOut): void
    {
        if ($optedOut) {
            update_post_meta($productId, self::META_DISABLED, 'yes');

            return;
        }

        delete_post_meta($productId, self::META_DISABLED);
    }

    /**
     * The per-product message override, or an empty string when there is none.
     */
    public static function messageOverride(int $productId): string
    {
        $message = get_post_meta($productId, self::META_MESSAGE, true);

        return is_string($message) ? trim($message) : '';
    }

    public static function setMessageOverride(int $productId, string $message): void
    {
        $message = trim($message);

        if ($message === '') {
            delete_post_meta($productId, self::META_MESSAGE);

            return;
        }

        update_post_meta($productId, self::META_MESSAGE, $message);
    }

    public static function lastStockStatus(int $productId): string
    {
        $status = get_post_meta($productId, self::META_STOCK_STATUS, true);

        return is_string($status) ? $status : '';
    }

    public static function rememberStockStatus(int $productId, string $status): void
    {
        update_post_meta($productId, self::META_STOCK_STATUS, $status);
    }

    public static function wasOnSale(int $productId): bool
    {
        return get_post_meta($productId, self::META_ON_SALE, true) === 'yes';
    }

    public static function rememberOnSale(int $productId, bool $onSale): void
    {
        update_post_meta($productId, self::META_ON_SALE, $onSale ? 'yes' : 'no');
    }
}
