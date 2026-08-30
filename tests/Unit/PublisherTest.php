<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Tests\Unit;

use Fopost\Sdk\Exception\RateLimitException;
use Fopost\WooCommerce\Admin\Notices;
use Fopost\WooCommerce\Api\PostGateway;
use Fopost\WooCommerce\Log;
use Fopost\WooCommerce\ProductState;
use Fopost\WooCommerce\Publisher;
use Fopost\WooCommerce\Settings;
use Fopost\WooCommerce\Tests\TestCase;
use RuntimeException;

/** Records what the publisher asked FoPost to do, or throws instead. */
final class FakeGateway implements PostGateway
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    public function __construct(
        private readonly ?\Throwable $failure = null,
        private readonly string $postId = 'post_123',
    ) {
    }

    public function createAndPublish(string $workspaceId, string $message, array $accountIds, array $media = []): string
    {
        $this->calls[] = compact('workspaceId', 'message', 'accountIds', 'media');

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->postId;
    }
}

class PublisherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->connect();
    }

    public function testASuccessfulSendIsLoggedWithTheFoPostPostId(): void
    {
        $this->product(21, ['name' => 'Cedar Desk', 'regular_price' => '249', 'image_id' => 5]);
        update_option(Settings::templateOptionName(Settings::TRIGGER_PUBLISHED), '{product_name} is out: {permalink}');

        $gateway = new FakeGateway();

        $postId = (new Publisher($gateway))->run(21, Settings::TRIGGER_PUBLISHED);

        $this->assertSame('post_123', $postId);

        $entries = Log::forProduct(21);
        $this->assertCount(1, $entries);
        $this->assertSame(Log::STATUS_SUCCESS, $entries[0]['status']);
        $this->assertSame('post_123', $entries[0]['post_id']);

        $this->assertCount(1, $gateway->calls);
        $this->assertSame('ws-1', $gateway->calls[0]['workspaceId']);
        $this->assertSame(['acc-1'], $gateway->calls[0]['accountIds']);
        $this->assertSame('Cedar Desk is out: https://shop.example/product/21', $gateway->calls[0]['message']);
        $this->assertSame(
            [['type' => 'image', 'name' => '5.jpg', 'url' => 'https://shop.example/uploads/5.jpg']],
            $gateway->calls[0]['media']
        );
    }

    public function testAnApiFailureIsRecordedRatherThanThrown(): void
    {
        $this->product(22, ['name' => 'Cedar Desk', 'regular_price' => '249']);

        $gateway = new FakeGateway(new RateLimitException('Too many requests', 429, 'rate_limited'));

        $postId = (new Publisher($gateway))->run(22, Settings::TRIGGER_PUBLISHED);

        $this->assertNull($postId);

        $entries = Log::forProduct(22);
        $this->assertCount(1, $entries);
        $this->assertSame(Log::STATUS_FAILED, $entries[0]['status']);
        $this->assertSame('', $entries[0]['post_id']);
        $this->assertSame('Too many requests', $entries[0]['message']);

        $notices = Notices::all();
        $this->assertCount(1, $notices);
        $this->assertSame(22, $notices[0]['product_id']);
    }

    public function testAnUnexpectedErrorIsAlsoContained(): void
    {
        $this->product(23, ['name' => 'Cedar Desk', 'regular_price' => '249']);

        $postId = (new Publisher(new FakeGateway(new RuntimeException('boom'))))->run(23, Settings::TRIGGER_PUBLISHED);

        $this->assertNull($postId);
        $this->assertSame(Log::STATUS_FAILED, Log::forProduct(23)[0]['status']);
    }

    public function testAMissingProductIsRecordedNotFatal(): void
    {
        $postId = (new Publisher(new FakeGateway()))->run(999, Settings::TRIGGER_PUBLISHED);

        $this->assertNull($postId);
        $this->assertSame(Log::STATUS_FAILED, Log::forProduct(999)[0]['status']);
    }

    public function testAProductOverrideWinsOverTheTriggerTemplate(): void
    {
        $this->product(24, ['name' => 'Cedar Desk', 'regular_price' => '249']);
        update_option(Settings::templateOptionName(Settings::TRIGGER_PUBLISHED), 'default copy');
        ProductState::setMessageOverride(24, 'Hand written: {product_name}');

        $gateway = new FakeGateway();
        (new Publisher($gateway))->run(24, Settings::TRIGGER_PUBLISHED);

        $this->assertSame('Hand written: Cedar Desk', $gateway->calls[0]['message']);
    }

    public function testTheImageIsLeftOffWhenTheSettingIsOff(): void
    {
        $this->product(25, ['name' => 'Cedar Desk', 'regular_price' => '249', 'image_id' => 5]);
        update_option(Settings::OPTION_ATTACH_IMAGE, 'no');

        $gateway = new FakeGateway();
        (new Publisher($gateway))->run(25, Settings::TRIGGER_PUBLISHED);

        $this->assertSame([], $gateway->calls[0]['media']);
    }

    public function testAnUnconnectedSiteSendsNothingAndSaysWhy(): void
    {
        $this->product(26, ['name' => 'Cedar Desk', 'regular_price' => '249']);
        delete_option(Settings::OPTION_API_KEY);

        $gateway = new FakeGateway();
        $postId  = (new Publisher($gateway))->run(26, Settings::TRIGGER_PUBLISHED);

        $this->assertNull($postId);
        $this->assertSame([], $gateway->calls);
        $this->assertSame(Log::STATUS_FAILED, Log::forProduct(26)[0]['status']);
    }

    public function testTheTrailIsCappedSoAProductMetaRowStaysSmall(): void
    {
        $this->product(27, ['name' => 'Cedar Desk', 'regular_price' => '249']);

        for ($i = 0; $i < 25; $i++) {
            Log::record(27, Settings::TRIGGER_PUBLISHED, Log::STATUS_SUCCESS, 'post_' . $i);
        }

        $entries = Log::forProduct(27);

        $this->assertCount(20, $entries);
        $this->assertSame('post_24', $entries[0]['post_id']);
    }
}
