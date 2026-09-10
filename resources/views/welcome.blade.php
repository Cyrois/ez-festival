<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
    <div class="flex min-h-screen items-center justify-center">
        <div class="text-center">
            <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name', 'Laravel') }}</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Laravel {{ app()->version() }}</p>
        </div>
    </div>
</body>
</html>
