<div x-data="{ selected: [], helpOpen: false, selectMode: false, toggleCard(id) { this.selected.includes(id) ? this.selected = this.selected.filter(i => i !== id) : this.selected.push(id); } }"
     @keydown.window="
        if ($event.metaKey || $event.ctrlKey || $event.altKey) return;
        if ($event.target.matches('input, textarea, select, [contenteditable]')) return;
        if ($event.key === '/') { $event.preventDefault(); document.getElementById('board-search')?.focus(); }
        else if ($event.key === 'b') { $wire.setView('board'); }
        else if ($event.key === 'c') { $wire.setView('calendar'); }
        else if ($event.key === '?') { helpOpen = true; }
     "
     @open-shortcuts.window="helpOpen = true"
     wire:init="loadCards"
     class="flex h-[calc(100dvh-8rem)] flex-col">
    {{-- Board header --}}
    <div class="mb-3 flex flex-col gap-3 sm:mb-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <a href="{{ route('dashboard') }}" wire:navigate
               class="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
                <x-phosphor-arrow-left class="h-4 w-4"/> {{ __('Tableau de bord') }}
            </a>
            <div class="text-center sm:text-left">
                @if ($renamingBoard)
                    <input
                        type="text"
                        wire:model="boardNameDraft"
                        wire:keydown.enter="renameBoard"
                        wire:keydown.escape="$set('renamingBoard', false)"
                        wire:blur="renameBoard"
                        x-init="$el.focus(); $el.select()"
                        class="w-full rounded-lg border border-indigo-300 bg-white px-2 py-0.5 text-xl font-semibold tracking-tight focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none sm:text-2xl dark:border-indigo-700 dark:bg-neutral-800"
                    >
                @else
                    <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ $board->name }}</h1>
                @endif
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $board->workspace->name }}</p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-2 sm:justify-end sm:gap-3">
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
                        :title="u.guest ? u.name + ' {{ __('(invité)') }}' : u.name"
                        x-text="(u.guest ? u.name.replace('Visiteur ', '') : u.name).charAt(0).toUpperCase()"
                    ></span>
                </template>
            </div>

            <div class="flex gap-2">
                {{-- View toggle: board / calendar --}}
                <div class="flex items-center rounded-lg border border-neutral-300 p-0.5 dark:border-neutral-700">
                    <button type="button" wire:click="setView('board')"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-sm transition {{ $view === 'board' ? 'bg-indigo-600 text-white' : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200' }}"
                            title="{{ __('Tableau') }}">
                        <x-phosphor-squares-four class="h-4 w-4"/><span class="hidden sm:inline">{{ __('Tableau') }}</span>
                    </button>
                    <button type="button" wire:click="setView('calendar')"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-sm transition {{ $view === 'calendar' ? 'bg-indigo-600 text-white' : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200' }}"
                            title="{{ __('Calendrier') }}">
                        <x-phosphor-calendar-blank class="h-4 w-4"/><span class="hidden sm:inline">{{ __('Calendrier') }}</span>
                    </button>
                </div>

                <button type="button" wire:click="toggleActivity"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        title="{{ __('Activité') }}">
                    <x-phosphor-clock-counter-clockwise class="h-4 w-4"/>
                </button>

                @if ($view === 'board')
                    <button type="button"
                            x-data="{ allCollapsed: false }"
                            @click="allCollapsed = ! allCollapsed; $dispatch(allCollapsed ? 'collapse-all' : 'expand-all')"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            :title="allCollapsed ? '{{ __('Tout déplier') }}' : '{{ __('Tout replier') }}'">
                        <x-phosphor-arrows-in-line-horizontal x-show="! allCollapsed" class="h-4 w-4"/>
                        <x-phosphor-arrows-out-line-horizontal x-show="allCollapsed" x-cloak class="h-4 w-4"/>
                    </button>

                    <button type="button" @click="selectMode = ! selectMode; if (! selectMode) selected = []"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border transition"
                            :class="selectMode ? 'border-indigo-400 bg-indigo-50 text-indigo-600 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                            title="{{ __('Sélectionner des cartes') }}">
                        <x-phosphor-check-square class="h-4 w-4"/>
                    </button>
                @endif

                @can('update', $board)
                    <x-context-menu>
                        <x-slot:trigger>
                            <button type="button" @click="openAt($event.clientX, $event.clientY)"
                                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                    title="{{ __('Options du board (clic droit aussi)') }}">
                                <x-phosphor-dots-three-vertical class="h-4 w-4"/>
                            </button>
                        </x-slot:trigger>
                        <x-slot:menu>
                            <x-context-menu.item icon="pencil-simple"
                                                 wire:click="startRenameBoard">{{ __('Renommer') }}</x-context-menu.item>
                            <x-context-menu.item icon="hash"
                                                 @click="navigator.clipboard?.writeText('{{ $board->public_id }}'); window.toast('{{ __('ID copié') }}', { type: 'success' })">{{ __("Copier l'ID du board") }}</x-context-menu.item>
                            <x-context-menu.item icon="robot"
                                                 wire:click="$dispatch('open-automations')">{{ __('Automations') }}</x-context-menu.item>
                            @if (config('board.public_sharing'))
                                <x-context-menu.item icon="share-network"
                                                     wire:click="openShare">{{ __('Partager…') }}</x-context-menu.item>
                            @endif
                            @can('admin')
                                <x-context-menu.item icon="stack"
                                                     wire:click="toggleTemplate">{{ $board->is_template ? __('Retirer des modèles') : __('Définir comme modèle') }}</x-context-menu.item>
                            @endcan
                            <x-context-menu.item icon="image"
                                                 wire:click="openBackground">{{ __('Fond du tableau…') }}</x-context-menu.item>
                            <x-context-menu.separator/>
                            <x-context-menu.item icon="users"
                                                 wire:click="toggleMembers">
                                {{ __('Membres') }}
                            </x-context-menu.item>
                            <x-context-menu.item icon="sliders-horizontal"
                                                 wire:click="toggleCustomFields">
                                {{ __('Champs personnalisés') }}
                            </x-context-menu.item>
                            @can('managePlugins', $board)
                                <x-context-menu.item icon="puzzle-piece"
                                                     wire:click="togglePlugins">
                                    {{ __('Power-Ups') }}
                                </x-context-menu.item>
                            @endcan
                            <x-context-menu.separator/>
                            <x-context-menu.item icon="file-csv"
                                                 @click="window.location.href = '{{ route('boards.export', ['board' => $board->id, 'format' => 'csv']) }}'">{{ __('Exporter en CSV') }}</x-context-menu.item>
                            <x-context-menu.item icon="file-xls"
                                                 @click="window.location.href = '{{ route('boards.export', ['board' => $board->id, 'format' => 'xlsx']) }}'">{{ __('Exporter en XLSX') }}</x-context-menu.item>
                            <x-context-menu.item icon="download-simple"
                                                 @click="window.location.href = '{{ route('boards.export', ['board' => $board->id, 'format' => 'json']) }}'">{{ __('Exporter en JSON') }}</x-context-menu.item>
                            <x-context-menu.separator/>
                            <x-context-menu.item icon="trash"
                                                 wire:click="toggleTrash">
                                {{ __('Corbeille') }}
                            </x-context-menu.item>
                            @can('delete', $board)
                                <x-context-menu.separator/>
                                <x-context-menu.item icon="trash" variant="danger"
                                                     @click="$store.confirm.open({ title: '{{ __('Supprimer le board') }}', message: '{{ __('Supprimer définitivement ce board et tout son contenu ? Cette action est irréversible.') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteBoard())">{{ __('Supprimer le board') }}</x-context-menu.item>
                            @endcan
                        </x-slot:menu>
                    </x-context-menu>
                @endcan
            </div>
        </div>
    </div>

    {{-- Filters --}}
    @php
        $optLabels = ['' => __('Tous les labels')];
        foreach ($labels as $optLabel) { $optLabels[$optLabel->id] = $optLabel->name ?? __('Sans nom'); }

        $optMembers = ['' => __('Tous les membres')];
        foreach ($members as $optMember) { $optMembers[$optMember->id] = $optMember->name; }

        $optDue = [
            '' => __('Échéance : toutes'),
            'overdue' => __('En retard'),
            'due' => __('Avec échéance'),
            'none' => __('Sans échéance'),
        ];
    @endphp
    <div x-data="{ showFilters: false }" class="mb-3 space-y-2">
        {{-- Primary row: search + (desktop) filters inline + (mobile) filters toggle + saved views --}}
        <div class="flex items-center gap-2">
            <div class="relative min-w-0 flex-1 sm:flex-none">
                <x-phosphor-magnifying-glass class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-neutral-400"/>
                <input
                    type="search"
                    id="board-search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Rechercher une carte…') }}"
                    class="w-full rounded-lg border border-neutral-300 bg-white py-1.5 pl-8 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none sm:w-56 dark:border-neutral-700 dark:bg-neutral-800"
                >
                <x-phosphor-spinner-gap wire:loading wire:target="search"
                                        class="absolute right-2 top-2 h-4 w-4 animate-spin text-neutral-400"/>
            </div>

            {{-- Desktop: filter dropdowns inline --}}
            <div class="hidden items-center gap-2 sm:flex">
                <x-filter-dropdown field="filterLabel" icon="tag" :options="$optLabels" :current="$filterLabel" :placeholder="__('Tous les labels')" />
                <x-filter-dropdown field="filterMember" icon="user" :options="$optMembers" :current="$filterMember" :placeholder="__('Tous les membres')" />
                <x-filter-dropdown field="filterDue" icon="clock" :options="$optDue" :current="$filterDue" :placeholder="__('Échéance : toutes')" />
                @if ($this->hasActiveFilters())
                    <button type="button" wire:click="resetFilters"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                        {{ __('Réinitialiser') }}
                    </button>
                @endif
            </div>

            {{-- Mobile: filters toggle --}}
            <button type="button" @click="showFilters = ! showFilters"
                    class="flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm shadow-sm sm:hidden {{ $this->activeFilterCount() > 0 ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-300 bg-white text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' }}">
                <x-phosphor-funnel class="h-4 w-4"/>
                <span>{{ __('Filtres') }}</span>
                @if ($this->activeFilterCount() > 0)
                    <span class="rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white">{{ $this->activeFilterCount() }}</span>
                @endif
            </button>

            {{-- Saved views --}}
            <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative shrink-0 sm:ml-auto">
                <button type="button" @click="open = ! open"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm text-neutral-600 shadow-sm hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                    <x-phosphor-bookmarks-simple class="h-4 w-4 shrink-0 opacity-70"/>
                    <span class="hidden sm:inline">{{ __('Vues') }}</span>
                    @if ($views->isNotEmpty())
                        <span class="rounded-full bg-neutral-200 px-1.5 text-xs font-medium text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $views->count() }}</span>
                    @endif
                    <x-phosphor-caret-down class="h-3.5 w-3.5 shrink-0 opacity-60 transition-transform" ::class="open && 'rotate-180'"/>
                </button>

                <div x-show="open" x-cloak x-transition
                     class="absolute right-0 z-40 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-800 dark:bg-neutral-900">
                    @forelse ($views as $view)
                        <div wire:key="view-{{ $view->id }}" class="group/view flex items-center gap-1 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800">
                            <button type="button" wire:click="applyView({{ $view->id }})" @click="open = false" class="min-w-0 flex-1 truncate px-2.5 py-1.5 text-left text-sm text-neutral-700 dark:text-neutral-300">{{ $view->name }}</button>
                            <button type="button" wire:click="deleteView({{ $view->id }})" class="mr-1 shrink-0 rounded p-1 text-neutral-300 opacity-100 transition hover:text-red-500 sm:opacity-0 sm:group-hover/view:opacity-100" title="{{ __('Supprimer') }}"><x-phosphor-x class="h-3.5 w-3.5"/></button>
                        </div>
                    @empty
                        <p class="px-2.5 py-2 text-xs text-neutral-400">{{ __('Aucune vue enregistrée.') }}</p>
                    @endforelse

                    <div class="mt-1 border-t border-neutral-100 p-1.5 dark:border-neutral-800">
                        <form wire:submit="saveView" class="flex items-center gap-1.5" @click.stop>
                            <input type="text" wire:model="newViewName" placeholder="{{ __('Nom de la vue') }}" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                            <button type="submit" class="shrink-0 rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-500">{{ __('Enregistrer') }}</button>
                        </form>
                        @error('newViewName') <p class="mt-1 px-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile: collapsible filter controls (stacked full-width) --}}
        <div x-show="showFilters" x-cloak x-transition class="grid grid-cols-1 gap-2 sm:!hidden">
            <x-filter-dropdown field="filterLabel" icon="tag" :options="$optLabels" :current="$filterLabel" :placeholder="__('Tous les labels')" />
            <x-filter-dropdown field="filterMember" icon="user" :options="$optMembers" :current="$filterMember" :placeholder="__('Tous les membres')" />
            <x-filter-dropdown field="filterDue" icon="clock" :options="$optDue" :current="$filterDue" :placeholder="__('Échéance : toutes')" />
            @if ($this->hasActiveFilters())
                <button type="button" wire:click="resetFilters"
                        class="rounded-lg border border-neutral-200 px-3 py-1.5 text-center text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:border-neutral-700 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                    {{ __('Réinitialiser') }}
                </button>
            @endif
        </div>
    </div>

    @php
        $coverPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b'];
        $boardBg = $board->backgroundStyle();
    @endphp

    @if ($view !== 'calendar')
    {{-- Lists (columns) --}}
    <div
        wire:sort="reorderLists"
        wire:loading.class.delay="opacity-40"
        wire:target="search, filterLabel, filterMember, filterDue, resetFilters, applyFilter, applyView"
        @if ($boardBg) style="background: {{ $boardBg }};" @endif
        class="flex flex-1 snap-x snap-mandatory items-start gap-3 overflow-x-auto scroll-p-1 py-4 transition-opacity sm:snap-none sm:gap-4 {{ $boardBg ? 'rounded-xl px-3' : '' }}"
    >
        @foreach ($lists as $list)
            <div
                wire:key="list-{{ $list->id }}"
                wire:sort:item="{{ $list->id }}"
                x-data="{ cardCount: {{ $list->cards_count }}, wipLimit: {{ $list->wip_limit ?? 'null' }}, collapsed: JSON.parse(localStorage.getItem('board-list-collapsed:{{ $list->public_id }}') ?? 'false') }"
                x-init="
                    $watch('collapsed', v => localStorage.setItem('board-list-collapsed:{{ $list->public_id }}', JSON.stringify(v)));
                    $nextTick(() => {
                        if (! $refs.cards) return;
                        const update = () => cardCount = $refs.cards.querySelectorAll(':scope > li').length;
                        new MutationObserver(update).observe($refs.cards, { childList: true });
                        update();
                    })
                "
                @collapse-all.window="collapsed = true"
                @expand-all.window="collapsed = false"
                :class="collapsed ? 'w-11 self-stretch' : 'w-full sm:w-72'"
                class="flex max-h-full shrink-0 snap-start flex-col overflow-hidden rounded-xl bg-neutral-200/70 dark:bg-neutral-900"
            >
                {{-- Collapsed strip --}}
                <div x-show="collapsed" x-cloak @click="collapsed = false" class="flex flex-1 cursor-pointer select-none flex-col items-center gap-2 py-2.5" title="{{ $list->name }}">
                    <button type="button" @click.stop="collapsed = false" class="shrink-0 rounded p-1 text-neutral-500 hover:bg-neutral-300 dark:hover:bg-neutral-800" title="{{ __('Déplier la liste') }}">
                        <x-phosphor-arrows-out-line-horizontal class="h-4 w-4"/>
                    </button>
                    @if ($list->cover_path)
                        <img src="{{ Storage::disk('public')->url($list->cover_path) }}" alt="" class="h-6 w-6 shrink-0 rounded object-cover">
                    @elseif ($list->cover_color)
                        <span class="h-6 w-1.5 shrink-0 rounded-full" style="background-color: {{ $list->cover_color }}"></span>
                    @endif
                    <span class="shrink-0 rounded-full bg-neutral-300/70 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400" x-text="wipLimit ? cardCount + '/' + wipLimit : cardCount">{{ $list->cards_count }}</span>
                    <span class="mt-1 min-h-0 flex-1 overflow-hidden text-sm font-semibold tracking-wide [writing-mode:vertical-rl]">{{ Str::limit($list->name, 40) }}</span>
                </div>

                {{-- Expanded content --}}
                <div x-show="! collapsed" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @if ($list->cover_path)
                    <img src="{{ Storage::disk('public')->url($list->cover_path) }}" alt="" class="h-16 w-full shrink-0 object-cover">
                @elseif ($list->cover_color)
                    <div class="h-2 w-full shrink-0" style="background-color: {{ $list->cover_color }}"></div>
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
                            @unless ($list->isPluginList())
                                <span
                                    class="shrink-0 rounded-full px-1.5 py-0.5 text-xs font-medium transition-colors"
                                    :class="wipLimit && cardCount > wipLimit ? 'bg-red-200 text-red-700 dark:bg-red-500/25 dark:text-red-300' : 'bg-neutral-300/70 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'"
                                    :title="wipLimit && cardCount > wipLimit ? '{{ __('Limite WIP dépassée') }}' : ''"
                                    x-text="wipLimit ? cardCount + '/' + wipLimit : cardCount">{{ $list->cards_count }}{{ $list->wip_limit ? '/'.$list->wip_limit : '' }}</span>
                            @endunless
                        </div>
                        <button type="button" wire:sort:ignore @click="openAt($event.clientX, $event.clientY)"
                                class="shrink-0 rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                title="{{ __('Options de la liste (clic droit aussi)') }}">
                            <x-phosphor-dots-three class="h-4 w-4"/>
                        </button>
                        <button type="button" wire:sort:ignore @click.stop="collapsed = true"
                                class="shrink-0 rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                title="{{ __('Réduire la liste') }}">
                            <x-phosphor-arrows-in-line-horizontal class="h-4 w-4"/>
                        </button>
                    </x-slot:trigger>
                    <x-slot:menu>
                        <x-context-menu.item icon="pencil-simple"
                                             @click="document.getElementById('list-name-{{ $list->id }}')?.focus()">{{ __('Renommer') }}</x-context-menu.item>
                        <x-context-menu.item icon="hash"
                                             @click="navigator.clipboard?.writeText('{{ $list->public_id }}'); window.toast('{{ __('ID copié') }}', { type: 'success' })">{{ __("Copier l'ID de la liste") }}</x-context-menu.item>
                        <x-context-menu.item icon="copy"
                                             wire:click="duplicateList({{ $list->id }})">{{ __('Dupliquer') }}</x-context-menu.item>
                        <x-context-menu.item icon="image"
                                             wire:click="openListCover({{ $list->id }})">{{ __('Image de couverture…') }}</x-context-menu.item>
                        @if ($list->cover_path)
                            <x-context-menu.item icon="x" wire:click="removeListCover({{ $list->id }})">{{ __("Retirer l'image") }}</x-context-menu.item>
                        @endif
                        <x-context-menu.separator/>
                        <div class="px-2 py-1.5">
                            <p class="mb-1.5 text-xs text-neutral-500">{{ __('Couleur de la liste') }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($coverPalette as $swatch)
                                    <button type="button" wire:click="setListColor({{ $list->id }}, '{{ $swatch }}')"
                                            class="h-5 w-5 rounded-full ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-800"
                                            style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
                                @endforeach
                                <button type="button" wire:click="setListColor({{ $list->id }}, null)"
                                        class="flex h-5 w-5 items-center justify-center rounded-full border border-neutral-300 text-neutral-400 hover:text-neutral-700 dark:border-neutral-600 dark:hover:text-neutral-200"
                                        title="{{ __('Aucune couleur') }}">
                                    <x-phosphor-x class="h-3 w-3"/>
                                </button>
                            </div>
                        </div>
                        <div class="px-2 py-1.5">
                            <label class="mb-1 block text-xs text-neutral-500">{{ __('Limite de cartes (WIP)') }}</label>
                            <input type="number" min="0" value="{{ $list->wip_limit }}" wire:change="setWipLimit({{ $list->id }}, $event.target.value)" placeholder="{{ __('Aucune') }}" class="w-24 rounded border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                        </div>
                        <x-context-menu.separator/>
                        <x-context-menu.item icon="archive" variant="danger"
                                             @click="$store.confirm.open({ title: '{{ __('Archiver la liste') }}', message: '{{ __('Archiver cette liste et ses cartes ?') }}', confirmLabel: '{{ __('Archiver') }}' }).then(ok => ok && $wire.archiveList({{ $list->id }}))">{{ __('Archiver') }}</x-context-menu.item>
                    </x-slot:menu>
                </x-context-menu>

                @if (! $list->isPluginList())
                {{-- Cards --}}
                @if ($cardsReady)
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
                            class="group relative shrink-0 cursor-grab overflow-hidden rounded-lg border bg-white text-sm shadow-sm dark:bg-neutral-800"
                            :class="selected.includes({{ $card->id }}) ? 'border-indigo-500 dark:border-indigo-500' : 'border-neutral-200 dark:border-neutral-700'"
                        >
                            {{-- Selection overlay (only in select mode): clicking anywhere toggles selection --}}
                            <div x-show="selectMode" x-cloak wire:sort:ignore @click.stop="toggleCard({{ $card->id }})"
                                 class="absolute inset-0 z-20 cursor-pointer transition"
                                 :class="selected.includes({{ $card->id }}) ? 'bg-indigo-500/10' : 'hover:bg-neutral-500/5'">
                                <span class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-md border-2 bg-white shadow dark:bg-neutral-900"
                                      :class="selected.includes({{ $card->id }}) ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-neutral-400 dark:border-neutral-500'">
                                    <svg x-show="selected.includes({{ $card->id }})" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                </span>
                            </div>

                            <x-context-menu class="block">
                                <x-slot:trigger>
                                    @if ($card->cover_path)
                                        <img src="{{ Storage::disk('public')->url($card->cover_path) }}" alt=""
                                             class="h-24 w-full object-cover">
                                    @elseif ($card->cover_color)
                                        <div class="h-9 w-full"
                                             style="background-color: {{ $card->cover_color }}"></div>
                                    @endif

                                    <div class="p-2.5">
                                        @if ($card->labels->isNotEmpty())
                                            <div class="mb-1.5 flex flex-wrap gap-1">
                                                @foreach ($card->labels as $label)
                                                    <span class="h-1.5 w-8 rounded-full"
                                                          style="background-color: {{ $label->color }}"
                                                          title="{{ $label->name }}"></span>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="flex items-start justify-between gap-2">
                                            <button type="button"
                                                    wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })"
                                                    class="break-words text-left hover:text-indigo-600 dark:hover:text-indigo-400">
                                                {{ $card->title }}
                                            </button>
                                            <button type="button" wire:sort:ignore
                                                    @click="openAt($event.clientX, $event.clientY)"
                                                    class="shrink-0 text-neutral-400 opacity-100 transition hover:text-neutral-700 group-hover:opacity-100 sm:opacity-0 dark:hover:text-neutral-200"
                                                    title="{{ __('Options de la carte (clic droit aussi)') }}">
                                                <x-phosphor-dots-three class="h-4 w-4"/>
                                            </button>
                                        </div>

                                        {{-- Badges --}}
                                        @if ($card->due_at || $itemsTotal > 0 || $card->attachments_count > 0 || $card->completed_at)
                                            <div
                                                class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                                                @if ($card->completed_at)
                                                    <span
                                                        class="rounded bg-green-100 px-1.5 py-0.5 text-green-700 dark:bg-green-500/15 dark:text-green-400">{{ __('Terminée') }}</span>
                                                @endif
                                                @if ($card->due_at)
                                                    <span
                                                        class="rounded px-1.5 py-0.5 {{ $overdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-700/50' }}">
                                                        {{ $card->due_at->translatedFormat('d M') }}
                                                    </span>
                                                @endif
                                                @if ($itemsTotal > 0)
                                                    <span
                                                        class="{{ $itemsDone === $itemsTotal ? 'text-green-600 dark:text-green-400' : '' }}"><x-phosphor-check
                                                            class="inline-flex self-center h-4 w-4"/> {{ $itemsDone }}/{{ $itemsTotal }}</span>
                                                @endif
                                                @if ($card->attachments_count > 0)
                                                    <span class="inline-flex items-center gap-0.5"><x-phosphor-paperclip
                                                            class="h-3.5 w-3.5"/> {{ $card->attachments_count }}</span>
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Custom field values --}}
                                        @if ($customFields->isNotEmpty())
                                            @php
                                                $cfValues = $card->customFieldValues->keyBy('custom_field_id');
                                                $cfShown = $customFields->filter(fn ($f) => filled(optional($cfValues->get($f->id))->value));
                                            @endphp
                                            @if ($cfShown->isNotEmpty())
                                                <div class="mt-2 flex flex-wrap gap-1">
                                                    @foreach ($cfShown as $field)
                                                        @php $val = $cfValues->get($field->id)->value; @endphp
                                                        <span class="inline-flex items-center gap-1 rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] text-neutral-600 dark:bg-neutral-700/50 dark:text-neutral-300">
                                                            <span class="font-medium">{{ $field->name }}:</span>
                                                            @if ($field->type === \App\Enums\CustomFieldType::Checkbox)
                                                                <x-phosphor-check class="h-3 w-3 text-green-600 dark:text-green-400"/>
                                                            @elseif ($field->type === \App\Enums\CustomFieldType::Date)
                                                                {{ \Illuminate\Support\Carbon::parse($val)->translatedFormat('d M Y') }}
                                                            @else
                                                                {{ $val }}
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endif

                                        @if ($card->members->isNotEmpty())
                                            <div class="mt-2 flex -space-x-1.5">
                                                @foreach ($card->members as $member)
                                                    <x-user-avatar :user="$member" size="xs" class="ring-2 ring-white dark:ring-neutral-800" />
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </x-slot:trigger>
                                <x-slot:menu>
                                    <x-context-menu.item icon="arrow-square-out"
                                                         wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })">{{ __('Ouvrir') }}</x-context-menu.item>
                                    <x-context-menu.item icon="copy"
                                                         wire:click="duplicateCard({{ $card->id }})">{{ __('Dupliquer') }}</x-context-menu.item>
                                    <x-context-menu.item icon="link"
                                                         @click="navigator.clipboard?.writeText('{{ route('boards.show', ['board' => $board, 'card' => $card->public_id]) }}'); window.toast('{{ __('Lien copié') }}', { type: 'success' })">{{ __('Copier le lien') }}</x-context-menu.item>
                                    <x-context-menu.item icon="hash"
                                                         @click="navigator.clipboard?.writeText('{{ $card->public_id }}'); window.toast('{{ __('ID copié') }}', { type: 'success' })">{{ __("Copier l'ID") }}</x-context-menu.item>
                                    @if ($lists->count() > 1)
                                        <x-context-menu.separator/>
                                        <div class="px-2 py-1.5">
                                            <p class="mb-1 flex items-center gap-1 text-xs text-neutral-500">
                                                <x-phosphor-arrows-left-right
                                                    class="h-3.5 w-3.5"/> {{ __('Déplacer vers') }}</p>
                                            <div class="flex max-h-48 flex-col overflow-y-auto">
                                                @foreach ($lists as $targetList)
                                                    @if ($targetList->id !== $list->id)
                                                        <button type="button"
                                                                wire:click="moveCardToList({{ $card->id }}, {{ $targetList->id }})"
                                                                class="truncate rounded px-2 py-1 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ $targetList->name }}</button>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    <x-context-menu.separator/>
                                    <x-context-menu.item icon="archive" variant="danger"
                                                         wire:click="archiveCard({{ $card->id }})">{{ __('Archiver') }}</x-context-menu.item>
                                </x-slot:menu>
                            </x-context-menu>
                        </li>
                    @endforeach
                </ul>
                @else
                    {{-- Cards skeleton (shown until wire:init loads them) --}}
                    <ul class="flex flex-col gap-2 overflow-hidden px-2">
                        @foreach (range(1, min(3, max(1, (int) $list->cards_count))) as $i)
                            <li class="rounded-lg border border-neutral-200 bg-white px-3 py-2.5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
                                <div class="h-3.5 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700" style="width: {{ [80, 60, 72][($i - 1) % 3] }}%"></div>
                                <div class="mt-2 flex gap-2">
                                    <div class="h-2.5 w-10 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                                    <div class="h-2.5 w-8 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Add card --}}
                <div class="flex items-center gap-1 p-2">
                    <form wire:submit="addCard({{ $list->id }})" class="min-w-0 flex-1">
                        <input
                            type="text"
                            wire:model="newCardTitle.{{ $list->id }}"
                            placeholder="{{ __('+ Ajouter une carte') }}"
                            class="w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-sm placeholder-neutral-500 hover:bg-white focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                        >
                    </form>

                    @if ($cardTemplates->isNotEmpty())
                        <x-context-menu class="shrink-0">
                            <x-slot:trigger>
                                <button type="button" @click="openAt($event.clientX, $event.clientY)"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                        title="{{ __('Ajouter depuis un modèle') }}">
                                    <x-phosphor-stack class="h-4 w-4"/>
                                </button>
                            </x-slot:trigger>
                            <x-slot:menu>
                                <p class="px-2 py-1 text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Depuis un modèle') }}</p>
                                @foreach ($cardTemplates as $template)
                                    <x-context-menu.item icon="cards"
                                                         wire:click="addCardFromTemplate({{ $list->id }}, {{ $template->id }})">{{ $template->name }}</x-context-menu.item>
                                @endforeach
                            </x-slot:menu>
                        </x-context-menu>
                    @endif
                </div>
                @else
                    {{-- Plugin-sourced list: rendered by a lazy child (skeleton until loaded). --}}
                    <livewire:boards.plugin-list :list="$list" wire:key="plugin-list-{{ $list->id }}"/>
                @endif
                </div>
            </div>
        @endforeach

        {{-- Add list --}}
        <form wire:submit="addList" class="w-full shrink-0 snap-start sm:w-72">
            <input
                type="text"
                wire:model="newListName"
                placeholder="{{ __('+ Ajouter une liste') }}"
                class="w-full rounded-xl border border-dashed border-neutral-300 bg-white/50 px-3 py-2 text-sm  placeholder-neutral-500 dark:placeholder-neutral-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900/50"
            >
            @error('newListName') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </form>
    </div>

    {{-- Bulk actions bar (shown when cards are multi-selected) --}}
    <div x-show="selected.length > 0" x-cloak
         class="fixed inset-x-0 bottom-4 z-40 mx-auto flex w-max max-w-[calc(100vw-1.5rem)] flex-wrap items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-white px-3 py-2 shadow-xl dark:border-neutral-700 dark:bg-neutral-900">
        <span class="text-sm font-medium" x-text="selected.length + ' {{ __('sélectionnée(s)') }}'"></span>

        <div x-data="{ o: false }" @click.outside="o = false" class="relative">
            <button type="button" @click="o = ! o" class="flex items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-sm hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800"><x-phosphor-arrows-left-right class="h-4 w-4"/> {{ __('Déplacer') }}</button>
            <div x-show="o" x-cloak class="absolute bottom-full left-0 mb-1 max-h-56 w-48 overflow-y-auto rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                @foreach ($lists as $bulkList)
                    <button type="button" @click="$wire.bulkMove(selected, {{ $bulkList->id }}); selected = []; o = false" class="block w-full truncate rounded px-2 py-1.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ $bulkList->name }}</button>
                @endforeach
            </div>
        </div>

        @if ($labels->isNotEmpty())
            <div x-data="{ o: false }" @click.outside="o = false" class="relative">
                <button type="button" @click="o = ! o" class="flex items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-sm hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800"><x-phosphor-tag class="h-4 w-4"/> {{ __('Label') }}</button>
                <div x-show="o" x-cloak class="absolute bottom-full left-0 mb-1 max-h-56 w-52 overflow-y-auto rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                    @foreach ($labels as $bulkLabel)
                        <button type="button" @click="$wire.bulkAddLabel(selected, {{ $bulkLabel->id }}); selected = []; o = false" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $bulkLabel->color }}"></span>
                            <span class="truncate">{{ $bulkLabel->name ?? __('Sans nom') }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <button type="button" @click="$wire.bulkArchive(selected); selected = []" class="flex items-center gap-1 rounded-lg border border-neutral-300 px-2.5 py-1.5 text-sm text-red-600 hover:bg-red-50 dark:border-neutral-700 dark:text-red-400 dark:hover:bg-red-500/10"><x-phosphor-archive class="h-4 w-4"/> {{ __('Archiver') }}</button>

        <button type="button" @click="selected = []; selectMode = false" class="rounded-lg p-1.5 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200" title="{{ __('Désélectionner') }}"><x-phosphor-x class="h-4 w-4"/></button>
    </div>
    @else
        @include('livewire.boards.partials.calendar')
    @endif

    {{-- Trash / archive panel --}}
    @if ($showTrash)
        <x-modal title="{{ __('Corbeille') }}" max-width="2xl" on-close="$wire.toggleTrash()">
            <div class="max-h-[70vh] space-y-6 overflow-y-auto p-5">
                {{-- Archived lists --}}
                <div>
                    <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Listes archivées') }}</h3>
                    @forelse ($archivedLists as $list)
                        <div wire:key="arch-list-{{ $list->id }}"
                             class="flex items-center justify-between gap-2 border-b border-neutral-50 py-2 text-sm dark:border-neutral-800/50">
                            <span class="font-medium">{{ $list->name }}</span>
                            <div class="flex shrink-0 gap-3">
                                <button type="button" wire:click="restoreList({{ $list->id }})"
                                        class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Restaurer') }}</button>
                                <button type="button"
                                        @click="$store.confirm.open({ title: '{{ __('Supprimer la liste') }}', message: '{{ __('Supprimer définitivement cette liste et ses cartes ?') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteListPermanently({{ $list->id }}))"
                                        class="text-xs text-neutral-400 hover:text-red-500">{{ __('Supprimer définitivement') }}</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-neutral-400">{{ __('Aucune liste archivée.') }}</p>
                    @endforelse
                </div>

                {{-- Archived cards --}}
                <div>
                    <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Cartes archivées') }}</h3>
                    @forelse ($archivedCards as $card)
                        <div wire:key="arch-card-{{ $card->id }}"
                             class="flex items-center justify-between gap-2 border-b border-neutral-50 py-2 text-sm dark:border-neutral-800/50">
                            <div class="min-w-0">
                                <p class="truncate">{{ $card->title }}</p>
                                <p class="truncate text-xs text-neutral-400">{{ $card->list?->name }}</p>
                            </div>
                            <div class="flex shrink-0 gap-3">
                                <button type="button" wire:click="restoreCard({{ $card->id }})"
                                        class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Restaurer') }}</button>
                                <button type="button"
                                        @click="$store.confirm.open({ title: '{{ __('Supprimer la carte') }}', message: '{{ __('Supprimer définitivement cette carte ?') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteCardPermanently({{ $card->id }}))"
                                        class="text-xs text-neutral-400 hover:text-red-500">{{ __('Supprimer définitivement') }}</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-neutral-400">{{ __('Aucune carte archivée.') }}</p>
                    @endforelse
                </div>
            </div>
        </x-modal>
    @endif

    {{-- Share panel --}}
    @if ($showShare)
        <x-modal max-width="lg" on-close="$wire.$set('showShare', false)" wire:key="share-modal">
            <x-slot:header>
                <span class="flex items-center gap-2"><x-phosphor-share-network class="h-5 w-5"/> {{ __('Partager le tableau') }}</span>
            </x-slot:header>

            <div class="space-y-4 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium">{{ __('Lien public en lecture seule') }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Toute personne disposant du lien peut consulter ce tableau et ses cartes, sans compte.') }}</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-label="{{ __('Activer le partage public en lecture seule') }}"
                        aria-checked="{{ $board->isShared() ? 'true' : 'false' }}"
                        wire:click="toggleShare"
                        class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ $board->isShared() ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700' }}"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $board->isShared() ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                    </button>
                </div>

                @if ($board->isShared())
                    @php $shareUrl = route('boards.public', ['token' => $board->share_token]); @endphp
                    <div class="flex items-center gap-2" x-data="{ copied: false }">
                        <input type="text" readonly value="{{ $shareUrl }}" @focus="$el.select()"
                               class="flex-1 rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                        <button
                            type="button"
                            @click="navigator.clipboard?.writeText('{{ $shareUrl }}'); window.toast('{{ __('Lien copié') }}', { type: 'success' }); copied = true; setTimeout(() => copied = false, 1500)"
                            class="flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            <x-phosphor-copy class="h-4 w-4"/>
                            <span x-text="copied ? 'Copié !' : 'Copier'"></span>
                        </button>
                    </div>
                    <a href="{{ $shareUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        <x-phosphor-arrow-square-out class="h-3.5 w-3.5"/> {{ __('Ouvrir dans un nouvel onglet') }}
                    </a>
                @else
                    <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500 dark:bg-neutral-800/50 dark:text-neutral-400">{{ __("Activez le partage pour générer un lien. Le désactiver invalide immédiatement l'ancien lien.") }}</p>
                @endif
            </div>
        </x-modal>
    @endif

    {{-- Board background panel --}}
    @if ($showBackground)
        <x-modal max-width="lg" on-close="$wire.$set('showBackground', false)">
            <x-slot:header>
                <span class="flex items-center gap-2"><x-phosphor-image
                        class="h-5 w-5"/> {{ __('Fond du tableau') }}</span>
            </x-slot:header>

            <div class="space-y-4 p-5">
                @if ($board->background_image)
                    <div class="relative overflow-hidden rounded-lg">
                        <img src="{{ Storage::disk('public')->url($board->background_image) }}" alt=""
                             class="h-32 w-full object-cover">
                        <button type="button" wire:click="setBackground(null)"
                                class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                                title="{{ __('Retirer l\'image') }}">
                            <x-phosphor-x class="h-4 w-4"/>
                        </button>
                    </div>
                @endif

                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Dégradés') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (config('board.backgrounds') as $bgKey => $bgCss)
                            <button type="button" wire:click="setBackground('{{ $bgKey }}')"
                                    class="h-9 w-9 rounded-lg ring-offset-2 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-900 {{ $board->background === $bgKey ? 'ring-2 ring-indigo-500' : '' }}"
                                    style="background: {{ $bgCss }}" title="{{ ucfirst($bgKey) }}"></button>
                        @endforeach
                        <button type="button" wire:click="setBackground(null)"
                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-400 hover:text-neutral-700 dark:border-neutral-600 dark:hover:text-neutral-200"
                                title="{{ __('Aucun fond') }}">
                            <x-phosphor-x class="h-4 w-4"/>
                        </button>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Image personnalisée') }}</p>
                    <x-dropzone model="backgroundUpload" action="uploadBackground" accept="image/*"
                                hint="{{ __('Image de fond · 10 Mo max') }}"/>
                </div>
            </div>
        </x-modal>
    @endif

    {{-- Board members panel --}}
    @if ($showMembers)
        <x-modal max-width="lg" on-close="$wire.$set('showMembers', false)">
            <x-slot:header>
                <span class="flex items-center gap-2"><x-phosphor-users class="h-5 w-5"/> {{ __('Membres du board') }}</span>
            </x-slot:header>

            <div class="space-y-5 p-5">
                {{-- Current members --}}
                <ul class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach ($boardMembers as $member)
                        @php $isOwner = $member->pivot->role === \App\Enums\Role::Owner->value; @endphp
                        <li wire:key="bm-{{ $member->id }}" class="flex items-center justify-between gap-3 py-2">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-user-avatar :user="$member" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $member->name }}</p>
                                    <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $member->email }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($isOwner)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">{{ __('Propriétaire') }}</span>
                                @elseif ($canManageMembers)
                                    <select wire:change="updateBoardMemberRole({{ $member->id }}, $event.target.value)" class="rounded-lg border border-neutral-300 bg-white px-2 py-1 text-xs shadow-sm focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                        <option value="member" @selected($member->pivot->role === 'member')>{{ __('Membre') }}</option>
                                        <option value="admin" @selected($member->pivot->role === 'admin')>{{ __('Administrateur') }}</option>
                                    </select>
                                    <button type="button" wire:click="removeBoardMember({{ $member->id }})" class="text-xs text-neutral-400 hover:text-red-500">{{ __('Retirer') }}</button>
                                @else
                                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ \App\Enums\Role::from($member->pivot->role)->label() }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Add from the workspace --}}
                @if ($canManageMembers)
                    <div>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Ajouter depuis le workspace') }}</p>
                        @forelse ($addableMembers as $candidate)
                            <div wire:key="add-{{ $candidate->id }}" class="flex items-center justify-between gap-3 py-1.5">
                                <div class="flex min-w-0 items-center gap-2">
                                    <x-user-avatar :user="$candidate" size="sm" />
                                    <span class="truncate text-sm">{{ $candidate->name }}</span>
                                </div>
                                <button type="button" wire:click="addBoardMember({{ $candidate->id }})" class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-500">{{ __('Ajouter') }}</button>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-400">{{ __('Tous les membres du workspace sont déjà sur ce board.') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </x-modal>
    @endif

    {{-- Custom fields panel --}}
    @if ($showCustomFields)
        @php $fieldTypes = \App\Enums\CustomFieldType::cases(); @endphp
        <x-modal max-width="lg" on-close="$wire.$set('showCustomFields', false)">
            <x-slot:header>
                <span class="flex items-center gap-2"><x-phosphor-sliders-horizontal class="h-5 w-5"/> {{ __('Champs personnalisés') }}</span>
            </x-slot:header>

            <div class="space-y-5 p-5">
                @if ($customFields->isEmpty())
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun champ personnalisé. Ajoutez-en un ci-dessous pour enrichir les cartes.') }}</p>
                @else
                    <ul class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($customFields as $field)
                            <li wire:key="cf-{{ $field->id }}" class="flex items-center justify-between gap-3 py-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <x-dynamic-component :component="'phosphor-'.$field->type->icon()" class="h-4 w-4 shrink-0 text-neutral-400"/>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ $field->name }}</p>
                                        <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ __($field->type->label()) }}@if ($field->type->hasOptions() && $field->options) · {{ implode(', ', $field->options) }}@endif</p>
                                    </div>
                                </div>
                                <button type="button"
                                        @click="$store.confirm.open({ title: '{{ __('Supprimer le champ') }}', message: '{{ __('Supprimer ce champ et toutes ses valeurs sur les cartes ?') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteCustomField({{ $field->id }}))"
                                        class="shrink-0 rounded-full p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        title="{{ __('Supprimer') }}"><x-phosphor-trash class="h-4 w-4"/></button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form wire:submit="addCustomField" class="space-y-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Nom du champ') }}</label>
                            <input type="text" wire:model="newFieldName" placeholder="{{ __('Priorité, Estimation…') }}"
                                   class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                            @error('newFieldName') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Type') }}</label>
                            <select wire:model.live="newFieldType"
                                    class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                @foreach ($fieldTypes as $ft)
                                    <option value="{{ $ft->value }}">{{ __($ft->label()) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if ($newFieldType === 'select')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Options (séparées par des virgules)') }}</label>
                            <input type="text" wire:model="newFieldOptions" placeholder="{{ __('Basse, Moyenne, Haute') }}"
                                   class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                            @error('newFieldOptions') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <button type="submit"
                            class="flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                        <x-phosphor-plus class="h-4 w-4"/> {{ __('Ajouter le champ') }}
                    </button>
                </form>
            </div>
        </x-modal>
    @endif

    {{-- Power-Ups (plugins) panel --}}
    @if ($showPlugins)
        <x-modal max-width="2xl" on-close="$wire.$set('showPlugins', false)">
            <x-slot:header>
                <span class="flex items-center gap-2"><x-phosphor-puzzle-piece class="h-5 w-5"/> {{ __('Power-Ups') }}</span>
            </x-slot:header>

            <div class="space-y-6 p-5">
                {{-- Installed instances --}}
                <div class="space-y-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Installés') }}</h3>
                    @forelse ($installedPlugins as $instance)
                        @php
                            $def = $pluginRegistry->get($instance->plugin_key);
                            $connected = $instance->isConnected();
                            $needsOAuth = $def?->requiresOAuth() ?? false;
                            $configFields = $def?->configFields($instance->config ?? []) ?? [];
                            $isConfigured = collect($configFields)->every(fn ($f) => filled($instance->config[$f['key']] ?? null));
                        @endphp
                        <div wire:key="plugin-{{ $instance->id }}" class="rounded-xl border border-neutral-200 p-3 dark:border-neutral-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-2">
                                    <x-dynamic-component :component="'phosphor-'.($def?->icon() ?? 'puzzle-piece')" class="mt-0.5 h-5 w-5 shrink-0 text-neutral-500"/>
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-2 text-sm font-medium">
                                            {{ $instance->name }}
                                            @if (! $instance->is_active)
                                                <span class="rounded bg-neutral-200 px-1.5 text-[10px] font-normal text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400">{{ __('inactif') }}</span>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">
                                            @if ($needsOAuth)
                                                @if ($connected)
                                                    <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400"><x-phosphor-plugs-connected class="h-3.5 w-3.5"/> {{ __('Connecté') }}@if (! empty($instance->config['account'])) · {{ $instance->config['account'] }}@endif</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400"><x-phosphor-plug class="h-3.5 w-3.5"/> {{ __('Non connecté') }}</span>
                                                @endif
                                            @else
                                                {{ $def?->description() }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    @if (! empty($configFields))
                                        <button type="button" wire:click="startPluginConfig({{ $instance->id }})"
                                                class="rounded-lg border border-neutral-300 px-2 py-1 text-xs hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                            {{ __('Configurer') }}
                                        </button>
                                    @endif
                                    @if ($needsOAuth && $isConfigured)
                                        <a href="{{ route('plugins.oauth.redirect', $instance) }}"
                                           class="rounded-lg border border-neutral-300 px-2 py-1 text-xs hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                            {{ $connected ? __('Reconnecter') : __('Connecter') }}
                                        </a>
                                    @endif
                                    <button type="button" wire:click="togglePluginActive({{ $instance->id }})"
                                            class="rounded-lg border border-neutral-300 px-2 py-1 text-xs hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                        {{ $instance->is_active ? __('Désactiver') : __('Activer') }}
                                    </button>
                                    <button type="button"
                                            @click="$store.confirm.open({ title: '{{ __('Désinstaller le plugin') }}', message: '{{ __('Retirer ce Power-Up ? Les listes qui en dépendent deviendront vides.') }}', confirmLabel: '{{ __('Désinstaller') }}', danger: true }).then(ok => ok && $wire.uninstallPlugin({{ $instance->id }}))"
                                            class="rounded-lg p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="{{ __('Désinstaller') }}">
                                        <x-phosphor-trash class="h-4 w-4"/>
                                    </button>
                                </div>
                            </div>

                            {{-- Credentials configuration --}}
                            @if ($editingPluginId === $instance->id && ! empty($configFields))
                                <form wire:submit="savePluginConfig" class="mt-3 space-y-3 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                                    @if ($needsOAuth)
                                        <div class="rounded-lg bg-neutral-50 p-2.5 text-xs text-neutral-600 dark:bg-neutral-800/50 dark:text-neutral-300">
                                            <p class="mb-1 font-medium">{{ __("URL de rappel à renseigner dans l'app OAuth :") }}</p>
                                            <div class="flex items-center gap-2">
                                                <code class="min-w-0 flex-1 truncate rounded bg-white px-2 py-1 dark:bg-neutral-900">{{ route('plugins.oauth.callback') }}</code>
                                                <button type="button" class="shrink-0 rounded p-1 hover:bg-neutral-200 dark:hover:bg-neutral-700"
                                                        @click="navigator.clipboard?.writeText('{{ route('plugins.oauth.callback') }}'); window.toast('{{ __('Copié') }}', { type: 'success' })" title="{{ __('Copier') }}">
                                                    <x-phosphor-copy class="h-3.5 w-3.5"/>
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    @foreach ($configFields as $field)
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-neutral-500">{{ $field['label'] }}</label>
                                            <input type="{{ ($field['type'] ?? 'text') === 'password' ? 'password' : 'text' }}"
                                                   wire:model="pluginConfigDraft.{{ $field['key'] }}"
                                                   placeholder="{{ ($field['type'] ?? 'text') === 'password' && filled($instance->config[$field['key']] ?? null) ? '••••••••' : ($field['placeholder'] ?? '') }}"
                                                   autocomplete="off"
                                                   class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                            @if (! empty($field['help'])) <p class="mt-1 text-xs text-neutral-400">{{ $field['help'] }}</p> @endif
                                        </div>
                                    @endforeach

                                    <div class="flex items-center gap-2">
                                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ __('Enregistrer') }}</button>
                                        <button type="button" wire:click="$set('editingPluginId', null)" class="rounded-lg px-3 py-1.5 text-sm text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                                    </div>
                                </form>
                            @elseif ($needsOAuth && ! $isConfigured)
                                <p class="mt-2 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                    <x-phosphor-warning class="h-3.5 w-3.5"/> {{ __("Renseignez les identifiants OAuth pour connecter.") }}
                                </p>
                            @endif

                            {{-- Create a list from this plugin --}}
                            @if ($def instanceof \Board\PluginSdk\Contracts\ProvidesListSource && ($instance->is_active) && (! $needsOAuth || $connected))
                                @if ($configuringPluginId === $instance->id)
                                    <form wire:submit="createPluginList" class="mt-3 space-y-3 border-t border-neutral-100 pt-3 dark:border-neutral-800">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Nom de la liste') }}</label>
                                                <input type="text" wire:model="newPluginListName" placeholder="{{ __('Commits, PRs…') }}"
                                                       class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                                @error('newPluginListName') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Type de source') }}</label>
                                                <select wire:model="newPluginListMode"
                                                        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                                    <option value="">{{ __('Choisir…') }}</option>
                                                    @foreach ($def->sourceModes() as $sourceMode)
                                                        <option value="{{ $sourceMode['key'] }}">{{ $sourceMode['label'] }}</option>
                                                    @endforeach
                                                </select>
                                                @error('newPluginListMode') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                            </div>
                                        </div>

                                        @foreach ($def->listConfigFields($instance->config ?? []) as $field)
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-neutral-500">{{ $field['label'] }}</label>
                                                @if (($field['type'] ?? 'text') === 'select')
                                                    <x-searchable-select
                                                        :model="'newPluginListConfig.'.$field['key']"
                                                        :options="$field['options'] ?? []"
                                                        :placeholder="__('Choisir…')"/>
                                                @else
                                                    <input type="text" wire:model="newPluginListConfig.{{ $field['key'] }}" placeholder="{{ $field['placeholder'] ?? '' }}"
                                                           class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                                @endif
                                                @if (! empty($field['help'])) <p class="mt-1 text-xs text-neutral-400">{{ $field['help'] }}</p> @endif
                                            </div>
                                        @endforeach

                                        <div class="flex items-center gap-2">
                                            <button type="submit" wire:target="createPluginList" wire:loading.attr="disabled"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">
                                                <x-phosphor-spinner-gap wire:loading wire:target="createPluginList" class="h-3.5 w-3.5 animate-spin"/>
                                                {{ __('Créer la liste') }}
                                            </button>
                                            <button type="button" wire:click="$set('configuringPluginId', null)" class="rounded-lg px-3 py-1.5 text-sm text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                                        </div>
                                    </form>
                                @else
                                    <button type="button" wire:click="startPluginList({{ $instance->id }})"
                                            wire:target="startPluginList({{ $instance->id }})" wire:loading.attr="disabled"
                                            class="mt-3 inline-flex items-center gap-1 rounded-lg border border-dashed border-neutral-300 px-2.5 py-1 text-xs text-neutral-600 hover:border-indigo-400 hover:text-indigo-600 disabled:opacity-60 dark:border-neutral-700 dark:text-neutral-300">
                                        <x-phosphor-plus wire:loading.remove wire:target="startPluginList({{ $instance->id }})" class="h-3.5 w-3.5"/>
                                        <x-phosphor-spinner-gap wire:loading wire:target="startPluginList({{ $instance->id }})" class="h-3.5 w-3.5 animate-spin"/>
                                        <span wire:loading.remove wire:target="startPluginList({{ $instance->id }})">{{ __('Créer une liste') }}</span>
                                        <span wire:loading wire:target="startPluginList({{ $instance->id }})">{{ __('Chargement…') }}</span>
                                    </button>
                                @endif
                            @elseif ($needsOAuth && ! $connected)
                                <p class="mt-2 text-xs text-neutral-400">{{ __('Connectez le plugin pour créer des listes.') }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun Power-Up installé.') }}</p>
                    @endforelse
                </div>

                {{-- Catalog --}}
                @php $installedKeys = $installedPlugins->pluck('plugin_key')->all(); @endphp
                @php $catalog = collect($availablePlugins)->reject(fn ($p, $key) => in_array($key, $installedKeys, true)); @endphp
                @if ($catalog->isNotEmpty())
                    <div class="space-y-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Catalogue') }}</h3>
                        @foreach ($catalog as $key => $def)
                            <div wire:key="catalog-{{ $key }}" class="flex items-center justify-between gap-3 rounded-xl border border-neutral-200 p-3 dark:border-neutral-800">
                                <div class="flex min-w-0 items-start gap-2">
                                    <x-dynamic-component :component="'phosphor-'.$def->icon()" class="mt-0.5 h-5 w-5 shrink-0 text-neutral-500"/>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ $def->label() }}</p>
                                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $def->description() }}</p>
                                    </div>
                                </div>
                                <button type="button" wire:click="installPlugin('{{ $key }}')"
                                        class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ __('Installer') }}</button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-modal>
    @endif

    {{-- List cover image panel --}}
    @if ($coverListId)
        @php $coverList = $lists->firstWhere('id', $coverListId); @endphp
        <x-modal max-width="lg" on-close="$wire.closeListCover()">
            <x-slot:header>
                <span class="flex items-center gap-2"><x-phosphor-image class="h-5 w-5"/> {{ __('Image de couverture de la liste') }}</span>
            </x-slot:header>

            <div class="space-y-4 p-5">
                @if ($coverList?->cover_path)
                    <div class="relative overflow-hidden rounded-lg">
                        <img src="{{ Storage::disk('public')->url($coverList->cover_path) }}" alt="" class="h-32 w-full object-cover">
                        <button type="button" wire:click="removeListCover({{ $coverListId }})" class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70" title="{{ __("Retirer l'image") }}"><x-phosphor-x class="h-4 w-4"/></button>
                    </div>
                @endif
                <x-dropzone model="listCoverUpload" action="uploadListCover" accept="image/*" hint="{{ __('Image de couverture · 10 Mo max') }}"/>
            </div>
        </x-modal>
    @endif

    @can('update', $board)
        <livewire:boards.automations :board="$board" :show-trigger="false" wire:key="automations-{{ $board->id }}"/>
    @endcan

    {{-- Keyboard shortcuts help (desktop) --}}
    <div x-show="helpOpen" x-cloak x-transition.opacity @keydown.escape.window="helpOpen = false" @click="helpOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm">
        <div @click.stop class="w-full max-w-sm rounded-2xl border border-neutral-200 bg-white p-5 shadow-xl dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-base font-semibold"><x-phosphor-keyboard class="h-5 w-5"/> {{ __('Raccourcis clavier') }}</h2>
                <button type="button" @click="helpOpen = false" class="rounded-full p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"><x-phosphor-x class="h-4 w-4"/></button>
            </div>
            @php
                $shortcuts = [
                    __('Rechercher une carte') => '/',
                    __('Vue tableau') => 'B',
                    __('Vue calendrier') => 'C',
                    __('Fermer / annuler') => 'Échap',
                    __('Afficher cette aide') => '?',
                ];
            @endphp
            <ul class="space-y-2 text-sm">
                @foreach ($shortcuts as $label => $key)
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-neutral-600 dark:text-neutral-300">{{ $label }}</span>
                        <kbd class="rounded border border-neutral-300 bg-neutral-100 px-1.5 py-0.5 font-mono text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $key }}</kbd>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Activity slide-over --}}
    @if ($showActivity)
    <div x-data="{ tab: 'all', shown: false }" x-init="requestAnimationFrame(() => shown = true)"
         @keydown.escape.window="$wire.toggleActivity()"
         class="fixed inset-0 z-50 flex justify-end">
        <div x-show="shown" x-transition.opacity @click="$wire.toggleActivity()"
             class="absolute inset-0 bg-neutral-900/40 backdrop-blur-sm"></div>

        <aside x-show="shown"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               class="relative flex h-full w-full max-w-md flex-col border-l border-neutral-200 bg-white shadow-xl dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <x-phosphor-clock-counter-clockwise class="h-5 w-5"/> {{ __('Activité') }}
                </h2>
                <button type="button" wire:click="toggleActivity"
                        class="rounded-full p-1.5 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                    <x-phosphor-x class="h-4 w-4"/>
                </button>
            </div>

            <div class="flex gap-1 border-b border-neutral-200 px-4 py-2 dark:border-neutral-800">
                <button type="button" @click="tab = 'all'"
                        class="rounded-lg px-3 py-1 text-sm font-medium transition"
                        :class="tab === 'all' ? 'bg-indigo-600 text-white' : 'text-neutral-500 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800'">
                    {{ __('Tout') }}
                </button>
                <button type="button" @click="tab = 'comments'"
                        class="rounded-lg px-3 py-1 text-sm font-medium transition"
                        :class="tab === 'comments' ? 'bg-indigo-600 text-white' : 'text-neutral-500 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800'">
                    {{ __('Commentaires') }}
                </button>
                @foreach ($activityTabs as $pTab)
                    <button type="button" @click="tab = 'plugin:{{ $pTab['plugin_key'] }}'"
                            class="rounded-lg px-3 py-1 text-sm font-medium transition"
                            :class="tab === 'plugin:{{ $pTab['plugin_key'] }}' ? 'bg-indigo-600 text-white' : 'text-neutral-500 hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800'">
                        {{ $pTab['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-3">
                @forelse ($activities as $activity)
                    @php
                        $ft = $activity->focusTarget();
                        $sectionArg = $ft['section'] ? "'".$ft['section']."'" : 'null';
                        $commentArg = $ft['comment'] ?? 'null';
                        $rowTab = $activity->isComment() ? 'comments' : ($activity->pluginKey() ? 'plugin:'.$activity->pluginKey() : null);
                    @endphp
                    <div class="flex gap-3 rounded-lg py-2.5 {{ $ft['card'] ? '-mx-2 cursor-pointer px-2 transition hover:bg-neutral-50 dark:hover:bg-neutral-800/60' : '' }}"
                         x-show="tab === 'all'@if ($rowTab) || tab === '{{ $rowTab }}'@endif"
                         @if ($ft['card'])
                             role="button" tabindex="0"
                             wire:click="focusActivity({{ $ft['card'] }}, {{ $sectionArg }}, {{ $commentArg }})"
                             wire:keydown.enter="focusActivity({{ $ft['card'] }}, {{ $sectionArg }}, {{ $commentArg }})"
                             title="{{ __('Ouvrir') }}"
                         @endif>
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                            {{ mb_strtoupper(mb_substr($activity->user?->name ?? '?', 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-neutral-700 dark:text-neutral-200">
                                <span class="font-semibold">{{ $activity->user?->name ?? __('un membre') }}</span>
                                {{ $activity->describe() }}
                            </p>
                            @if ($activity->isComment() && ! empty($activity->properties['excerpt']))
                                <p class="mt-1 rounded-lg bg-neutral-100 px-3 py-2 text-sm text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                    {{ $activity->properties['excerpt'] }}
                                </p>
                            @endif
                            <p class="mt-0.5 text-xs text-neutral-400 dark:text-neutral-500">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                        @if ($ft['card'])
                            <x-phosphor-arrow-up-right class="mt-1 h-3.5 w-3.5 shrink-0 text-neutral-300 dark:text-neutral-600"/>
                        @endif
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-neutral-400 dark:text-neutral-500">{{ __('Aucune activité pour le moment.') }}</p>
                @endforelse

                @if ($activities->contains(fn ($a) => $a->isComment()) === false)
                    <p x-show="tab === 'comments'" x-cloak class="py-8 text-center text-sm text-neutral-400 dark:text-neutral-500">{{ __('Aucun commentaire pour le moment.') }}</p>
                @endif
            </div>
        </aside>
    </div>
    @endif

    <livewire:cards.card-detail :board="$board" wire:key="card-detail-{{ $board->id }}"/>
</div>
