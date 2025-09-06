<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        <script>
            // On page load or when changing themes, best to add inline in `head` to avoid FOUC
            const storedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (storedTheme === 'dark' || (!storedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            const fonts = {
                sans: 'Figtree, sans-serif',
                serif: 'Georgia, serif',
                mono: 'ui-monospace, SFMono-Regular, Menlo, monospace',
            };
            const storedFont = localStorage.getItem('font');
            if (storedFont && fonts[storedFont]) {
                document.documentElement.style.setProperty('--app-font', fonts[storedFont]);
            }
        </script>
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
