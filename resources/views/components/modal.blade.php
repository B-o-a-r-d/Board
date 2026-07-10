@props([
    'onClose' => '$wire.close()',
    'title' => null,
    'maxWidth' => 'lg',
    'align' => 'start',
])

@php
    $maxW = [
        'sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg',
        'xl' => 'max-w-xl', '2xl' => 'max-w-2xl', '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl', '5xl' => 'max-w-5xl', '6xl' => 'max-w-6xl',
    ][$maxWidth] ?? 'max-w-lg';

    $items = $align === 'center' ? 'items-center' : 'items-start';
@endphp

{{--
    Reusable modal chrome (style docs/ui_elements/modal.html). Visibility is
    driven by the parent Livewire @if; this component only supplies the overlay,
    entrance transition, and close-on-escape / click-outside behaviour.

    <x-modal title="…" on-close="$wire.toggleTrash()" max-width="2xl">
        <x-slot:footer>…</x-slot:footer>
        … body …
    </x-modal>
--}}
<div
    x-data="{ shown: false }"
    x-init="$nextTick(() => shown = true)"
    @keydown.escape.window="{{ $onClose }}"
    class="fixed inset-0 z-40 flex {{ $items }} justify-center overflow-y-auto p-4 sm:p-8"
    x-cloak
>
    <div
        x-show="shown"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        @click="{{ $onClose }}"
        class="fixed inset-0 bg-neutral-900/50 backdrop-blur-sm"
    ></div>

    <div
        x-show="shown"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        {{ $attributes->merge(['class' => "relative w-full {$maxW} rounded-2xl bg-white shadow-xl dark:bg-neutral-900"]) }}
    >
        @if ($title !== null || isset($header))
            <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-3 dark:border-neutral-800">
                <h2 class="text-base font-semibold">{{ $header ?? $title }}</h2>
                <button type="button" @click="{{ $onClose }}" class="rounded-full p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"><x-phosphor-x class="h-4 w-4" /></button>
            </div>
        @endif

        {{ $slot }}

        @isset($footer)
            <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 px-5 py-3 sm:flex-row sm:justify-end dark:border-neutral-800">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
