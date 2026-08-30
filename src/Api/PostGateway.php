<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Api;

defined('ABSPATH') || exit;

/**
 * The one thing this plugin asks of FoPost: send this message to these accounts.
 *
 * Publisher depends on the interface rather than the SDK client so the failure
 * path can be exercised without a network.
 */
interface PostGateway
{
    /**
     * Create the post and queue it for delivery.
     *
     * @param array<int, string>                                     $accountIds
     * @param array<int, array{type: string, name: string, url: string}> $media
     *
     * @return string The FoPost post id.
     */
    public function createAndPublish(string $workspaceId, string $message, array $accountIds, array $media = []): string;
}
