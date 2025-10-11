<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Haasib') }}</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    
    <!-- Scripts -->
    @vite(['resources/js/app.js', 'resources/js/styles/app.css'])
    
    <!-- CSRF Token -->
    @csrf
    
    <!-- Inertia Head -->
    @inertiaHead
</head>
<body class="bg-gray-100 dark:bg-gray-900 antialiased">
    @inertia
</body>
</html>