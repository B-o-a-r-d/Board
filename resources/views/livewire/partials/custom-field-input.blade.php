{{-- One custom field on the card modal: edit widget per type for contributors,
     read-only rendering otherwise. Expects: $field, $val (raw stored string),
     $canContribute, $boardMembers. --}}
@php
    use App\Enums\CustomFieldType;

    $decoded = $field->decode($val);
    $inputClasses = 'w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800';
    $fieldMember = $field->type === CustomFieldType::Member && $val ? $boardMembers->firstWhere('id', (int) $val) : null;
@endphp

<div wire:key="cf-input-{{ $field->id }}">
    @if ($canContribute)
        @if ($field->type === CustomFieldType::Checkbox)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" @checked($val)
                       wire:change="saveCustomField({{ $field->id }}, $event.target.checked)"
                       class="h-4 w-4 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500/40 dark:border-neutral-600 dark:bg-neutral-800">
                {{ $field->name }}
            </label>
        @else
            <label class="mb-0.5 block text-xs text-neutral-500">{{ $field->name }}</label>
            @if ($field->type === CustomFieldType::Select)
                <x-select
                    :options="$field->optionList()"
                    :value="$val"
                    clearable
                    @select-change="$wire.saveCustomField({{ $field->id }}, $event.detail)"
                />
            @elseif ($field->type === CustomFieldType::MultiSelect)
                <x-select
                    multiple
                    :options="$field->optionList()"
                    :value="$decoded ?? []"
                    clearable
                    @select-change="$wire.saveCustomField({{ $field->id }}, $event.detail)"
                />
            @elseif ($field->type === CustomFieldType::Member)
                <x-select
                    :options="$boardMembers->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->values()->all()"
                    :value="$val"
                    clearable
                    :placeholder="__('Choisir un membre…')"
                    @select-change="$wire.saveCustomField({{ $field->id }}, $event.detail)"
                />
            @elseif ($field->type === CustomFieldType::Number)
                <input type="number" value="{{ $val }}" wire:change="saveCustomField({{ $field->id }}, $event.target.value)" class="{{ $inputClasses }}">
            @elseif ($field->type === CustomFieldType::Date)
                <input type="date" value="{{ $val }}" wire:change="saveCustomField({{ $field->id }}, $event.target.value)" class="{{ $inputClasses }}">
            @elseif ($field->type === CustomFieldType::Url)
                <div class="flex items-center gap-1.5">
                    <input type="url" value="{{ $val }}" placeholder="https://…" wire:change="saveCustomField({{ $field->id }}, $event.target.value)" class="{{ $inputClasses }}">
                    @if ($val)
                        <a href="{{ $val }}" target="_blank" rel="noopener noreferrer" title="{{ __('Ouvrir le lien') }}"
                           class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-neutral-300 text-neutral-500 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                            <x-phosphor-arrow-square-out class="h-4 w-4"/>
                        </a>
                    @endif
                </div>
            @elseif ($field->type === CustomFieldType::Email)
                <div class="flex items-center gap-1.5">
                    <input type="email" value="{{ $val }}" placeholder="nom@exemple.com" wire:change="saveCustomField({{ $field->id }}, $event.target.value)" class="{{ $inputClasses }}">
                    @if ($val)
                        <a href="mailto:{{ $val }}" title="{{ __('Écrire un email') }}"
                           class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-neutral-300 text-neutral-500 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                            <x-phosphor-envelope-simple class="h-4 w-4"/>
                        </a>
                    @endif
                </div>
            @elseif ($field->type === CustomFieldType::Money)
                <div class="flex items-center gap-2">
                    <input type="number" step="0.01" value="{{ $val }}" wire:change="saveCustomField({{ $field->id }}, $event.target.value)" class="{{ $inputClasses }}">
                    <span class="shrink-0 text-sm text-neutral-500">{{ $field->currency() }}</span>
                </div>
            @elseif ($field->type === CustomFieldType::Rating)
                <div class="flex items-center gap-0.5">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" wire:click="saveCustomField({{ $field->id }}, {{ $i === (int) $val ? 0 : $i }})" title="{{ $i }}/5" class="rounded p-0.5 transition hover:scale-110">
                            <x-dynamic-component :component="$i <= (int) $val ? 'phosphor-star-fill' : 'phosphor-star'" class="h-5 w-5 {{ $i <= (int) $val ? 'text-amber-400' : 'text-neutral-300 dark:text-neutral-600' }}"/>
                        </button>
                    @endfor
                </div>
            @elseif ($field->type === CustomFieldType::Progress)
                <div class="flex items-center gap-2">
                    <input type="range" min="0" max="100" step="5" value="{{ (int) $val }}"
                           wire:change="saveCustomField({{ $field->id }}, $event.target.value)"
                           class="h-1.5 min-w-0 flex-1 cursor-pointer accent-indigo-600">
                    <span class="w-10 shrink-0 text-right text-xs tabular-nums text-neutral-500">{{ (int) $val }}%</span>
                </div>
            @else
                <input type="text" value="{{ $val }}" wire:change="saveCustomField({{ $field->id }}, $event.target.value)" class="{{ $inputClasses }}">
            @endif
        @endif
        @error('cf-'.$field->id) <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    @else
        @if ($field->type === CustomFieldType::Checkbox)
            <label class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                <span class="flex h-4 w-4 items-center justify-center rounded border {{ $val ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-neutral-300 dark:border-neutral-600' }}">@if ($val)<x-phosphor-check class="h-3 w-3"/>@endif</span>
                {{ $field->name }}
            </label>
        @else
            <label class="mb-0.5 block text-xs text-neutral-500">{{ $field->name }}</label>
            @if ($val === null || $val === '')
                <p class="text-sm text-neutral-400">—</p>
            @elseif ($field->type === CustomFieldType::Url)
                <a href="{{ $val }}" target="_blank" rel="noopener noreferrer" class="inline-flex max-w-full items-center gap-1 truncate text-sm text-indigo-600 hover:underline dark:text-indigo-400"><x-phosphor-link class="h-3.5 w-3.5 shrink-0"/><span class="truncate">{{ $val }}</span></a>
            @elseif ($field->type === CustomFieldType::Email)
                <a href="mailto:{{ $val }}" class="inline-flex max-w-full items-center gap-1 truncate text-sm text-indigo-600 hover:underline dark:text-indigo-400"><x-phosphor-envelope-simple class="h-3.5 w-3.5 shrink-0"/><span class="truncate">{{ $val }}</span></a>
            @elseif ($field->type === CustomFieldType::MultiSelect)
                <div class="flex flex-wrap gap-1">
                    @foreach ((array) $decoded as $picked)
                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ $picked }}</span>
                    @endforeach
                </div>
            @elseif ($field->type === CustomFieldType::Member)
                @if ($fieldMember)
                    <span class="inline-flex items-center gap-1.5 text-sm text-neutral-700 dark:text-neutral-200"><x-user-avatar :user="$fieldMember" size="xs"/>{{ $fieldMember->name }}</span>
                @else
                    <p class="text-sm text-neutral-400">—</p>
                @endif
            @elseif ($field->type === CustomFieldType::Money)
                <p class="text-sm text-neutral-700 dark:text-neutral-200">{{ rtrim(rtrim(number_format((float) $val, 2, ',', ' '), '0'), ',') }} {{ $field->currency() }}</p>
            @elseif ($field->type === CustomFieldType::Rating)
                <div class="flex items-center gap-0.5">
                    @for ($i = 1; $i <= 5; $i++)
                        <x-dynamic-component :component="$i <= (int) $val ? 'phosphor-star-fill' : 'phosphor-star'" class="h-4 w-4 {{ $i <= (int) $val ? 'text-amber-400' : 'text-neutral-300 dark:text-neutral-600' }}"/>
                    @endfor
                </div>
            @elseif ($field->type === CustomFieldType::Progress)
                <div class="flex items-center gap-2">
                    <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ (int) $val }}%"></div>
                    </div>
                    <span class="w-10 shrink-0 text-right text-xs tabular-nums text-neutral-500">{{ (int) $val }}%</span>
                </div>
            @else
                <p class="text-sm text-neutral-700 dark:text-neutral-200">{{ $val }}</p>
            @endif
        @endif
    @endif
</div>
