{{-- Header strip: cover, list selector chip, window actions (watch/menu/close).
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
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
                                    @if ($canMirror)
                                        {{-- The picker's targets load on demand ($wire.showMirrorPicker):
                                             scanning every workspace board is too costly per render. --}}
                                        <button type="button" @click="menuOpen = false; openTransient('showMirror'); flashElement('card-mirrors'); $wire.showMirrorPicker = true" class="{{ $menuItem }}">
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
