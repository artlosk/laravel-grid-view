<?php

namespace Artlosk\LaravelGridView\Columns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Artlosk\LaravelGridView\Support\EvaluatesClosures;
use UnexpectedValueException;

class Column
{
    use EvaluatesClosures;

    protected string|Closure|null $label = null;

    protected bool|Closure $visible = true;

    protected bool $sortable = false;

    protected string|Closure|null $sort = null;

    protected ?Closure $renderer = null;

    protected string|Closure|null $cellClass = null;

    protected string|Closure|null $headerClass = null;

    protected bool $raw = false;

    protected function __construct(protected string $key) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(string|Closure $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function isVisible(GridView $grid): bool
    {
        if ($this->evaluate($this->visible, $grid)) {
            return true;
        }

        return false;
    }

    public function sortable(string|Closure|null $sort = null): static
    {
        $this->sortable = true;
        $this->sort = $sort;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function applySort(Builder $query, string $direction): void
    {
        if ($this->sort instanceof Closure) {
            ($this->sort)($query, $direction);

            return;
        }

        $query->orderBy($this->sort ?? $this->key, $direction);
    }

    public function render(Closure $renderer, bool $raw = false): static
    {
        $this->renderer = $renderer;
        $this->raw = $raw;

        return $this;
    }

    public function raw(Closure $renderer): static
    {
        return $this->render($renderer, true);
    }

    public function cellClass(string|Closure|null $class): static
    {
        $this->cellClass = $class;

        return $this;
    }

    public function headerClass(string|Closure|null $class): static
    {
        $this->headerClass = $class;

        return $this;
    }

    public function getLabel(GridView $grid): string
    {
        $label = $this->evaluate($this->label, $grid) ?? $this->key;

        if (!is_string($label)) {
            throw new UnexpectedValueException("Column [{$this->key}] label must be a string.");
        }

        return $label;
    }

    public function getCellClass(object $record, ExpandedRow $row): ?string
    {
        return $this->evaluate($this->cellClass, $record, $row);
    }

    public function getHeaderClass(GridView $grid): ?string
    {
        return $this->evaluate($this->headerClass, $grid);
    }

    public function getValue(object $record, ExpandedRow $row): mixed
    {
        if ($row->has($this->key)) {
            $value = $row->get($this->key);
        } else {
            $value = data_get($record, $this->key);
        }

        if ($this->renderer) {
            $value = ($this->renderer)($value, $record, $row);
        }

        if ($value instanceof Htmlable) {
            return $value;
        }

        return $this->raw ? new HtmlString((string) $value) : $value;
    }
}
