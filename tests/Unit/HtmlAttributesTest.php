<?php

namespace Artlosk\LaravelGridView\Tests\Unit;

use Artlosk\LaravelGridView\Support\HtmlAttributes;
use Artlosk\LaravelGridView\Tests\TestCase;
use InvalidArgumentException;

class HtmlAttributesTest extends TestCase
{
    public function test_it_escapes_values_and_preserves_boolean_semantics(): void
    {
        $attributes = HtmlAttributes::escaped([
            'title' => 'x" onmouseover="alert(1)',
            'data-label' => '<unsafe>&',
            'disabled' => true,
            'hidden' => false,
            'data-empty' => null,
        ]);

        $this->assertSame('x&quot; onmouseover=&quot;alert(1)', $attributes['title']);
        $this->assertSame('&lt;unsafe&gt;&amp;', $attributes['data-label']);
        $this->assertTrue($attributes['disabled']);
        $this->assertFalse($attributes['hidden']);
        $this->assertNull($attributes['data-empty']);
    }

    public function test_reserved_attributes_are_filtered_case_insensitively(): void
    {
        $attributes = HtmlAttributes::escaped([
            'HREF' => '/unsafe',
            'class' => 'safe',
        ], ['href']);

        $this->assertSame(['class' => 'safe'], $attributes);
    }

    public function test_it_rejects_invalid_attribute_names(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HtmlAttributes::escaped(['title onmouseover' => 'unsafe']);
    }

    public function test_it_rejects_inline_event_attributes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HtmlAttributes::escaped(['onmouseover' => 'alert(1)']);
    }

    public function test_it_renders_and_merges_attributes(): void
    {
        $attributes = HtmlAttributes::merge(['class' => 'a', 'title' => 'x'], ['class' => 'b', 'title' => '"y"', 'hidden' => true, 'skip' => null]);

        $this->assertSame(' class="a b" title="&quot;y&quot;" hidden', HtmlAttributes::render($attributes)->toHtml());
    }
}
