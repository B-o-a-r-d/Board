<div class="flex h-[calc(100vh-8rem)] flex-col">
    {{-- Board header --}}
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
                <x-phosphor-arrow-left class="h-4 w-4" /> Tableau de bord
            </a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $board->name }}</h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $board->workspace->name }}</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Presence: who is currently viewing this board --}}
            <div
                class="flex items-center -space-x-2"
                x-data='{
                    users: [],
                    init() {
                        if (! window.Echo) return;
                        const channel = "board-presence.{{ $board->id }}";
                        window.Echo.join(channel)
                            .here((u) => { this.users = u; })
                            .joining((u) => { this.users = [...this.users, u]; })
                            .leaving((u) => { this.users = this.users.filter((x) => x.id !== u.id); });
                        document.addEventListener("livewire:navigating", () => window.Echo.leave(channel), { once: true });
                    }
                }'
            >
                <template x-for="u in users" :key="u.id">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 ring-2 ring-white dark:bg-indigo-500/20 dark:text-indigo-300 dark:ring-neutral-950" :title="u.name" x-text="u.name.charAt(0).toUpperCase()"></span>
                </template>
            </div>

            <button type="button" wire:click="toggleTrash" class="flex items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" title="Corbeille du board">
                <x-phosphor-trash class="h-4 w-4" /> Corbeille
            </button>

            @can('delete', $board)
                <div x-data="{ menu: false }" class="relative">
                    <button type="button" @click="menu = ! menu" @click.outside="menu = false" class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" title="Options du board">
                        <x-phosphor-dots-three-vertical class="h-4 w-4" />
                    </button>
                    <div x-show="menu" x-transition x-cloak class="absolute right-0 z-30 mt-2 w-48 rounded-xl border border-neutral-200 bg-white py-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900">
                        <button type="button" wire:click="deleteBoard" wire:confirm="Supprimer définitivement ce board et tout son contenu ? Cette action est irréversible." class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-neutral-100 dark:text-red-400 dark:hover:bg-neutral-800">
                            Supprimer le board
                        </button>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="relative">
            <x-phosphor-magnifying-glass class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-neutral-400" />
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Rechercher une carte…"
                class="w-56 rounded-lg border border-neutral-300 bg-white py-1.5 pl-8 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
            >
            <x-phosphor-spinner-gap wire:loading wire:target="search" class="absolute right-2 top-2 h-4 w-4 animate-spin text-neutral-400" />
        </div>

        <select wire:model.live="filterLabel" class="rounded-lg border border-neutral-300 bg-white py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
            <option value="">Tous les labels</option>
            @foreach ($labels as $label)
                <option value="{{ $label->id }}">{{ $label->name ?? 'Sans nom' }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterMember" class="rounded-lg border border-neutral-300 bg-white py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
            <option value="">Tous les membres</option>
            @foreach ($members as $member)
                <option value="{{ $member->id }}">{{ $member->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterDue" class="rounded-lg border border-neutral-300 bg-white py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
            <option value="">Échéance : toutes</option>
            <option value="overdue">En retard</option>
            <option value="due">Avec échéance</option>
            <option value="none">Sans échéance</option>
        </select>

        @if ($this->hasActiveFilters())
            <button type="button" wire:click="resetFilters" class="rounded-lg px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                Réinitialiser
            </button>
        @endif
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
                    <div wire:sort:ignore x-data="{ confirming: false }" class="relative">
                        <button type="button" @click="confirming = true" class="rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="Archiver la liste"><x-phosphor-dots-three class="h-4 w-4" /></button>
                        <div x-show="confirming" x-cloak @click.outside="confirming = false" class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-neutral-200 bg-white p-2 text-xs shadow-lg dark:border-neutral-700 dark:bg-neutral-800">
                            <p class="mb-2">Archiver cette liste et ses cartes ?</p>
                            <button type="button" wire:click="archiveList({{ $list->id }})" @click="confirming = false" class="w-full rounded bg-amber-600 px-2 py-1 font-medium text-white hover:bg-amber-500">Archiver</button>
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
                        @php
                            $items = $card->checklists->flatMap->items;
                            $itemsTotal = $items->count();
                            $itemsDone = $items->where('is_completed', true)->count();
                            $overdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast();
                        @endphp
                        <li
                            wire:key="card-{{ $card->id }}"
                            wire:sort:item="{{ $card->id }}"
                            class="group shrink-0 cursor-grab overflow-hidden rounded-lg border border-neutral-200 bg-white text-sm shadow-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            @if ($card->cover_path)
                                <img src="{{ Storage::disk('public')->url($card->cover_path) }}" alt="" class="h-24 w-full object-cover">
                            @endif

                            <div class="p-2.5">
                                @if ($card->labels->isNotEmpty())
                                    <div class="mb-1.5 flex flex-wrap gap-1">
                                        @foreach ($card->labels as $label)
                                            <span class="h-1.5 w-8 rounded-full" style="background-color: {{ $label->color }}" title="{{ $label->name }}"></span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex items-start justify-between gap-2">
                                    <button type="button" wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })" class="break-words text-left hover:text-indigo-600 dark:hover:text-indigo-400">
                                        {{ $card->title }}
                                    </button>
                                    <div wire:sort:ignore>
                                        <button type="button" wire:click="archiveCard({{ $card->id }})" class="text-neutral-400 opacity-0 transition hover:text-red-500 group-hover:opacity-100" title="Archiver la carte"><x-phosphor-archive class="h-4 w-4" /></button>
                                    </div>
                                </div>

                                {{-- Badges --}}
                                @if ($card->due_at || $itemsTotal > 0 || $card->attachments_count > 0 || $card->completed_at)
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($card->completed_at)
                                            <span class="rounded bg-green-100 px-1.5 py-0.5 text-green-700 dark:bg-green-500/15 dark:text-green-400">Terminée</span>
                                        @endif
                                        @if ($card->due_at)
                                            <span class="rounded px-1.5 py-0.5 {{ $overdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-700/50' }}">
                                                {{ $card->due_at->translatedFormat('d M') }}
                                            </span>
                                        @endif
                                        @if ($itemsTotal > 0)
                                            <span class="{{ $itemsDone === $itemsTotal ? 'text-green-600 dark:text-green-400' : '' }}"><x-phosphor-check class="inline-flex self-center h-4 w-4" /> {{ $itemsDone }}/{{ $itemsTotal }}</span>
                                        @endif
                                        @if ($card->attachments_count > 0)
                                            <span class="inline-flex items-center gap-0.5"><x-phosphor-paperclip class="h-3.5 w-3.5" /> {{ $card->attachments_count }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if ($card->members->isNotEmpty())
                                    <div class="mt-2 flex -space-x-1.5">
                                        @foreach ($card->members as $member)
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700 ring-2 ring-white dark:bg-indigo-500/20 dark:text-indigo-300 dark:ring-neutral-800" title="{{ $member->name }}">
                                                {{ Str::of($member->name)->substr(0, 1)->upper() }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
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

    {{-- Trash / archive panel --}}
    @if ($showTrash)
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl dark:bg-neutral-900">
                <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-3 dark:border-neutral-800">
                    <h2 class="text-base font-semibold">Corbeille</h2>
                    <button type="button" wire:click="toggleTrash" class="rounded-full p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"><x-phosphor-x class="h-4 w-4" /></button>
                </div>

                <div class="max-h-[70vh] space-y-6 overflow-y-auto p-5">
                    {{-- Archived lists --}}
                    <div>
                        <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Listes archivées</h3>
                        @forelse ($archivedLists as $list)
                            <div wire:key="arch-list-{{ $list->id }}" class="flex items-center justify-between gap-2 border-b border-neutral-50 py-2 text-sm dark:border-neutral-800/50">
                                <span class="font-medium">{{ $list->name }}</span>
                                <div class="flex shrink-0 gap-3">
                                    <button type="button" wire:click="restoreList({{ $list->id }})" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">Restaurer</button>
                                    <button type="button" wire:click="deleteListPermanently({{ $list->id }})" wire:confirm="Supprimer définitivement cette liste et ses cartes ?" class="text-xs text-neutral-400 hover:text-red-500">Supprimer définitivement</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-400">Aucune liste archivée.</p>
                        @endforelse
                    </div>

                    {{-- Archived cards --}}
                    <div>
                        <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Cartes archivées</h3>
                        @forelse ($archivedCards as $card)
                            <div wire:key="arch-card-{{ $card->id }}" class="flex items-center justify-between gap-2 border-b border-neutral-50 py-2 text-sm dark:border-neutral-800/50">
                                <div class="min-w-0">
                                    <p class="truncate">{{ $card->title }}</p>
                                    <p class="truncate text-xs text-neutral-400">{{ $card->list?->name }}</p>
                                </div>
                                <div class="flex shrink-0 gap-3">
                                    <button type="button" wire:click="restoreCard({{ $card->id }})" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">Restaurer</button>
                                    <button type="button" wire:click="deleteCardPermanently({{ $card->id }})" wire:confirm="Supprimer définitivement cette carte ?" class="text-xs text-neutral-400 hover:text-red-500">Supprimer définitivement</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-400">Aucune carte archivée.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <livewire:cards.card-detail :board="$board" wire:key="card-detail-{{ $board->id }}" />
</div>
