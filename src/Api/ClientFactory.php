<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Api;

defined('ABSPATH') || exit;

use Fopost\Sdk\Client;
use Fopost\WooCommerce\Settings;
use RuntimeException;

/**
 * Builds a configured SDK client from the stored settings.
 */
final class ClientFactory
{
    public static function client(?string $apiKey = null): Client
    {
        $apiKey ??= Settings::apiKey();

        if ($apiKey === '') {
            throw new RuntimeException('fopost: no API key is configured');
        }

        /**
         * Filters the FoPost API base URL. Useful when pointing a staging site elsewhere.
         *
         * @param string $baseUrl
         */
        $baseUrl = apply_filters('fopost_wc_api_base_url', Client::DEFAULT_BASE_URL);
        $baseUrl = is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : Client::DEFAULT_BASE_URL;

        /**
         * Filters the per-request timeout, in seconds.
         *
         * @param float $timeout
         */
        $timeout = (float) apply_filters('fopost_wc_api_timeout', 30.0);

        return new Client(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            timeout: $timeout,
            transport: new WpTransport($timeout),
        );
    }

    public static function gateway(?string $apiKey = null): PostGateway
    {
        return new SdkPostGateway(self::client($apiKey));
    }
}
