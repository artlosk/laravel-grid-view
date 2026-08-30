<div class="lgv-actions">
    @foreach ($actions as $action)
        @php
            $accessible = [
                'title' => $action['title'],
                'aria-label' => $action['title'],
            ];
            $confirm = $action['confirm'] !== null ? ['data-grid-confirm' => $action['confirm']] : [];
            $reservedControlAttributes = $action['type'] === 'link'
                ? ['href']
                : ['type', 'form', 'formaction', 'formmethod', 'formenctype', 'formtarget', 'formnovalidate'];
            $safeAttributes = Artlosk\LaravelGridView\Support\HtmlAttributes::escaped(
                $action['attributes'],
                array_merge($reservedControlAttributes, ['title', 'aria-label', 'data-grid-confirm']),
            );
            $generatedAttributes = Artlosk\LaravelGridView\Support\HtmlAttributes::escaped(array_merge(
                $accessible,
                $action['type'] === 'form' ? [] : $confirm,
            ));
            $attributes = new Illuminate\View\ComponentAttributeBag(array_merge(
                $safeAttributes,
                $generatedAttributes,
            ));
            $safeFormAttributes = Artlosk\LaravelGridView\Support\HtmlAttributes::escaped(
                $action['form_attributes'],
                ['action', 'method', 'data-grid-confirm'],
            );
            $formAttributes = new Illuminate\View\ComponentAttributeBag(array_merge(
                $safeFormAttributes,
                Artlosk\LaravelGridView\Support\HtmlAttributes::escaped($confirm),
            ));
        @endphp

        @if ($action['type'] === 'form')
            <form
                action="{{ $action['url'] }}"
                method="POST"
                {{ $formAttributes->class('lgv-action-form') }}
            >
                @csrf
                @if (!in_array($action['method'], ['GET', 'POST'], true))
                    @method($action['method'])
                @endif
                <button type="submit" {{ $attributes->class('lgv-action') }}>
                    @if ($action['icon'] !== '')
                        <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                    @else
                        <span>{{ $action['title'] }}</span>
                    @endif
                </button>
            </form>
        @elseif ($action['type'] === 'button')
            <button
                type="button"
                {{ $attributes->class('lgv-action') }}
            >
                @if ($action['icon'] !== '')
                    <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                @else
                    <span>{{ $action['title'] }}</span>
                @endif
            </button>
        @else
            <a
                href="{{ $action['url'] }}"
                {{ $attributes->class('lgv-action') }}
            >
                @if ($action['icon'] !== '')
                    <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                @else
                    <span>{{ $action['title'] }}</span>
                @endif
            </a>
        @endif
    @endforeach
</div>
