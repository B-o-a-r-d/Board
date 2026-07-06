<div x-data="{ focused: false }" @click.outside="focused = false" class="relative hidden w-64 sm:block">
    <x-phosphor-magnifying-glass class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-neutral-400" />
    <input
        type="search"
        wire:model.live.debounce.300ms="query"
        @focus="focused = true"
        placeholder="Rechercher boards & cartes…"
        class="w-full rounded-lg border border-neutral-300 bg-white py-1.5 pl-8 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
    >
    <x-phosphor-spinner-gap wire:loading wire:target="query" class="absolute right-2 top-2 h-4 w-4 animate-spin text-neutral-400" />

    <div
        x-show="focused && $wire.query.trim().length >= 2"
        x-cloak
        class="absolute right-0 z-50 mt-2 max-h-96 w-80 overflow-y-auto rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
    >
        @if ($boards->isEmpty() && $cards->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">Aucun résultat pour « {{ $term }} ».</p>
        @else
            @if ($boards->isNotEmpty())
                <div class="border-b border-neutral-100 py-1 dark:border-neutral-800">
                    <p class="px-4 py-1 text-xs font-medium uppercase tracking-wide text-neutral-400">Boards</p>
                    @foreach ($boards as $board)
                        <a href="{{ route('boards.show', $board) }}" wire:navigate @click="focused = false" class="block px-4 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800">
                            {{ $board->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($cards->isNotEmpty())
                <div class="py-1">
                    <p class="px-4 py-1 text-xs font-medium uppercase tracking-wide text-neutral-400">Cartes</p>
                    @foreach ($cards as $card)
                        <a href="{{ route('boards.show', ['board' => $card->board_id, 'card' => $card->id]) }}" wire:navigate @click="focused = false" class="block px-4 py-2 hover:bg-neutral-50 dark:hover:bg-neutral-800">
                            <p class="truncate text-sm">{{ $card->title }}</p>
                            <p class="truncate text-xs text-neutral-400">{{ $card->board->name }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
