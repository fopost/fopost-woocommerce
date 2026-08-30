<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

/**
 * The plugin's stored configuration: FoPost connection, trigger toggles, message templates.
 *
 * Every value lives in its own `fopost_wc_` option so the WooCommerce settings
 * API can render it directly. Writes always go through sanitize().
 */
final class Settings
{
    public const TRIGGER_PUBLISHED     = 'published';
    public const TRIGGER_ON_SALE       = 'on_sale';
    public const TRIGGER_BACK_IN_STOCK = 'back_in_stock';

    public const TRIGGERS = [
        self::TRIGGER_PUBLISHED,
        self::TRIGGER_ON_SALE,
        self::TRIGGER_BACK_IN_STOCK,
    ];

    public const OPTION_API_KEY      = 'fopost_wc_api_key';
    public const OPTION_WORKSPACE_ID = 'fopost_wc_workspace_id';
    public const OPTION_ACCOUNTS     = 'fopost_wc_accounts';
    public const OPTION_ATTACH_IMAGE = 'fopost_wc_attach_image';

    /** Request-only key: ticking it clears the stored API key. Never persisted. */
    public const FIELD_CLEAR_API_KEY = 'fopost_wc_api_key_clear';

    /** Longest message we will accept in a template field. */
    private const MAX_TEMPLATE_LENGTH = 5000;

    /**
     * Default message per trigger. Placeholders are resolved by Template.
     *
     * @return array<string, string>
     */
    public static function defaultTemplates(): array
    {
        return [
            self::TRIGGER_PUBLISHED => __(
                "New in the shop: {product_name}, now {price}\n\n{short_description}\n\n{permalink}",
                'fopost-woocommerce'
            ),
            self::TRIGGER_ON_SALE => __(
                "On sale now: {product_name} is {sale_price}, down from {price}.\n\n{permalink}",
                'fopost-woocommerce'
            ),
            self::TRIGGER_BACK_IN_STOCK => __(
                "Back in stock: {product_name}, {price}\n\n{permalink}",
                'fopost-woocommerce'
            ),
        ];
    }

    public static function triggerOptionName(string $trigger): string
    {
        return 'fopost_wc_trigger_' . $trigger;
    }

    public static function templateOptionName(string $trigger): string
    {
        return 'fopost_wc_template_' . $trigger;
    }

    /**
     * Every option this plugin owns, for uninstall.
     *
     * @return array<int, string>
     */
    public static function optionNames(): array
    {
        $names = [
            self::OPTION_API_KEY,
            self::OPTION_WORKSPACE_ID,
            self::OPTION_ACCOUNTS,
            self::OPTION_ATTACH_IMAGE,
        ];

        foreach (self::TRIGGERS as $trigger) {
            $names[] = self::triggerOptionName($trigger);
            $names[] = self::templateOptionName($trigger);
        }

        return $names;
    }

    public static function seedDefaults(): void
    {
        foreach (self::defaultTemplates() as $trigger => $template) {
            add_option(self::templateOptionName($trigger), $template);
            add_option(self::triggerOptionName($trigger), 'no');
        }

        add_option(self::OPTION_ATTACH_IMAGE, 'yes');
    }

    public static function apiKey(): string
    {
        $key = get_option(self::OPTION_API_KEY, '');

        return is_string($key) ? trim($key) : '';
    }

    /**
     * A short, non-secret hint for the settings screen. Never the key itself.
     */
    public static function apiKeyHint(): string
    {
        $key = self::apiKey();

        if ($key === '') {
            return '';
        }

        return str_repeat('*', 4) . substr($key, -4);
    }

    public static function workspaceId(): string
    {
        $id = get_option(self::OPTION_WORKSPACE_ID, '');

        return is_string($id) ? trim($id) : '';
    }

    /** @return array<int, string> */
    public static function accountIds(): array
    {
        $accounts = get_option(self::OPTION_ACCOUNTS, []);

        if (is_string($accounts)) {
            $accounts = $accounts === '' ? [] : explode(',', $accounts);
        }

        if (! is_array($accounts)) {
            return [];
        }

        return self::cleanIdList($accounts);
    }

    public static function attachesImage(): bool
    {
        return get_option(self::OPTION_ATTACH_IMAGE, 'yes') === 'yes';
    }

    public static function triggerEnabled(string $trigger): bool
    {
        if (! in_array($trigger, self::TRIGGERS, true)) {
            return false;
        }

        return get_option(self::triggerOptionName($trigger), 'no') === 'yes';
    }

    public static function template(string $trigger): string
    {
        if (! in_array($trigger, self::TRIGGERS, true)) {
            return '';
        }

        $stored = get_option(self::templateOptionName($trigger), null);

        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        return self::defaultTemplates()[$trigger] ?? '';
    }

    /**
     * Whether the plugin has everything it needs to send a post.
     */
    public static function isConfigured(): bool
    {
        return self::apiKey() !== '' && self::workspaceId() !== '' && self::accountIds() !== [];
    }

    /**
     * Normalize a raw settings submission into the options to write.
     *
     * Unknown keys are dropped, every value is coerced to its declared shape, and
     * anything that fails validation falls back to a safe default rather than being
     * stored. The caller is responsible for the capability and nonce checks.
     *
     * @param array<string, mixed> $raw Unslashed request data.
     *
     * @return array<string, mixed> Option name to value.
     */
    public static function sanitize(array $raw): array
    {
        $clean = [];

        // The form never renders the stored key, so an empty submission means "leave it alone".
        if (self::checkbox($raw[self::FIELD_CLEAR_API_KEY] ?? 'no') === 'yes') {
            $clean[self::OPTION_API_KEY] = '';
        } elseif (array_key_exists(self::OPTION_API_KEY, $raw)) {
            $submitted = is_scalar($raw[self::OPTION_API_KEY]) ? trim((string) $raw[self::OPTION_API_KEY]) : '';

            if ($submitted !== '') {
                $clean[self::OPTION_API_KEY] = sanitize_text_field($submitted);
            }
        }

        if (array_key_exists(self::OPTION_WORKSPACE_ID, $raw)) {
            $clean[self::OPTION_WORKSPACE_ID] = self::cleanId(
                is_scalar($raw[self::OPTION_WORKSPACE_ID]) ? (string) $raw[self::OPTION_WORKSPACE_ID] : ''
            );
        }

        if (array_key_exists(self::OPTION_ACCOUNTS, $raw)) {
            $accounts = $raw[self::OPTION_ACCOUNTS];

            if (is_string($accounts)) {
                $accounts = $accounts === '' ? [] : explode(',', $accounts);
            }

            $clean[self::OPTION_ACCOUNTS] = is_array($accounts) ? self::cleanIdList($accounts) : [];
        }

        if (array_key_exists(self::OPTION_ATTACH_IMAGE, $raw)) {
            $clean[self::OPTION_ATTACH_IMAGE] = self::checkbox($raw[self::OPTION_ATTACH_IMAGE]);
        }

        foreach (self::TRIGGERS as $trigger) {
            $toggle = self::triggerOptionName($trigger);
            if (array_key_exists($toggle, $raw)) {
                $clean[$toggle] = self::checkbox($raw[$toggle]);
            }

            $template = self::templateOptionName($trigger);
            if (array_key_exists($template, $raw)) {
                $clean[$template] = self::cleanTemplate(
                    is_scalar($raw[$template]) ? (string) $raw[$template] : '',
                    $trigger
                );
            }
        }

        return $clean;
    }

    /**
     * Persist an already sanitized settings map.
     *
     * @param array<string, mixed> $clean
     */
    public static function update(array $clean): void
    {
        $allowed = self::optionNames();

        foreach ($clean as $option => $value) {
            if (! in_array($option, $allowed, true)) {
                continue;
            }

            update_option($option, $value);
        }
    }

    private static function checkbox(mixed $value): string
    {
        if (is_string($value)) {
            $value = strtolower(trim($value));
        }

        return in_array($value, ['yes', '1', 1, true, 'on', 'true'], true) ? 'yes' : 'no';
    }

    /**
     * Workspace and account ids are opaque API identifiers, so only the characters
     * an identifier can contain survive.
     */
    private static function cleanId(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_\-]/', '', trim($value));

        return is_string($value) ? substr($value, 0, 64) : '';
    }

    /**
     * @param array<int|string, mixed> $values
     *
     * @return array<int, string>
     */
    private static function cleanIdList(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $id = self::cleanId((string) $value);
            if ($id !== '' && ! in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    private static function cleanTemplate(string $value, string $trigger): string
    {
        $value = sanitize_textarea_field($value);

        if (trim($value) === '') {
            return self::defaultTemplates()[$trigger] ?? '';
        }

        return substr($value, 0, self::MAX_TEMPLATE_LENGTH);
    }
}
