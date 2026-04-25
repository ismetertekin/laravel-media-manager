<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Media Manager – Dev</title>

    @php
    $distPath = public_path('vendor/media-manager/dist/media-manager.js');
    $useDist = file_exists($distPath);
    @endphp

    @if($useDist)
        <link rel="stylesheet" href="{{ asset('vendor/media-manager/dist/media-manager.css') }}">
        <script src="{{ asset('vendor/media-manager/dist/media-manager.js') }}" defer></script>
    @else
        @vite(['packages/yazilim360/media-manager/resources/js/media-manager.js'])
    @endif

    {{-- Load Axios from CDN (for standalone demo) --}}
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    {{--
        Demo stilleri SADECE bu sayfaya özel — global * { } kullanma:
        Aksi halde #media-manager-root içindeki modal (.mm-backdrop) de etkilenir.
    --}}
    <style>
        body.mm-demo-page {
            box-sizing: border-box;
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f1f5f9;
        }
        body.mm-demo-page .demo-container {
            box-sizing: border-box;
            text-align: center;
            padding: 2rem;
        }
        body.mm-demo-page .demo-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 0.5rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #06b6d4);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        body.mm-demo-page .demo-subtitle {
            margin: 0 0 2.5rem;
            color: #94a3b8;
            font-size: 1.1rem;
        }
        body.mm-demo-page .demo-btn {
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            padding: 0.85rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 8px 30px rgba(99,102,241,0.4);
            transition: transform 0.2s, box-shadow 0.2s;
            margin: 0.5rem;
        }
        body.mm-demo-page .demo-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(99,102,241,0.5);
        }
        body.mm-demo-page .demo-output {
            box-sizing: border-box;
            margin: 2rem 0 0;
            padding: 1.5rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            text-align: left;
            font-family: ui-monospace, monospace;
            font-size: 0.85rem;
            color: #94a3b8;
            min-height: 80px;
            max-width: 600px;
            width: 100%;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="mm-demo-page">

@php
    $mmPickerTranslations = trans('media-manager::media-manager', [], app()->getLocale());
    if (! is_array($mmPickerTranslations)) {
        $mmPickerTranslations = [];
    }
    $mmPickerConfig = config('media-manager');
    $demoLocale = strtolower(explode('-', str_replace('_', '-', app()->getLocale()))[0] ?? 'en');
    $allowedDemo = config('media-manager.allowed_locales', ['en', 'tr']);
    if (! in_array($demoLocale, $allowedDemo, true)) {
        $demoLocale = in_array('en', $allowedDemo, true) ? 'en' : ($allowedDemo[0] ?? 'en');
    }
@endphp

{{-- Mount point for the media manager Vue app --}}
<div
    id="media-manager-root"
    data-translations='@json($mmPickerTranslations)'
    data-config='@json($mmPickerConfig)'
></div>
<script>
    window.mediaManagerLang = {!! json_encode($mmPickerTranslations, JSON_UNESCAPED_UNICODE) !!};
</script>

<div class="demo-container">
    <h1 class="demo-title">📸 Media Manager</h1>
    <p class="demo-subtitle">Yazilim360 · Spatie MediaLibrary · Vue 3</p>

    <div>
        {{-- Single select example --}}
        <button class="demo-btn" onclick="openSingle()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Open (Single Select)
        </button>

        {{-- Multi select example --}}
        <button class="demo-btn" onclick="openMulti()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5" rx="1"/><rect x="10" y="3" width="5" height="5" rx="1"/><rect x="3" y="10" width="5" height="5" rx="1"/><rect x="10" y="10" width="5" height="5" rx="1"/></svg>
            Open (Multi Select – max 5)
        </button>
    </div>

    <pre class="demo-output" id="demo-output">// Selected files will appear here...</pre>
</div>

<script>
    // Axios global setup (mimics what Laravel ships by default)
    window.axios = axios;
    window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

    function openSingle() {
        window.MediaManager.open({
            multiple: false,
            max: 1,
            types: ['image', 'video', 'document'],
            sidebar: @json(config('media-manager.sidebar')),
            locale: @json($demoLocale),
            theme: 'dark',
            onSelect: (files) => {
                document.getElementById('demo-output').textContent = JSON.stringify(files, null, 2);
            }
        });
    }

    function openMulti() {
        window.MediaManager.open({
            multiple: true,
            max: 5,
            types: ['image', 'video'],
            sidebar: @json(config('media-manager.sidebar')),
            locale: @json($demoLocale),
            theme: 'dark',
            onSelect: (files) => {
                document.getElementById('demo-output').textContent = JSON.stringify(files, null, 2);
            }
        });
    }
</script>

</body>
</html>
