<?php

declare(strict_types=1);

namespace Fopost\WooCommerce;

defined('ABSPATH') || exit;

use WC_Product;

/**
 * Turns a message template into the text of a social post.
 *
 * Every value is reduced to plain text before substitution: a product's short
 * description is authored as HTML, and a social network wants none of it.
 * Both the placeholder map and the finished message are filterable, so a theme
 * or another plugin can add tokens without touching this class.
 */
final class Template
{
    public const PLACEHOLDERS = [
        '{product_name}',
        '{price}',
        '{sale_price}',
        '{permalink}',
        '{short_description}',
        '{sku}',
        '{categories}',
    ];

    /** Short description is trimmed to this many characters so a post stays postable. */
    private const SHORT_DESCRIPTION_LIMIT = 280;

    /**
     * Resolve a template against a product.
     */
    public static function render(string $template, WC_Product $product, string $trigger = ''): string
    {
        $placeholders = self::placeholders($product, $trigger);

        $message = strtr($template, $placeholders);
        $message = self::normalizeWhitespace($message);

        /**
         * Filters the finished message, after every placeholder has been substituted.
         *
         * @param string     $message  Plain text message.
         * @param string     $template The template it came from.
         * @param WC_Product $product  The product being posted.
         * @param string     $trigger  Which trigger produced this message.
         */
        $message = apply_filters('fopost_wc_rendered_message', $message, $template, $product, $trigger);

        return is_string($message) ? $message : '';
    }

    /**
     * The placeholder map for a product.
     *
     * @return array<string, string>
     */
    public static function placeholders(WC_Product $product, string $trigger = ''): array
    {
        $placeholders = [
            '{product_name}'      => self::text($product->get_name()),
            '{price}'             => self::price($product->get_regular_price() !== '' ? $product->get_regular_price() : $product->get_price()),
            '{sale_price}'        => self::price($product->get_sale_price()),
            '{permalink}'         => self::permalink($product),
            '{short_description}' => self::shortDescription($product),
            '{sku}'               => self::text($product->get_sku()),
            '{categories}'        => self::categories($product),
        ];

        /**
         * Filters the placeholder map before substitution.
         *
         * Keys are the literal tokens including their braces. Values are plain text,
         * they are inserted verbatim, so a filter must not return HTML.
         *
         * @param array<string, string> $placeholders Token to replacement.
         * @param WC_Product            $product      The product being posted.
         * @param string                $trigger      Which trigger produced this message.
         */
        $filtered = apply_filters('fopost_wc_template_placeholders', $placeholders, $product, $trigger);

        if (! is_array($filtered)) {
            return $placeholders;
        }

        $clean = [];
        foreach ($filtered as $token => $value) {
            if (! is_string($token) || ! is_scalar($value)) {
                continue;
            }

            $clean[$token] = self::text((string) $value);
        }

        return $clean;
    }

    /**
     * Reduce any value to plain single-safe text: no tags, no encoded entities, trimmed.
     */
    public static function text(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = wp_strip_all_tags((string) $value, true);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Decoding can reveal markup that was stored encoded, so strip a second time.
        $text = wp_strip_all_tags($text, true);

        return trim($text);
    }

    private static function price(mixed $amount): string
    {
        if (! is_scalar($amount) || (string) $amount === '') {
            return '';
        }

        if (function_exists('wc_price')) {
            return self::text(wc_price((float) $amount));
        }

        return self::text((string) $amount);
    }

    private static function permalink(WC_Product $product): string
    {
        $url = get_permalink($product->get_id());

        return is_string($url) ? esc_url_raw($url) : '';
    }

    private static function shortDescription(WC_Product $product): string
    {
        $text = self::text($product->get_short_description());

        if (mb_strlen($text) <= self::SHORT_DESCRIPTION_LIMIT) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, self::SHORT_DESCRIPTION_LIMIT - 1)) . '…';
    }

    private static function categories(WC_Product $product): string
    {
        $names = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);

        if (! is_array($names) || $names === []) {
            return '';
        }

        $clean = [];
        foreach ($names as $name) {
            $text = self::text($name);
            if ($text !== '') {
                $clean[] = $text;
            }
        }

        return implode(', ', $clean);
    }

    /**
     * Collapse the gaps an empty placeholder leaves behind.
     */
    private static function normalizeWhitespace(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = (string) preg_replace('/[ \t]+/', ' ', $message);
        $message = (string) preg_replace('/ *\n */', "\n", $message);
        $message = (string) preg_replace('/\n{3,}/', "\n\n", $message);

        return trim($message);
    }
}
