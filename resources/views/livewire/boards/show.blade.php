<div class="flex h-[calc(100vh-8rem)] flex-col">
    {{-- Board header --}}
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
                <x-phosphor-arrow-left class="h-4 w-4" /> Tableau de bord
            </a>
            @if ($renamingBoard)
                <input
                    type="text"
                    wire:model="boardNameDraft"
                    wire:keydown.enter="renameBoard"
                    wire:keydown.escape="$set('renamingBoard', false)"
                    wire:blur="renameBoard"
                    x-init="$el.focus(); $el.select()"
                    class="w-full rounded-lg border border-indigo-300 bg-white px-2 py-0.5 text-2xl font-semibold tracking-tight focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-indigo-700 dark:bg-neutral-800"
                >
            @else
                <h1 class="text-2xl font-semibold tracking-tight">{{ $board->name }}</h1>
            @endif
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
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ring-2 ring-white dark:ring-neutral-950"
                        :class="u.guest ? 'text-white' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300'"
                        :style="u.guest ? `background-color: ${u.color}` : ''"
                        :title="u.guest ? u.name + ' (invité)' : u.name"
                        x-text="(u.guest ? u.name.replace('Visiteur ', '') : u.name).charAt(0).toUpperCase()"
                    ></span>
                </template>
            </div>

            <button type="button" wire:click="toggleTrash" class="flex items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" title="Corbeille du board">
                <x-phosphor-trash class="h-4 w-4" /> Corbeille
            </button>

            @can('update', $board)
                <livewire:boards.automations :board="$board" wire:key="automations-{{ $board->id }}" />
            @endcan

            @can('update', $board)
                <x-context-menu>
                    <x-slot:trigger>
                        <button type="button" @click="openAt($event.clientX, $event.clientY)" class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" title="Options du board (clic droit aussi)">
                            <x-phosphor-dots-three-vertical class="h-4 w-4" />
                        </button>
                    </x-slot:trigger>
                    <x-slot:menu>
                        <x-context-menu.item icon="pencil-simple" wire:click="startRenameBoard">Renommer</x-context-menu.item>
                        @if (config('board.public_sharing'))
                            <x-context-menu.item icon="share-network" wire:click="openShare">Partager…</x-context-menu.item>
                        @endif
                        @can('admin')
                            <x-context-menu.item icon="stack" wire:click="toggleTemplate">{{ $board->is_template ? 'Retirer des modèles' : 'Définir comme modèle' }}</x-context-menu.item>
                        @endcan
                        <x-context-menu.separator />
                        <div class="px-2 py-1.5">
                            <p class="mb-1.5 text-xs text-neutral-500">Fond du tableau</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach (config('board.backgrounds') as $bgKey => $bgCss)
                                    <button type="button" wire:click="setBackground('{{ $bgKey }}')" class="h-6 w-6 rounded-md ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-800 {{ $board->background === $bgKey ? 'ring-2 ring-indigo-500' : '' }}" style="background: {{ $bgCss }}" title="{{ ucfirst($bgKey) }}"></button>
                                @endforeach
                                <button type="button" wire:click="setBackground(null)" class="flex h-6 w-6 items-center justify-center rounded-md border border-neutral-300 text-neutral-400 hover:text-neutral-700 dark:border-neutral-600 dark:hover:text-neutral-200" title="Aucun fond"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                        <x-context-menu.separator />
                        <x-context-menu.item icon="file-csv" @click="window.location.href = '{{ route('boards.export', ['board' => $board->id, 'format' => 'csv']) }}'">Exporter en CSV</x-context-menu.item>
                        <x-context-menu.item icon="file-xls" @click="window.location.href = '{{ route('boards.export', ['board' => $board->id, 'format' => 'xlsx']) }}'">Exporter en XLSX</x-context-menu.item>
                        <x-context-menu.item icon="download-simple" @click="window.location.href = '{{ route('boards.export', ['board' => $board->id, 'format' => 'json']) }}'">Exporter en JSON</x-context-menu.item>
                        @can('delete', $board)
                            <x-context-menu.separator />
                            <x-context-menu.item icon="trash" variant="danger" @click="$store.confirm.open({ title: 'Supprimer le board', message: 'Supprimer définitivement ce board et tout son contenu ? Cette action est irréversible.', confirmLabel: 'Supprimer', danger: true }).then(ok => ok && $wire.deleteBoard())">Supprimer le board</x-context-menu.item>
                        @endcan
                    </x-slot:menu>
                </x-context-menu>
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

    @php
        $coverPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b'];
        $boardBg = $board->background ? (config('board.backgrounds')[$board->background] ?? null) : null;
    @endphp

    {{-- Lists (columns) --}}
    <div
        wire:sort="reorderLists"
        wire:loading.class.delay="opacity-40"
        wire:target="search, filterLabel, filterMember, filterDue, resetFilters"
        @if ($boardBg) style="background: {{ $boardBg }};" @endif
        class="flex flex-1 items-start gap-4 overflow-x-auto py-4 transition-opacity {{ $boardBg ? 'rounded-xl px-3' : '' }}"
    >
        @foreach ($lists as $list)
            <div
                wire:key="list-{{ $list->id }}"
                wire:sort:item="{{ $list->id }}"
                x-data="{ cardCount: {{ $list->cards->count() }} }"
                x-init="$nextTick(() => {
                    if (! $refs.cards) return;
                    const update = () => cardCount = $refs.cards.querySelectorAll(':scope > li').length;
                    new MutationObserver(update).observe($refs.cards, { childList: true });
                    update();
                })"
                class="flex max-h-full w-72 shrink-0 flex-col overflow-hidden rounded-xl bg-neutral-200/70 dark:bg-neutral-900"
            >
                @if ($list->cover_color)
                    <div class="h-2 w-full" style="background-color: {{ $list->cover_color }}"></div>
                @endif

                {{-- List header (drag handle for column reordering) --}}
                <x-context-menu wire:sort:handle class="flex cursor-grab items-center justify-between gap-2 px-3 py-2">
                    <x-slot:trigger>
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <input
                                type="text"
                                id="list-name-{{ $list->id }}"
                                value="{{ $list->name }}"
                                wire:change="renameList({{ $list->id }}, $event.target.value)"
                                class="w-full min-w-0 truncate rounded bg-transparent px-1 py-0.5 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:focus:bg-neutral-800"
                            >
                            <span class="shrink-0 rounded-full bg-neutral-300/70 px-1.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400" x-text="cardCount">{{ $list->cards->count() }}</span>
                        </div>
                        <button type="button" wire:sort:ignore @click="openAt($event.clientX, $event.clientY)" class="shrink-0 rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="Options de la liste (clic droit aussi)"><x-phosphor-dots-three class="h-4 w-4" /></button>
                    </x-slot:trigger>
                    <x-slot:menu>
                        <x-context-menu.item icon="pencil-simple" @click="document.getElementById('list-name-{{ $list->id }}')?.focus()">Renommer</x-context-menu.item>
                        <x-context-menu.item icon="copy" wire:click="duplicateList({{ $list->id }})">Dupliquer</x-context-menu.item>
                        <x-context-menu.separator />
                        <div class="px-2 py-1.5">
                            <p class="mb-1.5 text-xs text-neutral-500">Couleur de la liste</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($coverPalette as $swatch)
                                    <button type="button" wire:click="setListColor({{ $list->id }}, '{{ $swatch }}')" class="h-5 w-5 rounded-full ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-800" style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
                                @endforeach
                                <button type="button" wire:click="setListColor({{ $list->id }}, null)" class="flex h-5 w-5 items-center justify-center rounded-full border border-neutral-300 text-neutral-400 hover:text-neutral-700 dark:border-neutral-600 dark:hover:text-neutral-200" title="Aucune couleur"><x-phosphor-x class="h-3 w-3" /></button>
                            </div>
                        </div>
                        <x-context-menu.separator />
                        <x-context-menu.item icon="archive" variant="danger" @click="$store.confirm.open({ title: 'Archiver la liste', message: 'Archiver cette liste et ses cartes ?', confirmLabel: 'Archiver' }).then(ok => ok && $wire.archiveList({{ $list->id }}))">Archiver</x-context-menu.item>
                    </x-slot:menu>
                </x-context-menu>

                {{-- Cards --}}
                <ul
                    x-ref="cards"
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
                            <x-context-menu class="block">
                                <x-slot:trigger>
                                    @if ($card->cover_path)
                                        <img src="{{ Storage::disk('public')->url($card->cover_path) }}" alt="" class="h-24 w-full object-cover">
                                    @elseif ($card->cover_color)
                                        <div class="h-9 w-full" style="background-color: {{ $card->cover_color }}"></div>
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
                                            <button type="button" wire:sort:ignore @click="openAt($event.clientX, $event.clientY)" class="shrink-0 text-neutral-400 opacity-0 transition hover:text-neutral-700 group-hover:opacity-100 dark:hover:text-neutral-200" title="Options de la carte (clic droit aussi)"><x-phosphor-dots-three class="h-4 w-4" /></button>
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
                                </x-slot:trigger>
                                <x-slot:menu>
                                    <x-context-menu.item icon="arrow-square-out" wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })">Ouvrir</x-context-menu.item>
                                    <x-context-menu.item icon="copy" wire:click="duplicateCard({{ $card->id }})">Dupliquer</x-context-menu.item>
                                    <x-context-menu.item icon="link" @click="navigator.clipboard?.writeText('{{ route('boards.show', ['board' => $board->id, 'card' => $card->id]) }}')">Copier le lien</x-context-menu.item>
                                    <x-context-menu.separator />
                                    <x-context-menu.item icon="archive" variant="danger" wire:click="archiveCard({{ $card->id }})">Archiver</x-context-menu.item>
                                </x-slot:menu>
                            </x-context-menu>
                        </li>
                    @endforeach
                </ul>

                {{-- Add card --}}
                <div class="flex items-center gap-1 p-2">
                    <form wire:submit="addCard({{ $list->id }})" class="min-w-0 flex-1">
                        <input
                            type="text"
                            wire:model="newCardTitle.{{ $list->id }}"
                            placeholder="+ Ajouter une carte"
                            class="w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-sm placeholder-neutral-500 hover:bg-white focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                        >
                    </form>

                    @if ($cardTemplates->isNotEmpty())
                        <x-context-menu class="shrink-0">
                            <x-slot:trigger>
                                <button type="button" @click="openAt($event.clientX, $event.clientY)" class="flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="Ajouter depuis un modèle">
                                    <x-phosphor-stack class="h-4 w-4" />
                                </button>
                            </x-slot:trigger>
                            <x-slot:menu>
                                <p class="px-2 py-1 text-xs font-medium uppercase tracking-wide text-neutral-400">Depuis un modèle</p>
                                @foreach ($cardTemplates as $template)
                                    <x-context-menu.item icon="cards" wire:click="addCardFromTemplate({{ $list->id }}, {{ $template->id }})">{{ $template->name }}</x-context-menu.item>
                                @endforeach
                            </x-slot:menu>
                        </x-context-menu>
                    @endif
                </div>
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
        <x-modal title="Corbeille" max-width="2xl" on-close="$wire.toggleTrash()">
                <div class="max-h-[70vh] space-y-6 overflow-y-auto p-5">
                    {{-- Archived lists --}}
                    <div>
                        <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Listes archivées</h3>
                        @forelse ($archivedLists as $list)
                            <div wire:key="arch-list-{{ $list->id }}" class="flex items-center justify-between gap-2 border-b border-neutral-50 py-2 text-sm dark:border-neutral-800/50">
                                <span class="font-medium">{{ $list->name }}</span>
                                <div class="flex shrink-0 gap-3">
                                    <button type="button" wire:click="restoreList({{ $list->id }})" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">Restaurer</button>
                                    <button type="button" @click="$store.confirm.open({ title: 'Supprimer la liste', message: 'Supprimer définitivement cette liste et ses cartes ?', confirmLabel: 'Supprimer', danger: true }).then(ok => ok && $wire.deleteListPermanently({{ $list->id }}))" class="text-xs text-neutral-400 hover:text-red-500">Supprimer définitivement</button>
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
                                    <button type="button" @click="$store.confirm.open({ title: 'Supprimer la carte', message: 'Supprimer définitivement cette carte ?', confirmLabel: 'Supprimer', danger: true }).then(ok => ok && $wire.deleteCardPermanently({{ $card->id }}))" class="text-xs text-neutral-400 hover:text-red-500">Supprimer définitivement</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-400">Aucune carte archivée.</p>
                        @endforelse
                    </div>
                </div>
        </x-modal>
    @endif

    {{-- Share panel --}}
    @if ($showShare)
        <x-modal max-width="lg" on-close="$wire.$set('showShare', false)" wire:key="share-modal">
            <x-slot:header>
                <span class="flex items-center gap-2"><x-phosphor-share-network class="h-5 w-5" /> Partager le tableau</span>
            </x-slot:header>

                <div class="space-y-4 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium">Lien public en lecture seule</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Toute personne disposant du lien peut consulter ce tableau et ses cartes, sans compte.</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-label="Activer le partage public en lecture seule"
                            aria-checked="{{ $board->isShared() ? 'true' : 'false' }}"
                            wire:click="toggleShare"
                            class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ $board->isShared() ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700' }}"
                        >
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $board->isShared() ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                        </button>
                    </div>

                    @if ($board->isShared())
                        @php $shareUrl = route('boards.public', ['token' => $board->share_token]); @endphp
                        <div class="flex items-center gap-2" x-data="{ copied: false }">
                            <input type="text" readonly value="{{ $shareUrl }}" @focus="$el.select()" class="flex-1 rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                            <button
                                type="button"
                                @click="navigator.clipboard?.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                class="flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500"
                            >
                                <x-phosphor-copy class="h-4 w-4" />
                                <span x-text="copied ? 'Copié !' : 'Copier'"></span>
                            </button>
                        </div>
                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                            <x-phosphor-arrow-square-out class="h-3.5 w-3.5" /> Ouvrir dans un nouvel onglet
                        </a>
                    @else
                        <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500 dark:bg-neutral-800/50 dark:text-neutral-400">Activez le partage pour générer un lien. Le désactiver invalide immédiatement l'ancien lien.</p>
                    @endif
                </div>
        </x-modal>
    @endif

    <livewire:cards.card-detail :board="$board" wire:key="card-detail-{{ $board->id }}" />
</div>
