{{-- A calendar chip for a workspace-aggregated card (needs $card + eager-loaded board). Links to its board. --}}
@php $calOverdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast(); @endphp
<a
    href="{{ route('boards.show', ['board' => $card->board, 'card' => $card->public_id]) }}"
    wire:navigate
    title="{{ $card->board->name }} · {{ $card->title }}"
    class="flex w-full items-center gap-1 rounded border border-l-4 px-1.5 py-1 text-left text-xs shadow-sm transition hover:brightness-95 {{ $card->completed_at ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300' : ($calOverdue ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300' : 'border-neutral-200 bg-white text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200') }}"
    style="border-left-color: hsl({{ ($card->board_id * 47) % 360 }} 60% 55%)"
>
    <span class="min-w-0 flex-1 truncate">{{ $card->title }}</span>
    @if ($card->members->isNotEmpty())
        <span class="flex shrink-0 items-center gap-0.5 text-[10px] opacity-70"><x-phosphor-user class="h-3 w-3"/>{{ $card->members->count() }}</span>
    @endif
</a>
