<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Api;

defined('ABSPATH') || exit;

use Fopost\WooCommerce\Settings;
use Throwable;

/**
 * Reads the workspaces and accounts the stored API key can see, so the settings
 * screen can offer them as choices instead of asking for raw identifiers.
 *
 * Both lookups are cached, and both fail soft: a settings screen must still render
 * when FoPost is unreachable.
 */
final class Directory
{
    private const TRANSIENT_WORKSPACES = 'fopost_wc_workspaces';
    private const TRANSIENT_ACCOUNTS   = 'fopost_wc_accounts_';
    private const TTL                  = 300;

    /**
     * @return array<string, string> Workspace id to name.
     */
    public static function workspaces(): array
    {
        if (Settings::apiKey() === '') {
            return [];
        }

        $cached = get_transient(self::TRANSIENT_WORKSPACES);
        if (is_array($cached)) {
            return $cached;
        }

        $choices = [];

        try {
            foreach (ClientFactory::client()->workspaces()->list() as $workspace) {
                $choices[$workspace->id] = $workspace->name;
            }
        } catch (Throwable $e) {
            return [];
        }

        set_transient(self::TRANSIENT_WORKSPACES, $choices, self::TTL);

        return $choices;
    }

    /**
     * @return array<string, string> Account id to a readable label.
     */
    public static function accounts(string $workspaceId): array
    {
        if (Settings::apiKey() === '' || $workspaceId === '') {
            return [];
        }

        $key    = self::TRANSIENT_ACCOUNTS . md5($workspaceId);
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }

        $choices = [];

        try {
            foreach (ClientFactory::client()->accounts()->list($workspaceId) as $account) {
                $name = $account->name ?? $account->username ?? $account->id;
                $choices[$account->id] = sprintf('%s (%s)', $name, $account->platform);
            }
        } catch (Throwable $e) {
            return [];
        }

        set_transient($key, $choices, self::TTL);

        return $choices;
    }

    public static function flush(): void
    {
        delete_transient(self::TRANSIENT_WORKSPACES);

        $workspaceId = Settings::workspaceId();
        if ($workspaceId !== '') {
            delete_transient(self::TRANSIENT_ACCOUNTS . md5($workspaceId));
        }
    }
}
