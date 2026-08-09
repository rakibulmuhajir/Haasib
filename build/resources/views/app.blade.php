<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
