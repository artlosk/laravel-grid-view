# Laravel Grid View

[Русская версия](README.ru.md)

A small server-side table for Laravel. It supports sorting, pagination, row actions, grouped rows, and optional AJAX navigation.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Node.js 20+ only for JavaScript tests

Laravel 13 requires PHP 8.3+.

## Install

```bash
composer require artlosk/laravel-grid-view
```

Laravel finds the service provider automatically.

Publish only the files you want to change:

```bash
php artisan vendor:publish --tag=grid-view-config
php artisan vendor:publish --tag=grid-view-views
php artisan vendor:publish --tag=grid-view-translations
php artisan vendor:publish --tag=grid-view-assets
```

## Basic table

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
            Column::make('name')->label('Name')->sortable(),
            Column::make('email')->label('Email')->sortable(),
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

## Actions

```php
use Artlosk\LaravelGridView\Actions\Action;
use Artlosk\LaravelGridView\Columns\ActionColumn;

ActionColumn::make()->actions([
    Action::link('show', fn ($user) => route('users.show', $user))
        ->title('Open')
        ->icon('fa fa-eye'),

    Action::form('delete', fn ($user) => route('users.destroy', $user), 'DELETE')
        ->title('Delete')
        ->icon('fa fa-trash')
        ->visible(fn ($user) => auth()->user()->can('delete', $user))
        ->confirm('Delete this user?'),
]);
```

`visible()` changes the table only. Keep access checks in policies and controllers.

## Grouped rows

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

Shared cells receive `rowspan`. Each record gets its own `<tbody>`.

## AJAX

Include the published files and call `ajax()`:

```blade
<link rel="stylesheet" href="{{ asset('vendor/grid-view/grid-view.css') }}">
<script src="{{ asset('vendor/grid-view/grid-view.js') }}" defer></script>
```

Links still work when JavaScript is disabled.

## More examples

- [PHP API](docs/api.md)
- [AJAX](docs/ajax.md)
- [Appearance](docs/customization.md)

## Tests

```bash
composer test
npm test
```

## Support

If this package helps you, you can tip development. Donations are voluntary; no goods or services are expected in return.

| Network | Address |
|---------|---------|
| TON | `UQAIUhN2MYnoAvgOvH5FYmfKLZbSFHhfRunIFO71xIwvO7NT` |
| BTC | `bc1qfuhz4y8jq68ct9q6pzvyyunamzhkqx6ey75y5m` |
| ETH | `0x87B1F22144ce332CBF037533ccCE4039C7567274` |
| USDT (ERC-20) | `0x87B1F22144ce332CBF037533ccCE4039C7567274` |
| USDT (TRC-20) | `TMVfMAfLmNcHNxKZhaT56nkNgSqvsx5NaE` |
| USDT (TON) | `UQAIUhN2MYnoAvgOvH5FYmfKLZbSFHhfRunIFO71xIwvO7NT` |

Send USDT only on the network listed for that address (ERC-20, TRC-20, or TON).

The package uses the MIT license.
