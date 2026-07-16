{{-- Right-panel alternates: manual automations and Power-Ups.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
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
