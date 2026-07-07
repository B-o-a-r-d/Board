{{-- A single linked-card row (needs $link and $other). Opens that card on click. --}}
<div wire:key="cardlink-{{ $link->id }}" class="flex items-center gap-1">
    <button type="button" wire:click="openCard({{ $other->id }})"
            class="min-w-0 flex-1 truncate rounded px-2 py-1 text-left text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $other->completed_at ? 'text-neutral-400 line-through' : 'text-neutral-700 dark:text-neutral-300' }}">
        {{ $other->title }}
    </button>
    <button type="button" wire:click="unlinkCard({{ $link->id }})" class="shrink-0 rounded p-1 text-neutral-300 transition hover:text-red-500" title="{{ __('Retirer') }}"><x-phosphor-x class="h-3.5 w-3.5"/></button>
</div>
