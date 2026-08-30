<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Runs on plugin deactivation. Drops queued work so a disabled plugin sends nothing.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        Scheduler::cancelAll();
    }
}
