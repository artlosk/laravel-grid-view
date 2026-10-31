<?php

namespace Artlosk\LaravelGridView\Tests\Unit;

use Artlosk\LaravelGridView\Rows\ExpandedGroup;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Artlosk\LaravelGridView\Tests\TestCase;
use InvalidArgumentException;

class ExpandedRowsTest extends TestCase
{
    public function test_expanded_row_preserves_an_explicit_null_value(): void
    {
        $row = ExpandedRow::make(['detail' => null]);

        $this->assertTrue($row->has('detail'));
        $this->assertNull($row->get('detail', 'fallback'));
        $this->assertSame('fallback', $row->get('missing', 'fallback'));
    }

    public function test_group_rejects_values_that_are_not_expanded_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ExpandedGroup::make(['invalid']);
    }

    public function test_group_rejects_duplicate_shared_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ExpandedGroup::make([ExpandedRow::make()], ['name', 'name']);
    }
}
