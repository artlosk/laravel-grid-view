<?php

namespace Artlosk\LaravelGridView\Columns;

use Artlosk\LaravelGridView\Formatter;
use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Artlosk\LaravelGridView\Support\HtmlAttributes;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A column of model data, the default column. Short form: 'attribute', 'attribute:format', 'attribute:format:Label'.
 *
 *     [
 *         'attribute' => 'status',          // value, sort key and column key
 *         'label' => 'Status',
 *         'value' => fn ($model, $row) => ...,  // or a dotted path: 'country.name'
 *         'format' => 'text',               // 'raw', ['date', 'd.m.Y'], own format or fn ($value, $model) => ...
 *         'view' => 'partials.status-badge',    // Blade view with $model, $value, $row
 *         'url' => fn ($model) => route(...),   // wraps the cell into a link
 *         'linkOptions' => ['data-dialog' => 'view'],
 *         'sortable' => false,              // the header links only if the data provider allows the attribute
 *     ]
 */
class DataColumn extends Column
{
    protected ?string $attribute = null;

    protected ?string $key = null;

    protected Closure|string|null $value = null;

    /** @var string|array<int, mixed>|Closure */
    protected string|array|Closure $format = 'text';

    protected ?string $view = null;

    protected ?Closure $url = null;

    /** @var array<string, mixed> */
    protected array $linkOptions = [];

    protected bool $sortable = true;

    public function __construct(array $config, GridView $grid, int $index)
    {
        parent::__construct($config, $grid, $index);

        if ($this->attribute === null && $this->key === null && $this->value === null && $this->view === null) {
            throw new InvalidArgumentException('Grid data column needs an attribute, a key, a value or a view.');
        }
    }

    /**
     * 'name:text:Label' → ['attribute' => 'name', 'format' => 'text', 'label' => 'Label']
     *
     * @return array<string, string>
     */
    public static function parse(string $definition): array
    {
        if (!preg_match('/^([^:]+)(?::(\w*))?(?::(.+))?$/Du', $definition, $parts)) {
            throw new InvalidArgumentException("Invalid grid column definition [{$definition}].");
        }

        return array_filter([
            'attribute' => $parts[1],
            'format' => $parts[2] ?? '',
            'label' => $parts[3] ?? '',
        ], static fn (string $value): bool => $value !== '');
    }

    public function key(): string
    {
        return $this->key ?? $this->attribute ?? 'column'.$this->index;
    }

    public function label(): string
    {
        return GridView::label($this->label ?? Str::headline($this->attribute ?? $this->key ?? ''));
    }

    public function isSortable(): bool
    {
        return $this->sortable && $this->grid->dataProvider()->sort()->has($this->key());
    }

    public function cell(mixed $model, ExpandedRow $row, int $index): Htmlable
    {
        $value = $this->value($model, $row);

        if ($this->view !== null) {
            $html = view($this->view, ['model' => $model, 'value' => $value, 'row' => $row]);
        } elseif ($this->format instanceof Closure) {
            $html = Formatter::html(($this->format)($value, $model, $row));
        } else {
            $html = Formatter::format($value, $this->format, $model);
        }

        if ($this->url === null) {
            return $html;
        }

        $attributes = HtmlAttributes::merge(config('grid-view.link_attributes', []), $this->linkOptions, [
            'href' => ($this->url)($model),
        ]);

        return new HtmlString('<a'.HtmlAttributes::render($attributes)->toHtml().'>'.$html->toHtml().'</a>');
    }

    // Value of an expanded row wins, then "value", then the model attribute
    public function value(mixed $model, ExpandedRow $row): mixed
    {
        if ($row->has($this->key())) {
            return $row->get($this->key());
        }

        if ($this->value instanceof Closure) {
            return ($this->value)($model, $row);
        }

        $path = $this->value ?? $this->attribute;

        return $path === null ? null : data_get($model, $path);
    }

    protected function options(): array
    {
        return [...parent::options(), 'attribute', 'key', 'value', 'format', 'view', 'url', 'linkOptions', 'sortable'];
    }
}
