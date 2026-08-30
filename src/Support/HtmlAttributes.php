<?php

namespace Artlosk\LaravelGridView\Support;

use InvalidArgumentException;
use Stringable;

class HtmlAttributes
{
    /**
     * @param  array<array-key, mixed>  $attributes
     * @param  list<string>  $reserved
     * @return array<string, bool|string|null>
     */
    public static function escaped(array $attributes, array $reserved = []): array
    {
        $escaped = [];
        $reserved = array_map('strtolower', $reserved);

        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !preg_match('/^[^\s"\'<>\/=]+$/D', $name)) {
                throw new InvalidArgumentException('HTML attribute names must contain only valid characters.');
            }

            $lowerName = strtolower($name);

            if (in_array($lowerName, $reserved, true)) {
                continue;
            }

            if (str_starts_with($lowerName, 'on')) {
                throw new InvalidArgumentException("Inline event attribute [{$name}] is not allowed.");
            }

            if ($value === null || is_bool($value)) {
                $escaped[$name] = $value;

                continue;
            }

            if (!is_scalar($value) && !$value instanceof Stringable) {
                throw new InvalidArgumentException(
                    "HTML attribute [{$name}] must be a scalar, Stringable, boolean, or null value.",
                );
            }

            $escaped[$name] = e((string) $value);
        }

        return $escaped;
    }
}
