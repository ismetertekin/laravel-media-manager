@php
    $mmConfig = \Yazilim360\MediaManager\Support\ClientConfig::toArray();
    $mmTranslations = \Yazilim360\MediaManager\Support\ClientConfig::translations();
    $mmCss = \Yazilim360\MediaManager\Support\ClientConfig::asset('media-manager.css');
    $mmJs = \Yazilim360\MediaManager\Support\ClientConfig::asset('media-manager.js');
    $locale = strtolower(explode('-', str_replace('_', '-', app()->getLocale()))[0] ?? 'en');
    if (! in_array($locale, $mmConfig['allowed_locales'], true)) {
        $locale = 'en';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Media Manager – Demo</title>

    @if($mmJs)
        <link rel="stylesheet" href="{{ $mmCss }}">
        <script src="{{ $mmJs }}" defer></script>
    @else
        @vite(['packages/yazilim360/media-manager/resources/js/media-manager.js'])
    @endif

    {{-- Demo page styles only (the manager brings its own, scoped under .mm-backdrop) --}}
    <style>
        :root { color-scheme: light; --d-bg: #fafafa; --d-fg: #18181b; --d-muted: #71717a; --d-card: #fff; --d-border: #e4e4e7; --d-primary: #7239ea; }
        html.dark { color-scheme: dark; --d-bg: #09090b; --d-fg: #fafafa; --d-muted: #a1a1aa; --d-card: #0c0c0f; --d-border: #27272a; }
        body { margin: 0; min-height: 100vh; font-family: Inter, system-ui, sans-serif; font-size: 14px; background: var(--d-bg); color: var(--d-fg); }
        main { max-width: 56rem; margin: 0 auto; padding: 3rem 1.25rem; }
        h1 { margin: 0 0 .25rem; font-size: 1.5rem; }
        p.sub { margin: 0 0 1.5rem; color: var(--d-muted); }
        .card { padding: 1.25rem; margin-bottom: 1rem; background: var(--d-card); border: 1px solid var(--d-border); border-radius: .75rem; }
        .card h2 { margin: 0 0 .75rem; font-size: .9375rem; }
        .row { display: flex; flex-wrap: wrap; gap: .5rem; }
        button { height: 2.125rem; padding: 0 .75rem; border: 1px solid var(--d-border); border-radius: .5rem; background: var(--d-card); color: var(--d-fg); font: inherit; font-weight: 500; cursor: pointer; }
        button.primary { border-color: transparent; background: var(--d-primary); color: #fff; }
        pre { margin: 1rem 0 0; padding: 1rem; max-height: 22rem; overflow: auto; border-radius: .5rem; background: var(--d-bg); border: 1px solid var(--d-border); font-size: .75rem; white-space: pre-wrap; }
        #inline-host { height: 640px; margin-top: 1rem; }
    </style>
</head>
<body>
<main>
    <h1>Media Manager</h1>
    <p class="sub">Yazilim360 · Spatie MediaLibrary · Vue 3</p>

    <div class="card">
        <h2>Picker</h2>
        <div class="row">
            <button class="primary" onclick="openSingle()">Single select</button>
            <button class="primary" onclick="openMulti()">Multiple (max 5, reorderable)</button>
            <button onclick="openVariants()">With theme variants + crop</button>
            <button onclick="openManage()">Library only</button>
            <button onclick="openInline()">Inline (embedded)</button>
            <button onclick="document.documentElement.classList.toggle('dark')">Toggle host dark mode</button>
        </div>
        <pre id="out">// Selected files will appear here…</pre>
        <div id="inline-host"></div>
    </div>
</main>

<div id="media-manager-root"
     data-translations="{{ json_encode($mmTranslations, JSON_UNESCAPED_UNICODE) }}"
     data-config="{{ json_encode($mmConfig) }}"></div>

<script>
    var out = function (files) { document.getElementById('out').textContent = JSON.stringify(files, null, 2); };
    var base = { locale: @json($locale), sidebar: true, onSelect: out };

    function openSingle() { MediaManager.open(Object.assign({}, base, { multiple: false })); }
    function openMulti() { MediaManager.open(Object.assign({}, base, { multiple: true, max: 5, types: ['image', 'video'] })); }
    function openManage() { MediaManager.open({ locale: base.locale, sidebar: true, select: false }); }
    function openInline() { MediaManager.open(Object.assign({}, base, { container: '#inline-host', multiple: true })); }
    function openVariants() {
        // What a theme would pass: 1:1 for lists, 3:4 for the product page.
        MediaManager.open(Object.assign({}, base, {
            variants: [
                { name: 'thumb', width: 128, height: 128 },
                { name: 'card', width: 256, height: 256 },
                { name: 'product', ratio: '3:4' }
            ],
            types: ['image'],
            folder: 'themes/demo/products'
        }));
    }
</script>
</body>
</html>
