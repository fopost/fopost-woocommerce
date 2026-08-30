<?php

/**
 * FoPost for WooCommerce uninstall.
 *
 * Fired when the plugin is deleted. Removes options, product meta, and transients.
 *
 * @package Fopost\WooCommerce
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (class_exists(\Fopost\WooCommerce\Uninstaller::class)) {
    \Fopost\WooCommerce\Uninstaller::uninstall();

    return;
}

// Minimal fallback cleanup without the autoloader.
$fopost_wc_options = [
    'fopost_wc_api_key',
    'fopost_wc_workspace_id',
    'fopost_wc_accounts',
    'fopost_wc_attach_image',
    'fopost_wc_trigger_published',
    'fopost_wc_trigger_on_sale',
    'fopost_wc_trigger_back_in_stock',
    'fopost_wc_template_published',
    'fopost_wc_template_on_sale',
    'fopost_wc_template_back_in_stock',
    'fopost_wc_db_version',
    'fopost_wc_notices',
];

foreach ($fopost_wc_options as $fopost_wc_option) {
    delete_option($fopost_wc_option);
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        '_fopost_wc_%'
    )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_fopost_wc_%',
        '_transient_timeout_fopost_wc_%'
    )
);
