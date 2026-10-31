<?php

namespace Artlosk\LaravelGridView\Columns;

use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Drag handle of a grid with the "reorder" option: drag a record with a mouse or a finger, or focus it and press ↑ ↓.
 * The grid puts it first; add ['class' => 'reorder'] to the columns to place it elsewhere.
 */
class ReorderColumn extends Column
{
    public function key(): string
    {
        return 'reorder';
    }

    public function label(): string
    {
        return $this->label === null ? '' : GridView::label($this->label);
    }

    public function isVisible(): bool
    {
        return $this->grid->isReorderable() && parent::isVisible();
    }

    public function cell(mixed $model, ExpandedRow $row, int $index): Htmlable
    {
        return new HtmlString(view('grid-view::reorder-handle')->render());
    }
}
