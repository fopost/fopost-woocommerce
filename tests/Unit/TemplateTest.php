<?php

declare(strict_types=1);

namespace Fopost\WooCommerce\Tests\Unit;

use Fopost\WooCommerce\Template;
use Fopost\WooCommerce\Tests\TestCase;

class TemplateTest extends TestCase
{
    private const EVERY_PLACEHOLDER =
        "{product_name}|{price}|{sale_price}|{permalink}|{short_description}|{sku}|{categories}";

    public function testEveryPlaceholderIsSubstituted(): void
    {
        $product = $this->product(42, [
            'name'              => 'Cedar Desk',
            'regular_price'     => '249',
            'sale_price'        => '199',
            'short_description' => 'A solid cedar writing desk.',
            'sku'               => 'DESK-01',
        ]);

        $GLOBALS['fopost_wc_test_terms'][42] = ['Furniture', 'Desks'];

        $message = Template::render(self::EVERY_PLACEHOLDER, $product);

        foreach (Template::PLACEHOLDERS as $placeholder) {
            $this->assertStringNotContainsString(
                $placeholder,
                $message,
                "{$placeholder} was left in the rendered message"
            );
        }

        $this->assertSame(
            'Cedar Desk|$249.00|$199.00|https://shop.example/product/42|A solid cedar writing desk.|DESK-01|Furniture, Desks',
            $message
        );
    }

    public function testMarkupInProductDataNeverReachesTheMessage(): void
    {
        $product = $this->product(7, [
            'name'              => 'Lamp <script>alert(1)</script>',
            'regular_price'     => '30',
            'short_description' => '<p>Warm <strong>glow</strong>.</p><script>steal()</script>',
        ]);

        $message = Template::render('{product_name}: {short_description}', $product);

        $this->assertStringNotContainsString('<', $message);
        $this->assertStringNotContainsString('alert(1)', $message);
        $this->assertStringNotContainsString('steal()', $message);
        $this->assertStringContainsString('Warm glow.', $message);
    }

    public function testEncodedMarkupIsDecodedThenStripped(): void
    {
        $product = $this->product(8, [
            'name'          => '&lt;img src=x onerror=alert(1)&gt;Mug',
            'regular_price' => '12',
        ]);

        $message = Template::render('{product_name}', $product);

        $this->assertSame('Mug', $message);
    }

    public function testAnEmptyPlaceholderDoesNotLeaveARaggedMessage(): void
    {
        $product = $this->product(9, ['name' => 'Notebook', 'regular_price' => '5']);

        $message = Template::render("{product_name}\n\n{short_description}\n\n{permalink}", $product);

        $this->assertSame("Notebook\n\nhttps://shop.example/product/9", $message);
    }

    public function testAFilterCanAddAPlaceholder(): void
    {
        $product = $this->product(10, ['name' => 'Chair', 'regular_price' => '99']);

        $this->assertSame('Chair', Template::render('{product_name}', $product));
    }
}
