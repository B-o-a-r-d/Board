<div x-data="{ selected: [], helpOpen: false, selectMode: false, toggleCard(id) { this.selected.includes(id) ? this.selected = this.selected.filter(i => i !== id) : this.selected.push(id); } }"
     @keydown.window="
        if ($event.metaKey || $event.ctrlKey || $event.altKey) return;
        if ($event.target.matches('input, textarea, select, [contenteditable]')) return;
        if ($event.key === 'b') { $wire.setView('board'); }
        else if ($event.key === 'c') { $wire.setView('calendar'); }
        else if ($event.key === 't') { $wire.setView('timeline'); }
        else if ($event.key === 'e') { $wire.setView('table'); }
        else if ($event.key === 'd') { $wire.setView('dashboard'); }
        else if ($event.key === '?') { helpOpen = true; }
     "
     @open-shortcuts.window="helpOpen = true"
     wire:init="loadCards"
     class="-mb-8 flex h-[calc(100dvh-6rem)] flex-col">
    @php $boardBg = $board->backgroundStyle(); @endphp
    @if ($boardBg)
        {{-- Full-bleed board background: a fixed layer behind every board surface,
             plus a soft contrast overlay so the glass topbar and lists stay legible. --}}
        <div class="pointer-events-none fixed inset-0 -z-10" style="background: {{ $boardBg }};" aria-hidden="true"></div>
        <div class="pointer-events-none fixed inset-0 -z-10 bg-black/20" aria-hidden="true"></div>
    @endif
    @php
        // Options shared by the topbar filter/view dropdowns.
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

        $viewOptions = [
            'board' => ['label' => __('Tableau'), 'icon' => 'squares-four'],
            'calendar' => ['label' => __('Calendrier'), 'icon' => 'calendar-blank'],
            'timeline' => ['label' => __('Timeline'), 'icon' => 'chart-bar-horizontal'],
            'table' => ['label' => __('Table'), 'icon' => 'table'],
            'dashboard' => ['label' => __('Dashboard'), 'icon' => 'chart-pie-slice'],
        ];

        // Topbar icon buttons get an opaque surface so they stand out over the glass.
        $topBtn = 'flex h-8 shrink-0 items-center justify-center rounded-lg border shadow-sm transition '.($boardBg
            ? 'border-black/10 bg-white/90 text-neutral-700 hover:bg-white dark:border-white/10 dark:bg-neutral-800/90 dark:text-neutral-200 dark:hover:bg-neutral-800'
            : 'border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700');
        $panelClasses = 'rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900';
    @endphp

    {{-- Board topbar: slim full-bleed bar glued under the navbar (no rounding).
         relative z-30 keeps its dropdowns above the list columns (whose backdrop-blur
         creates sibling stacking contexts painted in DOM order). --}}
    <div @class([
        'relative z-30 -mx-4 -mt-8 mb-3 flex min-h-12 flex-wrap items-center gap-x-2 gap-y-1.5 border-b px-4 py-1.5 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8',
        'border-white/20 dark:border-white/10' => $boardBg,
        'border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900' => ! $boardBg,
    ])>
        @if ($boardBg)
            {{-- The blur lives on a -z child: backdrop-filter on the bar itself would make it
                 the containing block of the fixed mobile filters modal and break its stacking. --}}
            <div class="absolute inset-0 -z-10 bg-white/40 backdrop-blur-md dark:bg-neutral-900/50" aria-hidden="true"></div>
        @endif
        <div class="flex min-w-0 flex-1 items-center gap-2">
            @if ($renamingBoard)
                <input
                    type="text"
                    wire:model="boardNameDraft"
                    wire:keydown.enter="renameBoard"
                    wire:keydown.escape="$set('renamingBoard', false)"
                    wire:blur="renameBoard"
                    x-init="$el.focus(); $el.select()"
                    class="w-full max-w-xs rounded-lg border border-indigo-300 bg-white px-2 py-0.5 text-base font-semibold tracking-tight focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-indigo-700 dark:bg-neutral-800"
                >
            @else
                <h1 class="truncate text-base font-semibold tracking-tight sm:text-lg">{{ $board->name }}</h1>
            @endif
            <span class="hidden shrink-0 items-center rounded-full bg-neutral-200/80 px-2 py-0.5 text-[11px] font-medium text-neutral-600 sm:inline-flex dark:bg-neutral-800 dark:text-neutral-300">{{ $board->workspace->name }}</span>
            @unless ($canContribute)
                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-neutral-200/80 px-2 py-0.5 text-[11px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300" title="{{ __('Votre rôle est en lecture seule sur ce tableau.') }}">
                    <x-phosphor-eye class="h-3.5 w-3.5"/><span class="hidden sm:inline">{{ __('Lecture seule') }}</span>
                </span>
            @endunless
        </div>

        <div class="flex flex-wrap items-center gap-2">
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
                    <div x-data="hoverCard(u)" @mouseenter="enter()" @mouseleave="leave()" class="relative inline-flex leading-none">
                        <template x-if="u.avatar_url">
                            <img
                                :src="u.avatar_url"
                                :alt="u.name"
                                :title="u.name"
                                draggable="false"
                                class="h-8 w-8 rounded-full object-cover ring-2 ring-white dark:ring-neutral-950"
                            >
                        </template>
                        <template x-if="! u.avatar_url">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ring-2 ring-white dark:ring-neutral-950"
                                :class="u.guest ? 'text-white' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300'"
                                :style="u.guest ? `background-color: ${u.color}` : ''"
                                :title="u.guest ? u.name + ' {{ __('(invité)') }}' : u.name"
                                x-text="(u.guest ? u.name.replace(/^\S+\s/, '') : u.name).charAt(0).toUpperCase()"
                            ></span>
                        </template>

                        {{-- Hover card (teleported to body, lazy) --}}
                        <template x-teleport="body">
                            <template x-if="open">
                                <div x-transition @mouseenter="enter()" @mouseleave="leave()"
                                     :style="`top: ${coords.top}px; left: ${coords.left}px;`"
                                     class="fixed z-50 w-64 cursor-default rounded-xl border border-neutral-200/70 bg-white p-4 text-left shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                    <div class="flex items-start gap-3">
                                        <template x-if="user.avatar_url">
                                            <img :src="user.avatar_url" :alt="user.name" class="h-10 w-10 shrink-0 rounded-full object-cover">
                                        </template>
                                        <template x-if="! user.avatar_url">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                                  :class="user.guest ? 'text-white' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300'"
                                                  :style="user.guest ? `background-color: ${user.color}` : ''"
                                                  x-text="(user.guest ? user.name.replace(/^\S+\s/, '') : user.name).charAt(0).toUpperCase()"></span>
                                        </template>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-semibold text-neutral-900 dark:text-neutral-100" x-text="user.name"></p>
                                            <template x-if="user.guest">
                                                <p class="mt-0.5 text-sm italic text-neutral-400">{{ __('Invité') }}</p>
                                            </template>
                                            <template x-if="! user.guest && user.biography">
                                                <p class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-300" x-text="user.biography"></p>
                                            </template>
                                            <template x-if="! user.guest && ! user.biography">
                                                <p class="mt-0.5 text-sm italic text-neutral-400">{{ __('Pas de biographie.') }}</p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" wire:click="toggleActivity"
                        class="w-8 {{ $topBtn }}"
                        title="{{ __('Activité') }}">
                    <x-phosphor-clock-counter-clockwise class="h-4 w-4"/>
                </button>

                @if ($view === 'board')
                    <button type="button"
                            x-data="{ allCollapsed: false }"
                            @click="allCollapsed = ! allCollapsed; $dispatch(allCollapsed ? 'collapse-all' : 'expand-all')"
                            class="w-8 {{ $topBtn }}"
                            :title="allCollapsed ? '{{ __('Tout déplier') }}' : '{{ __('Tout replier') }}'">
                        <x-phosphor-arrows-in-line-horizontal x-show="! allCollapsed" class="h-4 w-4"/>
                        <x-phosphor-arrows-out-line-horizontal x-show="allCollapsed" x-cloak class="h-4 w-4"/>
                    </button>

                    @if ($canContribute)
                    <button type="button" @click="selectMode = ! selectMode; if (! selectMode) selected = []"
                            class="w-8 {{ $topBtn }}"
                            :class="selectMode && '!border-indigo-400 !bg-indigo-50 !text-indigo-600 dark:!border-indigo-500/40 dark:!bg-indigo-500/15 dark:!text-indigo-300'"
                            title="{{ __('Sélectionner des cartes') }}">
                        <x-phosphor-check-square class="h-4 w-4"/>
                    </button>
                    @endif
                @endif

                {{-- Filters: icon trigger → dropdown on desktop, modal on mobile --}}
                <div x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false" class="relative">
                    <button type="button" @click="filtersOpen = ! filtersOpen" class="relative w-8 {{ $topBtn }}" title="{{ __('Filtres') }}">
                        <x-phosphor-funnel class="h-4 w-4"/>
                        @if ($this->activeFilterCount() > 0)
                            <span class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-semibold text-white">{{ $this->activeFilterCount() }}</span>
                        @endif
                    </button>

                    {{-- Mobile backdrop --}}
                    <div x-show="filtersOpen" x-cloak x-transition.opacity @click="filtersOpen = false"
                         class="fixed inset-0 z-40 bg-neutral-900/40 backdrop-blur-sm sm:hidden"></div>

                    {{-- Panel: centered modal on mobile, right-aligned dropdown on sm+ --}}
                    <div x-show="filtersOpen" x-cloak x-transition @click.outside="filtersOpen = false"
                         class="fixed inset-x-4 top-1/2 z-50 max-h-[85dvh] -translate-y-1/2 overflow-y-auto p-3 {{ $panelClasses }} sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-1 sm:max-h-none sm:w-72 sm:translate-y-0 sm:overflow-visible">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">{{ __('Filtres') }}</h3>
                            <button type="button" @click="filtersOpen = false" class="rounded p-1 text-neutral-400 hover:bg-neutral-100 sm:hidden dark:hover:bg-neutral-800"><x-phosphor-x class="h-4 w-4"/></button>
                        </div>
                        <div class="space-y-2">
                            <x-filter-dropdown icon="tag" :options="$optLabels" :selected="$filterLabels" :multiple="true" action="toggleLabel" :placeholder="__('Labels')" />
                            <x-filter-dropdown icon="user" :options="$optMembers" :selected="$filterMembers" :multiple="true" action="toggleMember" :placeholder="__('Membres')" />
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="toggleMember({{ auth()->id() }})"
                                        class="rounded-lg border px-3 py-1.5 text-center text-sm shadow-sm transition {{ in_array(auth()->id(), $filterMembers, true) ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' }}">
                                    {{ __('Moi') }}
                                </button>
                                <button type="button" wire:click="toggleUnassigned"
                                        class="rounded-lg border px-3 py-1.5 text-center text-sm shadow-sm transition {{ $filterUnassigned ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-300 bg-white text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' }}">
                                    {{ __('Sans membre') }}
                                </button>
                            </div>
                            <x-filter-dropdown field="filterDue" icon="clock" :options="$optDue" :current="$filterDue" :placeholder="__('Échéance : toutes')" />
                            @if ($this->hasActiveFilters())
                                <button type="button" wire:click="resetFilters"
                                        class="w-full rounded-lg border border-neutral-200 px-3 py-1.5 text-center text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:border-neutral-700 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                    {{ __('Réinitialiser') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- View switcher: dropdown --}}
                <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
                    <button type="button" @click="open = ! open" class="gap-1.5 px-2 {{ $topBtn }}" title="{{ __('Changer de vue') }}">
                        <x-dynamic-component :component="'phosphor-'.$viewOptions[$view]['icon']" class="h-4 w-4"/>
                        <span class="hidden text-sm md:inline">{{ $viewOptions[$view]['label'] }}</span>
                        <x-phosphor-caret-down class="h-3.5 w-3.5 opacity-60 transition-transform" ::class="open && 'rotate-180'"/>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 z-40 mt-1 w-44 p-1 {{ $panelClasses }}">
                        @foreach ($viewOptions as $viewKey => $viewOpt)
                            <button type="button" wire:click="setView('{{ $viewKey }}')" @click="open = false"
                                    class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $view === $viewKey ? 'font-medium text-indigo-600 dark:text-indigo-400' : 'text-neutral-700 dark:text-neutral-300' }}">
                                <x-dynamic-component :component="'phosphor-'.$viewOpt['icon']" class="h-4 w-4 shrink-0 opacity-70"/>
                                <span class="flex-1">{{ $viewOpt['label'] }}</span>
                                @if ($view === $viewKey)<x-phosphor-check class="h-4 w-4 shrink-0"/>@endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Saved views: icon trigger --}}
                <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
                    <button type="button" @click="open = ! open" class="relative w-8 {{ $topBtn }}" title="{{ __('Vues enregistrées') }}">
                        <x-phosphor-bookmarks-simple class="h-4 w-4"/>
                        @if ($views->isNotEmpty())
                            <span class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-neutral-500 px-1 text-[10px] font-semibold text-white dark:bg-neutral-600">{{ $views->count() }}</span>
                        @endif
                    </button>

                    <div x-show="open" x-cloak x-transition
                         class="absolute right-0 z-40 mt-1 w-72 max-w-[calc(100vw-2rem)] p-1 {{ $panelClasses }}">
                        @forelse ($views as $savedView)
                            @php $isCalendarView = ($savedView->filters['view'] ?? 'board') === 'calendar'; @endphp
                            <div wire:key="view-{{ $savedView->id }}" x-data="{ editing: false }" class="group/view flex items-center gap-1 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                <template x-if="!editing">
                                    <button type="button" wire:click="applyView({{ $savedView->id }})" @click="open = false"
                                            class="flex min-w-0 flex-1 items-center gap-1.5 px-2.5 py-1.5 text-left text-sm text-neutral-700 dark:text-neutral-300">
                                        @if ($isCalendarView)
                                            <x-phosphor-calendar-blank class="h-3.5 w-3.5 shrink-0 opacity-60"/>
                                        @else
                                            <x-phosphor-squares-four class="h-3.5 w-3.5 shrink-0 opacity-60"/>
                                        @endif
                                        <span class="truncate">{{ $savedView->name }}</span>
                                    </button>
                                </template>
                                <template x-if="editing">
                                    <form class="flex min-w-0 flex-1 items-center px-1.5 py-1" @click.stop
                                          x-on:submit.prevent="$wire.renameView({{ $savedView->id }}, $refs.renameInput.value); editing = false">
                                        <input x-ref="renameInput" type="text" value="{{ $savedView->name }}" maxlength="60"
                                               x-on:keydown.escape="editing = false" x-on:blur="editing = false"
                                               class="w-full rounded border border-neutral-300 bg-white px-1.5 py-0.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    </form>
                                </template>
                                <button type="button" x-show="!editing"
                                        x-on:click.stop="editing = true; $nextTick(() => { $refs.renameInput.focus(); $refs.renameInput.select(); })"
                                        class="shrink-0 rounded p-1 text-neutral-300 opacity-100 transition hover:text-indigo-500 sm:opacity-0 sm:group-hover/view:opacity-100" title="{{ __('Renommer') }}"><x-phosphor-pencil-simple class="h-3.5 w-3.5"/></button>
                                <button type="button" wire:click="deleteView({{ $savedView->id }})" x-show="!editing" class="mr-1 shrink-0 rounded p-1 text-neutral-300 opacity-100 transition hover:text-red-500 sm:opacity-0 sm:group-hover/view:opacity-100" title="{{ __('Supprimer') }}"><x-phosphor-x class="h-3.5 w-3.5"/></button>
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

                @can('update', $board)
                    <x-context-menu>
                        <x-slot:trigger>
                            <button type="button" @click="openAt($event.clientX, $event.clientY)"
                                    class="w-8 {{ $topBtn }}"
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
                                                 @click="window.location.href = '{{ route('boards.export', ['board' => $board, 'format' => 'csv']) }}'">{{ __('Exporter en CSV') }}</x-context-menu.item>
                            <x-context-menu.item icon="file-xls"
                                                 @click="window.location.href = '{{ route('boards.export', ['board' => $board, 'format' => 'xlsx']) }}'">{{ __('Exporter en XLSX') }}</x-context-menu.item>
                            <x-context-menu.item icon="download-simple"
                                                 @click="window.location.href = '{{ route('boards.export', ['board' => $board, 'format' => 'json']) }}'">{{ __('Exporter en JSON') }}</x-context-menu.item>
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


    @php
        $coverPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b'];
    @endphp

    @if ($view === 'board')
    {{-- Lists (columns) — the background is now full-bleed behind the whole board --}}
    <div
        @if ($canContribute) wire:sort="reorderLists" @endif
        wire:loading.class.delay="opacity-40"
        wire:target="search, filterLabels, filterMembers, toggleLabel, toggleMember, toggleUnassigned, filterDue, resetFilters, applyFilter, applyView"
        class="flex flex-1 snap-x snap-mandatory items-start gap-3 overflow-x-auto scroll-p-1 py-4 transition-opacity sm:snap-none sm:gap-4"
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
                class="flex max-h-full shrink-0 snap-start flex-col overflow-hidden rounded-xl {{ $boardBg ? 'border border-white/20 bg-white/50 shadow-lg backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/60' : 'bg-neutral-200/70 dark:bg-neutral-900' }}"
            >
                {{-- Collapsed strip --}}
                <div x-show="collapsed" x-cloak @click="collapsed = false" class="flex flex-1 cursor-pointer select-none flex-col items-center gap-2 py-2.5" title="{{ $list->name }}">
                    <button type="button" @click.stop="collapsed = false" class="shrink-0 rounded p-1 text-neutral-500 hover:bg-neutral-300 dark:hover:bg-neutral-800" title="{{ __('Déplier la liste') }}">
                        <x-phosphor-arrows-out-line-horizontal class="h-4 w-4"/>
                    </button>
                    @if ($list->cover_path)
                        <img src="{{ $list->coverUrl() }}" alt="" class="h-6 w-6 shrink-0 rounded object-cover">
                    @elseif ($list->cover_color)
                        <span class="h-6 w-1.5 shrink-0 rounded-full" style="background-color: {{ $list->cover_color }}"></span>
                    @endif
                    <span class="shrink-0 rounded-full bg-neutral-300/70 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400" x-text="wipLimit ? cardCount + '/' + wipLimit : cardCount">{{ $list->cards_count }}</span>
                    <span class="mt-1 min-h-0 flex-1 overflow-hidden text-sm font-semibold tracking-wide [writing-mode:vertical-rl]">{{ Str::limit($list->name, 40) }}</span>
                </div>

                {{-- Expanded content --}}
                <div x-show="! collapsed" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @if ($list->cover_path)
                    <img src="{{ $list->coverUrl() }}" alt="" class="h-16 w-full shrink-0 object-cover">
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
                    @if ($canContribute) x-init="window.initCardSortable($el, $wire)" @endif
                    data-list-id="{{ $list->id }}"
                    class="flex min-h-2 flex-col gap-2 overflow-y-auto px-2 @unless ($canContribute) pb-3 @endunless"
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
                            data-card
                            data-card-id="{{ $card->id }}"
                            class="group relative shrink-0 cursor-grab overflow-hidden rounded-lg border bg-white text-sm shadow-sm dark:bg-neutral-800"
                            :class="selected.includes({{ $card->id }}) ? 'border-indigo-500 dark:border-indigo-500' : 'border-neutral-200 dark:border-neutral-700'"
                            x-on:click.capture="selectMode && (toggleCard({{ $card->id }}), $event.stopPropagation(), $event.preventDefault())"
                        >
                            {{-- Selection overlay (visual only; the whole card is clickable in select mode). --}}
                            <div x-show="selectMode" x-cloak wire:sort:ignore
                                 class="pointer-events-none absolute inset-0 z-20 transition"
                                 :class="selected.includes({{ $card->id }}) ? 'bg-indigo-500/10' : ''">
                                <span class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-md border-2 shadow"
                                      :class="selected.includes({{ $card->id }}) ? 'border-indigo-500 bg-indigo-500 text-white' : 'border-neutral-400 bg-white dark:border-neutral-500 dark:bg-neutral-900'">
                                    <svg x-show="selected.includes({{ $card->id }})" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                </span>
                            </div>

                            <x-context-menu class="block">
                                <x-slot:trigger>
                                    @if ($card->cover_path)
                                        <img src="{{ $card->coverUrl() }}" alt="" draggable="false"
                                             wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })"
                                             class="h-24 w-full object-cover">
                                    @elseif ($card->cover_color)
                                        <div class="h-9 w-full"
                                             wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })"
                                             style="background-color: {{ $card->cover_color }}"></div>
                                    @endif

                                    {{-- Clicking anywhere on the card body opens it (drag suppresses the click). --}}
                                    <div class="relative p-2.5" wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })">
                                        @if ($card->labels->isNotEmpty())
                                            <div class="mb-1.5 flex flex-wrap gap-1">
                                                @foreach ($card->labels as $label)
                                                    <span class="h-1.5 w-8 rounded-full"
                                                          style="background-color: {{ $label->color }}"
                                                          title="{{ $label->name }}"></span>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Hover toolbar (top-right): one-click "mark done" + options --}}
                                        <div class="absolute right-1.5 top-1.5 z-10 flex items-center gap-1">
                                            <button type="button" wire:sort:ignore
                                                    @click.stop="openAt($event.clientX, $event.clientY)"
                                                    class="flex h-5 w-5 items-center justify-center rounded text-neutral-400 opacity-0 transition hover:bg-neutral-200 hover:text-neutral-700 group-hover:opacity-100 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
                                                    title="{{ __('Options de la carte (clic droit aussi)') }}">
                                                <x-phosphor-dots-three class="h-4 w-4"/>
                                            </button>
                                            @if ($canContribute)
                                                <button type="button" wire:sort:ignore
                                                        wire:click.stop="toggleCardComplete({{ $card->id }})"
                                                        title="{{ $card->completed_at ? __('Marquer non terminée') : __('Marquer terminée') }}"
                                                        aria-label="{{ $card->completed_at ? __('Marquer non terminée') : __('Marquer terminée') }}"
                                                        class="flex h-5 w-5 items-center justify-center rounded-full border shadow-sm transition {{ $card->completed_at ? 'border-green-500 bg-green-500 text-white' : 'border-neutral-300 bg-white text-neutral-300 opacity-0 hover:border-green-500 hover:text-green-500 group-hover:opacity-100 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-500' }}">
                                                    <x-phosphor-check class="h-3 w-3"/>
                                                </button>
                                            @endif
                                        </div>

                                        <span class="block break-words text-left font-medium {{ $card->completed_at ? 'pr-8 text-neutral-500 line-through decoration-neutral-400' : '' }}">{{ $card->title }}</span>

                                        {{-- Badges --}}
                                        @if ($card->due_at || $itemsTotal > 0 || $card->attachments_count > 0)
                                            <div
                                                class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
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
                                                $cfShown = $customFields->filter(fn ($f) => $f->appliesToCard($card) && filled(optional($cfValues->get($f->id))->value));
                                            @endphp
                                            @if ($cfShown->isNotEmpty())
                                                <div class="mt-2 flex flex-wrap gap-1">
                                                    @foreach ($cfShown as $field)
                                                        @php $val = $cfValues->get($field->id)->value; @endphp
                                                        <span class="inline-flex items-center gap-1 rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] text-neutral-600 dark:bg-neutral-700/50 dark:text-neutral-300">
                                                            <span class="font-medium">{{ $field->name }}:</span>
                                                            @switch($field->type)
                                                                @case(\App\Enums\CustomFieldType::Checkbox)
                                                                    <x-phosphor-check class="h-3 w-3 text-green-600 dark:text-green-400"/>
                                                                    @break
                                                                @case(\App\Enums\CustomFieldType::Date)
                                                                    {{ \Illuminate\Support\Carbon::parse($val)->translatedFormat('d M Y') }}
                                                                    @break
                                                                @case(\App\Enums\CustomFieldType::MultiSelect)
                                                                    {{ Str::limit(implode(', ', (array) $field->decode($val)), 30) }}
                                                                    @break
                                                                @case(\App\Enums\CustomFieldType::Member)
                                                                    {{ $members->firstWhere('id', (int) $val)?->name ?? '—' }}
                                                                    @break
                                                                @case(\App\Enums\CustomFieldType::Money)
                                                                    {{ rtrim(rtrim(number_format((float) $val, 2, ',', ' '), '0'), ',') }} {{ $field->currency() }}
                                                                    @break
                                                                @case(\App\Enums\CustomFieldType::Rating)
                                                                    <span class="inline-flex items-center gap-0.5"><x-phosphor-star-fill class="h-3 w-3 text-amber-400"/>{{ (int) $val }}</span>
                                                                    @break
                                                                @case(\App\Enums\CustomFieldType::Progress)
                                                                    <span class="inline-flex items-center gap-1"><span class="h-1 w-8 overflow-hidden rounded-full bg-neutral-300 dark:bg-neutral-600"><span class="block h-full rounded-full bg-indigo-500" style="width: {{ (int) $val }}%"></span></span>{{ (int) $val }}%</span>
                                                                    @break
                                                                @case(\App\Enums\CustomFieldType::Url)
                                                                    <span class="inline-flex items-center gap-0.5"><x-phosphor-link class="h-3 w-3"/>{{ Str::limit((string) parse_url($val, PHP_URL_HOST) ?: $val, 24) }}</span>
                                                                    @break
                                                                @default
                                                                    {{ Str::limit($val, 30) }}
                                                            @endswitch
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

                {{-- Mirrored cards: the same underlying cards shown in this list --}}
                @if ($cardsReady && $list->mirrors->isNotEmpty())
                    <div class="space-y-2 px-2 pt-1">
                        @foreach ($list->mirrors as $mirror)
                            @include('livewire.boards.partials.mirror-card')
                        @endforeach
                    </div>
                @endif

                {{-- Add card --}}
                @if ($canContribute)
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
                @endif
                @else
                    {{-- Plugin-sourced list: rendered by a lazy child (skeleton until loaded). --}}
                    <livewire:boards.plugin-list :list="$list" wire:key="plugin-list-{{ $list->id }}"/>
                @endif
                </div>
            </div>
        @endforeach

        {{-- Add list --}}
        @if ($canContribute)
        <form wire:submit="addList" class="w-full shrink-0 snap-start sm:w-72">
            <input
                type="text"
                wire:model="newListName"
                placeholder="{{ __('+ Ajouter une liste') }}"
                class="w-full rounded-xl border border-dashed border-neutral-300 bg-white/50 px-3 py-2 text-sm  placeholder-neutral-500 dark:placeholder-neutral-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900/50 {{ $boardBg ? 'backdrop-blur-md' : '' }}"
            >
            @error('newListName') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </form>
        @endif
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
    @elseif ($view === 'calendar')
        @include('livewire.boards.partials.calendar')
    @elseif ($view === 'timeline')
        @include('livewire.boards.partials.timeline')
    @elseif ($view === 'table')
        @include('livewire.boards.partials.table')
    @else
        @include('livewire.boards.partials.dashboard')
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
                            <span x-text="copied ? '{{ __('Copié !') }}' : '{{ __('Copier') }}'"></span>
                        </button>
                    </div>
                    <a href="{{ $shareUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        <x-phosphor-arrow-square-out class="h-3.5 w-3.5"/> {{ __('Ouvrir dans un nouvel onglet') }}
                    </a>
                @else
                    <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500 dark:bg-neutral-800/50 dark:text-neutral-400">{{ __("Activez le partage pour générer un lien. Le désactiver invalide immédiatement l'ancien lien.") }}</p>
                @endif

                @if (config('board.ical_feeds'))
                    <div class="border-t border-neutral-200 pt-4 dark:border-neutral-800">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium">{{ __('Flux calendrier (iCal)') }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Abonnez-vous depuis Google Agenda, Apple Calendar ou Outlook pour voir les cartes datées de ce tableau.') }}</p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-label="{{ __('Activer le flux calendrier') }}"
                                aria-checked="{{ $board->hasIcalFeed() ? 'true' : 'false' }}"
                                wire:click="toggleIcalFeed"
                                class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ $board->hasIcalFeed() ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700' }}"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $board->hasIcalFeed() ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                            </button>
                        </div>

                        @if ($board->hasIcalFeed())
                            @php $icalUrl = $board->icalUrl(); @endphp
                            <div class="mt-3 flex items-center gap-2" x-data="{ copied: false }">
                                <input type="text" readonly value="{{ $icalUrl }}" @focus="$el.select()"
                                       class="flex-1 rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                <button
                                    type="button"
                                    @click="navigator.clipboard?.writeText('{{ $icalUrl }}'); window.toast('{{ __('Lien copié') }}', { type: 'success' }); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500"
                                >
                                    <x-phosphor-copy class="h-4 w-4"/>
                                    <span x-text="copied ? '{{ __('Copié !') }}' : '{{ __('Copier') }}'"></span>
                                </button>
                            </div>
                            <div class="mt-2 flex items-center gap-4">
                                <a href="{{ preg_replace('#^https?://#', 'webcal://', $icalUrl) }}"
                                   class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                    <x-phosphor-calendar-plus class="h-3.5 w-3.5"/> {{ __("S'abonner") }}
                                </a>
                                <button type="button" wire:click="regenerateIcalFeed"
                                        wire:confirm="{{ __('Régénérer le lien invalidera immédiatement les abonnements existants. Continuer ?') }}"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-700 hover:underline dark:text-neutral-400 dark:hover:text-neutral-200">
                                    <x-phosphor-arrows-clockwise class="h-3.5 w-3.5"/> {{ __('Régénérer le lien') }}
                                </button>
                            </div>
                        @else
                            <p class="mt-3 rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500 dark:bg-neutral-800/50 dark:text-neutral-400">{{ __('Activez le flux pour générer un lien iCal en lecture seule.') }}</p>
                        @endif
                    </div>
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
                        <img src="{{ $board->backgroundImageUrl() }}" alt=""
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
                                        @foreach ($boardRoles as $wsRole)
                                            @continue($wsRole->key === 'owner')
                                            <option value="{{ $wsRole->key }}" @selected($member->pivot->role === $wsRole->key)>{{ $wsRole->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="removeBoardMember({{ $member->id }})" class="text-xs text-neutral-400 hover:text-red-500">{{ __('Retirer') }}</button>
                                @else
                                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ optional($boardRoles->firstWhere('key', $member->pivot->role))->name ?? $member->pivot->role }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($canManageMembers)
                    <a href="{{ route('workspaces.roles', $board->workspace) }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        <x-phosphor-shield-check class="h-3.5 w-3.5"/> {{ __('Gérer les rôles et permissions') }}
                    </a>
                @endif

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
                @if ($allCustomFields->isEmpty())
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun champ personnalisé. Ajoutez-en un ci-dessous pour enrichir les cartes.') }}</p>
                @else
                    <ul class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($allCustomFields as $field)
                            <li wire:key="cf-{{ $field->id }}" class="flex items-center justify-between gap-3 py-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <x-dynamic-component :component="'phosphor-'.$field->type->icon()" class="h-4 w-4 shrink-0 text-neutral-400"/>
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-1.5 truncate text-sm font-medium">
                                            {{ $field->name }}
                                            @if ($field->isPluginManaged())
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"><x-phosphor-puzzle-piece class="h-3 w-3"/> {{ $field->plugin_key }}</span>
                                            @endif
                                            @if ($field->card_id)
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300" title="{{ $field->card?->title }}"><x-phosphor-cards class="h-3 w-3"/> {{ __('Carte') }} : {{ Str::limit($field->card?->title ?? '—', 18) }}</span>
                                            @elseif ($field->board_list_id)
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"><x-phosphor-list-dashes class="h-3 w-3"/> {{ __('Liste') }} : {{ Str::limit($field->list?->name ?? '—', 18) }}</span>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ __($field->type->label()) }}@if ($field->type->hasOptions() && $field->optionList()) · {{ implode(', ', $field->optionList()) }}@endif</p>
                                    </div>
                                </div>
                                @unless ($field->isPluginManaged())
                                <button type="button"
                                        @click="$store.confirm.open({ title: '{{ __('Supprimer le champ') }}', message: '{{ __('Supprimer ce champ et toutes ses valeurs sur les cartes ?') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteCustomField({{ $field->id }}))"
                                        class="shrink-0 rounded-full p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        title="{{ __('Supprimer') }}"><x-phosphor-trash class="h-4 w-4"/></button>
                                @endunless
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
                            @php $typeOptions = collect($fieldTypes)->map(fn ($ft) => ['value' => $ft->value, 'label' => $ft->label()])->all(); @endphp
                            <x-select
                                :options="$typeOptions"
                                :value="$newFieldType"
                                @select-change="$wire.set('newFieldType', $event.detail)"
                            />
                        </div>
                    </div>

                    @if (in_array($newFieldType, ['select', 'multiselect'], true))
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Options (séparées par des virgules)') }}</label>
                            <input type="text" wire:model="newFieldOptions" placeholder="{{ __('Basse, Moyenne, Haute') }}"
                                   class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                            @error('newFieldOptions') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($newFieldType === 'money')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Devise') }}</label>
                            <input type="text" wire:model="newFieldCurrency" maxlength="5" placeholder="€"
                                   class="w-24 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Emplacement sur la carte') }}</label>
                        @php $placementOptions = [
                            ['value' => 'sidebar', 'label' => __('Colonne Actions (latérale)')],
                            ['value' => 'content', 'label' => __('Contenu (sous la description)')],
                        ]; @endphp
                        <x-select
                            :options="$placementOptions"
                            :value="$newFieldPlacement"
                            @select-change="$wire.set('newFieldPlacement', $event.detail)"
                        />
                    </div>

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
                        <img src="{{ $coverList->coverUrl() }}" alt="" class="h-32 w-full object-cover">
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
                        <x-user-avatar :user="$activity->user" class="mt-0.5" />
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

            {{-- Retention footer: outside the scroll area so it stays visible; admins only --}}
            @can('update', $board)
                <div class="border-t border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/60">
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Purge automatique des activités anciennes') }}</label>
                    @php $retentionOptions = [
                        ['value' => '', 'label' => __('Tout conserver')],
                        ['value' => '30', 'label' => __('30 jours')],
                        ['value' => '90', 'label' => __('90 jours')],
                        ['value' => '180', 'label' => __('6 mois')],
                        ['value' => '365', 'label' => __('1 an')],
                    ]; @endphp
                    <x-select direction="up" :options="$retentionOptions" :value="$board->activity_retention_days"
                              @select-change="$wire.saveActivityRetention($event.detail)" />
                    <p class="mt-1 text-[11px] text-neutral-400">{{ __('Les activités plus anciennes sont supprimées chaque jour.') }}</p>
                </div>
            @endcan
        </aside>
    </div>
    @endif

    <livewire:cards.card-detail :board="$board" wire:key="card-detail-{{ $board->id }}"/>
</div>
