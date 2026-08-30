<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Tests\Unit;

use Fopost\WooCommerce\ProductState;
use Fopost\WooCommerce\Scheduler;
use Fopost\WooCommerce\Settings;
use Fopost\WooCommerce\Tests\TestCase;
use Fopost\WooCommerce\Triggers;
use WP_Post;

class TriggersTest extends TestCase
{
    private Triggers $triggers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->triggers = new Triggers();
        $this->connect();
        update_option(Settings::triggerOptionName(Settings::TRIGGER_PUBLISHED), 'yes');
        update_option(Settings::triggerOptionName(Settings::TRIGGER_ON_SALE), 'yes');
        update_option(Settings::triggerOptionName(Settings::TRIGGER_BACK_IN_STOCK), 'yes');
    }

    public function testPublishingAProductQueuesExactlyOneAction(): void
    {
        $this->triggers->onPostStatusTransition('publish', 'draft', new WP_Post(11, 'product'));

        $this->assertCount(1, $this->queue());
        $this->assertSame(
            ['hook' => Scheduler::HOOK, 'args' => [11, Settings::TRIGGER_PUBLISHED], 'group' => Scheduler::GROUP],
            $this->queue()[0]
        );
    }

    public function testTheSameProductIsNotQueuedTwice(): void
    {
        $this->triggers->onPostStatusTransition('publish', 'draft', new WP_Post(11, 'product'));
        $this->triggers->onPostStatusTransition('publish', 'pending', new WP_Post(11, 'product'));

        $this->assertCount(1, $this->queue());
    }

    public function testAnOptedOutProductQueuesNothing(): void
    {
        ProductState::setOptedOut(11, true);

        $this->triggers->onPostStatusTransition('publish', 'draft', new WP_Post(11, 'product'));

        $this->assertSame([], $this->queue());
    }

    public function testADisabledTriggerQueuesNothing(): void
    {
        update_option(Settings::triggerOptionName(Settings::TRIGGER_PUBLISHED), 'no');

        $this->triggers->onPostStatusTransition('publish', 'draft', new WP_Post(11, 'product'));

        $this->assertSame([], $this->queue());
    }

    public function testAnUnconnectedSiteQueuesNothing(): void
    {
        delete_option(Settings::OPTION_API_KEY);

        $this->triggers->onPostStatusTransition('publish', 'draft', new WP_Post(11, 'product'));

        $this->assertSame([], $this->queue());
    }

    public function testANonProductQueuesNothing(): void
    {
        $this->triggers->onPostStatusTransition('publish', 'draft', new WP_Post(11, 'post'));

        $this->assertSame([], $this->queue());
    }

    public function testAnAlreadyPublishedProductQueuesNothing(): void
    {
        $this->triggers->onPostStatusTransition('publish', 'publish', new WP_Post(11, 'product'));

        $this->assertSame([], $this->queue());
    }

    public function testOnlyTheRestockEdgeQueues(): void
    {
        // First sighting of the product is a save, not a restock.
        $this->triggers->onStockStatusChange(12, 'instock');
        $this->assertSame([], $this->queue());

        $this->triggers->onStockStatusChange(12, 'outofstock');
        $this->assertSame([], $this->queue());

        $this->triggers->onStockStatusChange(12, 'instock');
        $this->assertCount(1, $this->queue());
        $this->assertSame([12, Settings::TRIGGER_BACK_IN_STOCK], $this->queue()[0]['args']);
    }

    public function testOnlyTheEdgeIntoBeingOnSaleQueues(): void
    {
        $notOnSale = $this->product(13, ['on_sale' => false]);
        $onSale    = $this->product(13, ['on_sale' => true]);

        $this->triggers->onProductPropsUpdated($notOnSale, ['sale_price']);
        $this->assertSame([], $this->queue());

        $this->triggers->onProductPropsUpdated($onSale, ['sale_price']);
        $this->assertCount(1, $this->queue());
        $this->assertSame([13, Settings::TRIGGER_ON_SALE], $this->queue()[0]['args']);

        // Still on sale, a later price edit must not post again.
        $GLOBALS['fopost_wc_test_queue'] = [];
        $this->triggers->onProductPropsUpdated($onSale, ['regular_price']);
        $this->assertSame([], $this->queue());
    }

    public function testAnUnrelatedPropertyChangeQueuesNothing(): void
    {
        $product = $this->product(14, ['on_sale' => true]);

        $this->triggers->onProductPropsUpdated($product, ['description', 'weight']);

        $this->assertSame([], $this->queue());
    }
}
