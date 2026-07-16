{{-- Card relations (blocks / blocked by / relates to) + link search.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
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
