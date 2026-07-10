{{-- Timeline (Gantt) view: an 8-week day window, one swimlane per list, each
     dated card a bar spanning start_at → due_at. Data from Show::buildTimeline().
     Day width is fixed at 40px (must match dayWidth in timeline.js). --}}
@php $end = $timeline['start']->copy()->addDays($timeline['days'] - 1); @endphp
<div @if ($canContribute) x-data="timeline" @endif
     class="flex min-h-0 flex-1 flex-col overflow-hidden transition-opacity"
     wire:loading.class.delay="opacity-40"
     wire:target="search, filterLabels, filterMembers, toggleLabel, toggleMember, toggleUnassigned, filterDue, resetFilters, applyFilter, applyView, timelineStep, timelineToday, setCardSchedule">

    {{-- Range navigation — glass bar over a board background so it stays readable.
         translatedFormat uses PHP date chars: 'M' (short month), not ISO 'MMM'. --}}
    <div class="mb-3 flex items-center justify-between gap-2 {{ $boardBg ? 'rounded-lg border border-white/20 bg-white/70 px-3 py-2 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/70' : '' }}">
        <h2 class="text-base font-semibold capitalize sm:text-lg">{{ $timeline['start']->translatedFormat('d M') }} – {{ $end->translatedFormat('d M Y') }}</h2>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="timelineToday"
                    class="rounded-lg border border-neutral-300 px-2.5 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __("Aujourd'hui") }}</button>
            <button type="button" wire:click="timelineStep(-2)" aria-label="{{ __('Reculer') }}"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"><x-phosphor-caret-left class="h-4 w-4"/></button>
            <button type="button" wire:click="timelineStep(2)" aria-label="{{ __('Avancer') }}"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"><x-phosphor-caret-right class="h-4 w-4"/></button>
        </div>
    </div>

    @if (empty($timeline['lanes']))
        <p class="py-10 text-center text-sm text-neutral-400 {{ $boardBg ? 'rounded-xl bg-white/70 backdrop-blur-md dark:bg-neutral-900/70' : '' }}">{{ __('Aucune carte datée sur cette période.') }}</p>
    @else
        {{-- Opaque/glass surface: the board background must never bleed through the lanes --}}
        <div class="min-h-0 flex-1 overflow-auto rounded-xl border {{ $boardBg ? 'border-white/20 bg-white/95 shadow-lg backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/90' : 'border-neutral-300 bg-white dark:border-neutral-700 dark:bg-neutral-950' }}">
            <div class="relative" style="width: {{ 176 + $timeline['days'] * 40 }}px;">
                {{-- Day header (sticky, fixed 44px height — must match buildTimeline). --}}
                <div class="sticky top-0 z-20 flex h-11 border-b border-neutral-300 bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="sticky left-0 z-10 w-44 shrink-0 border-r border-neutral-300 bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900"></div>
                    @foreach ($timeline['dayList'] as $d)
                        <div class="flex w-10 shrink-0 flex-col justify-center border-r border-neutral-200 text-center dark:border-neutral-800 {{ $d['isWeekend'] ? 'bg-neutral-200/50 dark:bg-neutral-800/40' : '' }}">
                            @if ($d['isMonthStart'])
                                <div class="truncate text-[10px] font-medium uppercase text-indigo-500 dark:text-indigo-400">{{ $d['month'] }}</div>
                            @else
                                <div class="text-[10px] uppercase text-neutral-400">{{ $d['weekday'] }}</div>
                            @endif
                            <div class="text-xs {{ $d['isToday'] ? 'font-semibold text-indigo-600 dark:text-indigo-400' : 'text-neutral-600 dark:text-neutral-300' }}">{{ $d['day'] }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Lanes (one per list) --}}
                @foreach ($timeline['lanes'] as $lane)
                    <div wire:key="tl-lane-{{ $lane['list']->id }}" class="flex border-b border-neutral-200 dark:border-neutral-800">
                        <div class="sticky left-0 z-10 flex w-44 shrink-0 items-center gap-1.5 border-r border-neutral-300 bg-white px-3 py-2 dark:border-neutral-700 dark:bg-neutral-900">
                            @if ($lane['list']->cover_color)
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $lane['list']->cover_color }}"></span>
                            @endif
                            <span class="truncate text-sm font-medium">{{ $lane['list']->name }}</span>
                        </div>

                        <div class="relative" style="width: {{ $timeline['days'] * 40 }}px; height: {{ count($lane['bars']) * 36 + 8 }}px;">
                            @unless (is_null($timeline['todayOffset']))
                                <div class="pointer-events-none absolute inset-y-0 z-0 w-px bg-indigo-400/70" style="left: {{ $timeline['todayOffset'] * 40 }}px;"></div>
                            @endunless

                            @foreach ($lane['bars'] as $i => $bar)
                                @php
                                    $c = $bar['card'];
                                    $barClass = $c->completed_at
                                        ? 'border-green-300 bg-green-100 text-green-800 dark:border-green-500/30 dark:bg-green-500/15 dark:text-green-300'
                                        : ($bar['overdue']
                                            ? 'border-red-300 bg-red-100 text-red-800 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-300'
                                            : 'border-indigo-300 bg-indigo-100 text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-500/15 dark:text-indigo-200');
                                @endphp
                                <div
                                    data-tl-bar
                                    data-card-id="{{ $c->id }}"
                                    data-start="{{ $c->start_at?->toDateString() }}"
                                    data-due="{{ $c->due_at?->toDateString() }}"
                                    wire:key="tl-bar-{{ $c->id }}"
                                    class="group absolute z-10 flex h-8 items-center overflow-hidden rounded-md border shadow-sm {{ $barClass }}"
                                    style="left: {{ $bar['offset'] * 40 }}px; width: {{ max($bar['span'] * 40 - 4, 24) }}px; top: {{ $i * 36 + 4 }}px;"
                                >
                                    @if ($canContribute)<span data-tl-handle="start" class="absolute left-0 top-0 z-10 h-full w-1.5 cursor-ew-resize opacity-0 group-hover:opacity-100" title="{{ __('Modifier le début') }}"></span>@endif
                                    <button type="button" data-tl-handle="move"
                                            wire:click="$dispatch('open-card', { cardId: {{ $c->id }} })"
                                            title="{{ $c->title }}"
                                            class="flex h-full w-full items-center gap-1 px-2 text-left text-xs {{ $canContribute ? 'cursor-grab' : 'cursor-pointer' }}">
                                        @if ($c->labels->isNotEmpty())
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $c->labels->first()->color }}"></span>
                                        @endif
                                        <span class="truncate">{{ $c->title }}</span>
                                        @if ($c->members->isNotEmpty())
                                            <span class="ml-auto flex shrink-0 items-center gap-0.5 pl-1 opacity-70"><x-phosphor-user class="h-3 w-3"/>{{ $c->members->count() }}</span>
                                        @endif
                                    </button>
                                    @if ($canContribute)<span data-tl-handle="end" class="absolute right-0 top-0 z-10 h-full w-1.5 cursor-ew-resize opacity-0 group-hover:opacity-100" title="{{ __("Modifier l'échéance") }}"></span>@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Dependency arrows (blocks links). Overlay under the bars (z-5),
                     non-interactive; coordinates come from buildTimeline. --}}
                @if (! empty($timeline['edges']))
                    <svg class="pointer-events-none absolute left-0 top-0 z-[5]" width="{{ $timeline['gridWidth'] }}" height="{{ $timeline['gridHeight'] }}">
                        <defs>
                            <marker id="tl-arrow" viewBox="0 0 8 8" refX="6" refY="4" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                <path d="M0 0 L8 4 L0 8 z" class="fill-indigo-400" />
                            </marker>
                        </defs>
                        @foreach ($timeline['edges'] as $e)
                            <path d="M {{ $e['fx'] }} {{ $e['fy'] }} C {{ $e['fx'] + 26 }} {{ $e['fy'] }}, {{ $e['tx'] - 26 }} {{ $e['ty'] }}, {{ $e['tx'] }} {{ $e['ty'] }}"
                                  fill="none" stroke-width="1.5" class="stroke-indigo-400/70" marker-end="url(#tl-arrow)" />
                        @endforeach
                    </svg>
                @endif
            </div>
        </div>
    @endif
</div>
