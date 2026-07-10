{{-- Table (spreadsheet) view: the board's cards from Show::buildTable(), with a
     column per attribute + one per custom field. Title and due date edit inline;
     the list moves via a dropdown; the rest opens the card modal. --}}
@php
    $sortCaret = fn (string $col) => $tableSort === $col
        ? ($tableDir === 'asc' ? 'caret-up' : 'caret-down')
        : null;
@endphp
<div class="flex min-h-0 flex-1 flex-col overflow-hidden transition-opacity"
     wire:loading.class.delay="opacity-40"
     wire:target="search, filterLabels, filterMembers, toggleLabel, toggleMember, toggleUnassigned, filterDue, resetFilters, applyFilter, applyView, sortTable, renameCard, setCardDue, moveCardToList, toggleCardMember, toggleCardLabel, setCardCustomField">

    @if ($tableCards->isEmpty())
        <p class="py-10 text-center text-sm text-neutral-400 {{ $boardBg ? 'dark rounded-xl border border-white/15 bg-neutral-900/45 backdrop-blur-xl' : '' }}">{{ __('Aucune carte à afficher.') }}</p>
    @else
        {{-- Glassmorphism over a board background: dark translucent veil + blur, and the
             `dark` class forces light-theme-agnostic readable (light) text inside. --}}
        <div class="min-h-0 flex-1 overflow-auto rounded-xl border {{ $boardBg ? 'dark border-white/15 bg-neutral-900/45 text-neutral-100 shadow-lg backdrop-blur-xl' : 'border-neutral-300 bg-white dark:border-neutral-700 dark:bg-neutral-900' }}">
            <table class="min-w-full border-collapse text-sm">
                <thead class="sticky top-0 z-10 bg-neutral-100 text-left dark:bg-neutral-800/90 dark:backdrop-blur-md">
                    <tr class="border-b border-neutral-300 dark:border-neutral-700">
                        @foreach (['title' => __('Titre'), 'list' => __('Liste'), 'due' => __('Échéance')] as $col => $label)
                            <th class="whitespace-nowrap px-3 py-2 font-medium">
                                <button type="button" wire:click="sortTable('{{ $col }}')" class="flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $label }}
                                    @if ($sortCaret($col))
                                        <x-dynamic-component :component="'phosphor-'.$sortCaret($col)" class="h-3.5 w-3.5" />
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th class="whitespace-nowrap px-3 py-2 font-medium">{{ __('Membres') }}</th>
                        <th class="whitespace-nowrap px-3 py-2 font-medium">{{ __('Labels') }}</th>
                        @foreach ($customFields as $field)
                            <th class="whitespace-nowrap px-3 py-2 font-medium">{{ $field->name }}</th>
                        @endforeach
                        <th class="w-10 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tableCards as $card)
                        <tr wire:key="row-{{ $card->id }}" class="border-b border-neutral-100 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/40">
                            {{-- Title (inline edit) --}}
                            <td class="px-3 py-1.5">
                                @if ($canContribute)
                                <div x-data="{ editing: false }">
                                    <div x-show="!editing" @click="editing = true; $nextTick(() => $refs.t.focus())"
                                         class="min-w-[10rem] cursor-text rounded px-1 py-0.5 {{ $card->completed_at ? 'text-neutral-400 line-through' : '' }}">{{ $card->title }}</div>
                                    <input x-show="editing" x-cloak x-ref="t" type="text" value="{{ $card->title }}" maxlength="255"
                                           @keydown.enter.prevent="$el.blur()"
                                           @keydown.escape="$el.value = @js($card->title); $el.blur()"
                                           @blur="$wire.renameCard({{ $card->id }}, $el.value); editing = false"
                                           class="w-full min-w-[10rem] rounded border border-indigo-400 bg-white px-1 py-0.5 focus:outline-none dark:bg-neutral-800">
                                </div>
                                @else
                                    <span class="min-w-[10rem] {{ $card->completed_at ? 'text-neutral-400 line-through' : '' }}">{{ $card->title }}</span>
                                @endif
                            </td>

                            {{-- List (move dropdown) --}}
                            <td class="px-3 py-1.5">
                                @if ($canContribute)
                                <div x-data="{ o: false }" @click.outside="o = false" class="relative">
                                    <button type="button" @click="o = !o" class="flex items-center gap-1 whitespace-nowrap rounded px-1 py-0.5 text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-700">
                                        <span class="truncate">{{ $card->list?->name }}</span>
                                        <x-phosphor-caret-down class="h-3 w-3 opacity-60" />
                                    </button>
                                    <div x-show="o" x-cloak class="absolute left-0 z-20 mt-1 max-h-56 w-48 overflow-y-auto rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                        @foreach ($lists as $moveList)
                                            <button type="button" wire:click="moveCardToList({{ $card->id }}, {{ $moveList->id }})" @click="o = false"
                                                    class="block w-full truncate rounded px-2 py-1.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $card->board_list_id === $moveList->id ? 'font-medium text-indigo-600 dark:text-indigo-400' : '' }}">{{ $moveList->name }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                @else
                                    <span class="whitespace-nowrap text-neutral-600 dark:text-neutral-300">{{ $card->list?->name }}</span>
                                @endif
                            </td>

                            {{-- Due date (inline edit) --}}
                            <td class="whitespace-nowrap px-3 py-1.5">
                                @if ($canContribute)
                                <input type="date" value="{{ $card->due_at?->toDateString() }}"
                                       @change="$wire.setCardDue({{ $card->id }}, $event.target.value)"
                                       class="rounded border border-neutral-300 bg-white px-1.5 py-0.5 text-xs focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 {{ $card->due_at && ! $card->completed_at && $card->due_at->isPast() ? 'text-red-600 dark:text-red-400' : '' }}">
                                @else
                                    <span class="text-xs {{ $card->due_at && ! $card->completed_at && $card->due_at->isPast() ? 'text-red-600 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-300' }}">{{ $card->due_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                @endif
                            </td>

                            {{-- Members (inline picker) --}}
                            <td class="px-3 py-1.5">
                                @php $memberIds = $card->members->pluck('id'); @endphp
                                @if ($canContribute)
                                <div x-data="{ o: false }" @click.outside="o = false" class="relative">
                                    <button type="button" @click="o = !o" class="flex min-h-[1.5rem] items-center gap-0 rounded px-1 hover:bg-neutral-100 dark:hover:bg-neutral-700">
                                        @forelse ($card->members as $member)
                                            <span class="-ml-1.5 first:ml-0"><x-user-avatar :user="$member" size="xs" :hover-card="false" class="ring-2 ring-white dark:ring-neutral-900" /></span>
                                        @empty
                                            <x-phosphor-user-plus class="h-4 w-4 text-neutral-300 dark:text-neutral-600"/>
                                        @endforelse
                                    </button>
                                    <div x-show="o" x-cloak class="absolute left-0 z-20 mt-1 max-h-56 w-52 overflow-y-auto rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                        @foreach ($members as $boardMember)
                                            <button type="button" wire:click="toggleCardMember({{ $card->id }}, {{ $boardMember->id }})" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                                <x-user-avatar :user="$boardMember" size="xs" :hover-card="false" />
                                                <span class="min-w-0 flex-1 truncate">{{ $boardMember->name }}</span>
                                                @if ($memberIds->contains($boardMember->id))<x-phosphor-check class="h-4 w-4 shrink-0 text-indigo-600 dark:text-indigo-400"/>@endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                @else
                                    <div class="flex min-h-[1.5rem] items-center">
                                        @forelse ($card->members as $member)
                                            <span class="-ml-1.5 first:ml-0"><x-user-avatar :user="$member" size="xs" :hover-card="false" class="ring-2 ring-white dark:ring-neutral-900" /></span>
                                        @empty
                                            <span class="text-neutral-300 dark:text-neutral-600">—</span>
                                        @endforelse
                                    </div>
                                @endif
                            </td>

                            {{-- Labels (inline picker) --}}
                            <td class="px-3 py-1.5">
                                @php $labelIds = $card->labels->pluck('id'); @endphp
                                @if ($canContribute)
                                <div x-data="{ o: false }" @click.outside="o = false" class="relative">
                                    <button type="button" @click="o = !o" class="flex min-h-[1.5rem] flex-wrap items-center gap-1 rounded px-1 hover:bg-neutral-100 dark:hover:bg-neutral-700">
                                        @forelse ($card->labels as $label)
                                            <span class="inline-block max-w-[8rem] truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white" style="background-color: {{ $label->color }}">{{ $label->name ?? '·' }}</span>
                                        @empty
                                            <x-phosphor-tag class="h-4 w-4 text-neutral-300 dark:text-neutral-600"/>
                                        @endforelse
                                    </button>
                                    <div x-show="o" x-cloak class="absolute left-0 z-20 mt-1 max-h-56 w-52 overflow-y-auto rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                        @forelse ($labels as $boardLabel)
                                            <button type="button" wire:click="toggleCardLabel({{ $card->id }}, {{ $boardLabel->id }})" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                                <span class="h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $boardLabel->color }}"></span>
                                                <span class="min-w-0 flex-1 truncate">{{ $boardLabel->name ?? __('Sans nom') }}</span>
                                                @if ($labelIds->contains($boardLabel->id))<x-phosphor-check class="h-4 w-4 shrink-0 text-indigo-600 dark:text-indigo-400"/>@endif
                                            </button>
                                        @empty
                                            <p class="px-2 py-1.5 text-xs text-neutral-400">{{ __('Aucun label sur ce tableau.') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                                @else
                                    <div class="flex min-h-[1.5rem] flex-wrap items-center gap-1">
                                        @forelse ($card->labels as $label)
                                            <span class="inline-block max-w-[8rem] truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white" style="background-color: {{ $label->color }}">{{ $label->name ?? '·' }}</span>
                                        @empty
                                            <span class="text-neutral-300 dark:text-neutral-600">—</span>
                                        @endforelse
                                    </div>
                                @endif
                            </td>

                            {{-- Custom fields (inline editors, one per type) --}}
                            @foreach ($customFields as $field)
                                @php $cfv = $card->customFieldValues->firstWhere('custom_field_id', $field->id)?->value; @endphp
                                <td class="whitespace-nowrap px-3 py-1.5">
                                    @if ($canContribute)
                                    @switch($field->type->value)
                                        @case('checkbox')
                                            <input type="checkbox" @change="$wire.setCardCustomField({{ $card->id }}, {{ $field->id }}, $event.target.checked)" @checked($cfv === '1')
                                                   class="h-4 w-4 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 dark:border-neutral-600 dark:bg-neutral-800">
                                            @break
                                        @case('select')
                                            <select @change="$wire.setCardCustomField({{ $card->id }}, {{ $field->id }}, $event.target.value)"
                                                    class="rounded border border-neutral-300 bg-white px-1.5 py-0.5 text-xs focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                                <option value="">—</option>
                                                @foreach ($field->options ?? [] as $opt)
                                                    <option value="{{ $opt }}" @selected($cfv === $opt)>{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                            @break
                                        @case('date')
                                            <input type="date" value="{{ $cfv }}" @change="$wire.setCardCustomField({{ $card->id }}, {{ $field->id }}, $event.target.value)"
                                                   class="rounded border border-neutral-300 bg-white px-1.5 py-0.5 text-xs focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                            @break
                                        @case('number')
                                            <input type="number" value="{{ $cfv }}" @change="$wire.setCardCustomField({{ $card->id }}, {{ $field->id }}, $event.target.value)"
                                                   class="w-24 rounded border border-neutral-300 bg-white px-1.5 py-0.5 text-xs focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                            @break
                                        @default
                                            <input type="text" value="{{ $cfv }}" @change="$wire.setCardCustomField({{ $card->id }}, {{ $field->id }}, $event.target.value)"
                                                   class="w-32 rounded border border-neutral-300 bg-white px-1.5 py-0.5 text-xs focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    @endswitch
                                    @else
                                        @if ($field->type->value === 'checkbox')
                                            <span class="flex h-4 w-4 items-center justify-center rounded border {{ $cfv === '1' ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-neutral-300 dark:border-neutral-600' }}">@if ($cfv === '1')<x-phosphor-check class="h-3 w-3"/>@endif</span>
                                        @else
                                            <span class="text-xs text-neutral-600 dark:text-neutral-300">{{ $cfv !== null && $cfv !== '' ? $cfv : '—' }}</span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach

                            {{-- Open --}}
                            <td class="px-3 py-1.5">
                                <button type="button" wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })"
                                        class="rounded p-1 text-neutral-400 hover:bg-neutral-100 hover:text-indigo-600 dark:hover:bg-neutral-700 dark:hover:text-indigo-400" title="{{ __('Ouvrir') }}">
                                    <x-phosphor-arrow-square-out class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
