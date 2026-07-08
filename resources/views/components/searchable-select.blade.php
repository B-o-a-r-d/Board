@props(['model', 'options' => [], 'placeholder' => ''])

@php
    // value => label map, so the trigger can show the selected label without
    // duplicating the option data in Alpine.
    $labelMap = collect($options)->mapWithKeys(fn ($o) => [(string) $o['value'] => $o['label']])->all();
@endphp

<div
    x-data="{
        open: false,
        search: '',
        model: @js($model),
        value: $wire.get(@js($model)) ?? '',
        labels: @js($labelMap),
        get triggerLabel() {
            return this.value && this.labels[this.value] !== undefined ? this.labels[this.value] : @js($placeholder);
        },
        choose(v) {
            this.value = v;
            $wire.set(this.model, v, false); // deferred: no round-trip until submit
            this.open = false;
            this.search = '';
        },
        matches(label) { return this.search === '' || label.includes(this.search.toLowerCase()); },
    }"
    x-init="$watch('open', v => v && $nextTick(() => $refs.search?.focus()))"
    @click.outside="open = false"
    @keydown.escape="open = false"
    class="relative"
>
    <button type="button" @click="open = ! open"
            class="flex w-full items-center justify-between gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-left text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
        <span class="truncate" :class="{ 'text-neutral-400': ! value }" x-text="triggerLabel"></span>
        <x-phosphor-caret-down class="h-3.5 w-3.5 shrink-0 text-neutral-400" ::class="open && 'rotate-180'"/>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800">
        <div class="border-b border-neutral-100 p-2 dark:border-neutral-700">
            <input x-model="search" x-ref="search" type="text" placeholder="{{ __('Rechercher…') }}" @click.stop
                   class="w-full rounded-md border border-neutral-200 bg-neutral-50 px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900">
        </div>
        <ul class="max-h-56 overflow-auto py-1">
            @forelse ($options as $opt)
                <li @click="choose(@js((string) $opt['value']))"
                    x-show="matches(@js(mb_strtolower($opt['label'])))"
                    class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-700"
                    :class="{ 'bg-indigo-50 dark:bg-indigo-500/10': value === @js((string) $opt['value']) }">
                    @if (! empty($opt['icon']))
                        <x-dynamic-component :component="'phosphor-'.$opt['icon']" class="h-4 w-4 shrink-0 text-neutral-400"/>
                    @endif
                    <span class="truncate">{{ $opt['label'] }}</span>
                </li>
            @empty
                <li class="px-3 py-2 text-sm text-neutral-400">{{ __('Aucune option') }}</li>
            @endforelse
        </ul>
    </div>
</div>
