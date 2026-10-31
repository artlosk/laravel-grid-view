<?php

namespace Artlosk\LaravelGridView\Columns;

use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Row number across pages: 1, 2, 3...
 */
class SerialColumn extends Column
{
    public function key(): string
    {
        return 'serial';
    }

    public function label(): string
    {
        return $this->label === null ? '#' : GridView::label($this->label);
    }

    public function cell(mixed $model, ExpandedRow $row, int $index): Htmlable
    {
        $paginator = $this->grid->records();

        return new HtmlString((string) (($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1));
    }
}
