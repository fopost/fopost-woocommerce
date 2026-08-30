<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Api;

defined('ABSPATH') || exit;

use Fopost\Sdk\Exception\ApiException;
use Fopost\Sdk\Http\Response;
use Fopost\Sdk\Http\Transport;

/**
 * Puts the SDK's requests on the wire through the WordPress HTTP API.
 *
 * The SDK's own transport calls cURL directly. A plugin must not: hosts filter
 * outbound requests through WP_Http, and the site owner's proxy, timeout and
 * certificate settings all live there.
 */
final class WpTransport implements Transport
{
    public function __construct(
        private readonly float $timeout = 30.0,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $args = [
            'method'      => strtoupper($method),
            'timeout'     => $this->timeout,
            'redirection' => 3,
            'headers'     => $headers,
            'user-agent'  => self::userAgent($headers),
        ];

        if ($body !== null) {
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new ApiException(
                esc_html(sprintf('fopost: request failed (%s)', $response->get_error_message())),
                0,
                'transport_error',
                null,
            );
        }

        return new Response(
            (int) wp_remote_retrieve_response_code($response),
            self::normalizeHeaders(wp_remote_retrieve_headers($response)),
            (string) wp_remote_retrieve_body($response),
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private static function userAgent(array $headers): string
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'user-agent') {
                return (string) $value;
            }
        }

        return 'fopost-woocommerce/' . (defined('FOPOST_WC_VERSION') ? FOPOST_WC_VERSION : '0.0.0');
    }

    /**
     * The SDK reads headers by lowercased name.
     *
     * @return array<string, string>
     */
    private static function normalizeHeaders(mixed $headers): array
    {
        if (is_object($headers) && method_exists($headers, 'getAll')) {
            $headers = $headers->getAll();
        } elseif (is_object($headers) && is_iterable($headers)) {
            $collected = [];
            foreach ($headers as $name => $value) {
                $collected[$name] = $value;
            }
            $headers = $collected;
        }

        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $value = end($value);
            }

            if (! is_scalar($value)) {
                continue;
            }

            $normalized[strtolower((string) $name)] = (string) $value;
        }

        return $normalized;
    }
}
