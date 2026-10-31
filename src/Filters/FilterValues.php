<?php

namespace Artlosk\LaravelGridView\Filters;

use Illuminate\Http\Request;

/**
 * Normalized filter values of a request: only declared filters, only valid values.
 */
final class FilterValues
{
    /**
     * @param  array<string, string|list<string>|null>  $values
     * @param  array<string, string|list<string>|null>  $defaults
     */
    public function __construct(protected array $values = [], protected array $defaults = []) {}

    /**
     * @param  list<Filter>  $filters
     */
    public static function from(Request $request, array $filters): self
    {
        $values = [];
        $defaults = [];

        foreach ($filters as $filter) {
            $defaults[$filter->name] = $filter->defaultValue();
            $values[$filter->name] = $request->query->has($filter->name)
                ? $filter->normalize($request->query($filter->name))
                : $filter->defaultValue();
        }

        return new self($values, $defaults);
    }

    /** @return string|list<string>|null */
    public function get(string $name): string|array|null
    {
        return $this->values[$name] ?? null;
    }

    public function string(string $name): ?string
    {
        $value = $this->get($name);

        return is_string($value) ? $value : null;
    }

    public function int(string $name): ?int
    {
        $value = $this->string($name);

        return $value !== null && preg_match('/^-?\d+$/D', $value) ? (int) $value : null;
    }

    /** @return list<string> */
    public function array(string $name): array
    {
        $value = $this->get($name);

        return is_array($value) ? $value : [];
    }

    /** @return list<int> */
    public function ints(string $name): array
    {
        return array_values(array_map('intval', array_filter(
            $this->array($name),
            static fn (string $value): bool => (bool) preg_match('/^-?\d+$/D', $value),
        )));
    }

    /** @return array<string, string|list<string>|null> */
    public function all(): array
    {
        return $this->values;
    }

    // At least one filter differs from its default: the grid shows "Reset"
    public function active(): bool
    {
        foreach ($this->values as $name => $value) {
            $default = $this->defaults[$name] ?? null;

            if ($value !== $default && ($value !== [] || $default !== null)) {
                return true;
            }
        }

        return false;
    }
}
