@props([
    'field' => null,        // single-select: Livewire property name (set via applyFilter)
    'options',              // array<string|int, string> value => label; '' entry = "all" (single mode)
    'placeholder',
    'current' => null,      // single-select: currently selected value (null / '' = default)
    'selected' => [],        // multi-select: array of selected int values
    'multiple' => false,
    'action' => null,       // multi-select: Livewire method called with the option id (int)
    'icon' => null,
])

@php
    $count = $multiple ? count($selected) : 0;
    $isActive = $multiple ? ($count > 0) : ($current !== null && $current !== '');
    $activeLabel = (! $multiple && $current !== null && $current !== '') ? ($options[$current] ?? null) : null;
@endphp

{{-- Styled filter dropdown. Single mode sets a property via applyFilter(); multi
     mode toggles ids through the given action method and stays open. --}}
<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
    <button
        type="button"
        @click="open = ! open"
        class="inline-flex w-full items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm shadow-sm transition {{ $isActive ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' }}"
    >
        @if ($icon)
            <x-dynamic-component :component="'phosphor-'.$icon" class="h-4 w-4 shrink-0 opacity-70" />
        @endif
        <span class="flex-1 truncate text-left">{{ $activeLabel ?? $placeholder }}</span>
        @if ($multiple && $count > 0)
            <span class="shrink-0 rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white">{{ $count }}</span>
        @endif
        <x-phosphor-caret-down class="h-3.5 w-3.5 shrink-0 opacity-60 transition-transform" ::class="open && 'rotate-180'" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute left-0 z-40 mt-1 max-h-72 w-56 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-xl border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
    >
        @foreach ($options as $value => $label)
            @continue($multiple && $value === '')
            @php $isSelected = $multiple ? in_array((int) $value, $selected, true) : ((string) $current === (string) $value); @endphp
            <button
                type="button"
                @if ($multiple)
                    wire:click="{{ $action }}({{ (int) $value }})"
                @else
                    wire:click="applyFilter('{{ $field }}', '{{ $value }}')"
                    @click="open = false"
                @endif
                class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $isSelected ? 'font-medium text-indigo-600 dark:text-indigo-400' : 'text-neutral-700 dark:text-neutral-300' }}"
            >
                <span class="truncate">{{ $label }}</span>
                @if ($isSelected)
                    <x-phosphor-check class="h-4 w-4 shrink-0" />
                @endif
            </button>
        @endforeach
    </div>
</div>
