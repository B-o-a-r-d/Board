{{-- Workspace table: cards across every accessible board, sortable, each row links to its board. --}}
@php
    $sortCaret = fn (string $col) => $tableSort === $col
        ? ($tableDir === 'asc' ? 'caret-up' : 'caret-down')
        : null;
@endphp
<div class="flex min-h-0 flex-1 flex-col overflow-hidden transition-opacity"
     wire:loading.class.delay="opacity-40"
     wire:target="filterBoards, filterMembers, filterDue, resetFilters, sortTable">

    @if ($tableCards->isEmpty())
        <p class="py-16 text-center text-sm text-neutral-400">{{ __('Aucune carte à afficher.') }}</p>
    @else
        <div class="min-h-0 flex-1 overflow-auto rounded-xl border border-neutral-300 dark:border-neutral-700">
            <table class="min-w-full border-collapse text-sm">
                <thead class="sticky top-0 z-10 bg-neutral-100 text-left dark:bg-neutral-900">
                    <tr class="border-b border-neutral-300 dark:border-neutral-700">
                        @foreach (['board' => __('Tableau'), 'title' => __('Titre'), 'list' => __('Liste'), 'due' => __('Échéance')] as $col => $label)
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
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tableCards as $card)
                        <tr wire:key="ws-row-{{ $card->id }}"
                            class="cursor-pointer border-b border-neutral-100 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                            onclick="window.Livewire ? Livewire.navigate(this.dataset.href) : (window.location = this.dataset.href)"
                            data-href="{{ route('boards.show', ['board' => $card->board, 'card' => $card->public_id]) }}">
                            {{-- Board --}}
                            <td class="whitespace-nowrap px-3 py-1.5">
                                <span class="flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: hsl({{ ($card->board_id * 47) % 360 }} 60% 55%)"></span>
                                    <span class="max-w-[12rem] truncate text-neutral-600 dark:text-neutral-300">{{ $card->board->name }}</span>
                                </span>
                            </td>
                            {{-- Title --}}
                            <td class="px-3 py-1.5">
                                <span class="min-w-[10rem] {{ $card->completed_at ? 'text-neutral-400 line-through' : 'font-medium' }}">{{ $card->title }}</span>
                            </td>
                            {{-- List --}}
                            <td class="whitespace-nowrap px-3 py-1.5 text-neutral-600 dark:text-neutral-300">{{ $card->list?->name }}</td>
                            {{-- Due --}}
                            <td class="whitespace-nowrap px-3 py-1.5">
                                @if ($card->due_at)
                                    <span class="{{ $card->due_at->isPast() && ! $card->completed_at ? 'text-red-600 dark:text-red-400' : 'text-neutral-600 dark:text-neutral-300' }}">{{ $card->due_at->translatedFormat('j M') }}</span>
                                @else
                                    <span class="text-neutral-400">—</span>
                                @endif
                            </td>
                            {{-- Members --}}
                            <td class="px-3 py-1.5">
                                @if ($card->members->isNotEmpty())
                                    <span class="flex items-center gap-1 text-neutral-500 dark:text-neutral-400"><x-phosphor-user class="h-3.5 w-3.5"/>{{ $card->members->count() }}</span>
                                @else
                                    <span class="text-neutral-300 dark:text-neutral-600">—</span>
                                @endif
                            </td>
                            {{-- Labels --}}
                            <td class="px-3 py-1.5">
                                @if ($card->labels->isNotEmpty())
                                    <span class="flex flex-wrap gap-1">
                                        @foreach ($card->labels as $label)
                                            <span class="h-2 w-6 rounded-full" style="background-color: {{ $label->color }}" title="{{ $label->name }}"></span>
                                        @endforeach
                                    </span>
                                @else
                                    <span class="text-neutral-300 dark:text-neutral-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
