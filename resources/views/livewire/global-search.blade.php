<div x-data="{ focused: false, mobileOpen: false }" @click.outside="focused = false" class="relative">
    {{-- Mobile trigger (icon only) --}}
    <button
        type="button"
        @click="mobileOpen = true; $nextTick(() => $refs.mobileInput?.focus())"
        class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-neutral-100 sm:hidden dark:hover:bg-neutral-800"
        aria-label="{{ __('Rechercher boards & cartes…') }}"
    >
        <x-phosphor-magnifying-glass class="h-5 w-5 text-neutral-600 dark:text-neutral-300" />
    </button>

    {{-- Desktop inline search --}}
    <div class="relative hidden w-64 sm:block">
        <x-phosphor-magnifying-glass class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-neutral-400" />
        <input
            type="search"
            wire:model.live.debounce.300ms="query"
            @focus="focused = true"
            placeholder="{{ __('Rechercher boards & cartes…') }}"
            class="w-full rounded-lg border border-neutral-300 bg-white py-1.5 pl-8 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
        >
        <x-phosphor-spinner-gap wire:loading wire:target="query" class="absolute right-2 top-2 h-4 w-4 animate-spin text-neutral-400" />

        <div
            x-show="focused && $wire.query.trim().length >= 2"
            x-cloak
            class="absolute right-0 z-50 mt-2 max-h-96 w-80 overflow-y-auto rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
        >
            @include('livewire.partials.search-results')
        </div>
    </div>

    {{-- Mobile full-screen overlay --}}
    <div
        x-show="mobileOpen"
        x-cloak
        @keydown.escape.window="mobileOpen = false"
        class="fixed inset-0 z-50 flex flex-col bg-white sm:hidden dark:bg-neutral-950"
    >
        <div class="flex items-center gap-2 border-b border-neutral-200 p-3 dark:border-neutral-800">
            <div class="relative flex-1">
                <x-phosphor-magnifying-glass class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-neutral-400" />
                <input
                    type="search"
                    x-ref="mobileInput"
                    wire:model.live.debounce.300ms="query"
                    placeholder="{{ __('Rechercher boards & cartes…') }}"
                    class="w-full rounded-lg border border-neutral-300 bg-white py-2 pl-8 pr-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                >
            </div>
            <button type="button" @click="mobileOpen = false" class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 dark:text-neutral-300">{{ __('Fermer') }}</button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div x-show="$wire.query.trim().length >= 2">
                @include('livewire.partials.search-results')
            </div>
            <p x-show="$wire.query.trim().length < 2" class="px-4 py-8 text-center text-sm text-neutral-400">{{ __('Tapez au moins 2 caractères…') }}</p>
        </div>
    </div>
</div>
