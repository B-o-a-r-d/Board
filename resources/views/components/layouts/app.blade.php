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

                    <livewire:notifications-bell wire:key="nav-notifications" />

                    {{-- Profile dropdown (with theme toggle) --}}
                    <div
                        x-data="{ open: false, dark: document.documentElement.classList.contains('dark') }"
                        class="relative"
                    >
                        <button
                            type="button"
                            @click="open = ! open"
                            class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 text-sm font-medium hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        >
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                                {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                            </span>
                            <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                            <x-phosphor-caret-down class="h-4 w-4 text-neutral-400 transition-transform" ::class="open && 'rotate-180'" />
                        </button>

                        <div
                            x-show="open"
                            @click.outside="open = false"
                            @keydown.escape.window="open = false"
                            x-transition:enter="ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-2"
                            x-cloak
                            class="absolute right-0 z-50 mt-2 w-60 origin-top-right rounded-xl border border-neutral-200 bg-white p-1 text-neutral-700 shadow-lg dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
                        >
                            {{-- Account header --}}
                            <div class="px-2 py-1.5">
                                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs font-light text-neutral-400">{{ auth()->user()->email }}</p>
                            </div>

                            <div class="-mx-1 my-1 h-px bg-neutral-200 dark:bg-neutral-800"></div>

                            <a href="{{ route('profile.edit') }}" wire:navigate @click="open = false" class="flex items-center gap-2 rounded px-2 py-1.5 text-sm transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                <x-phosphor-user class="h-4 w-4" />
                                <span>Profil</span>
                            </a>

                            {{-- Theme toggle (stays open) --}}
                            <button
                                type="button"
                                @click="dark = ! dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                                class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
                            >
                                <x-phosphor-moon class="h-4 w-4" x-show="! dark" />
                                <x-phosphor-sun class="h-4 w-4" x-show="dark" x-cloak />
                                <span x-text="dark ? 'Mode clair' : 'Mode sombre'"></span>
                                <span class="relative ml-auto inline-flex h-5 w-9 items-center rounded-full transition" :class="dark ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition" :class="dark ? 'translate-x-4' : 'translate-x-0.5'"></span>
                                </span>
                            </button>

                            <div class="-mx-1 my-1 h-px bg-neutral-200 dark:bg-neutral-800"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                                    <x-phosphor-sign-out class="h-4 w-4" />
                                    <span>Se déconnecter</span>
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

    <x-confirm-modal />
</body>
</html>
