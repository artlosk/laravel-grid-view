# AJAX

[English version](ajax.md)

При `ajax => true` (по умолчанию) сортировка, страницы и форма фильтров перерисовывают только грид.

## Как это работает

1. JS пакета перехватывает клики по заголовкам, по пагинации и по «Сбросить», а также отправку формы фильтров.
2. Он запрашивает тот же адрес с заголовком `X-Grid-View: <id грида>`.
3. Страница рендерится как обычно. Промежуточный слой `PartialGridResponse` (его пакет сам добавляет в группу `web`) находит грид с этим id и отдаёт только его HTML.
4. JS заменяет грид, обновляет адрес в браузере (`history.pushState`) и отправляет событие `grid-view:rendered` с `detail: { id, grid, url }`.

Контроллеру ничего делать не нужно: он возвращает обычный view.

Если ответ без нужного грида (редирект, ошибка, другая страница), браузер просто переходит по адресу. Кнопка «Назад» перерисовывает гриды по `popstate`.

## Подключение

```blade
<script src="{{ asset('vendor/grid-view/grid-view.js') }}" defer></script>
```

или через Vite: `import '…/vendor/artlosk/laravel-grid-view/resources/js/grid-view.js'`.

## Внешняя форма фильтров

Форма вне грида (например, общая для нескольких страниц) связывается с ним атрибутом:

```blade
<form method="GET" action="{{ route('orders.index') }}" data-grid-view-filter="orders-grid">
    …
</form>

@grid(['id' => 'orders-grid', 'dataProvider' => $provider, 'showFilters' => false, 'columns' => [...]])
```

При отправке текущая сортировка сохраняется.

## Повторная инициализация

После перерисовки новые элементы (например, select с плагином) можно оживить в одном месте:

```js
document.addEventListener('grid-view:rendered', (event) => initPlugins(event.detail.grid));
```

Обработчики кликов лучше вешать делегированием на `document`: тогда их не нужно привязывать заново.

## Подтверждение

Кнопка с `confirm` получает атрибут `data-grid-confirm` (имя меняется в `confirm_attribute`). Без своего обработчика JS пакета показывает `window.confirm`.

## Настройки

| Ключ | По умолчанию | Назначение |
|---|---|---|
| `ajax` | `true` | AJAX для всех гридов; `'ajax' => false` в конкретном гриде |
| `partial_header` | `X-Grid-View` | заголовок запроса фрагмента |
