{{-- Meta rows: members / labels / due date, shown only when set.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
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
