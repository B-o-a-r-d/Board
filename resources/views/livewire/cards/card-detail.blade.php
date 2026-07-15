<div
    x-data="{
        opening: false,
        handleCardFocus(raw) {
            const detail = raw?.params?.[0] ?? raw ?? {};
            if (detail.comment) {
                this.flashElement('comment-' + detail.comment);
            } else if (detail.section === 'attachments') {
                window.dispatchEvent(new CustomEvent('card-open-attachments'));
            }
        },
        flashElement(id) {
            setTimeout(() => {
                const el = document.getElementById(id);
                if (! el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('cf-flash');
                setTimeout(() => el.classList.remove('cf-flash'), 1800);
            }, 200);
        }
    }"
    @card-focus.window="handleCardFocus($event.detail)"
    {{-- Instant open: show the skeleton the moment a card is clicked, before the
         openCard round-trip renders the real modal (which then hides it). --}}
    @open-card.window="opening = true"
    @card-modal-closed.window="opening = false"
>
    <div x-show="opening && ! $wire.showModal" x-cloak
         class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto p-4 sm:p-8">
        <div class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-6xl rounded-2xl border border-neutral-200 bg-white p-6 shadow-xl dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex flex-col gap-6 sm:flex-row">
                <div class="flex-1 space-y-4">
                    <div class="h-6 w-2/3 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                    <div class="h-4 w-1/3 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                    <div class="h-24 w-full animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                    <div class="h-4 w-1/2 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                    <div class="h-16 w-full animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                </div>
                <div class="hidden w-56 space-y-3 sm:block">
                    @foreach (range(1, 4) as $s)
                        <div class="h-8 w-full animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if ($showModal && $card)
        @php
            $isWatching = $card->watchers->contains(fn ($w) => $w->id === auth()->id());
            $isMemberOfCard = $card->members->contains(fn ($m) => $m->id === auth()->id());
            $dueOverdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast();
            $dueSoon = $card->due_at && ! $card->completed_at && ! $dueOverdue && $card->due_at->lte(now()->addDay());
            $cfValues = $card->customFieldValues->keyBy('custom_field_id');
            $sidebarFields = $customFields->where('placement', \App\Models\CustomField::PLACEMENT_SIDEBAR);
            $contentFields = $customFields->where('placement', \App\Models\CustomField::PLACEMENT_CONTENT);
            $hasLinks = $cardLinks['blocks']->isNotEmpty() || $cardLinks['blockedBy']->isNotEmpty() || $cardLinks['relates']->isNotEmpty();
            $tbBtn = 'flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-700';
        @endphp
        <x-modal max-width="6xl" on-close="$wire.close()" wire:key="card-modal-{{ $card->id }}">
            <div x-data="{
                    panel: 'comments',
                    openPicker: null,
                    checklistOpen: false,
                    {{-- Empty sections stay hidden: when links/mirrors exist the x-show
                         below is a literal `true`; these flags only cover the "just
                         opened, still empty" window and auto-reset after 15s. --}}
                    showRelations: false,
                    showMirror: false,
                    openTransient(flag) {
                        this[flag] = true;
                        setTimeout(() => { this[flag] = false }, 15000);
                    },
                 }">
                {{-- Header strip: cover (image / color) or plain surface, list chip + window actions --}}
                <div class="relative">
                    @if ($card->cover_path)
                        <img src="{{ $card->coverUrl() }}" alt="" class="h-40 w-full rounded-t-2xl object-cover">
                    @elseif ($card->cover_color)
                        <div class="h-24 w-full rounded-t-2xl" style="background-color: {{ $card->cover_color }}"></div>
                    @else
                        <div class="h-14 w-full rounded-t-2xl bg-neutral-100 dark:bg-neutral-800/70"></div>
                    @endif

                    {{-- List selector chip --}}
                    <div class="absolute left-3 top-3" x-data="{ moveOpen: false }" @open-card-move.window="moveOpen = true">
                        @if ($canContribute)
                            <button type="button" @click="moveOpen = ! moveOpen" :aria-expanded="moveOpen"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-white/85 px-2.5 text-sm font-medium text-neutral-700 shadow backdrop-blur transition hover:bg-white dark:bg-neutral-900/75 dark:text-neutral-200 dark:hover:bg-neutral-900">
                                {{ $card->list?->name ?? '—' }}
                                <x-phosphor-caret-down class="h-3.5 w-3.5 opacity-60 transition-transform" ::class="moveOpen && 'rotate-180'"/>
                            </button>
                            <div x-show="moveOpen" x-cloak x-transition @click.outside="moveOpen = false" @keydown.escape.window="moveOpen = false"
                                 class="absolute left-0 z-50 mt-1 max-h-64 w-56 overflow-y-auto rounded-xl border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                @foreach ($boardLists as $targetList)
                                    <button type="button" wire:click="moveToList({{ $targetList->id }})" @click="moveOpen = false"
                                            class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm transition hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $targetList->id === $card->board_list_id ? 'font-medium text-indigo-600 dark:text-indigo-400' : 'text-neutral-700 dark:text-neutral-300' }}">
                                        <span class="truncate">{{ $targetList->name }}</span>
                                        @if ($targetList->id === $card->board_list_id)<x-phosphor-check class="h-4 w-4 shrink-0"/>@endif
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <span class="inline-flex h-8 items-center rounded-lg bg-white/85 px-2.5 text-sm font-medium text-neutral-700 shadow backdrop-blur dark:bg-neutral-900/75 dark:text-neutral-200">{{ $card->list?->name ?? '—' }}</span>
                        @endif
                    </div>

                    {{-- Window actions: cover, watch, menu, close --}}
                    @php $stripBtn = 'flex h-8 w-8 items-center justify-center rounded-lg shadow backdrop-blur transition'; @endphp
                    <div class="absolute right-3 top-3 flex items-center gap-1.5">
                        @if ($canContribute)
                        <div class="relative" x-data="{ coverOpen: false }">
                            <button type="button" @click="coverOpen = ! coverOpen" title="{{ __('Couverture') }}"
                                    class="{{ $stripBtn }} bg-white/85 text-neutral-600 hover:bg-white dark:bg-neutral-900/75 dark:text-neutral-300 dark:hover:bg-neutral-900">
                                <x-phosphor-image class="h-4 w-4"/>
                            </button>
                            <div x-show="coverOpen" x-cloak x-transition @click.outside="coverOpen = false"
                                 class="absolute right-0 z-50 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-neutral-200 bg-white p-3 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Couverture') }}</p>
                                @if ($card->cover_path)
                                    <div class="relative mb-2 overflow-hidden rounded-lg">
                                        <img src="{{ $card->coverUrl() }}" alt="" class="h-24 w-full object-cover">
                                        <button type="button" wire:click="clearCover" class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70" title="{{ __('Retirer la couverture') }}"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                    </div>
                                @endif
                                @php $coverPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b']; @endphp
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($coverPalette as $swatch)
                                        <button type="button" wire:click="setCoverColor('{{ $swatch }}')" class="h-6 w-6 rounded-md ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-900 {{ $card->cover_color === $swatch ? 'ring-2 ring-indigo-500' : '' }}" style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
                                    @endforeach
                                    @if ($card->cover_color && ! $card->cover_path)
                                        <button type="button" wire:click="clearCover" class="flex h-6 items-center gap-1 rounded-md border border-neutral-300 px-2 text-xs text-neutral-500 hover:text-neutral-700 dark:border-neutral-700 dark:hover:text-neutral-200" title="{{ __('Retirer la couverture') }}"><x-phosphor-x class="h-3 w-3" /> {{ __('Retirer') }}</button>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    <x-dropzone model="coverUpload" action="uploadCover" accept="image/*" hint="{{ __('Image de couverture · 10 Mo max') }}" />
                                </div>
                            </div>
                        </div>
                        @endif

                        <button type="button" wire:click="toggleWatch" title="{{ $isWatching ? __('Suivi') : __('Suivre') }}"
                                class="{{ $stripBtn }} relative {{ $isWatching ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-white/85 text-neutral-600 hover:bg-white dark:bg-neutral-900/75 dark:text-neutral-300 dark:hover:bg-neutral-900' }}">
                            <x-phosphor-eye class="h-4 w-4"/>
                            @if ($card->watchers->isNotEmpty())
                                <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-neutral-700 px-1 text-[10px] font-semibold text-white dark:bg-neutral-200 dark:text-neutral-900">{{ $card->watchers->count() }}</span>
                            @endif
                        </button>

                        {{-- Card actions menu --}}
                        <div class="relative" x-data="{ menuOpen: false }">
                            <button type="button" @click="menuOpen = ! menuOpen" :aria-expanded="menuOpen" title="{{ __('Actions de la carte') }}"
                                    class="{{ $stripBtn }} bg-white/85 text-neutral-600 hover:bg-white dark:bg-neutral-900/75 dark:text-neutral-300 dark:hover:bg-neutral-900">
                                <x-phosphor-dots-three class="h-4 w-4"/>
                            </button>
                            <div x-show="menuOpen" x-cloak x-transition @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false"
                                 class="absolute right-0 z-50 mt-1 w-56 rounded-xl border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                @php $menuItem = 'flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800'; @endphp
                                @if ($canContribute)
                                    <button type="button" wire:click="toggleMember({{ auth()->id() }})" @click="menuOpen = false" class="{{ $menuItem }}">
                                        <x-dynamic-component :component="$isMemberOfCard ? 'phosphor-user-minus' : 'phosphor-user-plus'" class="h-4 w-4 opacity-70"/>
                                        {{ $isMemberOfCard ? __('Quitter') : __('Rejoindre') }}
                                    </button>
                                    <button type="button" @click="menuOpen = false; window.dispatchEvent(new CustomEvent('open-card-move'))" class="{{ $menuItem }}">
                                        <x-phosphor-arrow-right class="h-4 w-4 opacity-70"/> {{ __('Déplacer') }}
                                    </button>
                                    <button type="button" wire:click="duplicate" @click="menuOpen = false" class="{{ $menuItem }}">
                                        <x-phosphor-copy class="h-4 w-4 opacity-70"/> {{ __('Copier') }}
                                    </button>
                                    @if ($mirrorTargets->isNotEmpty() || $cardMirrors->isNotEmpty())
                                        <button type="button" @click="menuOpen = false; openTransient('showMirror'); flashElement('card-mirrors')" class="{{ $menuItem }}">
                                            <x-phosphor-cards class="h-4 w-4 opacity-70"/> {{ __('Miroir') }}
                                        </button>
                                    @endif
                                    @can('admin')
                                        <button type="button" wire:click="saveAsTemplate" @click="menuOpen = false" class="{{ $menuItem }}">
                                            <x-phosphor-stack class="h-4 w-4 opacity-70"/> {{ __('Créer un modèle') }}
                                        </button>
                                    @endcan
                                @endif
                                <button type="button" wire:click="toggleWatch" class="{{ $menuItem }}">
                                    <x-phosphor-eye class="h-4 w-4 opacity-70"/> {{ __('Suivre') }}
                                    @if ($isWatching)<span class="ml-auto flex h-4 w-4 items-center justify-center rounded bg-green-500 text-white"><x-phosphor-check class="h-3 w-3"/></span>@endif
                                </button>
                                <div class="mx-1 my-1 h-px bg-neutral-100 dark:bg-neutral-800"></div>
                                <button type="button" @click="menuOpen = false; navigator.clipboard?.writeText('{{ route('boards.show', ['board' => $board, 'card' => $card->public_id]) }}'); window.toast('{{ __('Lien copié') }}', { type: 'success' })" class="{{ $menuItem }}">
                                    <x-phosphor-share-network class="h-4 w-4 opacity-70"/> {{ __('Partager') }}
                                </button>
                                @if ($canContribute)
                                    <button type="button" wire:click="archive" @click="menuOpen = false" class="{{ $menuItem }}">
                                        <x-phosphor-archive class="h-4 w-4 opacity-70"/> {{ __('Archiver') }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        <button type="button" wire:click="close" title="{{ __('Fermer') }}"
                                class="{{ $stripBtn }} bg-white/85 text-neutral-600 hover:bg-white dark:bg-neutral-900/75 dark:text-neutral-300 dark:hover:bg-neutral-900">
                            <x-phosphor-x class="h-4 w-4"/>
                        </button>
                    </div>
                </div>

                <div class="grid lg:grid-cols-12">
                    {{-- Content column --}}
                    <div class="order-1 space-y-6 p-4 sm:p-6 lg:col-span-7">
                        {{-- Title row: completion circle + title --}}
                        <div>
                            <div class="flex items-center gap-3">
                                @if ($canContribute)
                                    <button type="button" wire:click="toggleComplete"
                                            title="{{ $card->completed_at ? __('Marquer non terminée') : __('Marquer terminée') }}"
                                            aria-label="{{ $card->completed_at ? __('Marquer non terminée') : __('Marquer terminée') }}"
                                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition {{ $card->completed_at ? 'border-green-600 bg-green-600 text-white' : 'border-neutral-400 text-transparent hover:border-green-500 hover:text-green-500' }}">
                                        <x-phosphor-check class="h-3 w-3"/>
                                    </button>
                                    {{-- Implicit save: title persists on blur / Enter, no save button --}}
                                    <input
                                        type="text"
                                        wire:model.blur="title"
                                        @keydown.enter.prevent="$event.target.blur()"
                                        class="min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1 text-xl font-semibold hover:bg-neutral-100 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:hover:bg-neutral-800 dark:focus:bg-neutral-800 {{ $card->completed_at ? 'text-neutral-500 line-through decoration-neutral-400' : '' }}"
                                    >
                                @else
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 {{ $card->completed_at ? 'border-green-600 bg-green-600 text-white' : 'border-neutral-400 text-transparent' }}"><x-phosphor-check class="h-3 w-3"/></span>
                                    <h2 class="min-w-0 flex-1 px-2 py-1 text-xl font-semibold">{{ $card->title }}</h2>
                                @endif
                            </div>
                            @error('title') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                            {{-- Action bar: "Ajouter" popover + contextual shortcuts --}}
                            @if ($canContribute)
                            @php $actionBtn = 'inline-flex h-8 items-center gap-1.5 rounded-md border border-neutral-300 px-2.5 text-sm text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-700/50 dark:hover:text-white'; @endphp
                            <div class="relative mt-3 flex flex-wrap items-center gap-2"
                                 x-data="{ addMenuOpen: false, addPanel: 'menu' }"
                                 x-init="$watch('addMenuOpen', v => { if (! v) addPanel = 'menu' })"
                                 @card-field-added.window="addMenuOpen = false">
                                <button type="button"
                                        @click="addMenuOpen = ! addMenuOpen"
                                        :aria-expanded="addMenuOpen"
                                        x-ref="addButton"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-md bg-neutral-800 px-2.5 text-sm font-medium text-white transition hover:bg-neutral-700 dark:bg-neutral-200 dark:text-neutral-900 dark:hover:bg-neutral-100">
                                    <x-phosphor-plus class="h-4 w-4"/> {{ __('Ajouter') }}
                                </button>
                                @unless ($card->start_at || $card->due_at)
                                    <button type="button" class="{{ $actionBtn }}" @click="openPicker = openPicker === 'dates' ? null : 'dates'">
                                        <x-phosphor-clock class="h-4 w-4"/> <span class="hidden sm:inline">{{ __('Dates') }}</span>
                                    </button>
                                @endunless
                                <button type="button" class="{{ $actionBtn }}" @click="checklistOpen = ! checklistOpen">
                                    <x-phosphor-check-square class="h-4 w-4"/> <span class="hidden sm:inline">{{ __('Checklist') }}</span>
                                </button>
                                <button type="button" class="{{ $actionBtn }}" @click="window.dispatchEvent(new CustomEvent('card-open-attachments'))">
                                    <x-phosphor-paperclip class="h-4 w-4"/> <span class="hidden sm:inline">{{ __('Pièce jointe') }}</span>
                                </button>

                                {{-- New checklist popover --}}
                                <div x-show="checklistOpen" x-cloak x-transition @click.outside="checklistOpen = false" @keydown.escape.window="checklistOpen = false"
                                     class="absolute left-0 top-full z-50 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-lg border border-neutral-200 bg-white p-3 shadow-[0_12px_32px_rgba(0,0,0,0.25)] dark:border-neutral-700 dark:bg-neutral-800 dark:shadow-[0_12px_32px_rgba(0,0,0,0.55)]">
                                    <p class="mb-2 text-center text-sm font-medium text-neutral-600 dark:text-neutral-300">{{ __('Checklist') }}</p>
                                    <form wire:submit="addChecklist" @submit="checklistOpen = false" class="flex gap-2">
                                        <input type="text" id="card-new-checklist" wire:model="newChecklistTitle" placeholder="{{ __('Nouvelle checklist') }}" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                        <button type="submit" class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Ajouter') }}</button>
                                    </form>
                                </div>

                                {{-- "Add to card" popover --}}
                                <div x-show="addMenuOpen" x-cloak
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
                                     @click.outside="addMenuOpen = false"
                                     @keydown.escape.window="if (addMenuOpen) { addMenuOpen = false; $refs.addButton.focus() }"
                                     class="absolute left-0 top-full z-50 mt-2 w-[306px] max-w-[calc(100vw-2rem)] rounded-lg border border-neutral-200 bg-white p-3 shadow-[0_12px_32px_rgba(0,0,0,0.25)] dark:border-neutral-700 dark:bg-neutral-800 dark:shadow-[0_12px_32px_rgba(0,0,0,0.55)]">
                                    {{-- Header --}}
                                    <div class="relative mb-2 flex h-8 items-center justify-center">
                                        <button type="button" x-show="addPanel !== 'menu'" @click="addPanel = 'menu'"
                                                class="absolute left-0 top-0 flex h-8 w-8 items-center justify-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-white"
                                                aria-label="{{ __('Retour') }}">
                                            <x-phosphor-caret-left class="h-4 w-4"/>
                                        </button>
                                        <p class="text-sm font-medium text-neutral-600 dark:text-neutral-300" x-text="addPanel === 'menu' ? @js(__('Ajouter à la carte')) : @js(__('Nouveau champ personnalisé'))"></p>
                                        <button type="button" @click="addMenuOpen = false; $refs.addButton.focus()"
                                                class="absolute right-0 top-0 flex h-8 w-8 items-center justify-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-white"
                                                aria-label="{{ __('Fermer') }}">
                                            <x-phosphor-x class="h-4 w-4"/>
                                        </button>
                                    </div>

                                    {{-- Panel: menu entries --}}
                                    <div x-show="addPanel === 'menu'" class="space-y-1">
                                        @php $menuEntry = 'group flex w-full items-start gap-2 rounded-md px-1 py-2 text-left transition hover:bg-neutral-100 dark:hover:bg-neutral-700/50'; @endphp
                                        @php $menuIcon = 'flex h-10 w-10 shrink-0 items-center justify-center rounded border border-neutral-300 text-neutral-500 group-hover:border-neutral-400 group-hover:text-neutral-800 dark:border-neutral-600 dark:text-neutral-300 dark:group-hover:border-neutral-500 dark:group-hover:text-white'; @endphp
                                        <button type="button" class="{{ $menuEntry }}" @click="addMenuOpen = false; openPicker = 'labels'">
                                            <span class="{{ $menuIcon }}"><x-phosphor-tag class="h-5 w-5"/></span>
                                            <span class="min-w-0 pt-0.5">
                                                <span class="block text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ __('Labels') }}</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ __('Organisez, répertoriez et priorisez') }}</span>
                                            </span>
                                        </button>
                                        <button type="button" class="{{ $menuEntry }}" @click="addMenuOpen = false; openPicker = 'dates'">
                                            <span class="{{ $menuIcon }}"><x-phosphor-clock class="h-5 w-5"/></span>
                                            <span class="min-w-0 pt-0.5">
                                                <span class="block text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ __('Dates') }}</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ __('Début, échéance et rappels') }}</span>
                                            </span>
                                        </button>
                                        <button type="button" class="{{ $menuEntry }}" @click="addMenuOpen = false; checklistOpen = true">
                                            <span class="{{ $menuIcon }}"><x-phosphor-check-square class="h-5 w-5"/></span>
                                            <span class="min-w-0 pt-0.5">
                                                <span class="block text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ __('Checklist') }}</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ __('Ajouter des sous-tâches') }}</span>
                                            </span>
                                        </button>
                                        <button type="button" class="{{ $menuEntry }}" @click="addMenuOpen = false; openPicker = 'members'">
                                            <span class="{{ $menuIcon }}"><x-phosphor-user-plus class="h-5 w-5"/></span>
                                            <span class="min-w-0 pt-0.5">
                                                <span class="block text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ __('Membres') }}</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ __('Attribuer des membres') }}</span>
                                            </span>
                                        </button>
                                        <button type="button" class="{{ $menuEntry }}" @click="addMenuOpen = false; window.dispatchEvent(new CustomEvent('card-open-attachments'))">
                                            <span class="{{ $menuIcon }}"><x-phosphor-paperclip class="h-5 w-5"/></span>
                                            <span class="min-w-0 pt-0.5">
                                                <span class="block text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ __('Pièce jointe') }}</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ __('Ajouter des fichiers ou des liens') }}</span>
                                            </span>
                                        </button>
                                        <button type="button" class="{{ $menuEntry }}" @click="addMenuOpen = false; openTransient('showRelations'); flashElement('card-relations')">
                                            <span class="{{ $menuIcon }}"><x-phosphor-git-branch class="h-5 w-5"/></span>
                                            <span class="min-w-0 pt-0.5">
                                                <span class="block text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ __('Relations') }}</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ __('Lier des cartes entre elles') }}</span>
                                            </span>
                                        </button>
                                        <button type="button" class="{{ $menuEntry }}" @click="addPanel = 'field'">
                                            <span class="{{ $menuIcon }}"><x-phosphor-sliders-horizontal class="h-5 w-5"/></span>
                                            <span class="min-w-0 pt-0.5">
                                                <span class="block text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ __('Champs personnalisés') }}</span>
                                                <span class="mt-0.5 block text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ __('Créer vos propres champs') }}</span>
                                            </span>
                                        </button>
                                    </div>

                                    {{-- Panel: create a custom field (card / list / board scope) --}}
                                    <div x-show="addPanel === 'field'" x-cloak class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Nom du champ') }}</label>
                                            <input type="text" wire:model="newCfName" placeholder="{{ __('Priorité, Estimation…') }}"
                                                   class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                            @error('newCfName') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Type') }}</label>
                                            @php $cfTypeOptions = collect(\App\Enums\CustomFieldType::cases())->map(fn ($ft) => ['value' => $ft->value, 'label' => $ft->label()])->all(); @endphp
                                            <x-select :options="$cfTypeOptions" :value="$newCfType" @select-change="$wire.set('newCfType', $event.detail)" />
                                        </div>
                                        @if (in_array($newCfType, ['select', 'multiselect'], true))
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Options (séparées par des virgules)') }}</label>
                                                <input type="text" wire:model="newCfOptions" placeholder="{{ __('Basse, Moyenne, Haute') }}"
                                                       class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                                @error('newCfOptions') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                            </div>
                                        @endif
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Portée') }}</label>
                                            @php
                                                $cfScopeOptions = [
                                                    ['value' => 'card', 'label' => __('Cette carte uniquement')],
                                                    ['value' => 'list', 'label' => __('Toutes les cartes de la liste')],
                                                ];
                                                if (auth()->user()->can('update', $board)) {
                                                    $cfScopeOptions[] = ['value' => 'board', 'label' => __('Tout le tableau')];
                                                }
                                            @endphp
                                            <x-select :options="$cfScopeOptions" :value="$newCfScope" @select-change="$wire.set('newCfScope', $event.detail)" />
                                        </div>
                                        <button type="button" wire:click="addCardCustomField"
                                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-indigo-500">
                                            <x-phosphor-plus class="h-4 w-4"/> {{ __('Ajouter le champ') }}
                                            <span wire:loading wire:target="addCardCustomField"><x-phosphor-circle-notch class="h-4 w-4 animate-spin"/></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- Meta rows: members / labels / due date — only shown when set --}}
                        @if ($card->members->isNotEmpty() || $card->labels->isNotEmpty() || $card->due_at || $card->start_at)
                        <div class="relative">
                            <div class="flex flex-wrap items-start gap-x-8 gap-y-3">
                                @if ($card->members->isNotEmpty())
                                    <div>
                                        <p class="mb-1.5 text-xs font-medium text-neutral-500">{{ __('Membres') }}</p>
                                        <div class="flex items-center -space-x-1.5">
                                            @foreach ($card->members as $member)
                                                <x-user-avatar :user="$member" class="ring-2 ring-white dark:ring-neutral-900" />
                                            @endforeach
                                            @if ($canContribute)
                                                <button type="button" @click="openPicker = openPicker === 'members' ? null : 'members'" title="{{ __('Attribuer des membres') }}"
                                                        class="!ml-1.5 flex h-8 w-8 items-center justify-center rounded-full border border-neutral-300 text-neutral-500 transition hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-800">
                                                    <x-phosphor-plus class="h-4 w-4"/>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if ($card->labels->isNotEmpty())
                                    <div>
                                        <p class="mb-1.5 text-xs font-medium text-neutral-500">{{ __('Labels') }}</p>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            @foreach ($card->labels as $label)
                                                <span class="flex h-8 min-w-12 items-center justify-center rounded-md px-2 text-xs font-medium text-white shadow-sm" style="background-color: {{ $label->color }}" title="{{ $label->name }}">{{ $label->name }}</span>
                                            @endforeach
                                            @if ($canContribute)
                                                <button type="button" @click="openPicker = openPicker === 'labels' ? null : 'labels'" title="{{ __('Labels') }}"
                                                        class="flex h-8 w-8 items-center justify-center rounded-md border border-neutral-300 text-neutral-500 transition hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-800">
                                                    <x-phosphor-plus class="h-4 w-4"/>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if ($card->due_at || $card->start_at)
                                    <div>
                                        <p class="mb-1.5 text-xs font-medium text-neutral-500">{{ $card->due_at ? __('Date limite') : __('Début') }}</p>
                                        <button type="button" @if ($canContribute) @click="openPicker = openPicker === 'dates' ? null : 'dates'" @endif
                                                class="flex h-8 items-center gap-2 rounded-md border border-neutral-300 px-2.5 text-sm text-neutral-700 transition dark:border-neutral-600 dark:text-neutral-200 {{ $canContribute ? 'hover:bg-neutral-100 dark:hover:bg-neutral-800' : 'cursor-default' }}">
                                            {{ ($card->due_at ?? $card->start_at)->translatedFormat('d M, H:i') }}
                                            @if ($card->completed_at)
                                                <span class="rounded bg-green-100 px-1.5 py-0.5 text-[11px] font-medium text-green-700 dark:bg-green-500/15 dark:text-green-400">{{ __('Terminée') }}</span>
                                            @elseif ($dueOverdue)
                                                <span class="rounded bg-red-100 px-1.5 py-0.5 text-[11px] font-medium text-red-700 dark:bg-red-500/15 dark:text-red-400">{{ __('En retard') }}</span>
                                            @elseif ($dueSoon)
                                                <span class="rounded bg-amber-400 px-1.5 py-0.5 text-[11px] font-semibold text-amber-950">{{ __('Dû prochainement') }}</span>
                                            @endif
                                            @if ($canContribute)<x-phosphor-caret-down class="h-3.5 w-3.5 opacity-60"/>@endif
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- Pickers (members / labels / dates) — shared popovers, also reachable from "Ajouter" --}}
                        @if ($canContribute)
                        <div class="relative" x-show="openPicker !== null" x-cloak style="margin-top: 0;">
                            <div x-transition @click.outside="openPicker = null" @keydown.escape.window="openPicker = null"
                                 class="absolute left-0 top-0 z-40 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-neutral-200 bg-white p-3 shadow-[0_12px_32px_rgba(0,0,0,0.25)] dark:border-neutral-700 dark:bg-neutral-800 dark:shadow-[0_12px_32px_rgba(0,0,0,0.55)]">
                                <div class="relative mb-2 flex h-8 items-center justify-center">
                                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-300"
                                       x-text="openPicker === 'members' ? @js(__('Membres')) : (openPicker === 'labels' ? @js(__('Labels')) : @js(__('Dates')))"></p>
                                    <button type="button" @click="openPicker = null"
                                            class="absolute right-0 top-0 flex h-8 w-8 items-center justify-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-white"
                                            aria-label="{{ __('Fermer') }}">
                                        <x-phosphor-x class="h-4 w-4"/>
                                    </button>
                                </div>

                                {{-- Members picker --}}
                                <div x-show="openPicker === 'members'" id="card-members" class="space-y-1">
                                    @foreach ($boardMembers as $member)
                                        @php $assigned = $card->members->contains($member->id); @endphp
                                        <button type="button" wire:click="toggleMember({{ $member->id }})" class="flex w-full items-center gap-2 rounded-lg px-2 py-1 text-left text-sm {{ $assigned ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-neutral-100 dark:hover:bg-neutral-700/50' }}">
                                            <x-user-avatar :user="$member" size="sm" />
                                            <span class="truncate">{{ $member->name }}</span>
                                            @if ($assigned) <x-phosphor-check class="ml-auto h-4 w-4 text-indigo-600 dark:text-indigo-400" /> @endif
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Labels picker --}}
                                <div x-show="openPicker === 'labels'" id="card-labels">
                                    @php $labelPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b']; @endphp
                                    <div class="space-y-2">
                                        @foreach ($boardLabels as $label)
                                            @php $on = $card->labels->contains($label->id); @endphp
                                            <x-context-menu wire:key="label-{{ $label->id }}" class="group/label flex items-center gap-1">
                                                <x-slot:trigger>
                                                    <button type="button" wire:click="toggleLabel({{ $label->id }})" class="flex flex-1 items-center gap-2 rounded-lg px-2 py-1 text-left text-sm {{ $on ? 'ring-2 ring-indigo-400' : 'hover:bg-neutral-100 dark:hover:bg-neutral-700/50' }}">
                                                        <span class="h-3 w-6 shrink-0 rounded-full" style="background-color: {{ $label->color }}"></span>
                                                        <span class="truncate">{{ $label->name ?? '—' }}</span>
                                                    </button>
                                                    <button type="button" @click="openAt($event.clientX, $event.clientY)" class="shrink-0 rounded p-1 text-neutral-400 opacity-100 transition hover:bg-neutral-100 hover:text-neutral-700 group-hover/label:opacity-100 sm:opacity-0 dark:hover:bg-neutral-700 dark:hover:text-neutral-200" title="{{ __('Options du label (clic droit aussi)') }}"><x-phosphor-dots-three class="h-4 w-4" /></button>
                                                </x-slot:trigger>
                                                <x-slot:menu>
                                                    <div class="p-1" x-data="{ name: @js($label->name) }" @click.stop>
                                                        <input
                                                            type="text"
                                                            x-model="name"
                                                            @keydown.enter="$wire.renameLabel({{ $label->id }}, name); shown = false"
                                                            placeholder="{{ __('Nom du label') }}"
                                                            class="w-full rounded-md border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900"
                                                        >
                                                    </div>
                                                    <div class="flex flex-wrap gap-1.5 px-2 py-1.5">
                                                        @foreach ($labelPalette as $swatch)
                                                            <button type="button" @click="$wire.recolorLabel({{ $label->id }}, '{{ $swatch }}'); shown = false" class="h-5 w-5 rounded-full ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-800" style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
                                                        @endforeach
                                                    </div>
                                                    <x-context-menu.separator />
                                                    <x-context-menu.item icon="hash" @click="navigator.clipboard?.writeText('{{ $label->public_id }}'); window.toast('{{ __('ID copié') }}', { type: 'success' })">{{ __("Copier l'ID") }}</x-context-menu.item>
                                                    <x-context-menu.item icon="trash" variant="danger" @click="$store.confirm.open({ title: '{{ __('Supprimer le label') }}', message: '{{ __('Supprimer ce label du board ?') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteLabel({{ $label->id }}))">{{ __('Supprimer') }}</x-context-menu.item>
                                                </x-slot:menu>
                                            </x-context-menu>
                                        @endforeach
                                    </div>
                                    <form wire:submit="createLabel" class="mt-2 flex items-center gap-2">
                                        <input type="color" wire:model="newLabelColor" class="h-8 w-8 rounded border border-neutral-300 dark:border-neutral-700">
                                        <input type="text" wire:model="newLabelName" placeholder="{{ __('Nouveau label') }}" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                        <button type="submit" class="shrink-0 rounded-lg border border-neutral-300 px-2 py-1 text-sm hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-700/50">+</button>
                                    </form>
                                </div>

                                {{-- Dates picker --}}
                                <div x-show="openPicker === 'dates'" id="card-dates" class="space-y-2">
                                    <div>
                                        <label class="mb-0.5 block text-xs text-neutral-500">{{ __('Début') }}</label>
                                        <div class="flex gap-2">
                                            <input type="date" wire:model="startDate" wire:change="saveDates" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                            <input type="time" wire:model="startTime" wire:change="saveDates" aria-label="{{ __('Heure de début') }}" class="w-28 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-0.5 block text-xs text-neutral-500">{{ __('Échéance') }}</label>
                                        <div class="flex gap-2">
                                            <input type="date" wire:model="dueDate" wire:change="saveDates" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                            <input type="time" wire:model="dueTime" wire:change="saveDates" aria-label="{{ __('Heure d’échéance') }}" class="w-28 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                        </div>
                                        @error('dueDate') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        <p class="mt-1 text-[11px] text-neutral-400">{{ __('Heure optionnelle (12:00 par défaut).') }}</p>
                                    </div>
                                    @if ($card->start_at || $card->due_at)
                                        <div class="flex items-center justify-end text-xs">
                                            <button type="button" wire:click="clearDates" @click="openPicker = null" class="text-neutral-400 hover:text-red-500">{{ __('Retirer') }}</button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Custom fields (sidebar placement) — only when fields apply to this card --}}
                        @if ($sidebarFields->isNotEmpty())
                            <div>
                                <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Champs personnalisés') }}</h3>
                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    @foreach ($sidebarFields as $field)
                                        @include('livewire.partials.custom-field-input', ['field' => $field, 'val' => optional($cfValues->get($field->id))->value])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Description : éditeur WYSIWYG (TipTap → markdown) --}}
                        @if ($canContribute)
                        <div wire:key="desc-{{ $card->id }}" wire:ignore x-data="markdownEditor(@js((string) $card->description))">
                            <label class="mb-1 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500"><x-phosphor-text-align-left class="h-4 w-4"/>{{ __('Description') }}</label>

                            {{-- Read mode --}}
                            <div x-ref="readview" x-show="! editing" @click="edit()" class="markdown min-h-[3rem] cursor-text rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-sm hover:border-neutral-300 dark:border-neutral-700/60 dark:bg-neutral-800/50 dark:hover:border-neutral-700">
                                @if (filled($card->description))
                                    {!! Str::markdown($card->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                @else
                                    <span class="text-neutral-400">{{ __('Ajouter une description plus détaillée…') }}</span>
                                @endif
                            </div>

                            {{-- Edit mode --}}
                            <div x-show="editing" x-cloak class="rounded-lg border border-neutral-300 dark:border-neutral-700">
                                <div class="flex flex-wrap items-center gap-0.5 border-b border-neutral-200 p-1 dark:border-neutral-700">
                                    <button type="button" @click="run('toggleBold')" :class="isActive('bold') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-bold" title="{{ __('Gras') }}">B</button>
                                    <button type="button" @click="run('toggleItalic')" :class="isActive('italic') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} italic" title="{{ __('Italique') }}">I</button>
                                    <button type="button" @click="run('toggleStrike')" :class="isActive('strike') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} line-through" title="{{ __('Barré') }}">S</button>
                                    <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                    <button type="button" @click="run('toggleHeading', { level: 2 })" :class="isActive('heading', { level: 2 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('Titre') }}">H2</button>
                                    <button type="button" @click="run('toggleHeading', { level: 3 })" :class="isActive('heading', { level: 3 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('Sous-titre') }}">H3</button>
                                    <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                    <button type="button" @click="run('toggleBulletList')" :class="isActive('bulletList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste à puces') }}"><x-phosphor-list-bullets class="h-4 w-4" /></button>
                                    <button type="button" @click="run('toggleOrderedList')" :class="isActive('orderedList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste numérotée') }}"><x-phosphor-list-numbers class="h-4 w-4" /></button>
                                    <button type="button" @click="run('toggleCodeBlock')" :class="isActive('codeBlock') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Bloc de code') }}"><x-phosphor-code class="h-4 w-4" /></button>
                                    <button type="button" @click="toggleLink()" :class="isActive('link') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Lien') }}"><x-phosphor-link class="h-4 w-4" /></button>
                                </div>

                                <div class="js-editor-mount" wire:ignore x-ignore></div>

                                <div class="flex items-center justify-end gap-2 border-t border-neutral-200 p-1.5 dark:border-neutral-700">
                                    <button type="button" @click="cancel()" class="rounded-lg px-3 py-1 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                                    <button type="button" @click="save()" class="rounded-lg bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Enregistrer') }}</button>
                                </div>
                            </div>
                        </div>
                        @else
                            <div>
                                <label class="mb-1 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500"><x-phosphor-text-align-left class="h-4 w-4"/>{{ __('Description') }}</label>
                                <div class="markdown min-h-[3rem] rounded-lg border border-transparent bg-neutral-50 p-3 text-sm dark:bg-neutral-800/50">
                                    @if (filled($card->description))
                                        {!! Str::markdown($card->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                    @else
                                        <span class="text-neutral-400">{{ __('Aucune description.') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Description link previews --}}
                        @foreach ($this->linkPreviews($card->description) as $preview)
                            <x-link-preview
                                :preview="$preview"
                                :hidden="in_array($preview->url, $card->hidden_previews ?? [], true)"
                                wire-toggle="toggleDescriptionPreview('{{ $preview->url }}')"
                                wire:key="desc-lp-{{ $card->id }}-{{ $preview->id }}"
                            />
                        @endforeach

                        {{-- Custom fields placed in the content column (user choice or plugin placement) --}}
                        @if ($contentFields->isNotEmpty())
                            <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                                <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Champs personnalisés') }}</h3>
                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    @foreach ($contentFields as $field)
                                        @include('livewire.partials.custom-field-input', ['field' => $field, 'val' => optional($cfValues->get($field->id))->value])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Attachments — hidden entirely while the card has none --}}
                        @php
                            $media = $card->attachments
                                ->filter(fn ($a) => $a->isImage() || $a->isVideo())
                                ->map(fn ($a) => ['type' => $a->isImage() ? 'image' : 'video', 'url' => $a->url, 'mime' => $a->mime_type])
                                ->values()->all();
                            $mediaUrls = array_column($media, 'url');
                        @endphp
                        <div id="card-section-attachments"
                             x-data="{
                                forceOpen: @js($card->attachments->isNotEmpty()),
                                showDrop: @js($card->attachments->isEmpty()),
                                view: localStorage.getItem('card-attachments-view') ?? 'list',
                             }"
                             x-init="$watch('view', v => localStorage.setItem('card-attachments-view', v))"
                             x-show="forceOpen"
                             x-cloak
                             @card-open-attachments.window="forceOpen = true; showDrop = true; setTimeout(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200)"
                             class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500">
                                    <x-phosphor-paperclip class="h-4 w-4"/>
                                    {{ __('Pièces jointes') }}
                                    @if ($card->attachments->isNotEmpty())<span class="rounded-full bg-neutral-200 px-1.5 text-[10px] font-semibold text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $card->attachments->count() }}</span>@endif
                                </h3>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="view = 'list'" title="{{ __('Liste') }}" class="rounded p-1 transition" :class="view === 'list' ? 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200' : 'text-neutral-400 hover:text-neutral-600'"><x-phosphor-list class="h-4 w-4" /></button>
                                    <button type="button" @click="view = 'grid'" title="{{ __('Grille') }}" class="rounded p-1 transition" :class="view === 'grid' ? 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200' : 'text-neutral-400 hover:text-neutral-600'"><x-phosphor-squares-four class="h-4 w-4" /></button>
                                    @if ($canContribute)
                                        <button type="button" @click="showDrop = ! showDrop"
                                                class="ml-1 inline-flex h-7 items-center rounded-md border border-neutral-300 px-2.5 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Ajouter') }}</button>
                                    @endif
                                </div>
                            </div>

                            @if ($card->attachments->isNotEmpty())
                                <p class="text-xs font-medium text-neutral-500">{{ __('Fichiers') }}</p>
                                <div class="max-h-72 overflow-y-auto pr-1">
                                    {{-- Grid layout --}}
                                    <div x-show="view === 'grid'" class="grid grid-cols-2 gap-3">
                                        @foreach ($card->attachments as $attachment)
                                            <div wire:key="attg-{{ $attachment->id }}" class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                                                @if ($attachment->isImage())
                                                    <img src="{{ $attachment->url }}" alt="{{ $attachment->name }}" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="h-28 w-full cursor-zoom-in object-cover transition hover:opacity-90">
                                                @elseif ($attachment->isVideo())
                                                    <button type="button" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="group relative block h-28 w-full">
                                                        <video src="{{ $attachment->url }}" preload="metadata" muted class="pointer-events-none h-28 w-full bg-black object-contain"></video>
                                                        <span class="absolute inset-0 flex items-center justify-center bg-black/20 transition group-hover:bg-black/30"><span class="flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-white"><x-phosphor-play class="ml-0.5 h-5 w-5" /></span></span>
                                                    </button>
                                                @else
                                                    <div class="flex h-28 w-full items-center justify-center bg-neutral-100 dark:bg-neutral-800"><x-phosphor-file class="h-8 w-8 text-neutral-400" /></div>
                                                @endif
                                                <div class="flex items-center justify-between gap-1 p-2">
                                                    <span class="truncate text-xs" title="{{ $attachment->name }}">{{ $attachment->name }}</span>
                                                    @if ($canContribute)
                                                    <div class="flex shrink-0 gap-1">
                                                        @if ($attachment->isImage())
                                                            <button type="button" wire:click="setCover({{ $attachment->id }})" class="text-neutral-400 hover:text-amber-500" title="{{ __('Définir comme couverture') }}"><x-phosphor-star class="h-4 w-4" /></button>
                                                        @endif
                                                        <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" class="text-neutral-400 hover:text-red-500"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- List layout --}}
                                    <div x-show="view === 'list'" class="flex flex-col gap-1.5">
                                        @foreach ($card->attachments as $attachment)
                                            <div wire:key="attl-{{ $attachment->id }}" class="flex items-center gap-2 rounded-lg border border-neutral-200 p-1.5 dark:border-neutral-700">
                                                @if ($attachment->isImage())
                                                    <img src="{{ $attachment->url }}" alt="" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="h-10 w-10 shrink-0 cursor-zoom-in rounded object-cover">
                                                @elseif ($attachment->isVideo())
                                                    <button type="button" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="relative h-10 w-10 shrink-0 overflow-hidden rounded bg-black">
                                                        <video src="{{ $attachment->url }}" preload="metadata" muted class="pointer-events-none h-full w-full object-contain"></video>
                                                        <span class="absolute inset-0 flex items-center justify-center"><x-phosphor-play class="h-4 w-4 text-white" /></span>
                                                    </button>
                                                @else
                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-neutral-100 dark:bg-neutral-800"><x-phosphor-file class="h-5 w-5 text-neutral-400" /></span>
                                                @endif
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-xs font-medium" title="{{ $attachment->name }}">{{ $attachment->name }}</p>
                                                    <p class="truncate text-[11px] text-neutral-400">
                                                        {{ __('Ajout') }} : {{ $attachment->created_at->diffForHumans() }}@if ($card->cover_path && Str::contains($card->cover_path, basename($attachment->path ?? ''))) · {{ __('Image de couverture') }}@endif
                                                    </p>
                                                </div>
                                                @if ($canContribute)
                                                    @if ($attachment->isImage())
                                                        <button type="button" wire:click="setCover({{ $attachment->id }})" class="shrink-0 text-neutral-400 hover:text-amber-500" title="{{ __('Définir comme couverture') }}"><x-phosphor-star class="h-4 w-4" /></button>
                                                    @endif
                                                    <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" class="shrink-0 text-neutral-400 hover:text-red-500"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if ($canContribute)<div x-show="showDrop" x-cloak><x-dropzone model="upload" action="saveAttachment" accept="image/*,video/*" /></div>@endif
                        </div>

                        {{-- Checklists — the section only exists when the card has some --}}
                        @if ($card->checklists->isNotEmpty())
                        <div class="space-y-4">
                            @foreach ($card->checklists as $checklist)
                                @php
                                    $total = $checklist->items->count();
                                    $done = $checklist->items->where('is_completed', true)->count();
                                    $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
                                @endphp
                                <div
                                    wire:key="checklist-{{ $checklist->id }}"
                                    x-data="{ collapsed: JSON.parse(localStorage.getItem('checklist-collapsed-{{ $checklist->id }}') ?? 'false') }"
                                    class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <button type="button" @click="collapsed = ! collapsed; localStorage.setItem('checklist-collapsed-{{ $checklist->id }}', collapsed)" class="flex min-w-0 flex-1 items-center gap-1.5 text-left" title="{{ __('Replier / déplier') }}">
                                            <x-phosphor-check-square class="h-4 w-4 shrink-0 text-neutral-400"/>
                                            <span class="truncate text-sm font-medium">{{ $checklist->title }}</span>
                                            <span class="shrink-0 rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">{{ $done }}/{{ $total }}</span>
                                        </button>
                                        @if ($canContribute)<button type="button" wire:click="deleteChecklist({{ $checklist->id }})" class="shrink-0 rounded-md border border-neutral-200 px-2 py-0.5 text-xs text-neutral-400 transition hover:border-red-200 hover:text-red-500 dark:border-neutral-700">{{ __('Supprimer') }}</button>@endif
                                    </div>

                                    <div x-show="! collapsed" x-cloak class="mt-2">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="w-8 shrink-0 text-right text-[11px] tabular-nums text-neutral-400">{{ $pct }}%</span>
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                            <div class="h-full rounded-full bg-green-500" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>

                                    <ul class="space-y-1">
                                        @foreach ($checklist->items as $item)
                                            @php $itemOverdue = $item->due_at && ! $item->is_completed && $item->due_at->isPast(); @endphp
                                            <li wire:key="chk-item-{{ $item->id }}" class="group flex items-center gap-2 rounded px-1 py-0.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800/60">
                                                @if ($canContribute)
                                                <button type="button" wire:click="toggleChecklistItem({{ $item->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition {{ $item->is_completed ? 'border-green-500 bg-green-500 text-white' : 'border-neutral-300 hover:border-green-400 dark:border-neutral-600' }}">
                                                    @if ($item->is_completed)<x-phosphor-check class="h-3 w-3"/>@endif
                                                </button>
                                                <span class="min-w-0 flex-1 break-words {{ $item->is_completed ? 'text-neutral-400 line-through' : '' }}">{{ $item->content }}</span>
                                                <div class="ml-auto flex shrink-0 items-center gap-1.5">
                                                    @if ($item->due_at)
                                                        <span class="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs {{ $itemOverdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300' }}"><x-phosphor-calendar-blank class="h-3.5 w-3.5"/>{{ $item->due_at->translatedFormat('d M') }}</span>
                                                    @endif
                                                    @if ($item->assignee)
                                                        <x-user-avatar :user="$item->assignee" size="xs" :hover-card="false" />
                                                    @endif
                                                    <div x-data="{ open: false }" class="relative opacity-100 sm:opacity-0 sm:group-hover:opacity-100">
                                                        <button type="button" @click="open = ! open" class="rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"><x-phosphor-dots-three class="h-4 w-4"/></button>
                                                        <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-52 rounded-lg border border-neutral-200 bg-white p-1.5 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                                            <p class="px-1 pb-1 text-[10px] font-medium uppercase tracking-wide text-neutral-400">{{ __('Assigner à') }}</p>
                                                            <div class="max-h-36 overflow-y-auto">
                                                                @foreach ($boardMembers as $checklistMember)
                                                                    <button type="button" wire:click="assignChecklistItem({{ $item->id }}, {{ $checklistMember->id }})" @click="open = false" class="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left text-xs hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $item->assigned_to === $checklistMember->id ? 'font-semibold text-indigo-600 dark:text-indigo-400' : '' }}">
                                                                        <x-user-avatar :user="$checklistMember" size="xs" :hover-card="false" />
                                                                        <span class="truncate">{{ $checklistMember->name }}</span>
                                                                    </button>
                                                                @endforeach
                                                            </div>
                                                            @if ($item->assigned_to)
                                                                <button type="button" wire:click="assignChecklistItem({{ $item->id }}, null)" @click="open = false" class="mt-0.5 w-full rounded px-1.5 py-1 text-left text-xs text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ __('Retirer l’assignation') }}</button>
                                                            @endif
                                                            <p class="border-t border-neutral-100 px-1 pb-1 pt-1.5 text-[10px] font-medium uppercase tracking-wide text-neutral-400 dark:border-neutral-800">{{ __('Échéance') }}</p>
                                                            <input type="date" value="{{ $item->due_at?->format('Y-m-d') }}" @change="$wire.setChecklistItemDue({{ $item->id }}, $event.target.value); open = false" class="w-full rounded border border-neutral-200 bg-white px-1.5 py-1 text-xs dark:border-neutral-700 dark:bg-neutral-800">
                                                            @if ($item->due_at)
                                                                <button type="button" wire:click="setChecklistItemDue({{ $item->id }}, null)" @click="open = false" class="mt-0.5 w-full rounded px-1.5 py-1 text-left text-xs text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ __('Retirer l’échéance') }}</button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <button type="button" wire:click="deleteChecklistItem({{ $item->id }})" class="text-neutral-300 opacity-100 hover:text-red-500 group-hover:opacity-100 dark:text-neutral-600 sm:opacity-0"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                                </div>
                                                @else
                                                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border {{ $item->is_completed ? 'border-green-500 bg-green-500 text-white' : 'border-neutral-300 dark:border-neutral-600' }}">@if ($item->is_completed)<x-phosphor-check class="h-3 w-3"/>@endif</span>
                                                <span class="min-w-0 flex-1 {{ $item->is_completed ? 'text-neutral-400 line-through' : '' }}">{{ $item->content }}</span>
                                                <div class="ml-auto flex shrink-0 items-center gap-1.5">
                                                    @if ($item->due_at)
                                                        <span class="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs {{ $itemOverdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300' }}"><x-phosphor-calendar-blank class="h-3.5 w-3.5"/>{{ $item->due_at->translatedFormat('d M') }}</span>
                                                    @endif
                                                    @if ($item->assignee)<x-user-avatar :user="$item->assignee" size="xs" :hover-card="false" />@endif
                                                </div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if ($canContribute)
                                    <form wire:submit="addChecklistItem({{ $checklist->id }})" class="mt-2">
                                        <input type="text" wire:model="newChecklistItem.{{ $checklist->id }}" placeholder="{{ __('+ Ajouter un élément') }}" class="w-full rounded border border-neutral-200 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    </form>
                                    @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @endif

                        {{-- Relations (card links) — hidden until links exist or "Ajouter → Relations" --}}
                        <div id="card-relations" x-show="{{ $hasLinks ? 'true' : 'showRelations' }}" x-cloak>
                            <h3 class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500"><x-phosphor-git-branch class="h-4 w-4"/>{{ __('Relations') }}</h3>

                            @if ($hasLinks)
                                <div class="mb-2 space-y-2">
                                    @if ($cardLinks['blocks']->isNotEmpty())
                                        <div>
                                            <p class="mb-0.5 text-xs font-medium text-red-600 dark:text-red-400">{{ __('Bloque') }}</p>
                                            @foreach ($cardLinks['blocks'] as $link)
                                                @include('livewire.cards.partials.card-link', ['link' => $link, 'other' => $link->relatedCard])
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($cardLinks['blockedBy']->isNotEmpty())
                                        <div>
                                            <p class="mb-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Bloquée par') }}</p>
                                            @foreach ($cardLinks['blockedBy'] as $link)
                                                @include('livewire.cards.partials.card-link', ['link' => $link, 'other' => $link->card])
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($cardLinks['relates']->isNotEmpty())
                                        <div>
                                            <p class="mb-0.5 text-xs font-medium text-neutral-500">{{ __('Liée à') }}</p>
                                            @foreach ($cardLinks['relates'] as $link)
                                                @include('livewire.cards.partials.card-link', ['link' => $link, 'other' => $link->card_id === $card->id ? $link->relatedCard : $link->card])
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if ($canContribute)
                            <div class="flex items-center gap-2">
                                <select wire:model="linkType" class="shrink-0 rounded-lg border border-neutral-300 bg-white px-2 py-1.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    <option value="blocks">{{ __('Bloque') }}</option>
                                    <option value="blocked_by">{{ __('Bloquée par') }}</option>
                                    <option value="relates_to">{{ __('Liée à') }}</option>
                                </select>
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative min-w-0 flex-1">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="linkSearch"
                                        @focus="open = true"
                                        placeholder="{{ __('Rechercher une carte à lier…') }}"
                                        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                                    >
                                    <div x-show="open && $wire.linkSearch.trim().length >= 1" x-cloak
                                         class="absolute left-0 right-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                        @forelse ($linkCandidates as $candidate)
                                            <button type="button" wire:click="linkCard({{ $candidate->id }})" @click="open = false"
                                                    class="block w-full truncate rounded px-2 py-1.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ $candidate->title }}</button>
                                        @empty
                                            <p class="px-2 py-1.5 text-xs text-neutral-400">{{ __('Aucun résultat.') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- Mirrors — hidden until some exist or "⋯ → Miroir" --}}
                        <div id="card-mirrors" x-show="{{ $cardMirrors->isNotEmpty() ? 'true' : 'showMirror' }}" x-cloak>
                            <h3 class="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-neutral-500"><x-phosphor-cards class="h-4 w-4"/>{{ __('Miroirs') }}</h3>

                            @if ($cardMirrors->isNotEmpty())
                                <ul class="mb-3 space-y-1">
                                    @foreach ($cardMirrors as $mirror)
                                        <li wire:key="cm-{{ $mirror->id }}" class="flex items-center justify-between gap-2 rounded-lg bg-neutral-50 px-2.5 py-1.5 text-sm dark:bg-neutral-800/50">
                                            <span class="flex min-w-0 items-center gap-1.5">
                                                <x-phosphor-copy class="h-3.5 w-3.5 shrink-0 text-indigo-500"/>
                                                <span class="truncate">{{ $mirror->board->name }} <span class="text-neutral-400">· {{ $mirror->list->name }}</span></span>
                                            </span>
                                            <button type="button" wire:click="removeMirror({{ $mirror->id }})" class="shrink-0 text-xs text-neutral-400 hover:text-red-500">{{ __('Retirer') }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($canContribute && $mirrorTargets->isNotEmpty())
                                <div class="flex items-center gap-2">
                                    <select wire:model.live="mirrorListId" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                        <option value="">{{ __('Refléter sur…') }}</option>
                                        @foreach ($mirrorTargets as $tb)
                                            <optgroup label="{{ $tb->name }}">
                                                @foreach ($tb->lists as $tl)
                                                    @continue($tl->id === $card->board_list_id)
                                                    <option value="{{ $tl->id }}">{{ $tl->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="mirrorCard" @disabled($mirrorListId === '') class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{{ __('Refléter') }}</button>
                                </div>
                                @error('mirrorListId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    </div>

                    {{-- Right panel: comments & activity / automations / power-ups --}}
                    <div class="order-2 space-y-4 border-neutral-100 bg-neutral-50/70 p-4 sm:p-6 lg:col-span-5 lg:rounded-br-2xl lg:border-l dark:border-neutral-800 dark:bg-neutral-800/30">
                        {{-- Comments & activity --}}
                        <div x-show="panel === 'comments'" class="space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="flex min-w-0 items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                                    <x-phosphor-chat-circle-dots class="h-5 w-5 shrink-0 text-neutral-400" />
                                    <span class="truncate">{{ __('Commentaires et activité') }}</span>
                                </h3>
                                <button type="button" wire:click="toggleActivity"
                                        class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-neutral-300 px-2.5 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-700/50">
                                    {{ $showActivity ? __('Masquer les détails') : __('Afficher les détails') }}
                                    <span wire:loading wire:target="toggleActivity"><x-phosphor-circle-notch class="h-3.5 w-3.5 animate-spin text-neutral-400" /></span>
                                </button>
                            </div>

                            {{-- Comments (real-time) --}}
                            @php
                                $mentionMembers = $boardMembers->map(fn ($m) => [
                                    'id' => $m->id,
                                    'name' => $m->name,
                                    'slug' => \Illuminate\Support\Str::slug($m->name),
                                    'avatar_url' => $m->avatarUrl(),
                                ])->values();
                            @endphp
                            <div
                                wire:key="comment-composer-{{ $card->id }}"
                                class="space-y-3"
                                x-data="commentEditor(@js([
                                    'members' => $mentionMembers,
                                    'boardId' => $board->id,
                                    'cardId' => $card->id,
                                    'userId' => auth()->id(),
                                    'userName' => auth()->user()->name,
                                ]))"
                                x-init="init()"
                            >
                                @if ($canComment)
                                <div class="relative">
                                    <div class="rounded-lg border border-neutral-300 bg-white focus-within:border-indigo-500 dark:border-neutral-700 dark:bg-neutral-900/60">
                                        <div class="flex flex-wrap items-center gap-0.5 border-b border-neutral-200 p-1 dark:border-neutral-700">
                                            <button type="button" @click="run('toggleBold')" :class="isActive('bold') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-bold" title="{{ __('Gras') }}">B</button>
                                            <button type="button" @click="run('toggleItalic')" :class="isActive('italic') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} italic" title="{{ __('Italique') }}">I</button>
                                            <button type="button" @click="run('toggleStrike')" :class="isActive('strike') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} line-through" title="{{ __('Barré') }}">S</button>
                                            <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                            <button type="button" @click="run('toggleBulletList')" :class="isActive('bulletList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste à puces') }}"><x-phosphor-list-bullets class="h-4 w-4" /></button>
                                            <button type="button" @click="run('toggleOrderedList')" :class="isActive('orderedList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste numérotée') }}"><x-phosphor-list-numbers class="h-4 w-4" /></button>
                                            <button type="button" @click="toggleLink()" :class="isActive('link') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Lien') }}"><x-phosphor-link class="h-4 w-4" /></button>
                                        </div>

                                        <div class="js-comment-mount" wire:ignore x-ignore></div>

                                        <div class="flex items-center justify-between gap-2 border-t border-neutral-200 p-1.5 dark:border-neutral-700">
                                            <span class="text-xs text-indigo-500" x-text="typingText"></span>
                                            <button type="button" @click="submit()" :disabled="empty" class="rounded-lg bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">{{ __('Commenter') }}</button>
                                        </div>
                                    </div>

                                    {{-- @mention suggestions (driven by TipTap) --}}
                                    <div x-show="mention.open" x-cloak
                                         class="fixed z-[60] max-h-56 w-56 overflow-auto rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
                                         :style="`top: ${mention.top}px; left: ${mention.left}px`">
                                        <template x-for="(m, i) in mention.items" :key="m.id">
                                            <button type="button" @mousedown.prevent="pickMention(i)" @mouseenter="mention.index = i"
                                                    class="flex w-full items-center gap-2 px-2 py-1.5 text-left text-sm"
                                                    :class="i === mention.index ? 'bg-indigo-50 dark:bg-indigo-500/10' : ''">
                                                <template x-if="m.avatar_url"><img :src="m.avatar_url" alt="" class="h-5 w-5 shrink-0 rounded-full object-cover"></template>
                                                <template x-if="! m.avatar_url"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300" x-text="m.name.charAt(0).toUpperCase()"></span></template>
                                                <span class="truncate" x-text="m.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                    @error('newComment') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                </div>
                                @else
                                    <p class="rounded-lg bg-neutral-100 px-3 py-2 text-sm text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">{{ __('Lecture seule : vous ne pouvez pas commenter.') }}</p>
                                @endif

                                <div class="space-y-3">
                                    @foreach ($card->comments as $comment)
                                        @php $canDelete = $comment->user_id === auth()->id() || $board->memberRole(auth()->user())?->isAdministrator(); @endphp
                                        <div wire:key="comment-{{ $comment->id }}" id="comment-{{ $comment->id }}" class="group/comment flex gap-2">
                                            <x-user-avatar :user="$comment->user" size="sm" class="mt-0.5" />
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium">{{ $comment->user?->name ?? __('Utilisateur supprimé') }}</span>
                                                    <span class="text-xs text-neutral-400">{{ $comment->created_at->diffForHumans() }}@if ($comment->updated_at->gt($comment->created_at)) · {{ __('modifié') }}@endif</span>
                                                    <div class="ml-auto flex items-center gap-2" x-data="{ copied: false }">
                                                        <button type="button" @click="navigator.clipboard?.writeText('{{ route('boards.show', ['board' => $board, 'card' => $card->public_id]) }}#comment-{{ $comment->id }}'); window.toast('{{ __('Lien copié') }}', { type: 'success' }); copied = true; setTimeout(() => copied = false, 1500)" class="text-xs text-neutral-300 opacity-100 transition hover:text-indigo-500 group-hover/comment:opacity-100 sm:opacity-0" title="{{ __('Copier le lien du commentaire') }}"><span x-text="copied ? '{{ __('Copié !') }}' : '{{ __('Lien') }}'"></span></button>
                                                        @if ($comment->user_id === auth()->id())
                                                            <button type="button" wire:click="startEditComment({{ $comment->id }})" class="text-xs text-neutral-300 opacity-100 transition hover:text-indigo-500 group-hover/comment:opacity-100 sm:opacity-0">{{ __('Modifier') }}</button>
                                                        @endif
                                                        @if ($canDelete)
                                                            <button type="button" wire:click="deleteComment({{ $comment->id }})" class="text-xs text-neutral-300 opacity-100 transition hover:text-red-500 group-hover/comment:opacity-100 sm:opacity-0">{{ __('Supprimer') }}</button>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if ($editingCommentId === $comment->id)
                                                    <form wire:submit="saveComment" class="mt-1 space-y-1.5">
                                                        <textarea wire:model="editingCommentBody" rows="3" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"></textarea>
                                                        @error('editingCommentBody') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                                        <div class="flex gap-2">
                                                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-500">{{ __('Enregistrer') }}</button>
                                                            <button type="button" wire:click="cancelEditComment" class="rounded-lg px-3 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                                                        </div>
                                                    </form>
                                                @else
                                                    <div class="markdown mt-0.5 break-words text-sm text-neutral-700 dark:text-neutral-300">{!! $this->renderCommentBody($comment->body) !!}</div>
                                                    @foreach ($this->linkPreviews($comment->body) as $preview)
                                                        <x-link-preview
                                                            :preview="$preview"
                                                            :hidden="in_array($preview->url, $comment->hidden_previews ?? [], true)"
                                                            wire-toggle="toggleCommentPreview({{ $comment->id }}, '{{ $preview->url }}')"
                                                            wire:key="comment-{{ $comment->id }}-lp-{{ $preview->id }}"
                                                        />
                                                    @endforeach
                                                @endif

                                                {{-- Reactions --}}
                                                @php
                                                    $grouped = $comment->reactions->groupBy('emoji');
                                                    $myReactions = $comment->reactions->where('user_id', auth()->id())->pluck('emoji')->all();
                                                @endphp
                                                @if ($canComment)
                                                <div class="mt-1.5 flex flex-wrap items-center gap-1" x-data="{ picker: false }">
                                                    @foreach ($grouped as $emoji => $group)
                                                        <button type="button" wire:click="toggleReaction({{ $comment->id }}, '{{ $emoji }}')"
                                                                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition {{ in_array($emoji, $myReactions, true) ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-200 bg-neutral-50 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' }}">
                                                            <span>{{ $emoji }}</span><span class="font-medium">{{ $group->count() }}</span>
                                                        </button>
                                                    @endforeach
                                                    <div class="relative">
                                                        <button type="button" @click="picker = ! picker" class="flex h-6 w-6 items-center justify-center rounded-full border border-neutral-200 text-neutral-400 transition hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" title="{{ __('Ajouter une réaction') }}">
                                                            <x-phosphor-smiley class="h-4 w-4"/>
                                                        </button>
                                                        <div x-show="picker" x-cloak @click.outside="picker = false" class="absolute left-0 z-20 mt-1 flex gap-0.5 rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                                            @foreach ($reactionEmojis as $emoji)
                                                                <button type="button" wire:click="toggleReaction({{ $comment->id }}, '{{ $emoji }}')" @click="picker = false" class="flex h-7 w-7 items-center justify-center rounded text-base hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ $emoji }}</button>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                                @else
                                                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                                        @foreach ($grouped as $emoji => $group)
                                                            <span class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"><span>{{ $emoji }}</span><span class="font-medium">{{ $group->count() }}</span></span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Activity feed (lazy-loaded via the "details" toggle) --}}
                            @if ($showActivity)
                            <div class="space-y-2.5 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                                @forelse ($activities as $activity)
                                    @php
                                        $props = $activity->properties ?? [];
                                        if ($activity->type === 'card.moved' && ! empty($props['to_list'])) {
                                            $label = __('activity.card.moved_to', ['list' => $props['to_list']]);
                                        } elseif ($activity->type === 'member.assigned') {
                                            $label = __('activity.member.assigned', ['name' => $props['user_name'] ?? __('activity.someone')]);
                                        } else {
                                            $key = 'activity.' . $activity->type;
                                            $label = trans($key);
                                            $label = $label === $key ? $activity->type : $label;
                                        }
                                    @endphp
                                    <div wire:key="activity-{{ $activity->id }}" class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                        <x-user-avatar :user="$activity->user" size="xs" />
                                        <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ $activity->user?->name ?? __('Quelqu\'un') }}</span>
                                        <span>{{ $label }}</span>
                                        @if (Str::startsWith((string) $activity->source, 'mcp:'))
                                            <span class="inline-flex items-center gap-0.5 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"><x-phosphor-robot class="h-3 w-3" /> {{ Str::after($activity->source, 'mcp:') }}</span>
                                        @endif
                                        <span class="text-neutral-400">· {{ $activity->created_at->diffForHumans() }}</span>
                                    </div>
                                @empty
                                    <p class="text-xs text-neutral-400">{{ __('Aucune activité pour le moment.') }}</p>
                                @endforelse
                            </div>
                            @endif
                        </div>

                        {{-- Manual automations panel --}}
                        <div x-show="panel === 'automations'" x-cloak class="space-y-3">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                                <x-phosphor-lightning class="h-5 w-5 text-amber-500" /> {{ __('Automatisations') }}
                            </h3>
                            @if ($canContribute && $cardButtons->isNotEmpty())
                                <div class="space-y-1.5">
                                    @foreach ($cardButtons as $button)
                                        <button type="button" wire:click="runAutomation({{ $button->id }})" wire:key="cardbtn-{{ $button->id }}" class="flex w-full items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium transition hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900/60 dark:hover:bg-neutral-800">
                                            <x-dynamic-component :component="'phosphor-'.(($button->trigger_config['icon'] ?? null) ?: 'lightning')" class="h-4 w-4 text-amber-500" /> {{ $button->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-neutral-400">{{ __('Aucune action rapide sur ce board.') }}</p>
                            @endif
                        </div>

                        {{-- Power-Ups panel --}}
                        <div x-show="panel === 'powerups'" x-cloak class="space-y-3">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                                <x-phosphor-puzzle-piece class="h-5 w-5 text-neutral-400" /> {{ __('Power-Ups') }}
                            </h3>
                            @forelse ($boardPlugins as $plugin)
                                @php $pluginFieldCount = $customFields->where('plugin_key', $plugin->plugin_key)->count(); @endphp
                                <div wire:key="cardplugin-{{ $plugin->id }}" class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900/60">
                                    <x-phosphor-puzzle-piece class="h-4 w-4 shrink-0 text-indigo-500"/>
                                    <span class="min-w-0 flex-1 truncate font-medium">{{ $plugin->name }}</span>
                                    @if ($pluginFieldCount > 0)
                                        <span class="shrink-0 rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">{{ trans_choice(':count champ injecté|:count champs injectés', $pluginFieldCount, ['count' => $pluginFieldCount]) }}</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-neutral-400">{{ __('Aucun Power-Up actif sur ce board.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Bottom switcher pill --}}
                <div class="pointer-events-none sticky bottom-0 z-20 flex justify-center pb-4">
                    @php $pillBtn = 'pointer-events-auto inline-flex h-9 items-center gap-1.5 rounded-xl px-3 text-sm font-medium transition'; @endphp
                    <div class="pointer-events-auto flex items-center gap-0.5 rounded-2xl border border-neutral-200 bg-white/95 p-1 shadow-xl backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/95">
                        <button type="button" @click="panel = 'powerups'"
                                class="{{ $pillBtn }}" :class="panel === 'powerups' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'">
                            <x-phosphor-puzzle-piece class="h-4 w-4"/> {{ __('Power-Ups') }}
                        </button>
                        <button type="button" @click="panel = 'automations'"
                                class="{{ $pillBtn }}" :class="panel === 'automations' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'">
                            <x-phosphor-lightning class="h-4 w-4"/> {{ __('Automatisations') }}
                        </button>
                        <span class="mx-0.5 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                        <button type="button" @click="panel = 'comments'"
                                class="{{ $pillBtn }}" :class="panel === 'comments' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'">
                            <x-phosphor-chat-circle-dots class="h-4 w-4"/> {{ __('Commentaires') }}
                        </button>
                    </div>
                </div>
            </div>
        </x-modal>
    @endif
</div>
