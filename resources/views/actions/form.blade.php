@php use Artlosk\LaravelGridView\Support\HtmlAttributes; @endphp
<form method="POST" action="{{ $url }}" class="lgv-action-form" @if ($confirm)
    {{ $confirmAttribute }}="{{ $confirm }}"
@endif>
@csrf
@unless ($method === 'POST')
    @method($method)
@endunless
<button
        type="submit"
        title="{{ $label }}"
        aria-label="{{ $label }}"
        {{ HtmlAttributes::render($attributes) }}
>@if ($icon)
        <i class="{{ $icon }}" aria-hidden="true"></i>
    @else
        {{ $label }}
    @endif</button>
</form>
