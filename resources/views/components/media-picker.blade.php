{{--
    <x-media-picker /> — Blade component

    Usage:
        <x-media-picker />
        <x-media-picker :multiple="true" :max="5" :sidebar="false" locale="tr" theme="dark" />
        <x-media-picker :variants='[["name"=>"card","width"=>256,"height"=>256],["name"=>"product","ratio"=>"3:4"]]' folder="themes/default/products" on-select="onPicked" />

    JS API:
        window.MediaManager.open({
            locale: 'tr' | 'en',
            theme: 'auto' | 'light' | 'dark',
            variants: [{ name, width, height } | { name, ratio, width? }],
            onSelect(files) { ... },
        });
--}}

@php
    $mmConfig = \Yazilim360\MediaManager\Support\ClientConfig::toArray();
    $mmCss = \Yazilim360\MediaManager\Support\ClientConfig::asset('media-manager.css');
    $mmJs = \Yazilim360\MediaManager\Support\ClientConfig::asset('media-manager.js');
    $mmOptions = array_filter([
        'multiple' => $multiple,
        'max' => $max,
        'types' => json_decode($types, true) ?: null,
        'sidebar' => $sidebar,
        'locale' => $locale,
        'theme' => $theme,
        'variants' => $variants ?: null,
        'folder' => $folder,
        'crop' => $crop,
    ], fn ($v) => $v !== null);
@endphp

@once
    @if($mmJs)
        <link rel="stylesheet" href="{{ $mmCss }}">
        <script src="{{ $mmJs }}" defer></script>
    @else
        @vite(['packages/yazilim360/media-manager/resources/js/media-manager.js'])
    @endif

    <div
        id="media-manager-root"
        data-translations="{{ json_encode($mmPickerTranslations ?? [], JSON_UNESCAPED_UNICODE) }}"
        data-config="{{ json_encode($mmConfig) }}"
    ></div>

    <script>
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-mm-picker]');
            if (!btn) return;
            e.preventDefault();

            var options = JSON.parse(btn.getAttribute('data-mm-options') || '{}');
            var name = btn.getAttribute('data-mm-on-select');
            if (name && typeof window[name] === 'function') options.onSelect = window[name];

            window.MediaManager.open(options);
        });
    </script>
@endonce

<button
    type="button"
    class="{{ $buttonClass }}"
    data-mm-picker
    data-mm-options="{{ json_encode($mmOptions) }}"
    @if($onSelect) data-mm-on-select="{{ $onSelect }}" @endif
>
    {{ $buttonText }}
</button>
