<?php

namespace Artlosk\LaravelGridView\Actions;

use Closure;
use InvalidArgumentException;
use Artlosk\LaravelGridView\Support\EvaluatesClosures;
use UnexpectedValueException;

class Action
{
    use EvaluatesClosures;

    public const TYPE_LINK = 'link';
    public const TYPE_FORM = 'form';
    public const TYPE_BUTTON = 'button';

    protected string|Closure|null $title = null;

    protected string|Closure|null $icon = null;

    protected string|Closure|null $url = null;

    protected string $method = 'GET';

    protected bool|Closure $visible = true;

    protected array|Closure $attributes = [];

    protected array|Closure $formAttributes = [];

    protected string|Closure|null $confirm = null;

    protected function __construct(
        protected string $name,
        protected string $type = self::TYPE_LINK,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function link(string $name, string|Closure $url): static
    {
        return (new static($name, self::TYPE_LINK))->url($url);
    }

    public static function form(string $name, string|Closure $url, string $method = 'POST'): static
    {
        return (new static($name, self::TYPE_FORM))->url($url)->asForm($method);
    }

    public static function button(string $name): static
    {
        return new static($name, self::TYPE_BUTTON);
    }

    public function title(string|Closure $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function icon(string|Closure $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function url(string|Closure $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function method(string $method): static
    {
        $method = strtoupper($method);

        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException("Unsupported action method [{$method}].");
        }

        $this->method = $method;

        if ($this->type !== self::TYPE_BUTTON) {
            $this->type = $method === 'GET' ? self::TYPE_LINK : self::TYPE_FORM;
        }

        return $this;
    }

    public function asLink(): static
    {
        $this->type = self::TYPE_LINK;
        $this->method = 'GET';

        return $this;
    }

    public function asForm(string $method = 'POST'): static
    {
        $method = strtoupper($method);

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException("Form actions do not support method [{$method}].");
        }

        $this->type = self::TYPE_FORM;
        $this->method = $method;

        return $this;
    }

    public function asButton(): static
    {
        $this->type = self::TYPE_BUTTON;
        $this->url = null;

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function attributes(array|Closure $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function formAttributes(array|Closure $attributes): static
    {
        $this->formAttributes = $attributes;

        return $this;
    }

    public function confirm(string|Closure $message): static
    {
        $this->confirm = $message;

        return $this;
    }

    public function isVisible(object $record): bool
    {
        if ($this->evaluate($this->visible, $record)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *     name: string,
     *     type: string,
     *     title: string,
     *     icon: string,
     *     url: ?string,
     *     method: string,
     *     attributes: array<string, mixed>,
     *     form_attributes: array<string, mixed>,
     *     confirm: ?string
     * }
     */
    public function forRecord(object $record): array
    {
        $url = $this->evaluate($this->url, $record);
        $attributes = $this->evaluate($this->attributes, $record);
        $formAttributes = $this->evaluate($this->formAttributes, $record);
        $confirm = $this->evaluate($this->confirm, $record);
        $title = $this->evaluate($this->title, $record) ?? $this->name;
        $icon = $this->evaluate($this->icon, $record) ?? '';

        if ($this->type === self::TYPE_BUTTON) {
            $url = null;
        } elseif (!is_string($url) || trim($url) === '') {
            throw new UnexpectedValueException(
                "Action [{$this->name}] must return a non-empty URL.",
            );
        } else {
            $url = trim($url);
            $this->validateActionUrl($url);
        }

        if (!is_array($attributes)) {
            $attributes = [];
        }

        if (!is_array($formAttributes)) {
            $formAttributes = [];
        }

        if (!is_string($title) || !is_string($icon) || ($confirm !== null && !is_string($confirm))) {
            throw new UnexpectedValueException(
                "Action [{$this->name}] text callbacks must return strings or null.",
            );
        }

        return [
            'name' => $this->name,
            'type' => $this->type,
            'title' => $title,
            'icon' => $icon,
            'url' => is_string($url) ? $url : null,
            'method' => $this->method,
            'attributes' => $attributes,
            'form_attributes' => $formAttributes,
            'confirm' => $confirm,
        ];
    }

    protected function validateActionUrl(string $url): void
    {
        if (str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            throw new UnexpectedValueException(
                "Action [{$this->name}] URL contains unsafe characters.",
            );
        }

        $parts = parse_url($url);

        if ($parts === false) {
            throw new UnexpectedValueException("Action [{$this->name}] URL is invalid.");
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
            throw new UnexpectedValueException("Action [{$this->name}] URL scheme is not allowed.");
        }

        if ($this->type !== self::TYPE_FORM || !isset($parts['host'])) {
            return;
        }

        $requestParts = parse_url(request()->getSchemeAndHttpHost());
        $requestScheme = is_array($requestParts) ? strtolower($requestParts['scheme'] ?? '') : '';
        $sameHost = is_array($requestParts)
            && strtolower($parts['host']) === strtolower($requestParts['host'] ?? '')
            && $this->urlPort($parts, $scheme ?: $requestScheme)
                === $this->urlPort($requestParts, $requestScheme);

        if (
            !$sameHost
            || ($scheme !== '' && $scheme !== $requestScheme)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new UnexpectedValueException(
                "Form action [{$this->name}] must use a relative or same-origin URL.",
            );
        }
    }

    /**
     * @param  array<string, int|string>  $parts
     */
    protected function urlPort(array $parts, string $scheme): int
    {
        return $parts['port'] ?? (strtolower($scheme) === 'https' ? 443 : 80);
    }
}
