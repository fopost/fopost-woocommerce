<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Tests\Unit;

use Fopost\WooCommerce\Settings;
use Fopost\WooCommerce\Tests\TestCase;

class SettingsTest extends TestCase
{
    public function testUnknownKeysAreDropped(): void
    {
        $clean = Settings::sanitize(['some_other_plugin_option' => 'value', 'admin_email' => 'x']);

        $this->assertSame([], $clean);
    }

    public function testUpdateRefusesOptionsThePluginDoesNotOwn(): void
    {
        Settings::update(['siteurl' => 'https://evil.example', Settings::OPTION_WORKSPACE_ID => 'ws-1']);

        $this->assertFalse(get_option('siteurl'));
        $this->assertSame('ws-1', Settings::workspaceId());
    }

    public function testCheckboxesOnlyEverStoreYesOrNo(): void
    {
        $clean = Settings::sanitize([
            Settings::triggerOptionName(Settings::TRIGGER_PUBLISHED)     => 'yes',
            Settings::triggerOptionName(Settings::TRIGGER_ON_SALE)       => 'definitely',
            Settings::triggerOptionName(Settings::TRIGGER_BACK_IN_STOCK) => ['array'],
        ]);

        $this->assertSame('yes', $clean[Settings::triggerOptionName(Settings::TRIGGER_PUBLISHED)]);
        $this->assertSame('no', $clean[Settings::triggerOptionName(Settings::TRIGGER_ON_SALE)]);
        $this->assertSame('no', $clean[Settings::triggerOptionName(Settings::TRIGGER_BACK_IN_STOCK)]);
    }

    public function testIdentifiersAreStrippedToIdentifierCharacters(): void
    {
        $clean = Settings::sanitize([
            Settings::OPTION_WORKSPACE_ID => "  ws_1-abc\n<script>  ",
            Settings::OPTION_ACCOUNTS     => ['acc-1', 'acc-1', '', 'acc 2!', ['nested']],
        ]);

        $this->assertSame('ws_1-abcscript', $clean[Settings::OPTION_WORKSPACE_ID]);
        $this->assertSame(['acc-1', 'acc2'], $clean[Settings::OPTION_ACCOUNTS]);
    }

    public function testAnAbsentApiKeyLeavesTheStoredKeyAlone(): void
    {
        update_option(Settings::OPTION_API_KEY, 'fp_existing');

        $clean = Settings::sanitize([Settings::OPTION_API_KEY => '']);
        Settings::update($clean);

        $this->assertArrayNotHasKey(Settings::OPTION_API_KEY, $clean);
        $this->assertSame('fp_existing', Settings::apiKey());
    }

    public function testTheClearCheckboxWipesTheApiKey(): void
    {
        update_option(Settings::OPTION_API_KEY, 'fp_existing');

        Settings::update(Settings::sanitize([
            Settings::FIELD_CLEAR_API_KEY => 'yes',
            Settings::OPTION_API_KEY      => 'fp_ignored',
        ]));

        $this->assertSame('', Settings::apiKey());
    }

    public function testTheClearFlagIsNeverStored(): void
    {
        Settings::update(Settings::sanitize([Settings::FIELD_CLEAR_API_KEY => 'yes']));

        $this->assertFalse(get_option(Settings::FIELD_CLEAR_API_KEY));
    }

    public function testAnEmptyTemplateFallsBackToTheDefault(): void
    {
        $option = Settings::templateOptionName(Settings::TRIGGER_PUBLISHED);

        $clean = Settings::sanitize([$option => "   \n  "]);

        $this->assertSame(Settings::defaultTemplates()[Settings::TRIGGER_PUBLISHED], $clean[$option]);
    }

    public function testAnOversizedTemplateIsTruncated(): void
    {
        $option = Settings::templateOptionName(Settings::TRIGGER_ON_SALE);

        $clean = Settings::sanitize([$option => str_repeat('a', 9000)]);

        $this->assertSame(5000, strlen($clean[$option]));
    }

    public function testIsConfiguredNeedsAllThreePieces(): void
    {
        $this->assertFalse(Settings::isConfigured());

        update_option(Settings::OPTION_API_KEY, 'fp_key');
        $this->assertFalse(Settings::isConfigured());

        update_option(Settings::OPTION_WORKSPACE_ID, 'ws-1');
        $this->assertFalse(Settings::isConfigured());

        update_option(Settings::OPTION_ACCOUNTS, ['acc-1']);
        $this->assertTrue(Settings::isConfigured());
    }

    public function testAnUnknownTriggerIsNeverEnabled(): void
    {
        update_option('fopost_wc_trigger_refunded', 'yes');

        $this->assertFalse(Settings::triggerEnabled('refunded'));
    }
}
