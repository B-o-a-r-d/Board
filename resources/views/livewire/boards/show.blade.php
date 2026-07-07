<div class="flex h-[calc(100dvh-8rem)] flex-col">
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

                @if ($view === 'board')
                    <button type="button"
                            x-data="{ allCollapsed: false }"
                            @click="allCollapsed = ! allCollapsed; $dispatch(allCollapsed ? 'collapse-all' : 'expand-all')"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            :title="allCollapsed ? '{{ __('Tout déplier') }}' : '{{ __('Tout replier') }}'">
                        <x-phosphor-arrows-in-line-horizontal x-show="! allCollapsed" class="h-4 w-4"/>
                        <x-phosphor-arrows-out-line-horizontal x-show="allCollapsed" x-cloak class="h-4 w-4"/>
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
                x-data="{ cardCount: {{ $list->cards->count() }}, wipLimit: {{ $list->wip_limit ?? 'null' }}, collapsed: JSON.parse(localStorage.getItem('board-list-collapsed:{{ $list->public_id }}') ?? 'false') }"
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
                    <span class="shrink-0 rounded-full bg-neutral-300/70 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400" x-text="wipLimit ? cardCount + '/' + wipLimit : cardCount">{{ $list->cards->count() }}</span>
                    <span class="mt-1 min-h-0 flex-1 overflow-hidden text-sm font-semibold tracking-wide [writing-mode:vertical-rl]">{{ Str::limit($list->name, 40) }}</span>
                </div>

                {{-- Expanded content --}}
                <div x-show="! collapsed" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @if ($list->cover_path)
                    <img src="{{ Storage::disk('public')->url($list->cover_path) }}" alt="" class="h-16 w-full object-cover">
                @elseif ($list->cover_color)
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
                            <span
                                class="shrink-0 rounded-full px-1.5 py-0.5 text-xs font-medium transition-colors"
                                :class="wipLimit && cardCount > wipLimit ? 'bg-red-200 text-red-700 dark:bg-red-500/25 dark:text-red-300' : 'bg-neutral-300/70 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'"
                                :title="wipLimit && cardCount > wipLimit ? '{{ __('Limite WIP dépassée') }}' : ''"
                                x-text="wipLimit ? cardCount + '/' + wipLimit : cardCount">{{ $list->cards->count() }}{{ $list->wip_limit ? '/'.$list->wip_limit : '' }}</span>
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

                                        @if ($card->members->isNotEmpty())
                                            <div class="mt-2 flex -space-x-1.5">
                                                @foreach ($card->members as $member)
                                                    <span
                                                        class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700 ring-2 ring-white dark:bg-indigo-500/20 dark:text-indigo-300 dark:ring-neutral-800"
                                                        title="{{ $member->name }}">
                                                        {{ Str::of($member->name)->substr(0, 1)->upper() }}
                                                    </span>
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
                </div>
            </div>
        @endforeach

        {{-- Add list --}}
        <form wire:submit="addList" class="w-full shrink-0 snap-start sm:w-72">
            <input
                type="text"
                wire:model="newListName"
                placeholder="{{ __('+ Ajouter une liste') }}"
                class="w-full rounded-xl border border-dashed border-neutral-300 bg-white/50 px-3 py-2 text-sm placeholder-neutral-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900/50"
            >
            @error('newListName') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </form>
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
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">{{ Str::of($member->name)->substr(0, 1)->upper() }}</span>
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
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ Str::of($candidate->name)->substr(0, 1)->upper() }}</span>
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

    <livewire:cards.card-detail :board="$board" wire:key="card-detail-{{ $board->id }}"/>
</div>
