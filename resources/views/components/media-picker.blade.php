{{--
    <x-media-picker /> — Blade component

    Usage:
        <x-media-picker />
        <x-media-picker :multiple="true" :max="5" :sidebar="false" locale="tr" theme="dark" />
        <x-media-picker locale="en" theme="{{ $activeTheme }}" />

    JS API:
        window.MediaManager.open({
            locale: 'tr' | 'en',
            theme: 'light' | 'dark',
            onSelect(files) { ... },
        });
--}}

@php
$mmPickerTranslations = $mmPickerTranslations ?? [];
if (! is_array($mmPickerTranslations)) {
    $mmPickerTranslations = [];
}
$mmPickerConfig = config('media-manager');
$distPath = public_path('vendor/media-manager/media-manager.js');
$useDist = file_exists($distPath);
@endphp

@if($useDist)
<link rel="stylesheet" href="{{ asset('vendor/media-manager/media-manager.css') }}">
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="{{ asset('vendor/media-manager/media-manager.js') }}" defer></script>
@else
@vite(['packages/yazilim360/media-manager/resources/js/media-manager.js'])
@endif

<div
    id="media-manager-root"
    data-translations='@json($mmPickerTranslations)'
    data-config='@json($mmPickerConfig)'
></div>

<script>
    window.mediaManagerLang = {!! json_encode($mmPickerTranslations, JSON_UNESCAPED_UNICODE) !!};
</script>

<button
    type="button"
    class="{{ $buttonClass }}"
    id="mm-trigger-{{ \Illuminate\Support\Str::random(6) }}"
    onclick='
        window.MediaManager.open({
            multiple: {{ $multiple ? 'true' : 'false' }},
            max: {{ $max }},
            types: {!! $types !!},
            onSelect: {{ $onSelect ?: 'null' }},
            sidebar: @json($sidebar),
            locale: @json($locale),
            theme: @json($theme)
        });
    '
>
    {{ $buttonText }}
</button>
