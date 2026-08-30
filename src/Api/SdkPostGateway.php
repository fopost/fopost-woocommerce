<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Api;

defined('ABSPATH') || exit;

use Fopost\Sdk\Client;

/**
 * PostGateway backed by the official FoPost PHP SDK.
 */
final class SdkPostGateway implements PostGateway
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    /**
     * @param array<int, string>                                     $accountIds
     * @param array<int, array{type: string, name: string, url: string}> $media
     */
    public function createAndPublish(string $workspaceId, string $message, array $accountIds, array $media = []): string
    {
        $block = ['text' => $message];

        if ($media !== []) {
            $block['media'] = array_values($media);
        }

        $post = $this->client->posts()->create(
            workspaceId: $workspaceId,
            content: [$block],
            accounts: array_values($accountIds),
            status: 'draft',
        );

        // Publishing returns once delivery is queued, not once it is live.
        $this->client->posts()->publish($post->id);

        return $post->id;
    }
}
