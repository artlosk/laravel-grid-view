<?php

namespace Artlosk\LaravelGridView\Filters;

use Artlosk\LaravelGridView\GridView;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A filter of a data provider: what the user may send and how it is shown above the grid.
 *
 * Only declared filters reach the query, and every value is normalized:
 * text is trimmed, select values must be one of the items, dates must be Y-m-d.
 */
final class Filter
{
    public const TEXT = 'text';

    public const SELECT = 'select';

    public const DATE = 'date';

    protected ?string $placeholder = null;

    protected bool $multiple = false;

    protected ?string $emptyLabel = null;

    protected ?string $fieldClass = null;

    protected bool $hidden = false;

    /** @var string|list<string>|null */
    protected string|array|null $default = null;

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var list<array{value: string, label: string, attributes: array<string, mixed>}|array{group: string, items: list<array{value: string, label: string, attributes: array<string, mixed>}>}> */
    protected array $items = [];

    /** @var list<string> */
    protected array $values = [];

    protected function __construct(
        public readonly string $type,
        public readonly string $name,
        protected ?string $label,
    ) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
            throw new InvalidArgumentException("Invalid filter name [{$name}].");
        }
    }

    public static function text(string $name, ?string $label = null): self
    {
        return new self(self::TEXT, $name, $label);
    }

    /**
     * Items: [value => label] or a list of ['value' => ..., 'label' => ..., 'attributes' => [...]].
     * Option groups: ['Group' => [value => label]] or ['group' => 'Group', 'items' => [...]].
     *
     * @param  iterable<array-key, mixed>  $items
     */
    public static function select(string $name, ?string $label = null, iterable $items = []): self
    {
        $filter = new self(self::SELECT, $name, $label);

        foreach ($items as $key => $item) {
            if (is_array($item) && array_key_exists('items', $item)) {
                $filter->items[] = ['group' => (string) ($item['group'] ?? ''), 'items' => $filter->options($item['items'])];
            } elseif (is_array($item) && !array_key_exists('value', $item)) {
                $filter->items[] = ['group' => (string) $key, 'items' => $filter->options($item)];
            } else {
                $filter->items[] = $filter->option($key, $item);
            }
        }

        return $filter;
    }

    public static function date(string $name, ?string $label = null): self
    {
        return new self(self::DATE, $name, $label);
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function emptyLabel(string $label): self
    {
        $this->emptyLabel = $label;

        return $this;
    }

    /**
     * Value used when the request has no such parameter; an empty parameter still means "no filter"
     *
     * @param  string|list<string>|null  $value
     */
    public function default(string|array|null $value): self
    {
        $this->default = $value;

        return $this;
    }

    /** @return string|list<string>|null */
    public function defaultValue(): string|array|null
    {
        return $this->default;
    }

    // Accepted from the query string but not shown in the form: the value is kept in a hidden input
    public function hidden(bool $hidden = true): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    // CSS class of the field wrapper, e.g. "col-md-4"
    public function fieldClass(string $class): self
    {
        $this->fieldClass = $class;

        return $this;
    }

    /** @param  array<string, mixed>  $attributes */
    public function attributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function label(): string
    {
        return GridView::label($this->label ?? Str::headline($this->name));
    }

    public function placeholderText(): ?string
    {
        return $this->placeholder === null ? null : GridView::label($this->placeholder);
    }

    public function emptyLabelText(): string
    {
        return $this->emptyLabel === null ? __('grid-view::grid-view.all') : GridView::label($this->emptyLabel);
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function fieldClassName(): ?string
    {
        return $this->fieldClass;
    }

    /** @return array<string, mixed> */
    public function inputAttributes(): array
    {
        return $this->attributes;
    }

    /** @return list<array{value: string, label: string, attributes: array<string, mixed>}|array{group: string, items: list<array{value: string, label: string, attributes: array<string, mixed>}>}> */
    public function items(): array
    {
        return $this->items;
    }

    public function inputName(): string
    {
        return $this->multiple ? $this->name.'[]' : $this->name;
    }

    /** @return string|list<string>|null */
    public function normalize(mixed $input): string|array|null
    {
        return match ($this->type) {
            self::TEXT => is_string($input) && trim($input) !== '' ? trim($input) : null,
            self::DATE => is_string($input) && $this->isDate($input) ? $input : null,
            default => $this->multiple ? $this->normalizeMany($input) : $this->normalizeOne($input),
        };
    }

    protected function normalizeOne(mixed $input): ?string
    {
        if (!is_scalar($input) || $input === '') {
            return null;
        }

        $value = (string) $input;

        return $this->allows($value) ? $value : null;
    }

    /** @return list<string> */
    protected function normalizeMany(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $values = [];

        foreach ($input as $item) {
            if (is_scalar($item) && $item !== '' && $this->allows((string) $item)) {
                $values[] = (string) $item;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  iterable<array-key, mixed>  $items
     * @return list<array{value: string, label: string, attributes: array<string, mixed>}>
     */
    protected function options(iterable $items): array
    {
        $options = [];

        foreach ($items as $key => $item) {
            $options[] = $this->option($key, $item);
        }

        return $options;
    }

    /** @return array{value: string, label: string, attributes: array<string, mixed>} */
    protected function option(int|string $key, mixed $item): array
    {
        $option = is_array($item)
            ? [
                'value' => (string) $item['value'],
                'label' => (string) ($item['label'] ?? $item['value']),
                'attributes' => $item['attributes'] ?? $item['attrs'] ?? [],
            ]
            : ['value' => (string) $key, 'label' => (string) $item, 'attributes' => []];

        $this->values[] = $option['value'];

        return $option;
    }

    // A select without items accepts any scalar: the list is built elsewhere (e.g. by JS)
    protected function allows(string $value): bool
    {
        return $this->values === [] || in_array($value, $this->values, true);
    }

    protected function isDate(string $value): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
