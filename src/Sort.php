<?php

namespace Artlosk\LaravelGridView;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Sorting of a data provider: an allow-list of attributes, the default order and the current order.
 *
 * Attributes of a query: 'name' (orders by the column), 'balance' => 'accounts.current_balance' (another column)
 * or 'account' => fn (Builder $query, string $direction) => ... (custom ordering).
 * Attributes of an array: 'name' (the key), 'total' => 'totals.amount' (a dotted path)
 * or 'owner' => fn ($model) => ... (the value to compare).
 * Default order: 'name' ascending, '-created_at' descending.
 */
final class Sort
{
    /** @var array<string, string|Closure> */
    protected array $attributes = [];

    protected ?string $defaultColumn = null;

    protected string $defaultDirection = 'asc';

    protected ?string $explicitColumn = null;

    protected string $explicitDirection = 'asc';

    protected bool $explicit = false;

    /**
     * @param  array<array-key, string|Closure>  $attributes
     */
    public function __construct(
        array $attributes,
        ?string $default,
        protected Request $request,
        protected string $sortParameter,
        protected string $directionParameter,
    ) {
        foreach ($attributes as $name => $definition) {
            if (is_int($name)) {
                $this->attributes[(string) $definition] = (string) $definition;
            } else {
                $this->attributes[$name] = $definition;
            }
        }

        if ($default !== null && $default !== '') {
            [$this->defaultColumn, $this->defaultDirection] = self::parse($default);
        }
    }

    /** @return array{0: string, 1: 'asc'|'desc'} */
    public static function parse(string $spec): array
    {
        return str_starts_with($spec, '-') ? [substr($spec, 1), 'desc'] : [$spec, 'asc'];
    }

    /** @return list<string> */
    public function attributes(): array
    {
        return array_keys($this->attributes);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    // Order set by code (export jobs, console): the request is ignored
    public function set(?string $column, string $direction = 'asc'): void
    {
        $this->explicit = true;
        $this->explicitColumn = $column !== null && $this->has($column) ? $column : null;
        $this->explicitDirection = self::parseDirection($direction);
    }

    public function column(): ?string
    {
        if ($this->explicit) {
            return $this->explicitColumn ?? $this->defaultColumn;
        }

        return $this->requestedColumn() ?? $this->defaultColumn;
    }

    /** @return 'asc'|'desc' */
    public function direction(): string
    {
        if ($this->explicit) {
            return $this->explicitColumn !== null ? $this->explicitDirection : $this->defaultDirection;
        }

        if ($this->requestedColumn() !== null) {
            $direction = $this->request->query($this->directionParameter);

            return self::parseDirection(is_string($direction) ? $direction : 'asc');
        }

        return $this->defaultDirection;
    }

    /** @return 'asc'|'desc'|null */
    public function directionOf(string $name): ?string
    {
        return $this->column() === $name ? $this->direction() : null;
    }

    // Sorting applied to the query: false when there is nothing to sort by
    public function apply(Builder $query): bool
    {
        $column = $this->column();

        if ($column === null) {
            return false;
        }

        $definition = $this->attributes[$column] ?? $column;
        $query->reorder();

        if ($definition instanceof Closure) {
            $definition($query, $this->direction());
        } else {
            $query->orderBy($definition, $this->direction());
        }

        return true;
    }

    /**
     * Records of an array provider in the current order; PHP sorting is stable, so equal values keep their order
     *
     * @template TModel
     *
     * @param  Collection<array-key, TModel>  $models
     * @return Collection<int, TModel>
     */
    public function sortCollection(Collection $models): Collection
    {
        $column = $this->column();

        if ($column === null) {
            return $models->values();
        }

        $definition = $this->attributes[$column] ?? $column;
        $value = $definition instanceof Closure ? $definition : static fn (mixed $model): mixed => data_get($model, $definition);

        return $models->sortBy($value, SORT_REGULAR, $this->direction() === 'desc')->values();
    }

    /**
     * Query string of a header link: same column toggles the direction, another one starts ascending.
     *
     * @return array<string, string>
     */
    public function linkQuery(string $name): array
    {
        $direction = $this->column() === $name && $this->direction() === 'asc' ? 'desc' : 'asc';

        return [$this->sortParameter => $name, $this->directionParameter => $direction];
    }

    public function sortParameter(): string
    {
        return $this->sortParameter;
    }

    public function directionParameter(): string
    {
        return $this->directionParameter;
    }

    protected function requestedColumn(): ?string
    {
        $column = $this->request->query($this->sortParameter);

        return is_string($column) && $this->has($column) ? $column : null;
    }

    /** @return 'asc'|'desc' */
    protected static function parseDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }
}
