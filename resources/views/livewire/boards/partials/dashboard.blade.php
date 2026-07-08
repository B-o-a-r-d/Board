{{-- Dashboard (reporting) view: headline stats + per-list/member/label bar
     charts (pure CSS/SVG, no chart lib). Data from Show::buildDashboard(). --}}
@php
    $d = $dashboard;
    $maxList = collect($d['byList'])->max('count') ?: 1;
    $maxMember = collect($d['byMember'])->max('count') ?: 1;
    $maxLabel = collect($d['byLabel'])->max('count') ?: 1;
@endphp
<div class="min-h-0 flex-1 overflow-auto pb-4 transition-opacity"
     wire:loading.class.delay="opacity-40"
     wire:target="search, filterLabels, filterMembers, toggleLabel, toggleMember, toggleUnassigned, filterDue, resetFilters, applyFilter, applyView">

    {{-- Headline tiles --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Cartes') }}</span>
                <x-phosphor-cards class="h-4 w-4 text-neutral-300 dark:text-neutral-600"/>
            </div>
            <p class="mt-1 text-2xl font-semibold">{{ $d['total'] }}</p>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Terminées') }}</span>
                <x-phosphor-check-circle class="h-4 w-4 text-green-500"/>
            </div>
            <p class="mt-1 text-2xl font-semibold">{{ $d['completed'] }} <span class="text-sm font-normal text-neutral-400">/ {{ $d['total'] }}</span></p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                <div class="h-full rounded-full bg-green-500" style="width: {{ $d['completionRate'] }}%"></div>
            </div>
            <p class="mt-1 text-xs text-neutral-400">{{ $d['completionRate'] }}%</p>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('En retard') }}</span>
                <x-phosphor-warning-circle class="h-4 w-4 text-red-500"/>
            </div>
            <p class="mt-1 text-2xl font-semibold {{ $d['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $d['overdue'] }}</p>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('À venir (7 j)') }}</span>
                <x-phosphor-clock class="h-4 w-4 text-amber-500"/>
            </div>
            <p class="mt-1 text-2xl font-semibold">{{ $d['dueSoon'] }}</p>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Sans date') }}</span>
                <x-phosphor-calendar-x class="h-4 w-4 text-neutral-300 dark:text-neutral-600"/>
            </div>
            <p class="mt-1 text-2xl font-semibold">{{ $d['noDate'] }}</p>
        </div>
    </div>

    {{-- Breakdowns --}}
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Par liste --}}
        <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <h3 class="mb-3 text-sm font-semibold">{{ __('Par liste') }}</h3>
            @forelse ($d['byList'] as $row)
                <div class="mb-2.5">
                    <div class="mb-0.5 flex items-center justify-between gap-2 text-xs">
                        <span class="flex min-w-0 items-center gap-1.5">
                            @if ($row['color'])<span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $row['color'] }}"></span>@endif
                            <span class="truncate">{{ $row['name'] }}</span>
                        </span>
                        <span class="shrink-0 tabular-nums text-neutral-400">{{ $row['count'] }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                        <div class="h-full rounded-full" style="width: {{ round($row['count'] / $maxList * 100) }}%; background-color: {{ $row['color'] ?: '#6366f1' }}"></div>
                    </div>
                </div>
            @empty
                <p class="text-xs text-neutral-400">{{ __('Aucune liste.') }}</p>
            @endforelse
        </section>

        {{-- Par membre --}}
        <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <h3 class="mb-3 text-sm font-semibold">{{ __('Par membre') }}</h3>
            @forelse ($d['byMember'] as $row)
                <div class="mb-2.5">
                    <div class="mb-0.5 flex items-center justify-between gap-2 text-xs">
                        <span class="flex min-w-0 items-center gap-1.5">
                            @if ($row['user'])
                                <x-user-avatar :user="$row['user']" size="xs" :hover-card="false" />
                            @else
                                <x-phosphor-user class="h-4 w-4 text-neutral-300 dark:text-neutral-600"/>
                            @endif
                            <span class="truncate">{{ $row['name'] }}</span>
                        </span>
                        <span class="shrink-0 tabular-nums text-neutral-400">{{ $row['count'] }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ round($row['count'] / $maxMember * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-xs text-neutral-400">{{ __('Aucun membre.') }}</p>
            @endforelse
        </section>

        {{-- Par label --}}
        <section class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <h3 class="mb-3 text-sm font-semibold">{{ __('Par label') }}</h3>
            @forelse ($d['byLabel'] as $row)
                <div class="mb-2.5">
                    <div class="mb-0.5 flex items-center justify-between gap-2 text-xs">
                        <span class="flex min-w-0 items-center gap-1.5">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                            <span class="truncate">{{ $row['name'] }}</span>
                        </span>
                        <span class="shrink-0 tabular-nums text-neutral-400">{{ $row['count'] }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                        <div class="h-full rounded-full" style="width: {{ round($row['count'] / $maxLabel * 100) }}%; background-color: {{ $row['color'] ?: '#6366f1' }}"></div>
                    </div>
                </div>
            @empty
                <p class="text-xs text-neutral-400">{{ __('Aucun label.') }}</p>
            @endforelse
        </section>
    </div>
</div>
