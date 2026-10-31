<?php

namespace Artlosk\LaravelGridView\Tests\Unit;

use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Tests\TestCase;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FilterTest extends TestCase
{
    public function test_text_is_trimmed_and_rejects_arrays(): void
    {
        $filter = Filter::text('search');

        $this->assertSame('alice', $filter->normalize('  alice '));
        $this->assertNull($filter->normalize('   '));
        $this->assertNull($filter->normalize(['alice']));
    }

    public function test_select_accepts_only_its_items(): void
    {
        $filter = Filter::select('status', items: ['active' => 'Active', '2' => 'Inactive']);

        $this->assertSame('active', $filter->normalize('active'));
        $this->assertSame('2', $filter->normalize(2));
        $this->assertNull($filter->normalize('deleted'));
        $this->assertNull($filter->normalize(['active']));
    }

    public function test_select_items_in_list_form(): void
    {
        $filter = Filter::select('role', items: [
            ['value' => 1, 'label' => 'Admin', 'attrs' => ['data-color' => 'red']],
            ['value' => 2, 'label' => 'User'],
        ]);

        $this->assertSame([
            ['value' => '1', 'label' => 'Admin', 'attributes' => ['data-color' => 'red']],
            ['value' => '2', 'label' => 'User', 'attributes' => []],
        ], $filter->items());
    }

    public function test_select_option_groups(): void
    {
        $filter = Filter::select('source', items: [
            ['value' => 1, 'label' => 'Best rate'],
            ['group' => 'Bank', 'items' => [['value' => 2, 'label' => 'Cash', 'attrs' => ['data-tokens' => 'Bank']]]],
            'Other bank' => ['3' => 'Card'],
        ]);

        $this->assertSame([
            ['value' => '1', 'label' => 'Best rate', 'attributes' => []],
            ['group' => 'Bank', 'items' => [['value' => '2', 'label' => 'Cash', 'attributes' => ['data-tokens' => 'Bank']]]],
            ['group' => 'Other bank', 'items' => [['value' => '3', 'label' => 'Card', 'attributes' => []]]],
        ], $filter->items());
        $this->assertSame('3', $filter->normalize('3'));
        $this->assertNull($filter->normalize('Bank'));
    }

    public function test_multiple_select_keeps_allowed_unique_values(): void
    {
        $filter = Filter::select('ids', items: ['1' => 'A', '2' => 'B'])->multiple();

        $this->assertSame(['1', '2'], $filter->normalize(['1', '2', '2', '9', ['nested']]));
        $this->assertSame([], $filter->normalize('1'));
        $this->assertSame('ids[]', $filter->inputName());
    }

    public function test_date_must_be_a_real_day(): void
    {
        $filter = Filter::date('from');

        $this->assertSame('2026-02-28', $filter->normalize('2026-02-28'));
        $this->assertNull($filter->normalize('2026-02-30'));
        $this->assertNull($filter->normalize('28.02.2026'));
    }

    public function test_values_from_a_request(): void
    {
        $values = FilterValues::from(Request::create('/?search=x&status=7&ids[]=1&ids[]=b&extra=1'), [
            Filter::text('search'),
            Filter::select('status'),
            Filter::select('ids')->multiple(),
        ]);

        $this->assertSame(['search' => 'x', 'status' => '7', 'ids' => ['1', 'b']], $values->all());
        $this->assertSame(7, $values->int('status'));
        $this->assertNull($values->int('search'));
        $this->assertSame([1], $values->ints('ids'));
        $this->assertTrue($values->active());
        $this->assertFalse((new FilterValues(['search' => null, 'ids' => []]))->active());
    }

    public function test_labels_go_through_the_translator(): void
    {
        GridView::translateLabelsUsing(fn (string $label) => "t:{$label}");

        $filter = Filter::text('search', 'Search')->placeholder('Name');

        $this->assertSame('t:Search', $filter->label());
        $this->assertSame('t:Name', $filter->placeholderText());
        $this->assertSame('t:Created At', Filter::date('created_at')->label());
    }

    public function test_rejects_invalid_names(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Filter::text('bad name');
    }

    public function test_default_applies_only_when_the_parameter_is_missing(): void
    {
        $filters = [Filter::select('status', items: ['1' => 'Active', '2' => 'Inactive'])->default('1')];

        $missing = FilterValues::from(Request::create('/'), $filters);
        $empty = FilterValues::from(Request::create('/?status='), $filters);
        $chosen = FilterValues::from(Request::create('/?status=2'), $filters);

        $this->assertSame('1', $missing->string('status'));
        $this->assertFalse($missing->active());
        $this->assertNull($empty->string('status'));
        $this->assertTrue($empty->active());
        $this->assertTrue($chosen->active());
    }
}
