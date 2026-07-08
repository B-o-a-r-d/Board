<div class="flex h-[calc(100dvh-8rem)] flex-col">
    {{-- Board header --}}
    <div class="mb-3 flex flex-col gap-3 sm:mb-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="text-center sm:text-left">
            <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ $board->name }}</h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $board->workspace->name }} · {{ __('Tableau partagé en lecture seule') }}</p>
        </div>

        {{-- Everyone currently on this board (members + anonymous guests) --}}
        <div
            x-data="publicPresence(@js($board->share_token), {{ $board->id }})"
            class="flex items-center justify-center -space-x-2 sm:justify-end"
            wire:ignore
        >
            <template x-for="viewer in viewers" :key="viewer.id">
                <span
                    class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ring-2 ring-neutral-100 dark:ring-neutral-950"
                    :class="viewer.guest ? 'text-white' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300'"
                    :style="viewer.guest ? `background-color: ${viewer.color}` : ''"
                    :title="viewer.guest ? viewer.name + ' {{ __('(invité)') }}' : viewer.name"
                    x-text="(viewer.guest ? viewer.name.replace(/^\S+\s/, '') : viewer.name).charAt(0).toUpperCase()"
                ></span>
            </template>
        </div>
    </div>

    @php $boardBg = $board->backgroundStyle(); @endphp

    {{-- Lists (columns) --}}
    <div
        @if ($boardBg) style="background: {{ $boardBg }};" @endif
        class="flex flex-1 snap-x snap-mandatory items-start gap-3 overflow-x-auto scroll-p-1 py-4 sm:snap-none sm:gap-4 {{ $boardBg ? 'rounded-xl px-3' : '' }}"
    >
        @forelse ($lists as $list)
            <div
                wire:key="public-list-{{ $list->id }}"
                class="flex max-h-full w-full shrink-0 snap-start flex-col overflow-hidden rounded-xl bg-neutral-200/70 sm:w-72 dark:bg-neutral-900"
            >
                @if ($list->cover_color)
                    <div class="h-2 w-full" style="background-color: {{ $list->cover_color }}"></div>
                @endif

                <div class="flex items-center justify-between gap-2 px-3 py-2">
                    <span class="truncate px-1 text-sm font-semibold">{{ $list->name }}</span>
                    <span class="shrink-0 rounded-full bg-neutral-300/70 px-1.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ $list->cards->count() }}</span>
                </div>

                <ul class="flex min-h-2 flex-col gap-2 overflow-y-auto px-2 pb-2">
                    @foreach ($list->cards as $card)
                        @php
                            $items = $card->checklists->flatMap->items;
                            $itemsTotal = $items->count();
                            $itemsDone = $items->where('is_completed', true)->count();
                            $overdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast();
                        @endphp
                        <li
                            wire:key="public-card-{{ $card->id }}"
                            class="shrink-0 overflow-hidden rounded-lg border border-neutral-200 bg-white text-sm shadow-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            @if ($card->cover_path)
                                <img src="{{ Storage::disk('public')->url($card->cover_path) }}" alt="" class="h-24 w-full object-cover">
                            @elseif ($card->cover_color)
                                <div class="h-9 w-full" style="background-color: {{ $card->cover_color }}"></div>
                            @endif

                            <div class="p-2.5">
                                @if ($card->labels->isNotEmpty())
                                    <div class="mb-1.5 flex flex-wrap gap-1">
                                        @foreach ($card->labels as $label)
                                            <span class="h-1.5 w-8 rounded-full" style="background-color: {{ $label->color }}" title="{{ $label->name }}"></span>
                                        @endforeach
                                    </div>
                                @endif

                                <p class="break-words">{{ $card->title }}</p>

                                @if ($card->due_at || $itemsTotal > 0 || $card->completed_at)
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($card->completed_at)
                                            <span class="rounded bg-green-100 px-1.5 py-0.5 text-green-700 dark:bg-green-500/15 dark:text-green-400">{{ __('Terminée') }}</span>
                                        @endif
                                        @if ($card->due_at)
                                            <span class="rounded px-1.5 py-0.5 {{ $overdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-700/50' }}">
                                                {{ $card->due_at->translatedFormat('d M') }}
                                            </span>
                                        @endif
                                        @if ($itemsTotal > 0)
                                            <span class="{{ $itemsDone === $itemsTotal ? 'text-green-600 dark:text-green-400' : '' }}"><x-phosphor-check class="inline-flex h-4 w-4 self-center" /> {{ $itemsDone }}/{{ $itemsTotal }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if ($card->members->isNotEmpty())
                                    <div class="mt-2 flex -space-x-1.5">
                                        @foreach ($card->members as $member)
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700 ring-2 ring-white dark:bg-indigo-500/20 dark:text-indigo-300 dark:ring-neutral-800" title="{{ $member->name }}">
                                                {{ Str::of($member->name)->substr(0, 1)->upper() }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-neutral-400">{{ __('Ce tableau ne contient aucune liste.') }}</p>
        @endforelse
    </div>
</div>
