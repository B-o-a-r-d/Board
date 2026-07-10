@props([
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'clearable' => false,
    'direction' => 'down',
    'multiple' => false,
])

@php
    // Accept a list of scalars (value = label) or a list of ['value','label'].
    $items = collect($options)->map(fn ($o) => is_array($o)
        ? ['value' => (string) ($o['value'] ?? ''), 'label' => (string) ($o['label'] ?? ($o['value'] ?? ''))]
        : ['value' => (string) $o, 'label' => (string) $o])->values()->all();
    $placeholder ??= __('Choisir…');

    $initial = $multiple
        ? collect(is_array($value) ? $value : [])->map(fn ($v) => (string) $v)->values()->all()
        : ($value === null ? '' : (string) $value);
@endphp

{{--
    Pretty, keyboard-accessible select. Emits `select-change` with the value
    (or the array of values when `multiple`).

    <x-select :options="$field->options" :value="$val" clearable
              @select-change="$wire.saveCustomField({{ $field->id }}, $event.detail)" />
--}}
<div
    x-data="selectMenu({ items: @js($items), initial: @js($initial), multiple: @js((bool) $multiple) })"
    @keydown.escape.stop="open = false"
    @keydown.arrow-down.prevent="open ? next() : (open = true)"
    @keydown.arrow-up.prevent="open ? prev() : (open = true)"
    @keydown.enter.prevent="open && choose(active)"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <button
        type="button"
        x-ref="button"
        @click="toggle()"
        :aria-expanded="open"
        class="flex min-h-[38px] w-full items-center justify-between gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-left text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
    >
        <span class="truncate" :class="! buttonLabel() && 'text-neutral-400'" x-text="buttonLabel() ?? @js($placeholder)"></span>
        <x-phosphor-caret-up-down class="h-4 w-4 shrink-0 text-neutral-400" />
    </button>

    <ul
        x-show="open"
        x-cloak
        x-ref="list"
        @click.outside="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute left-0 z-50 max-h-60 w-full min-w-[10rem] overflow-auto rounded-lg border border-neutral-200 bg-white p-1 text-sm shadow-lg dark:border-neutral-700 dark:bg-neutral-800 {{ $direction === 'up' ? 'bottom-full mb-1' : 'mt-1' }}"
    >
        @if ($clearable)
            <li @click="clear()" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700">{{ __('—') }}</li>
        @endif
        <template x-for="item in items" :key="item.value">
            <li
                @click="choose(item)"
                @mousemove="active = item"
                :data-active="active && active.value === item.value"
                class="flex cursor-pointer items-center justify-between gap-2 rounded px-2 py-1.5 data-[active=true]:bg-neutral-100 dark:data-[active=true]:bg-neutral-700"
            >
                <span class="truncate" x-text="item.label"></span>
                <x-phosphor-check x-show="isPicked(item)" x-cloak class="h-4 w-4 shrink-0 text-indigo-600 dark:text-indigo-400" />
            </li>
        </template>
        <li x-show="items.length === 0" class="px-2 py-1.5 text-neutral-400">{{ __('Aucune option') }}</li>
    </ul>
</div>
