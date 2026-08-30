<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

use Fopost\Sdk\Exception\FopostException;
use Fopost\WooCommerce\Admin\Notices;
use Fopost\WooCommerce\Api\ClientFactory;
use Fopost\WooCommerce\Api\PostGateway;
use Throwable;

/**
 * Turns a queued product event into a FoPost post.
 *
 * This runs inside an Action Scheduler request, never inside the request that
 * published the product. Nothing here throws: a failure is recorded on the product
 * and surfaced as an admin notice, so a flaky API never turns into a fatal queue job.
 */
final class Publisher
{
    public function __construct(
        private readonly ?PostGateway $gateway = null,
    ) {
    }

    /**
     * The Action Scheduler callback.
     */
    public static function handle(mixed $productId, mixed $trigger = ''): void
    {
        (new self())->run((int) $productId, is_string($trigger) ? $trigger : '');
    }

    /**
     * Send one product post. Returns the FoPost post id, or null when nothing was sent.
     */
    public function run(int $productId, string $trigger): ?string
    {
        $product = function_exists('wc_get_product') ? wc_get_product($productId) : null;

        if (! $product instanceof \WC_Product) {
            Log::record($productId, $trigger, Log::STATUS_FAILED, '', __('The product no longer exists.', 'fopost-woocommerce'));

            return null;
        }

        $message = $this->message($productId, $product, $trigger);

        if ($message === '') {
            $this->fail($productId, $trigger, __('The message template rendered empty, so nothing was sent.', 'fopost-woocommerce'));

            return null;
        }

        if (! Settings::isConfigured()) {
            $this->fail($productId, $trigger, __('FoPost is not connected yet. Add an API key, a workspace and at least one account.', 'fopost-woocommerce'));

            return null;
        }

        try {
            $gateway = $this->gateway ?? ClientFactory::gateway();

            $postId = $gateway->createAndPublish(
                Settings::workspaceId(),
                $message,
                Settings::accountIds(),
                $this->media($product),
            );
        } catch (FopostException $e) {
            $this->fail($productId, $trigger, $e->getMessage());

            return null;
        } catch (Throwable $e) {
            $this->fail($productId, $trigger, $e->getMessage());

            return null;
        }

        Log::record($productId, $trigger, Log::STATUS_SUCCESS, $postId, mb_substr($message, 0, 200));

        return $postId;
    }

    /**
     * The per-product override wins over the trigger's template.
     */
    private function message(int $productId, \WC_Product $product, string $trigger): string
    {
        $override = ProductState::messageOverride($productId);
        $template = $override !== '' ? $override : Settings::template($trigger);

        if ($template === '') {
            return '';
        }

        return Template::render($template, $product, $trigger);
    }

    /**
     * The product's featured image, when there is one and the setting allows it.
     *
     * @return array<int, array{type: string, name: string, url: string}>
     */
    private function media(\WC_Product $product): array
    {
        if (! Settings::attachesImage()) {
            return [];
        }

        $imageId = (int) $product->get_image_id();
        if ($imageId <= 0) {
            return [];
        }

        $url = wp_get_attachment_image_url($imageId, 'full');
        if (! is_string($url) || $url === '') {
            return [];
        }

        return [[
            'type' => 'image',
            'name' => basename(wp_parse_url($url, PHP_URL_PATH) ?? $url),
            'url'  => esc_url_raw($url),
        ]];
    }

    private function fail(int $productId, string $trigger, string $reason): void
    {
        Log::record($productId, $trigger, Log::STATUS_FAILED, '', $reason);
        Notices::add($productId, $reason);
    }
}
