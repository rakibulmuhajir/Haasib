<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-skin="{{ config('skins.default') }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{--
            Server-rendered social and search metadata. Inertia sets the title on
            the client, so a crawler that does not execute JavaScript previously
            saw a bare "Haasib" and nothing else — no description, no card. Link
            validators that fetch the URL server-side rejected it for that reason.
            These tags are static on purpose: they must survive with JS disabled.
        --}}
        <meta name="description" content="Double-entry accounting for Pakistani SMEs. Multi-tenant Laravel and PostgreSQL, with modules for fuel stations, Umrah travel agencies, inventory, payroll and tax.">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Haasib">
        <meta property="og:title" content="Haasib — accounting built for Pakistani SMEs">
        <meta property="og:description" content="Double-entry accounting for Pakistani SMEs. Multi-tenant Laravel and PostgreSQL, with modules for fuel stations, Umrah travel agencies, inventory, payroll and tax.">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ url('/apple-touch-icon.png') }}">

        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="Haasib — accounting built for Pakistani SMEs">
        <meta name="twitter:description" content="Double-entry accounting for Pakistani SMEs. Multi-tenant Laravel and PostgreSQL, with modules for fuel stations, Umrah travel agencies, inventory, payroll and tax.">
        <meta name="twitter:image" content="{{ url('/apple-touch-icon.png') }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- The page ground, inline so it is painted before the stylesheet
             arrives. Without it the default white shows through above and below
             the app on a short page, and the paper stops at the content edge.
             The skin itself is a static attribute on <html> above — there is
             one skin, so there is nothing to detect and nothing to repaint.
             Keep each `ground` in step with --surface-canvas in app.css. --}}
        @php($ground = config('skins.available.'.config('skins.default').'.ground'))
        <style>
            html { background-color: {{ $ground['light'] }}; }
            html.dark { background-color: {{ $ground['dark'] }}; }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- The faces are served from this origin (resources/css/fonts.css), so
             there is no third-party stylesheet to wait on. These three are the
             ones the first screenful is set in: preloading them means the swap
             from the fallback happens before the reader notices it, rather than
             one paint later. --}}
        <link rel="preload" as="font" type="font/woff2" href="/fonts/public-sans-latin-400-normal.woff2" crossorigin>
        <link rel="preload" as="font" type="font/woff2" href="/fonts/zilla-slab-latin-600-normal.woff2" crossorigin>
        <link rel="preload" as="font" type="font/woff2" href="/fonts/ibm-plex-mono-latin-400-normal.woff2" crossorigin>

        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
