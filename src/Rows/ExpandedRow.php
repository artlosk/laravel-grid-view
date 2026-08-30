<?php

namespace Artlosk\LaravelGridView\Rows;

class ExpandedRow
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public readonly array $values = [],
        public readonly ?string $class = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function make(array $values = [], ?string $class = null): self
    {
        return new self($values, $class);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->values[$key] : $default;
    }
}
