<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Removes every trace of the plugin: options, product meta, transients.
 */
final class Uninstaller
{
    public static function uninstall(): void
    {
        self::removeOptions();
        self::removeProductMeta();
        self::clearTransients();
    }

    private static function removeOptions(): void
    {
        foreach (Settings::optionNames() as $option) {
            delete_option($option);
        }

        delete_option('fopost_wc_db_version');
        delete_option(\Fopost\WooCommerce\Admin\Notices::OPTION_KEY);
    }

    private static function removeProductMeta(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
                '_fopost_wc_%'
            )
        );
    }

    private static function clearTransients(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_fopost_wc_%',
                '_transient_timeout_fopost_wc_%'
            )
        );
    }
}
