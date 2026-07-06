@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title . ' — ' . config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-neutral-100 text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <a href="{{ url('/') }}" class="mb-8 flex items-center justify-center gap-2">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold text-white">B</span>
                <span class="text-xl font-semibold tracking-tight">{{ config('app.name') }}</span>
            </a>

            <div class="rounded-2xl border border-neutral-200 bg-white p-8 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
