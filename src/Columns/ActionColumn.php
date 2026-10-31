<?php

namespace Artlosk\LaravelGridView\Columns;

use Artlosk\LaravelGridView\GridView;
use Artlosk\LaravelGridView\Rows\ExpandedRow;
use Artlosk\LaravelGridView\Support\HtmlAttributes;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Buttons of a row, like ActionColumn in Yii2.
 *
 *     [
 *         'class' => 'actions',
 *         'route' => 'users',                       // users.show, users.edit, users.destroy with the model
 *         'template' => '{view} {update} {delete}',
 *         'visibleButtons' => ['delete' => fn ($user) => $user->isActive()],
 *         'buttons' => [
 *             'delete' => ['confirm' => 'Deactivate the user?'],
 *             'restore' => ['route' => 'restore', 'method' => 'POST', 'icon' => 'fa fa-undo', 'label' => 'Restore'],
 *         ],
 *         'urls' => ['view' => fn ($user) => route('profile', $user)],
 *     ]
 *
 * A button is a preset from config "grid-view.action_buttons" merged with "buttons":
 * route (suffix of "route" or a full name), url (fn ($model) => ...), method, label, icon, confirm, attributes.
 */
class ActionColumn extends Column
{
    protected ?string $template = null;

    protected ?string $route = null;

    /** @var array<string, Closure(mixed): string> */
    protected array $urls = [];

    /** @var array<string, array<string, mixed>> */
    protected array $buttons = [];

    /** @var array<string, bool|Closure(mixed): bool> */
    protected array $visibleButtons = [];

    public function key(): string
    {
        return 'actions';
    }

    public function label(): string
    {
        return $this->label === null ? '' : GridView::label($this->label);
    }

    public function headerAttributes(): array
    {
        return HtmlAttributes::merge(['class' => config('grid-view.action_column_class')], parent::headerAttributes());
    }

    public function contentAttributes(mixed $model, ExpandedRow $row): array
    {
        return HtmlAttributes::merge(['class' => config('grid-view.action_column_class')], parent::contentAttributes($model, $row));
    }

    public function cell(mixed $model, ExpandedRow $row, int $index): Htmlable
    {
        $template = $this->template ?? config('grid-view.action_template', '{view} {update} {delete}');

        $html = preg_replace_callback(
            '/\{([\w-]+)\}/',
            fn (array $match): string => $this->button($match[1], $model),
            $template,
        );

        return new HtmlString(trim((string) $html));
    }

    protected function button(string $name, mixed $model): string
    {
        $visible = $this->visibleButtons[$name] ?? true;

        if ($visible instanceof Closure ? !$visible($model) : !$visible) {
            return '';
        }

        $preset = config("grid-view.action_buttons.{$name}", []);
        $custom = $this->buttons[$name] ?? [];

        if ($preset === [] && $custom === []) {
            throw new InvalidArgumentException("Unknown grid action button [{$name}].");
        }

        $options = array_merge($preset, $custom);
        $attributes = HtmlAttributes::merge(
            $this->attributes($preset['attributes'] ?? [], $model),
            $this->attributes($custom['attributes'] ?? [], $model),
        );
        $method = strtoupper($options['method'] ?? 'GET');
        $label = $this->buttonLabel($name, $options['label'] ?? null);
        $confirm = $options['confirm'] ?? false;

        return view($method === 'GET' ? 'grid-view::actions.link' : 'grid-view::actions.form', [
            'url' => $this->url($name, $options, $model),
            'method' => $method,
            'label' => $label,
            'icon' => $options['icon'] ?? null,
            'attributes' => $attributes,
            'confirmAttribute' => config('grid-view.confirm_attribute', 'data-grid-confirm'),
            'confirm' => match (true) {
                $confirm === true => __('grid-view::grid-view.confirm_delete'),
                is_string($confirm) => GridView::label($confirm),
                default => null,
            },
        ])->render();
    }

    /** @param  array<string, mixed>  $options */
    protected function url(string $name, array $options, mixed $model): string
    {
        $url = $options['url'] ?? $this->urls[$name] ?? null;

        if ($url instanceof Closure) {
            return $url($model);
        }

        $route = $options['route'] ?? null;

        if (!is_string($route)) {
            throw new InvalidArgumentException("Grid action button [{$name}] needs a route or a url.");
        }

        return route($this->route === null ? $route : "{$this->route}.{$route}", $model);
    }

    /**
     * @param  array<string, mixed>|Closure(mixed): array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function attributes(array|Closure $attributes, mixed $model): array
    {
        return $attributes instanceof Closure ? $attributes($model) : $attributes;
    }

    protected function buttonLabel(string $name, ?string $label): string
    {
        if ($label !== null) {
            return GridView::label($label);
        }

        $key = "grid-view::grid-view.{$name}";
        $translated = __($key);

        return $translated === $key ? GridView::label(Str::headline($name)) : $translated;
    }

    protected function options(): array
    {
        return [...parent::options(), 'template', 'route', 'urls', 'buttons', 'visibleButtons'];
    }
}
