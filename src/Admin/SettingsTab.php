<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Admin;

defined('ABSPATH') || exit;

use Fopost\WooCommerce\Api\Directory;
use Fopost\WooCommerce\Settings;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * WooCommerce, Settings, FoPost.
 *
 * A settings tab is where a shop's configuration belongs, so this add-on adds no
 * top-level menu of its own.
 */
final class SettingsTab extends WC_Settings_Page
{
    public function __construct()
    {
        $this->id    = 'fopost';
        $this->label = __('FoPost', 'fopost-for-woocommerce');

        parent::__construct();

        add_action('woocommerce_admin_field_fopost_wc_api_key', [$this, 'renderApiKeyField']);
    }

    /**
     * @param array<int, WC_Settings_Page> $pages
     *
     * @return array<int, WC_Settings_Page>
     */
    public static function register(array $pages): array
    {
        $pages[] = new self();

        return $pages;
    }

    /**
     * @return array<string, string>
     */
    protected function get_own_sections(): array
    {
        return [
            ''         => __('Connection', 'fopost-for-woocommerce'),
            'triggers' => __('Triggers and Messages', 'fopost-for-woocommerce'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function get_settings_for_default_section(): array
    {
        $workspaces = Directory::workspaces();
        $accounts   = Directory::accounts(Settings::workspaceId());

        $settings = [
            [
                'title' => __('FoPost Connection', 'fopost-for-woocommerce'),
                'type'  => 'title',
                'desc'  => sprintf(
                    /* translators: %s: link to the FoPost documentation. */
                    __('Create an API key in your FoPost workspace settings, then choose where product posts should go. %s', 'fopost-for-woocommerce'),
                    '<a href="https://fopost.com/docs" target="_blank" rel="noopener noreferrer">' . esc_html__('Read the docs', 'fopost-for-woocommerce') . '</a>'
                ),
                'id'    => 'fopost_wc_connection_options',
            ],
            [
                'title' => __('API Key', 'fopost-for-woocommerce'),
                'desc'  => __('Stored on this site and sent only to FoPost. Leave blank to keep the key you already saved.', 'fopost-for-woocommerce'),
                'id'    => Settings::OPTION_API_KEY,
                'type'  => 'fopost_wc_api_key',
            ],
        ];

        $settings[] = $workspaces === []
            ? [
                'title'    => __('Workspace ID', 'fopost-for-woocommerce'),
                'desc_tip' => __('Save a working API key to pick a workspace from a list instead.', 'fopost-for-woocommerce'),
                'id'       => Settings::OPTION_WORKSPACE_ID,
                'type'     => 'text',
                'default'  => '',
            ]
            : [
                'title'   => __('Workspace', 'fopost-for-woocommerce'),
                'id'      => Settings::OPTION_WORKSPACE_ID,
                'type'    => 'select',
                'options' => ['' => __('Select a workspace', 'fopost-for-woocommerce')] + $workspaces,
                'default' => '',
            ];

        $settings[] = $accounts === []
            ? [
                'title'    => __('Account IDs', 'fopost-for-woocommerce'),
                'desc_tip' => __('Comma separated. Save a workspace to pick connected accounts from a list instead.', 'fopost-for-woocommerce'),
                'id'       => Settings::OPTION_ACCOUNTS,
                'type'     => 'text',
                'default'  => '',
            ]
            : [
                'title'    => __('Accounts', 'fopost-for-woocommerce'),
                'desc_tip' => __('Every product post goes to the accounts you pick here.', 'fopost-for-woocommerce'),
                'id'       => Settings::OPTION_ACCOUNTS,
                'type'     => 'multiselect',
                'class'    => 'wc-enhanced-select',
                'options'  => $accounts,
                'default'  => [],
            ];

        $settings[] = [
            'title'   => __('Attach the product image', 'fopost-for-woocommerce'),
            'desc'    => __('Send the featured image with every product post.', 'fopost-for-woocommerce'),
            'id'      => Settings::OPTION_ATTACH_IMAGE,
            'type'    => 'checkbox',
            'default' => 'yes',
        ];

        $settings[] = [
            'type' => 'sectionend',
            'id'   => 'fopost_wc_connection_options',
        ];

        return $settings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function get_settings_for_triggers_section(): array
    {
        $settings = [
            [
                'title' => __('Triggers and Messages', 'fopost-for-woocommerce'),
                'type'  => 'title',
                'desc'  => sprintf(
                    /* translators: %s: the list of supported placeholders. */
                    __('Turn on the product events you want posted, and edit what each one says. Placeholders: %s', 'fopost-for-woocommerce'),
                    '<code>' . esc_html(implode('</code> <code>', \Fopost\WooCommerce\Template::PLACEHOLDERS)) . '</code>'
                ),
                'id'    => 'fopost_wc_trigger_options',
            ],
        ];

        foreach (self::triggerLabels() as $trigger => $label) {
            $settings[] = [
                'title'   => $label,
                'desc'    => __('Post to FoPost when this happens.', 'fopost-for-woocommerce'),
                'id'      => Settings::triggerOptionName($trigger),
                'type'    => 'checkbox',
                'default' => 'no',
            ];

            $settings[] = [
                'title'    => __('Message', 'fopost-for-woocommerce'),
                'desc_tip' => __('Leave blank to fall back to the default message.', 'fopost-for-woocommerce'),
                'id'       => Settings::templateOptionName($trigger),
                'type'     => 'textarea',
                'css'      => 'width: 100%; height: 100px;',
                'default'  => Settings::defaultTemplates()[$trigger] ?? '',
            ];
        }

        $settings[] = [
            'type' => 'sectionend',
            'id'   => 'fopost_wc_trigger_options',
        ];

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    public static function triggerLabels(): array
    {
        return [
            Settings::TRIGGER_PUBLISHED     => __('Product published', 'fopost-for-woocommerce'),
            Settings::TRIGGER_ON_SALE       => __('Product goes on sale', 'fopost-for-woocommerce'),
            Settings::TRIGGER_BACK_IN_STOCK => __('Product back in stock', 'fopost-for-woocommerce'),
        ];
    }

    /**
     * The API key never leaves the server, so the field renders empty with a hint
     * of the stored value rather than the value itself.
     *
     * @param array<string, mixed> $field
     */
    public function renderApiKeyField(array $field): void
    {
        $hint  = Settings::apiKeyHint();
        $id    = isset($field['id']) ? (string) $field['id'] : Settings::OPTION_API_KEY;
        $title = isset($field['title']) ? (string) $field['title'] : '';
        $desc  = isset($field['desc']) ? (string) $field['desc'] : '';

        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></label>
            </th>
            <td class="forminp forminp-password">
                <input
                    name="<?php echo esc_attr($id); ?>"
                    id="<?php echo esc_attr($id); ?>"
                    type="password"
                    autocomplete="off"
                    value=""
                    placeholder="<?php echo esc_attr($hint !== '' ? $hint : __('fp_...', 'fopost-for-woocommerce')); ?>"
                    class="input-text regular-input"
                />
                <?php if ($desc !== '') : ?>
                    <p class="description"><?php echo esc_html($desc); ?></p>
                <?php endif; ?>
                <?php if ($hint !== '') : ?>
                    <p>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(Settings::FIELD_CLEAR_API_KEY); ?>" value="yes" />
                            <?php esc_html_e('Remove the stored API key', 'fopost-for-woocommerce'); ?>
                        </label>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Saves the section currently on screen.
     *
     * WooCommerce saves one section at a time and omits unchecked boxes and empty
     * multiselects from the request, so the section's own field list decides both
     * which options may be written and what an absent field means.
     */
    public function save(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You are not allowed to change these settings.', 'fopost-for-woocommerce'), 403);
        }

        check_admin_referer('woocommerce-settings');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; Settings::sanitize() sanitizes every value and drops unknown keys.
        $posted = wp_unslash($_POST);
        $posted = is_array($posted) ? $posted : [];

        $fields = $this->get_settings_for_section($this->get_current_section());

        $clean = Settings::sanitize(array_merge(self::absentDefaults($fields), $posted));

        Settings::update(array_intersect_key($clean, array_flip(self::fieldIds($fields))));

        Directory::flush();

        WC_Admin_Settings::add_message(__('FoPost settings saved.', 'fopost-for-woocommerce'));
    }

    /**
     * What an omitted field means, per field type.
     *
     * @param array<int, array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private static function absentDefaults(array $fields): array
    {
        $defaults = [];

        foreach ($fields as $field) {
            if (! isset($field['id'], $field['type']) || ! is_string($field['id'])) {
                continue;
            }

            if ($field['type'] === 'checkbox') {
                $defaults[$field['id']] = 'no';
            } elseif ($field['type'] === 'multiselect') {
                $defaults[$field['id']] = [];
            }
        }

        return $defaults;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     *
     * @return array<int, string>
     */
    private static function fieldIds(array $fields): array
    {
        $ids = [];

        foreach ($fields as $field) {
            $type = isset($field['type']) ? (string) $field['type'] : '';

            if (! isset($field['id']) || ! is_string($field['id']) || in_array($type, ['title', 'sectionend'], true)) {
                continue;
            }

            $ids[] = $field['id'];
        }

        return $ids;
    }
}
