<div class="-mb-6 flex h-[calc(100dvh-5.5rem)] flex-col">
    @php $boardBg = $board->backgroundStyle($board->share_token); @endphp
    @if ($boardBg)
        {{-- Full-bleed board background + contrast overlay, same as the members' board view --}}
        <div class="pointer-events-none fixed inset-0 -z-10" style="background: {{ $boardBg }};" aria-hidden="true"></div>
        <div class="pointer-events-none fixed inset-0 -z-10 bg-black/20" aria-hidden="true"></div>
    @endif

    {{-- Board topbar: slim full-bleed bar glued under the navbar (public layout uses py-6) --}}
    <div @class([
        'relative z-30 -mx-4 -mt-6 mb-3 flex min-h-12 flex-wrap items-center gap-x-2 gap-y-1.5 border-b px-4 py-1.5 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8',
        'dark border-white/15 text-neutral-100' => $boardBg,
        'border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900' => ! $boardBg,
    ])>
        @if ($boardBg)
            {{-- Dark glass veil, theme-agnostic (the `dark` class above forces light text) --}}
            <div class="absolute inset-0 -z-10 bg-neutral-900/45 backdrop-blur-xl" aria-hidden="true"></div>
        @endif
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <h1 class="truncate text-base font-semibold tracking-tight sm:text-lg" title="{{ __('Tableau partagé en lecture seule') }}">{{ $board->name }}</h1>
            <span class="hidden shrink-0 items-center rounded-full bg-neutral-200/80 px-2 py-0.5 text-[11px] font-medium text-neutral-600 sm:inline-flex dark:bg-neutral-800 dark:text-neutral-300">{{ $board->workspace->name }}</span>
        </div>

        {{-- Everyone currently on this board (members + anonymous guests) --}}
        <div
            x-data="publicPresence(@js($board->share_token), {{ $board->id }})"
            class="flex items-center -space-x-2"
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

    {{-- Lists (columns) — the background is full-bleed behind the whole board --}}
    <div class="flex flex-1 snap-x snap-mandatory items-start gap-3 overflow-x-auto scroll-p-1 py-4 sm:snap-none sm:gap-4">
        @forelse ($lists as $list)
            <div
                wire:key="public-list-{{ $list->id }}"
                class="flex max-h-full w-full shrink-0 snap-start flex-col overflow-hidden rounded-xl sm:w-72 {{ $boardBg ? 'dark border border-white/15 bg-neutral-900/50 text-neutral-100 shadow-lg backdrop-blur-md' : 'bg-neutral-200/70 dark:bg-neutral-900' }}"
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
                                <img src="{{ $card->coverUrl($board->share_token) }}" alt="" class="h-24 w-full object-cover">
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
