<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Admin;

defined('ABSPATH') || exit;

use Fopost\WooCommerce\Log;
use Fopost\WooCommerce\ProductState;
use Fopost\WooCommerce\Scheduler;
use Fopost\WooCommerce\Settings;
use WP_Post;

/**
 * The FoPost box on the product edit screen: opt this product out, override its
 * message, post it now, and see what has been sent.
 */
final class ProductMetaBox
{
    private const NONCE_ACTION = 'fopost_wc_save_product';
    private const NONCE_FIELD  = 'fopost_wc_product_nonce';

    public const POST_NOW_ACTION = 'fopost_wc_post_now';

    public function register(): void
    {
        add_action('add_meta_boxes_product', [$this, 'addMetaBox']);
        add_action('save_post_product', [$this, 'save'], 10, 2);
        add_action('admin_post_' . self::POST_NOW_ACTION, [$this, 'handlePostNow']);
    }

    public function addMetaBox(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        add_meta_box(
            'fopost_wc_product',
            __('FoPost', 'fopost-woocommerce'),
            [$this, 'render'],
            'product',
            'side',
            'default'
        );
    }

    public function render(WP_Post $post): void
    {
        $productId = (int) $post->ID;
        $optedOut  = ProductState::isOptedOut($productId);
        $override  = ProductState::messageOverride($productId);
        $entries   = Log::forProduct($productId);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        ?>
        <p>
            <label>
                <input type="checkbox" name="fopost_wc_disabled" value="yes" <?php checked($optedOut); ?> />
                <?php esc_html_e('Never post this product', 'fopost-woocommerce'); ?>
            </label>
        </p>

        <p>
            <label for="fopost_wc_message"><strong><?php esc_html_e('Custom message', 'fopost-woocommerce'); ?></strong></label>
            <textarea
                id="fopost_wc_message"
                name="fopost_wc_message"
                rows="4"
                style="width:100%"
                placeholder="<?php esc_attr_e('Leave blank to use the message for the trigger.', 'fopost-woocommerce'); ?>"
            ><?php echo esc_textarea($override); ?></textarea>
        </p>

        <?php if (Settings::isConfigured()) : ?>
            <p>
                <a class="button" href="<?php echo esc_url(self::postNowUrl($productId)); ?>">
                    <?php esc_html_e('Post Now', 'fopost-woocommerce'); ?>
                </a>
            </p>
        <?php else : ?>
            <p class="description">
                <?php esc_html_e('Connect FoPost in WooCommerce, Settings, FoPost to post this product.', 'fopost-woocommerce'); ?>
            </p>
        <?php endif; ?>

        <?php if ($entries !== []) : ?>
            <p><strong><?php esc_html_e('Recent activity', 'fopost-woocommerce'); ?></strong></p>
            <ul style="margin:0">
                <?php foreach (array_slice($entries, 0, 5) as $entry) : ?>
                    <li>
                        <?php
                        $status = isset($entry['status']) ? (string) $entry['status'] : '';
                        $time   = isset($entry['time']) ? (int) $entry['time'] : 0;

                        echo esc_html(
                            sprintf(
                                /* translators: 1: how long ago the attempt was, 2: outcome. */
                                __('%1$s ago, %2$s', 'fopost-woocommerce'),
                                $time > 0 ? human_time_diff($time) : __('unknown', 'fopost-woocommerce'),
                                $status === Log::STATUS_SUCCESS
                                    ? __('sent', 'fopost-woocommerce')
                                    : __('failed', 'fopost-woocommerce')
                            )
                        );
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }

    /**
     * @param int          $postId
     * @param WP_Post|null $post
     */
    public function save(mixed $postId, mixed $post = null): void
    {
        $postId = (int) $postId;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (! current_user_can('manage_woocommerce') || ! current_user_can('edit_post', $postId)) {
            return;
        }

        ProductState::setOptedOut($postId, isset($_POST['fopost_wc_disabled']));

        $message = isset($_POST['fopost_wc_message'])
            ? sanitize_textarea_field(wp_unslash($_POST['fopost_wc_message']))
            : '';

        ProductState::setMessageOverride($postId, $message);
    }

    /**
     * Queue one product on demand, ignoring the trigger toggles.
     */
    public function handlePostNow(): void
    {
        $productId = isset($_GET['product_id']) ? absint(wp_unslash($_GET['product_id'])) : 0;

        check_admin_referer(self::POST_NOW_ACTION . '_' . $productId);

        if (! current_user_can('manage_woocommerce') || ! current_user_can('edit_post', $productId)) {
            wp_die(esc_html__('You are not allowed to post this product.', 'fopost-woocommerce'), 403);
        }

        $queued = Settings::isConfigured() && Scheduler::enqueue($productId, Settings::TRIGGER_PUBLISHED);

        if (! $queued) {
            Notices::add($productId, __('The product could not be queued. Check the FoPost connection and that a post is not already pending.', 'fopost-woocommerce'));
        }

        wp_safe_redirect(add_query_arg('fopost_wc_queued', $queued ? '1' : '0', get_edit_post_link($productId, 'url')));
        exit;
    }

    private static function postNowUrl(int $productId): string
    {
        return wp_nonce_url(
            add_query_arg(
                ['action' => self::POST_NOW_ACTION, 'product_id' => $productId],
                admin_url('admin-post.php')
            ),
            self::POST_NOW_ACTION . '_' . $productId
        );
    }
}
