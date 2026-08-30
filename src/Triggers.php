<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

use WC_Product;
use WP_Post;

/**
 * The WooCommerce events that produce a post.
 *
 * Each handler decides whether this is really a transition worth posting about,
 * then hands the product to the queue. No handler ever calls the API.
 */
final class Triggers
{
    public function register(): void
    {
        add_action('transition_post_status', [$this, 'onPostStatusTransition'], 10, 3);
        add_action('woocommerce_product_set_stock_status', [$this, 'onStockStatusChange'], 10, 3);
        add_action('woocommerce_product_object_updated_props', [$this, 'onProductPropsUpdated'], 10, 2);
    }

    /**
     * A product moved into the published state for the first time.
     */
    public function onPostStatusTransition(string $newStatus, string $oldStatus, mixed $post): void
    {
        if (! $post instanceof WP_Post || $post->post_type !== 'product') {
            return;
        }

        if ($newStatus !== 'publish' || $oldStatus === 'publish') {
            return;
        }

        self::maybeEnqueue((int) $post->ID, Settings::TRIGGER_PUBLISHED);
    }

    /**
     * Stock came back. Only the out-of-stock to in-stock edge posts.
     */
    public function onStockStatusChange(mixed $productId, mixed $stockStatus, mixed $product = null): void
    {
        $productId   = (int) $productId;
        $stockStatus = is_string($stockStatus) ? $stockStatus : '';

        if ($productId <= 0 || $stockStatus === '') {
            return;
        }

        $previous = ProductState::lastStockStatus($productId);
        ProductState::rememberStockStatus($productId, $stockStatus);

        if ($stockStatus !== 'instock') {
            return;
        }

        // An empty previous value means this is the first time we have seen the
        // product, which is a first save, not a restock.
        if ($previous === '' || $previous === 'instock') {
            return;
        }

        self::maybeEnqueue($productId, Settings::TRIGGER_BACK_IN_STOCK);
    }

    /**
     * A price changed. Only the edge into being on sale posts.
     *
     * @param array<int, string> $updatedProps
     */
    public function onProductPropsUpdated(mixed $product, mixed $updatedProps): void
    {
        if (! $product instanceof WC_Product) {
            return;
        }

        $updatedProps = is_array($updatedProps) ? $updatedProps : [];
        $priceProps   = ['sale_price', 'regular_price', 'price', 'date_on_sale_from', 'date_on_sale_to'];

        if (array_intersect($priceProps, $updatedProps) === []) {
            return;
        }

        $productId = $product->get_id();
        if ($productId <= 0) {
            return;
        }

        $onSale     = (bool) $product->is_on_sale();
        $wasOnSale  = ProductState::wasOnSale($productId);
        ProductState::rememberOnSale($productId, $onSale);

        if (! $onSale || $wasOnSale) {
            return;
        }

        self::maybeEnqueue($productId, Settings::TRIGGER_ON_SALE);
    }

    /**
     * Queue a post unless the trigger is off, the product opted out, or the
     * plugin has no FoPost connection yet.
     */
    public static function maybeEnqueue(int $productId, string $trigger): bool
    {
        if (! Settings::triggerEnabled($trigger)) {
            return false;
        }

        if (! Settings::isConfigured()) {
            return false;
        }

        if (ProductState::isOptedOut($productId)) {
            return false;
        }

        /**
         * Filters whether a product event should produce a FoPost post.
         *
         * @param bool   $should    Whether to queue the post.
         * @param int    $productId The product.
         * @param string $trigger   Which trigger fired.
         */
        $should = apply_filters('fopost_wc_should_post', true, $productId, $trigger);

        if (! $should) {
            return false;
        }

        return Scheduler::enqueue($productId, $trigger);
    }
}
