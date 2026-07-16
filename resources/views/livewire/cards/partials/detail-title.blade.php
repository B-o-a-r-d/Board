{{-- Title row (completion + implicit-save title) and the "Ajouter" action bar with its popovers.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
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
