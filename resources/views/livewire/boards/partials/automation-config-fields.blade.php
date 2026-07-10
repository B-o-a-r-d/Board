{{-- Renders an automation configFields() declaration bound to a wire path.
     Expects: $fields (declaration array), $path (e.g. "triggerConfig" or
     "actions.0.config"), $values (current config array), plus $lists, $labels,
     $members, $customFields from the parent scope. $allowMe adds the
     "triggering user" option to member selects (actions only). --}}
@php $allowMe ??= false; @endphp
<div class="grid gap-2 sm:grid-cols-2">
    @foreach ($fields as $fieldDef)
        @php
            $key = $fieldDef['key'];
            $current = $values[$key] ?? null;
            $inputClasses = 'w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900';
        @endphp
        <div wire:key="cfg-{{ $path }}-{{ $key }}">
            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __($fieldDef['label']) }}</label>
            @switch($fieldDef['type'])
                @case('list')
                    <x-select
                        :options="$lists->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->values()->all()"
                        :value="$current"
                        clearable
                        @select-change="$wire.set('{{ $path }}.{{ $key }}', $event.detail)"
                    />
                    @break
                @case('label')
                    <x-select
                        :options="$labels->map(fn ($l) => ['value' => $l->id, 'label' => $l->name ?? __('Sans nom')])->values()->all()"
                        :value="$current"
                        clearable
                        @select-change="$wire.set('{{ $path }}.{{ $key }}', $event.detail)"
                    />
                    @break
                @case('member')
                    @php
                        $memberOptions = $members->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->values()->all();
                        if ($allowMe) {
                            array_unshift($memberOptions, ['value' => 'me', 'label' => __('L’utilisateur qui déclenche')]);
                        }
                    @endphp
                    <x-select
                        :options="$memberOptions"
                        :value="$current"
                        clearable
                        @select-change="$wire.set('{{ $path }}.{{ $key }}', $event.detail)"
                    />
                    @break
                @case('custom_field')
                    <x-select
                        :options="$customFields->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->values()->all()"
                        :value="$current"
                        clearable
                        @select-change="$wire.set('{{ $path }}.{{ $key }}', $event.detail)"
                    />
                    @break
                @case('number')
                    <input type="number" wire:model.live.debounce.400ms="{{ $path }}.{{ $key }}" class="{{ $inputClasses }}">
                    @break
                @case('checkbox')
                    <label class="flex h-[38px] items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="{{ $path }}.{{ $key }}"
                               class="h-4 w-4 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500/40 dark:border-neutral-600 dark:bg-neutral-800">
                        {{ __('Activé') }}
                    </label>
                    @break
                @case('password')
                    <input type="password" wire:model.live.debounce.400ms="{{ $path }}.{{ $key }}" class="{{ $inputClasses }}" autocomplete="new-password">
                    @break
                @default
                    <input type="text" wire:model.live.debounce.400ms="{{ $path }}.{{ $key }}" class="{{ $inputClasses }}">
            @endswitch
        </div>
    @endforeach
</div>
