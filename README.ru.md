# Laravel Grid View

[English version](README.md)

Таблица для Laravel в духе `GridView` из Yii2. Провайдер данных отвечает за запрос, фильтры, сортировку и страницы. Грид описывается одним массивом прямо во вью. Сортировка, страницы и фильтры работают без перезагрузки, и контроллеру для этого не нужен отдельный код.

## Требования

- PHP 8.2+
- Laravel 11, 12 или 13 (для Laravel 13 нужен PHP 8.3+)

## Установка

```bash
composer require artlosk/laravel-grid-view
```

Service provider Laravel найдёт сам. Стили и скрипт по умолчанию:

```bash
php artisan vendor:publish --tag=grid-view-assets
```

```blade
<link rel="stylesheet" href="{{ asset('vendor/grid-view/grid-view.css') }}">
<script src="{{ asset('vendor/grid-view/grid-view.js') }}" defer></script>
```

Через Vite можно импортировать `vendor/artlosk/laravel-grid-view/resources/js/grid-view.js` и `resources/css/grid-view.css` напрямую.

## Быстрый старт

Контроллер:

```php
use Artlosk\LaravelGridView\DataProviders\EloquentDataProvider;
use Artlosk\LaravelGridView\Filters\Filter;
use Artlosk\LaravelGridView\Filters\FilterValues;

public function index()
{
    $provider = new EloquentDataProvider(
        query: fn (FilterValues $filters) => User::query()
            ->when($filters->string('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($filters->int('status'), fn ($q, $status) => $q->where('status', $status)),
        filters: [
            Filter::text('search', 'Поиск'),
            Filter::select('status', 'Статус', [1 => 'Активные', 2 => 'Неактивные']),
        ],
        sort: ['id', 'name', 'created_at'],
        defaultSort: '-created_at',
        pageSize: 25,
    );

    return view('users.index', compact('provider'));
}
```

Вью:

```blade
@grid([
    'dataProvider' => $provider,
    'columns' => [
        'id',
        'name:text:Имя',
        'email:email',
        'created_at:datetime:Создан',
        ['attribute' => 'status', 'view' => 'users.status-badge'],
        ['class' => 'actions', 'route' => 'users'],
    ],
])
```

Готово:
- форма фильтров над таблицей;
- кликабельные заголовки для `id`, `name` и `created_at`;
- пагинация;
- кнопки «просмотр», «редактировать» и «удалить», которые ведут на `users.show`, `users.edit` и `users.destroy`;
- AJAX-перерисовка без перезагрузки страницы.

`@grid([...])` — то же самое, что `{!! GridView::widget([...]) !!}`.

## Провайдер данных

```php
new EloquentDataProvider(
    query: $builder,              // Builder или fn (FilterValues $filters) => Builder
    filters: [...],               // объявленные фильтры: только они попадают в запрос
    sort: [                       // белый список сортировки
        'name',                   // по колонке name
        'balance' => 'accounts.current_balance',          // по другой колонке
        'owner' => fn ($query, $direction) => $query->orderBy(...), // своя сортировка
    ],
    defaultSort: 'name',          // '-created_at' — по убыванию
    pageSize: 50,                 // null — из конфига, false — всё на одной странице
    maxPageSize: 100,             // клиент сам выбирает ?per_page= до этого предела (API)
);
```

Тот же провайдер подходит для API: `$provider->paginator()` отдаёт страницу записей. Для фоновых задач есть `$provider->sortBy('name', 'desc')->sortedQuery()`: порядок задаёт код, параметры запроса не учитываются.

Сортировка разрешена только по атрибутам из `sort`, остальное игнорируется. Если в запросе есть `sort_by`, но нет `sort_direction`, сортировка идёт по возрастанию. К сортировке добавляется порядок по первичному ключу, поэтому записи не скачут между страницами. Это можно отключить: `stable_pagination`.

Данные, которые уже в памяти (строки отчёта, ответ другого сервиса), отдаются в `ArrayDataProvider`. Строки могут быть массивами или объектами:

```php
new ArrayDataProvider(
    models: $report['rows'],      // iterable или fn (FilterValues $filters) => iterable
    sort: [
        'name',                   // по ключу name
        'amount' => 'totals.amount',                  // по пути через точку
        'owner' => fn ($row) => $row['owner']['name'], // по вычисленному значению
    ],
    defaultSort: '-amount',
    pageSize: 20,
);
```

Числовые строки сравниваются как числа, равные значения сохраняют порядок. `$provider->sortedModels()` отдаёт все отфильтрованные строки в текущем порядке.

## Фильтры

```php
Filter::text('search', 'Поиск')->placeholder('Имя или email');
Filter::select('status', 'Статус', [1 => 'Активные', 2 => 'Неактивные'])->default('1');
Filter::select('tags', 'Теги', $items)->multiple();
Filter::select('source', 'Источник', ['Банки' => [3 => 'Наличные', 4 => 'Карта']]); // группа
Filter::date('from', 'С');
Filter::select('country_id', 'Страна', $countries)->hidden(); // принимается, но не показывается
```

Значения приходят в `query` уже очищенными:
- текст обрезан от пробелов, массив вместо строки превращается в `null`;
- для select принимаются только значения из списка;
- дата должна быть настоящим днём в формате `Y-m-d`.

У `FilterValues` есть методы `string()`, `int()`, `array()`, `ints()`, `all()` и `active()`. `default()` действует, только когда параметра нет в запросе: пустое значение по-прежнему означает «без фильтра».

Пункты select можно передать как `[value => label]` или списком `['value' => ..., 'label' => ..., 'attributes' => [...]]`. Группы: `['Группа' => [value => label]]` или `['group' => 'Группа', 'items' => [...]]`.

`hidden()` — для фильтров, которые нужны API, но не показываются на странице: значение по-прежнему читается из адреса и хранится в скрытом поле формы.

## Колонки

Короткая запись: `'атрибут'`, `'атрибут:формат'`, `'атрибут:формат:Подпись'`.

```php
[
    'attribute' => 'country.name',      // значение, ключ колонки и ключ сортировки
    'label' => 'Страна',
    'value' => fn ($model, $row) => ..., // или путь через точку
    'format' => 'text',                 // 'raw', ['date', 'd.m.Y'], свой формат, fn ($value, $model) => ...
    'view' => 'partials.country',       // Blade-шаблон с $model, $value, $row
    'url' => fn ($model) => route('countries.show', $model),
    'linkOptions' => ['class' => 'link'],
    'sortable' => false,
    'headerOptions' => ['class' => 'text-end'],
    'contentOptions' => fn ($model, $row) => ['class' => 'text-end'],
    'visible' => true,
]
```

Типы колонок: `data` (по умолчанию), `actions`, `serial` (номер строки) или свой класс, унаследованный от `Columns\Column`. Опечатка в имени опции сразу выбрасывает исключение.

### Форматы

`text` (экранирование, по умолчанию), `raw`, `ntext`, `boolean`, `date`, `datetime`, `number`, `email`, `url`. Пустое значение выводится как `empty_cell` из конфига. Ссылки формата `url` строятся только для `http(s)` и относительных адресов.

Свой формат:

```php
Formatter::extend('money', fn ($value, $model, string $currency = 'USD') => view('partials.money', compact('value', 'currency')));
// 'balance:money' или ['attribute' => 'balance', 'format' => ['money', 'EUR']]
```

Строка экранируется, а `Htmlable` (`HtmlString`, `View`) выводится как есть.

### Кнопки действий

```php
[
    'class' => 'actions',
    'route' => 'users',                          // users.show / users.edit / users.destroy
    'template' => '{view} {update} {delete}',
    'visibleButtons' => ['delete' => fn ($user) => $user->isActive()],
    'buttons' => [
        'delete' => ['label' => 'Деактивировать', 'confirm' => 'Деактивировать пользователя?'],
        'restore' => ['route' => 'restore', 'method' => 'POST', 'icon' => 'fa fa-undo', 'label' => 'Восстановить'],
    ],
    'urls' => ['view' => fn ($user) => route('profile', $user)],
]
```

Кнопка получается из пресета `grid-view.action_buttons` и настроек колонки `buttons`. Доступные ключи: `route` (окончание имени после `route` колонки или полное имя), `url`, `method`, `label`, `icon`, `confirm`, `attributes`. CSS-классы при слиянии склеиваются, а значение `null` убирает атрибут пресета. Для запросов не через GET рендерится форма с `@csrf` и `@method`.

`visibleButtons` только прячет кнопки. Доступ проверяйте в политиках и контроллерах.

## Опции грида

```php
@grid([
    'dataProvider' => $provider,
    'columns' => [...],
    'id' => 'users-grid',          // постоянный id для AJAX, по умолчанию grid0, grid1…
    'caption' => 'Пользователи',   // заголовок над таблицей: строка или Htmlable
    'emptyText' => 'Пользователей пока нет',
    'showFilters' => false,        // без формы фильтров
    'ajax' => false,               // обычные ссылки вместо AJAX
    'rowClass' => fn ($user) => $user->isActive() ? null : 'muted',
    'rowExpander' => ...,          // см. «Группы строк»
    'reorder' => [...],            // см. «Свой порядок строк»
])
```

Опечатка в имени опции сразу выбрасывает исключение. `view(...)` для `caption` создавайте прямо в `@grid`: `View`, переданный в другую вью данными, Laravel заранее превращает в строку, а строка экранируется.

## Свой порядок строк

Записи можно перетаскивать в нужный порядок, например категории в меню:

```php
@grid([
    'dataProvider' => $provider,   // sort: ['position', ...], defaultSort: 'position', pageSize: false
    'reorder' => [
        'url' => route('categories.reorder'),
        'method' => 'PUT',         // по умолчанию POST
        'sort' => 'position',      // перетаскивать можно, только пока грид отсортирован по нему по возрастанию
        'key' => 'id',             // что отправляется: первичный ключ модели или по умолчанию id
    ],
    'columns' => [...],
])
```

Первой колонкой добавляется ручка (`['class' => 'reorder']` ставит её в другое место). Строку двигают мышью, пальцем или стрелками ↑ ↓ на ручке в фокусе. После каждого перемещения скрипт отправляет `{"ids": [...]}` с `X-CSRF-TOKEN` из `<meta name="csrf-token">`, порядок сохраняет ваш маршрут. Запросы идут по одному; если запрос не прошёл, грид перерисовывается с сохранённым порядком и приходит событие `grid-view:reorder-failed` (при успехе — `grid-view:reordered`).

Пока грид занимает больше одной страницы, перетаскивание выключено: на странице должны быть все записи.

## Группы строк

Одна запись может занимать несколько строк. Общие ячейки получают `rowspan`, и каждая запись выводится в своём `<tbody>`:

```php
'rowExpander' => fn ($invoice) => ExpandedGroup::make(
    $invoice->lines->map(fn ($line) => ExpandedRow::make(['product' => $line->name]))->all(),
    sharedColumns: ['number', 'actions'],
),
```

Значение из `ExpandedRow` по ключу колонки важнее `value` колонки, и формат применяется уже к нему.

## Подписи и переводы

```php
GridView::translateLabelsUsing(fn (string $label) => __($label));
```

Через переводчик проходят подписи колонок, кнопок, фильтров, плейсхолдеры, `caption`, `emptyText` и тексты подтверждения. Свои тексты пакета лежат в `grid-view::grid-view.*` (есть `en` и `ru`).

## AJAX

Подробности — в [docs/ajax.ru.md](docs/ajax.ru.md). Коротко:
- сортировка, страницы, форма фильтров и «Сбросить» перерисовывают только грид;
- JS отправляет заголовок `X-Grid-View: <id>`, а промежуточный слой пакета (группа `web`) возвращает только HTML этого грида;
- адрес в браузере обновляется, кнопка «Назад» работает;
- без JavaScript всё работает обычными ссылками.

## Оформление

Классы, шаблон пагинации и пресеты кнопок задаются в `config/grid-view.php`. Шаблоны можно опубликовать и переопределить:

```bash
php artisan vendor:publish --tag=grid-view-config
php artisan vendor:publish --tag=grid-view-views
php artisan vendor:publish --tag=grid-view-translations
```

Подробнее — в [docs/customization.ru.md](docs/customization.ru.md).

## Переход с v1

v2 несовместима с v1:
- **Вместо `GridView::make($query, $request)->columns([...])`** провайдер и `@grid([...])`.
- **`Column::make()`, `ActionColumn::make()->actions([...])`, `Action::link/form/button()`** заменены массивами колонок и пресетами кнопок.
- **`GridRequest::wantsPartial()` и `partialResponse()` больше не нужны**: частичный ответ формирует промежуточный слой.
- **Сортировка** задаётся провайдеру: `sort` и `defaultSort`. Вместо `->sortableKeys()` теперь `$provider->sort()->attributes()`.
- **Данные без HTML**: вместо `->records()` и `->sortedQuery()` теперь `$provider->paginator()` и `$provider->sortedQuery()`.

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
