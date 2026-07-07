@props([
    'field',
    'options',      // array<string|int, string> value => label (first entry = the "all" default, value '')
    'placeholder',
    'current',      // currently selected value (null / '' = default)
    'icon' => null,
])

@php
    $activeLabel = ($current !== null && $current !== '') ? ($options[$current] ?? null) : null;
@endphp

{{-- Styled filter dropdown backed by a Livewire property via applyFilter(). --}}
<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
    <button
        type="button"
        @click="open = ! open"
        class="inline-flex w-full items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm shadow-sm transition sm:w-auto {{ $activeLabel !== null ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' }}"
    >
        @if ($icon)
            <x-dynamic-component :component="'phosphor-'.$icon" class="h-4 w-4 shrink-0 opacity-70" />
        @endif
        <span class="flex-1 truncate text-left sm:max-w-[9rem]">{{ $activeLabel ?? $placeholder }}</span>
        <x-phosphor-caret-down class="h-3.5 w-3.5 shrink-0 opacity-60 transition-transform" ::class="open && 'rotate-180'" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute left-0 z-40 mt-1 max-h-72 w-56 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-xl border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
    >
        @foreach ($options as $value => $label)
            <button
                type="button"
                wire:click="applyFilter('{{ $field }}', '{{ $value }}')"
                @click="open = false"
                class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ (string) $current === (string) $value ? 'font-medium text-indigo-600 dark:text-indigo-400' : 'text-neutral-700 dark:text-neutral-300' }}"
            >
                <span class="truncate">{{ $label }}</span>
                @if ((string) $current === (string) $value)
                    <x-phosphor-check class="h-4 w-4 shrink-0" />
                @endif
            </button>
        @endforeach
    </div>
</div>
