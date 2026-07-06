<div class="flex h-[calc(100vh-8rem)] flex-col">
    {{-- Board header --}}
    <div class="mb-4 flex items-center justify-between">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">← Tableau de bord</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $board->name }}</h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $board->workspace->name }}</p>
        </div>
    </div>

    {{-- Lists (columns) --}}
    <div
        wire:sort="reorderLists"
        class="flex flex-1 items-start gap-4 overflow-x-auto pb-4"
    >
        @foreach ($lists as $list)
            <div
                wire:key="list-{{ $list->id }}"
                wire:sort:item="{{ $list->id }}"
                class="flex max-h-full w-72 shrink-0 flex-col rounded-xl bg-neutral-200/70 dark:bg-neutral-900"
            >
                {{-- List header (drag handle for column reordering) --}}
                <div wire:sort:handle class="flex cursor-grab items-center justify-between gap-2 px-3 py-2">
                    <input
                        type="text"
                        value="{{ $list->name }}"
                        wire:change="renameList({{ $list->id }}, $event.target.value)"
                        class="w-full truncate rounded bg-transparent px-1 py-0.5 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:focus:bg-neutral-800"
                    >
                    <div wire:sort:ignore x-data="{ confirm: false }" class="relative">
                        <button type="button" @click="confirm = true" class="rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="Supprimer la liste">✕</button>
                        <div x-show="confirm" x-cloak @click.outside="confirm = false" class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-neutral-200 bg-white p-2 text-xs shadow-lg dark:border-neutral-700 dark:bg-neutral-800">
                            <p class="mb-2">Supprimer cette liste et ses cartes ?</p>
                            <button type="button" wire:click="deleteList({{ $list->id }})" class="w-full rounded bg-red-600 px-2 py-1 font-medium text-white hover:bg-red-500">Supprimer</button>
                        </div>
                    </div>
                </div>

                {{-- Cards --}}
                <ul
                    wire:sort="moveCard"
                    wire:sort:group="cards"
                    wire:sort:group-id="{{ $list->id }}"
                    class="flex min-h-2 flex-col gap-2 overflow-y-auto px-2"
                >
                    @foreach ($list->cards as $card)
                        <li
                            wire:key="card-{{ $card->id }}"
                            wire:sort:item="{{ $card->id }}"
                            class="group cursor-grab rounded-lg border border-neutral-200 bg-white p-2.5 text-sm shadow-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            @if ($card->labels->isNotEmpty())
                                <div class="mb-1.5 flex flex-wrap gap-1">
                                    @foreach ($card->labels as $label)
                                        <span class="h-1.5 w-8 rounded-full" style="background-color: {{ $label->color }}" title="{{ $label->name }}"></span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex items-start justify-between gap-2">
                                <span class="break-words">{{ $card->title }}</span>
                                <div wire:sort:ignore>
                                    <button type="button" wire:click="deleteCard({{ $card->id }})" class="opacity-0 transition group-hover:opacity-100 text-neutral-400 hover:text-red-500" title="Supprimer la carte">✕</button>
                                </div>
                            </div>

                            @if ($card->members->isNotEmpty())
                                <div class="mt-2 flex -space-x-1.5">
                                    @foreach ($card->members as $member)
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700 ring-2 ring-white dark:bg-indigo-500/20 dark:text-indigo-300 dark:ring-neutral-800" title="{{ $member->name }}">
                                            {{ Str::of($member->name)->substr(0, 1)->upper() }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>

                {{-- Add card --}}
                <form wire:submit="addCard({{ $list->id }})" class="p-2">
                    <input
                        type="text"
                        wire:model="newCardTitle.{{ $list->id }}"
                        placeholder="+ Ajouter une carte"
                        class="w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-sm placeholder-neutral-500 hover:bg-white focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                    >
                </form>
            </div>
        @endforeach

        {{-- Add list --}}
        <form wire:submit="addList" class="w-72 shrink-0">
            <input
                type="text"
                wire:model="newListName"
                placeholder="+ Ajouter une liste"
                class="w-full rounded-xl border border-dashed border-neutral-300 bg-white/50 px-3 py-2 text-sm placeholder-neutral-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900/50"
            >
            @error('newListName') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </form>
    </div>
</div>
