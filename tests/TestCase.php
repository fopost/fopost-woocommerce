<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use WC_Product;

/**
 * Base test case. WordPress, WooCommerce and Action Scheduler stubs are loaded
 * from tests/bootstrap.php into the global namespace.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        fopost_wc_test_reset();
    }

    /**
     * A configured plugin: API key, workspace and one account.
     */
    protected function connect(): void
    {
        update_option('fopost_wc_api_key', 'fp_test_key');
        update_option('fopost_wc_workspace_id', 'ws-1');
        update_option('fopost_wc_accounts', ['acc-1']);
    }

    /**
     * @param array<string, mixed> $props
     */
    protected function product(int $id, array $props = []): WC_Product
    {
        $product = new WC_Product(array_merge(['id' => $id], $props));

        $GLOBALS['fopost_wc_test_products'][$id] = $product;

        return $product;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function queue(): array
    {
        return $GLOBALS['fopost_wc_test_queue'];
    }
}
