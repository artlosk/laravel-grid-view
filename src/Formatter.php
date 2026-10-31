<?php

namespace Artlosk\LaravelGridView;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Stringable;

/**
 * Cell formats: 'text' (escaped, default), 'raw', 'ntext', 'boolean', 'date', 'datetime', 'number', 'email', 'url'.
 *
 * A format with parameters is an array: ['date', 'd.m.Y'], ['number', 2].
 * Own formats: Formatter::extend('money', fn ($value, $model, ...$params) => ...); a string result is escaped,
 * an Htmlable (HtmlString, View) is printed as is.
 */
final class Formatter
{
    protected const BUILT_IN = ['text', 'raw', 'ntext', 'boolean', 'date', 'datetime', 'number', 'email', 'url'];

    /** @var array<string, Closure> */
    protected static array $formats = [];

    public static function extend(string $name, Closure $format): void
    {
        self::$formats[$name] = $format;
    }

    public static function has(string $name): bool
    {
        return isset(self::$formats[$name]) || in_array($name, self::BUILT_IN, true);
    }

    /**
     * @param  string|array<int, mixed>  $format
     */
    public static function format(mixed $value, string|array $format, mixed $model): Htmlable
    {
        $params = is_array($format) ? array_values($format) : [$format];
        $name = array_shift($params);

        if (!is_string($name)) {
            throw new InvalidArgumentException('Grid format name must be a string.');
        }

        if (isset(self::$formats[$name])) {
            return self::html((self::$formats[$name])($value, $model, ...$params));
        }

        if ($value === null || $value === '') {
            return self::html(config('grid-view.empty_cell', ''));
        }

        return self::html(match ($name) {
            'text' => $value,
            'raw' => new HtmlString(self::string($value)),
            'ntext' => new HtmlString(nl2br(e(self::string($value)))),
            'boolean' => __($value ? 'grid-view::grid-view.yes' : 'grid-view::grid-view.no'),
            'date' => self::date($value)->format($params[0] ?? config('grid-view.date_format', 'Y-m-d')),
            'datetime' => self::date($value)->format($params[0] ?? config('grid-view.datetime_format', 'Y-m-d H:i')),
            'number' => number_format((float) $value, (int) ($params[0] ?? 0), $params[1] ?? '.', $params[2] ?? ' '),
            'email' => new HtmlString('<a href="mailto:'.e(self::string($value)).'">'.e(self::string($value)).'</a>'),
            'url' => self::url(self::string($value)),
            default => throw new InvalidArgumentException("Unknown grid format [{$name}]."),
        });
    }

    // Value as HTML: Htmlable as is, anything else escaped
    public static function html(mixed $value): Htmlable
    {
        if ($value instanceof Htmlable) {
            return $value;
        }

        return new HtmlString(e(self::string($value)));
    }

    // Removes own formats (tests, long-running workers)
    public static function flush(): void
    {
        self::$formats = [];
    }

    protected static function string(mixed $value): string
    {
        if ($value === null || is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Grid cell value must be a scalar, Stringable or Htmlable.');
    }

    protected static function date(mixed $value): Carbon
    {
        return $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse(self::string($value));
    }

    // Only web and relative links: "javascript:" and other schemes are printed as text
    protected static function url(string $url): Htmlable
    {
        if (!preg_match('~^(https?://|/|\./|\.\./|#)~i', $url)) {
            return self::html($url);
        }

        return new HtmlString('<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a>');
    }
}
