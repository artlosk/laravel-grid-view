<?php

namespace Artlosk\LaravelGridView\Columns;

use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

/**
 * A column of GridView, configured with an array like in Yii2:
 * ['label' => ..., 'visible' => ..., 'headerOptions' => [...], 'contentOptions' => [...] or fn ($model, $row) => [...]].
 */
abstract class Column
{
    protected ?string $label = null;

    protected bool|Closure $visible = true;

    /** @var array<string, mixed> */
    protected array $headerOptions = [];

    /** @var array<string, mixed>|Closure(mixed, ExpandedRow): array<string, mixed> */
    protected array|Closure $contentOptions = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config, protected GridView $grid, protected int $index)
    {
        foreach ($config as $key => $value) {
            if (!in_array($key, $this->options(), true)) {
                throw new InvalidArgumentException(sprintf('Unknown option [%s] of grid column %s.', $key, static::class));
            }

            $this->{$key} = $value;
        }
    }

    // Column key: the name of an ExpandedRow value and of an ExpandedGroup shared column
    abstract public function key(): string;

    abstract public function cell(mixed $model, ExpandedRow $row, int $index): Htmlable;

    abstract public function label(): string;

    public function isVisible(): bool
    {
        return $this->visible instanceof Closure ? (bool) ($this->visible)() : $this->visible;
    }

    public function isSortable(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function headerAttributes(): array
    {
        return $this->headerOptions;
    }

    /** @return array<string, mixed> */
    public function contentAttributes(mixed $model, ExpandedRow $row): array
    {
        return $this->contentOptions instanceof Closure ? ($this->contentOptions)($model, $row) : $this->contentOptions;
    }

    /** @return list<string> */
    protected function options(): array
    {
        return ['label', 'visible', 'headerOptions', 'contentOptions'];
    }
}
