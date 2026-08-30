<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

/**
 * The record of what this plugin sent, and what came back.
 *
 * Entries live in product meta rather than a custom table. The trail is short and
 * always read per product, WordPress deletes it with the product, and it keeps the
 * plugin free of schema migrations and of direct SQL. A custom table would only pay
 * for itself if the log were queried across products by anything but "show me the
 * recent ones", which it is not.
 */
final class Log
{
    public const META_KEY = '_fopost_wc_log';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    /** Entries kept per product. */
    private const LIMIT = 20;

    /**
     * Append an entry to a product's trail.
     *
     * @return array{time: int, trigger: string, status: string, post_id: string, message: string}
     */
    public static function record(
        int $productId,
        string $trigger,
        string $status,
        string $postId = '',
        string $message = ''
    ): array {
        $entry = [
            'time'    => time(),
            'trigger' => $trigger,
            'status'  => $status === self::STATUS_SUCCESS ? self::STATUS_SUCCESS : self::STATUS_FAILED,
            'post_id' => $postId,
            'message' => mb_substr($message, 0, 500),
        ];

        $entries = self::forProduct($productId);
        array_unshift($entries, $entry);

        update_post_meta($productId, self::META_KEY, array_slice($entries, 0, self::LIMIT));

        /**
         * Fires after a delivery attempt has been recorded.
         *
         * @param int                  $productId
         * @param array<string, mixed> $entry
         */
        do_action('fopost_wc_logged', $productId, $entry);

        return $entry;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forProduct(int $productId): array
    {
        $entries = get_post_meta($productId, self::META_KEY, true);

        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_filter($entries, 'is_array'));
    }

    public static function clear(int $productId): void
    {
        delete_post_meta($productId, self::META_KEY);
    }
}
