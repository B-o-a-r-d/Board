{{-- A single card chip in the calendar (needs $card). Opens the card modal on click. --}}
@php $calOverdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast(); @endphp
<button
    type="button"
    wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })"
    title="{{ $card->title }}"
    class="flex w-full items-center gap-1 rounded border-l-2 px-1.5 py-1 text-left text-xs transition hover:brightness-95 {{ $card->completed_at ? 'border-l-green-500 bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300' : ($calOverdue ? 'border-l-red-500 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-l-indigo-400 bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200') }}"
>
    @if ($card->labels->isNotEmpty())
        <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $card->labels->first()->color }}"></span>
    @endif
    <span class="min-w-0 flex-1 truncate">{{ $card->title }}</span>
    @if ($card->members->isNotEmpty())
        <span class="shrink-0 text-[10px] opacity-70">{{ $card->members->count() }}👤</span>
    @endif
</button>
