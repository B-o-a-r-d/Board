{{-- A mirrored card: the SAME underlying card shown here (needs $mirror with eager card). --}}
@php $mc = $mirror->card; @endphp
@if ($mc)
    @php
        $mItems = $mc->checklists->flatMap->items;
        $mTotal = $mItems->count();
        $mDone = $mItems->where('is_completed', true)->count();
        $mOverdue = $mc->due_at && ! $mc->completed_at && $mc->due_at->isPast();
        $crossBoard = $mc->board_id !== $board->id;
    @endphp
    <div wire:key="mirror-{{ $mirror->id }}"
         class="group/mirror relative rounded-lg border border-dashed border-indigo-300 bg-indigo-50/40 shadow-sm dark:border-indigo-500/40 dark:bg-indigo-500/[0.07]">
        @if ($crossBoard)
            <a href="{{ route('boards.show', ['board' => $mc->board, 'card' => $mc->public_id]) }}" wire:navigate class="block cursor-pointer p-2.5 text-sm">
        @else
            <div wire:click="$dispatch('open-card', { cardId: {{ $mc->id }}, title: @js($mc->title) })" class="block cursor-pointer p-2.5 text-sm">
        @endif
            @if ($mc->labels->isNotEmpty())
                <div class="mb-1.5 flex flex-wrap gap-1">
                    @foreach ($mc->labels as $label)
                        <span class="h-1.5 w-8 rounded-full" style="background-color: {{ $label->color }}" title="{{ $label->name }}"></span>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center gap-1 text-[11px] font-medium text-indigo-600 dark:text-indigo-400">
                <x-phosphor-copy class="h-3 w-3"/> {{ __('Miroir') }}@if ($crossBoard) <span class="font-normal text-neutral-400">· {{ $mc->board->name }}</span>@endif
            </div>

            <span class="mt-0.5 block break-words pr-5 font-medium {{ $mc->completed_at ? 'text-neutral-400 line-through' : '' }}">{{ $mc->title }}</span>

            @if ($mc->due_at || $mTotal > 0 || $mc->members->isNotEmpty())
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                    @if ($mc->due_at)
                        <span class="rounded px-1.5 py-0.5 {{ $mOverdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-700/50' }}">{{ $mc->due_at->translatedFormat('d M') }}</span>
                    @endif
                    @if ($mTotal > 0)
                        <span class="{{ $mDone === $mTotal ? 'text-green-600 dark:text-green-400' : '' }}"><x-phosphor-check class="inline-flex h-4 w-4 self-center"/> {{ $mDone }}/{{ $mTotal }}</span>
                    @endif
                    @if ($mc->members->isNotEmpty())
                        <span class="ml-auto flex items-center gap-0.5"><x-phosphor-user class="h-3.5 w-3.5"/>{{ $mc->members->count() }}</span>
                    @endif
                </div>
            @endif
        @if ($crossBoard)</a>@else</div>@endif

        @if ($canContribute)
            <button type="button" wire:click="removeMirror({{ $mirror->id }})"
                    @click="$store.confirm.open({ title: @js(__('Retirer le miroir')), message: @js(__('Retirer ce miroir ? La carte d\'origine n\'est pas supprimée.')), confirmLabel: @js(__('Retirer')), danger: true }).then(ok => ok || $event.stopImmediatePropagation())"
                    class="absolute right-1.5 top-1.5 rounded p-0.5 text-neutral-400 opacity-0 transition hover:bg-white hover:text-red-500 group-hover/mirror:opacity-100 dark:hover:bg-neutral-800"
                    title="{{ __('Retirer le miroir') }}">
                <x-phosphor-x class="h-3.5 w-3.5"/>
            </button>
        @endif
    </div>
@endif
