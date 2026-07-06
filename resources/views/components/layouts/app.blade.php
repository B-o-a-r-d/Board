@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title . ' — ' . config('app.name') : config('app.name') }}</title>

    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-neutral-100 text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <div class="min-h-full">
        <header class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
            <nav class="mx-auto flex h-14 max-w-full items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">B</span>
                    <span class="font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <div class="flex items-center gap-2">
                    <livewire:global-search wire:key="nav-search" />

                    <button
                        type="button"
                        x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        @click="dark = ! dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                        class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        title="Basculer le thème"
                    >
                        <x-phosphor-moon class="h-5 w-5 text-neutral-600" x-show="! dark" />
                        <x-phosphor-sun class="h-5 w-5 text-neutral-300" x-show="dark" x-cloak />
                    </button>

                    <livewire:notifications-bell wire:key="nav-notifications" />

                    <div x-data="{ open: false }" class="relative">
                    <button
                        type="button"
                        @click="open = !open"
                        @click.outside="open = false"
                        class="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 text-sm font-medium hover:bg-neutral-100 dark:hover:bg-neutral-800"
                    >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        </span>
                        <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        x-cloak
                        class="absolute right-0 mt-2 w-48 origin-top-right rounded-xl border border-neutral-200 bg-white py-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">
                            Profil
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-neutral-100 dark:text-red-400 dark:hover:bg-neutral-800">
                                Se déconnecter
                            </button>
                        </form>
                    </div>
                    </div>
                </div>
            </nav>
        </header>

        <main class="mx-auto max-w-full px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
