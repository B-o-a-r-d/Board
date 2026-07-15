<div
    x-data="{ open: @entangle('open').live, tab: 'all' }"
    @activity-toggle.window="open = ! open"
    @keydown.escape.window="open = false"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex justify-end">
        <div x-show="open" x-transition.opacity @click="open = false"
             class="absolute inset-0 bg-neutral-900/40 backdrop-blur-sm"></div>

        <aside x-show="open"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
               class="relative flex h-full w-full max-w-md flex-col border-l border-neutral-200 bg-white shadow-xl dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <x-phosphor-clock-counter-clockwise class="h-5 w-5"/> {{ __('Activité') }}
                </h2>
                <button type="button" @click="open = false"
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
                {{-- Skeleton while the open round-trip fetches the log (instant shell, content after). --}}
                <div wire:loading.flex class="flex-col gap-3">
                    @for ($i = 0; $i < 5; $i++)
                        <div class="flex gap-3 py-2.5">
                            <div class="h-8 w-8 shrink-0 animate-pulse rounded-full bg-neutral-200 dark:bg-neutral-700"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3 w-3/4 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700"></div>
                                <div class="h-3 w-1/3 animate-pulse rounded bg-neutral-100 dark:bg-neutral-800"></div>
                            </div>
                        </div>
                    @endfor
                </div>

                <div wire:loading.remove>
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
</div>
