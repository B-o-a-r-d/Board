@props([
    'icon' => null,
    'variant' => 'default',
])

@php
    $tone = $variant === 'danger'
        ? 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10'
        : 'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-700';
@endphp

<button
    type="button"
    {{ $attributes->class("flex w-full cursor-pointer select-none items-center gap-2 rounded px-2 py-1.5 text-left outline-none {$tone}") }}
>
    @if ($icon)
        <x-dynamic-component :component="'phosphor-'.$icon" class="h-4 w-4 shrink-0" />
    @endif
    <span class="truncate">{{ $slot }}</span>
</button>
