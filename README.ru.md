# Laravel Grid View

[English version](README.md)

Небольшая серверная таблица для Laravel. Поддерживает сортировку, пагинацию, действия, группировку строк и загрузку через AJAX.

## Требования

- PHP 8.2+
- Laravel 11, 12 или 13
- Node.js 20+ нужен только для тестов JavaScript

Для Laravel 13 нужен PHP 8.3+.

## Установка

```bash
composer require artlosk/laravel-grid-view
```

Laravel сам найдёт service provider.

Публикуйте только те файлы, которые хотите изменить:

```bash
php artisan vendor:publish --tag=grid-view-config
php artisan vendor:publish --tag=grid-view-views
php artisan vendor:publish --tag=grid-view-translations
php artisan vendor:publish --tag=grid-view-assets
```

## Простая таблица

```php
use App\Models\User;
use Illuminate\Http\Request;
use Artlosk\LaravelGridView\Columns\Column;
use Artlosk\LaravelGridView\GridRequest;
use Artlosk\LaravelGridView\GridView;

public function index(Request $request)
{
    $grid = GridView::make(User::query(), $request, 'users-grid')
        ->columns([
            Column::make('name')->label('Имя')->sortable(),
            Column::make('email')->label('Почта')->sortable(),
        ])
        ->defaultSort('name')
        ->paginate(25)
        ->ajax();

    if (GridRequest::wantsPartial($request)) {
        return $grid->partialResponse();
    }

    return view('users.index', compact('grid'));
}
```

```blade
{{ $grid }}
```

## Действия

```php
use Artlosk\LaravelGridView\Actions\Action;
use Artlosk\LaravelGridView\Columns\ActionColumn;

ActionColumn::make()->actions([
    Action::link('show', fn ($user) => route('users.show', $user))
        ->title('Открыть')
        ->icon('fa fa-eye'),

    Action::form('delete', fn ($user) => route('users.destroy', $user), 'DELETE')
        ->title('Удалить')
        ->icon('fa fa-trash')
        ->visible(fn ($user) => auth()->user()->can('delete', $user))
        ->confirm('Удалить пользователя?'),
]);
```

`visible()` только меняет таблицу. Проверяйте доступ в policies и контроллерах.

## Группировка строк

```php
use Artlosk\LaravelGridView\Rows\ExpandedGroup;
use Artlosk\LaravelGridView\Rows\ExpandedRow;

$grid->rowExpander(fn ($invoice) => ExpandedGroup::make(
    $invoice->lines->map(fn ($line) => ExpandedRow::make([
        'product' => $line->product->name,
        'quantity' => $line->quantity,
    ]))->all(),
    sharedColumns: ['number', 'customer', 'actions'],
));
```

Общие ячейки получают `rowspan`. Для каждой записи создаётся свой `<tbody>`.

## AJAX

Подключите опубликованные файлы и вызовите `ajax()`:

```blade
<link rel="stylesheet" href="{{ asset('vendor/grid-view/grid-view.css') }}">
<script src="{{ asset('vendor/grid-view/grid-view.js') }}" defer></script>
```

Без JavaScript ссылки продолжат работать обычным способом.

## Другие примеры

- [PHP API](docs/api.ru.md)
- [AJAX](docs/ajax.ru.md)
- [Внешний вид](docs/customization.ru.md)

## Тесты

```bash
composer test
npm test
```

## Поддержка

Если пакет оказался полезен, можно поддержать разработку. Донат добровольный; взамен товары или услуги не предоставляются.

| Сеть | Адрес |
|------|-------|
| TON | `UQAIUhN2MYnoAvgOvH5FYmfKLZbSFHhfRunIFO71xIwvO7NT` |
| BTC | `bc1qfuhz4y8jq68ct9q6pzvyyunamzhkqx6ey75y5m` |
| ETH | `0x87B1F22144ce332CBF037533ccCE4039C7567274` |
| USDT (ERC-20) | `0x87B1F22144ce332CBF037533ccCE4039C7567274` |
| USDT (TRC-20) | `TMVfMAfLmNcHNxKZhaT56nkNgSqvsx5NaE` |
| USDT (TON) | `UQAIUhN2MYnoAvgOvH5FYmfKLZbSFHhfRunIFO71xIwvO7NT` |

USDT отправляйте только в той сети, которая указана у адреса (ERC-20, TRC-20 или TON).

Пакет распространяется по лицензии MIT.
