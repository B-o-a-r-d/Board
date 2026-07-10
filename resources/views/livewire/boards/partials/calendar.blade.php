{{-- Calendar view: month grid (desktop) + agenda list (mobile). Data from Show::buildCalendar(). --}}
<div class="flex min-h-0 flex-1 flex-col overflow-hidden transition-opacity"
     wire:loading.class.delay="opacity-40"
     wire:target="search, filterLabels, filterMembers, toggleLabel, toggleMember, toggleUnassigned, filterDue, resetFilters, applyFilter, applyView, calendarStep, calendarToday, rescheduleCard, createCardOnDate">

    {{-- Month navigation — glass bar over a board background so it stays readable --}}
    <div class="mb-3 flex items-center justify-between gap-2 {{ $boardBg ? 'rounded-lg border border-white/20 bg-white/70 px-3 py-2 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/70' : '' }}">
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
    <div class="hidden min-h-0 flex-1 flex-col overflow-auto rounded-xl border sm:flex {{ $boardBg ? 'border-white/20 bg-white/90 shadow-lg backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/85' : 'border-neutral-300 dark:border-neutral-700' }}">
        <div class="grid grid-cols-7 border-b border-neutral-300 bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900/60">
            @foreach ($calendar['weekDays'] as $weekDay)
                <div class="px-2 py-1.5 text-center text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $weekDay }}</div>
            @endforeach
        </div>
        <div class="grid flex-1 auto-rows-fr grid-cols-7">
            @foreach ($calendar['weeks'] as $week)
                @foreach ($week as $day)
                    {{-- Each day is a drop target: dropping a card here reschedules it (rescheduleCard). --}}
                    <div wire:key="cal-day-{{ $day['date']->toDateString() }}"
                         x-data="{ adding: false, over: false }"
                         @if ($canContribute)
                         x-on:dragover.prevent="over = true"
                         x-on:dragleave="over = false"
                         x-on:drop.prevent="over = false; $wire.rescheduleCard($event.dataTransfer.getData('text/card-id'), '{{ $day['date']->toDateString() }}')"
                         :class="over ? 'ring-2 ring-inset ring-indigo-400' : ''"
                         @endif
                         class="group/day relative min-h-[6rem] border-b border-r border-neutral-200 p-1 dark:border-neutral-800/60 {{ $day['inMonth'] ? 'bg-white dark:bg-transparent' : 'bg-neutral-100/70 dark:bg-neutral-900/40' }}">
                        <div class="mb-1 flex items-center justify-between gap-1">
                            @if ($day['inMonth'] && $canContribute)
                                <button type="button" x-show="!adding"
                                        x-on:click="adding = true; $nextTick(() => $refs.addInput?.focus())"
                                        class="flex h-5 w-5 items-center justify-center rounded text-neutral-400 opacity-0 transition hover:bg-neutral-100 hover:text-indigo-600 group-hover/day:opacity-100 dark:hover:bg-neutral-800 dark:hover:text-indigo-400"
                                        aria-label="{{ __('Ajouter une carte') }}">
                                    <x-phosphor-plus class="h-3.5 w-3.5"/>
                                </button>
                            @else
                                <span></span>
                            @endif
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs {{ $day['isToday'] ? 'bg-indigo-600 font-semibold text-white' : ($day['inMonth'] ? 'text-neutral-600 dark:text-neutral-300' : 'text-neutral-400') }}">{{ $day['day'] }}</span>
                        </div>

                        @if ($day['inMonth'] && $canContribute)
                            <form x-show="adding" x-cloak class="mb-1"
                                  x-on:submit.prevent="$wire.createCardOnDate('{{ $day['date']->toDateString() }}', $refs.addInput.value); $refs.addInput.value = ''; adding = false">
                                <input x-ref="addInput" type="text" maxlength="255"
                                       x-on:keydown.escape="adding = false"
                                       x-on:blur="adding = false"
                                       placeholder="{{ __('Titre de la carte…') }}"
                                       class="w-full rounded border border-neutral-300 bg-white px-1.5 py-1 text-xs text-neutral-800 focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                            </form>
                        @endif

                        <div class="space-y-1">
                            @foreach ($day['cards'] as $card)
                                @include('livewire.boards.partials.calendar-card')
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- Mobile: agenda (only days in-month with cards) --}}
    <div class="min-h-0 flex-1 space-y-4 overflow-auto pb-4 sm:hidden {{ $boardBg ? 'rounded-xl border border-white/20 bg-white/90 p-3 backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/85' : '' }}">
        @php $agendaHasCards = false; @endphp
        @foreach ($calendar['weeks'] as $week)
            @foreach ($week as $day)
                @if ($day['inMonth'] && $day['cards']->isNotEmpty())
                    @php $agendaHasCards = true; @endphp
                    <div wire:key="agenda-{{ $day['date']->toDateString() }}">
                        <p class="mb-1.5 text-sm font-semibold capitalize {{ $day['isToday'] ? 'text-indigo-600 dark:text-indigo-400' : '' }}">{{ $day['date']->translatedFormat('l j F') }}</p>
                        <div class="space-y-1.5">
                            @foreach ($day['cards'] as $card)
                                @include('livewire.boards.partials.calendar-card')
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
