{{-- Shared global-search results (used by the desktop dropdown and the mobile overlay). --}}
@if ($boards->isEmpty() && $cards->isEmpty())
    <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun résultat pour « :term ».', ['term' => $term]) }}</p>
@else
    @if ($boards->isNotEmpty())
        <div class="border-b border-neutral-100 py-1 dark:border-neutral-800">
            <p class="px-4 py-1 text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Boards') }}</p>
            @foreach ($boards as $board)
                <a href="{{ route('boards.show', $board) }}" wire:navigate @click="focused = false; mobileOpen = false" class="block px-4 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800">
                    {{ $board->name }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($cards->isNotEmpty())
        <div class="py-1">
            <p class="px-4 py-1 text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Cartes') }}</p>
            @foreach ($cards as $card)
                <a href="{{ route('boards.show', ['board' => $card->board, 'card' => $card->public_id]) }}" wire:navigate @click="focused = false; mobileOpen = false" class="block px-4 py-2 hover:bg-neutral-50 dark:hover:bg-neutral-800">
                    <p class="truncate text-sm">{{ $card->title }}</p>
                    <p class="truncate text-xs text-neutral-400">{{ $card->board->name }}</p>
                </a>
            @endforeach
        </div>
    @endif
@endif
