<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

/**
 * The queue in front of the FoPost API.
 *
 * Nothing this plugin does may slow down the request that published a product,
 * so a trigger only ever enqueues an Action Scheduler job. Action Scheduler ships
 * with WooCommerce and gives us retries and a visible queue for free, which is why
 * it is used instead of wp_schedule_single_event().
 */
final class Scheduler
{
    public const HOOK  = 'fopost_wc_publish_product';
    public const GROUP = 'fopost-woocommerce';

    public static function available(): bool
    {
        return function_exists('as_enqueue_async_action')
            && function_exists('as_has_scheduled_action');
    }

    /**
     * Queue one product post. Returns false when the job was not queued.
     */
    public static function enqueue(int $productId, string $trigger): bool
    {
        if ($productId <= 0 || ! in_array($trigger, Settings::TRIGGERS, true)) {
            return false;
        }

        if (! self::available()) {
            return false;
        }

        if (self::isQueued($productId, $trigger)) {
            return false;
        }

        $action = as_enqueue_async_action(self::HOOK, [$productId, $trigger], self::GROUP);

        return is_int($action) ? $action > 0 : (bool) $action;
    }

    public static function isQueued(int $productId, string $trigger): bool
    {
        if (! self::available()) {
            return false;
        }

        return (bool) as_has_scheduled_action(self::HOOK, [$productId, $trigger], self::GROUP);
    }

    /**
     * Drop every queued job. Called on deactivation so a disabled plugin sends nothing.
     */
    public static function cancelAll(): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK, [], self::GROUP);
        }
    }
}
