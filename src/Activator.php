<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Runs on plugin activation.
 */
final class Activator
{
    public static function activate(): void
    {
        Settings::seedDefaults();

        if (get_option('fopost_wc_db_version') === false) {
            add_option('fopost_wc_db_version', '1.0.0');
        }
    }
}
