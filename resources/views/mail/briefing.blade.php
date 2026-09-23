<x-mail::message>
@foreach ($sections as $section)
## {{ __($section['title']) }}{{ $section['count'] > 0 ? ' ('.$section['count'].')' : '' }}

@forelse ($section['items'] as $item)
@php
    $parts = array_filter([
        $item['detail'],
        $item['at'] ? \Illuminate\Support\Carbon::parse($item['at'])->timezone(config('app.timezone'))->format(strlen($item['at']) === 10 ? 'd/m' : 'd/m H:i') : null,
    ]);
@endphp
- {{ $item['label'] ? __($item['label']).' · ' : '' }}[{{ $item['title'] }}]({{ $item['url'] ? url($item['url']) : $url }}){{ $parts ? ' — '.implode(' · ', $parts) : '' }}
@empty
{{ __('Nothing.') }}
@endforelse

@if ($section['count'] > count($section['items']))
{{ __('And :count more.', ['count' => $section['count'] - count($section['items'])]) }}

@endif
@endforeach

<x-mail::button :url="$url">
{{ __('Open in CIDASH') }}
</x-mail::button>

{{ __('You can choose what you receive by email in Settings → Notifications.') }}
</x-mail::message>
