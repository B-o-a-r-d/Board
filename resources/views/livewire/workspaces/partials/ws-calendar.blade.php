{{-- Workspace calendar: month grid aggregating dated cards across every accessible board. --}}
<div class="flex min-h-0 flex-1 flex-col overflow-hidden transition-opacity"
     wire:loading.class.delay="opacity-40"
     wire:target="filterBoards, filterMembers, filterDue, resetFilters, calendarStep, calendarToday">

    {{-- Month navigation --}}
    <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="text-base font-semibold capitalize sm:text-lg">{{ $calendar['label'] }}</h2>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="calendarToday"
                    class="rounded-lg border border-neutral-300 px-2.5 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __("Aujourd'hui") }}</button>
            <button type="button" wire:click="calendarStep(-1)" aria-label="{{ __('Mois précédent') }}"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"><x-phosphor-caret-left class="h-4 w-4"/></button>
            <button type="button" wire:click="calendarStep(1)" aria-label="{{ __('Mois suivant') }}"
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"><x-phosphor-caret-right class="h-4 w-4"/></button>
        </div>
    </div>

    {{-- Desktop: month grid --}}
    <div class="hidden min-h-0 flex-1 flex-col overflow-auto rounded-xl border border-neutral-300 dark:border-neutral-700 sm:flex">
        <div class="grid grid-cols-7 border-b border-neutral-300 bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900/60">
            @foreach ($calendar['weekDays'] as $weekDay)
                <div class="px-2 py-1.5 text-center text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $weekDay }}</div>
            @endforeach
        </div>
        <div class="grid flex-1 auto-rows-fr grid-cols-7">
            @foreach ($calendar['weeks'] as $week)
                @foreach ($week as $day)
                    <div wire:key="cal-day-{{ $day['date']->toDateString() }}"
                         class="relative min-h-[6rem] border-b border-r border-neutral-200 p-1 dark:border-neutral-800/60 {{ $day['inMonth'] ? 'bg-white dark:bg-transparent' : 'bg-neutral-100/70 dark:bg-neutral-900/40' }}">
                        <div class="mb-1 flex items-center justify-end">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs {{ $day['isToday'] ? 'bg-indigo-600 font-semibold text-white' : ($day['inMonth'] ? 'text-neutral-600 dark:text-neutral-300' : 'text-neutral-400') }}">{{ $day['day'] }}</span>
                        </div>
                        <div class="space-y-1">
                            @foreach ($day['cards'] as $card)
                                @include('livewire.workspaces.partials.ws-calendar-card')
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- Mobile: agenda --}}
    <div class="min-h-0 flex-1 space-y-4 overflow-auto pb-4 sm:hidden">
        @php $agendaHasCards = false; @endphp
        @foreach ($calendar['weeks'] as $week)
            @foreach ($week as $day)
                @if ($day['inMonth'] && $day['cards']->isNotEmpty())
                    @php $agendaHasCards = true; @endphp
                    <div wire:key="agenda-{{ $day['date']->toDateString() }}">
                        <p class="mb-1.5 text-sm font-semibold capitalize {{ $day['isToday'] ? 'text-indigo-600 dark:text-indigo-400' : '' }}">{{ $day['date']->translatedFormat('l j F') }}</p>
                        <div class="space-y-1.5">
                            @foreach ($day['cards'] as $card)
                                @include('livewire.workspaces.partials.ws-calendar-card')
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        @endforeach
        @unless ($agendaHasCards)
            <p class="py-10 text-center text-sm text-neutral-400">{{ __('Aucune carte datée ce mois-ci.') }}</p>
        @endunless
    </div>
</div>
