<div class="mx-auto flex h-[calc(100vh-4.5rem)] max-w-full flex-col px-4 py-6 sm:px-6">
    {{-- Header --}}
    <div class="shrink-0">
        <a href="{{ route('dashboard') }}" wire:navigate
           class="flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200">
            <x-phosphor-arrow-left class="h-4 w-4"/> {{ __('Tableau de bord') }}
        </a>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">{{ $workspace->name }} <span class="text-neutral-400">· {{ __('Vues') }}</span></h1>

            {{-- View switcher --}}
            <div class="inline-flex items-center gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                <a href="{{ route('workspaces.calendar', $workspace) }}" wire:navigate
                   class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition {{ $view === 'calendar' ? 'bg-white text-neutral-900 shadow-sm dark:bg-neutral-950 dark:text-white' : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
                    <x-phosphor-calendar-blank class="h-4 w-4"/> {{ __('Calendrier') }}
                </a>
                <a href="{{ route('workspaces.table', $workspace) }}" wire:navigate
                   class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition {{ $view === 'table' ? 'bg-white text-neutral-900 shadow-sm dark:bg-neutral-950 dark:text-white' : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200' }}">
                    <x-phosphor-table class="h-4 w-4"/> {{ __('Table') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mt-4 flex shrink-0 flex-wrap items-center gap-2">
        {{-- Board filter --}}
        <div x-data="{ o: false }" @click.outside="o = false" class="relative">
            <button type="button" @click="o = !o"
                    class="flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">
                <x-phosphor-squares-four class="h-4 w-4"/> {{ __('Tableaux') }}
                @if (count($filterBoards)) <span class="rounded-full bg-indigo-600 px-1.5 text-[11px] font-semibold text-white">{{ count($filterBoards) }}</span> @endif
            </button>
            <div x-show="o" x-cloak class="absolute left-0 z-20 mt-1 max-h-72 w-60 overflow-y-auto rounded-xl border border-neutral-200 bg-white p-1.5 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                @forelse ($boards as $b)
                    <div wire:key="fb-{{ $b->id }}" class="relative">
                        <input type="checkbox" id="fb-in-{{ $b->id }}" value="{{ $b->id }}" wire:model.live="filterBoards" class="peer hidden">
                        <label for="fb-in-{{ $b->id }}"
                               class="flex cursor-pointer items-center gap-2 rounded-lg border border-transparent px-2 py-1.5 pr-7 text-sm text-neutral-600 transition hover:bg-neutral-100 peer-checked:border-indigo-500 peer-checked:bg-indigo-50/60 peer-checked:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:peer-checked:border-indigo-500 dark:peer-checked:bg-indigo-500/10 dark:peer-checked:text-white">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: hsl({{ ($b->id * 47) % 360 }} 60% 55%)"></span>
                            <span class="min-w-0 flex-1 truncate">{{ $b->name }}</span>
                        </label>
                        <x-phosphor-check class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-indigo-600 opacity-0 transition-opacity peer-checked:opacity-100 dark:text-indigo-400"/>
                    </div>
                @empty
                    <p class="px-2 py-1.5 text-sm text-neutral-400">{{ __('Aucun tableau.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Member filter --}}
        <div x-data="{ o: false }" @click.outside="o = false" class="relative">
            <button type="button" @click="o = !o"
                    class="flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">
                <x-phosphor-users class="h-4 w-4"/> {{ __('Membres') }}
                @if (count($filterMembers)) <span class="rounded-full bg-indigo-600 px-1.5 text-[11px] font-semibold text-white">{{ count($filterMembers) }}</span> @endif
            </button>
            <div x-show="o" x-cloak class="absolute left-0 z-20 mt-1 max-h-72 w-60 overflow-y-auto rounded-xl border border-neutral-200 bg-white p-1.5 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                @forelse ($members as $m)
                    <div wire:key="fm-{{ $m->id }}" class="relative">
                        <input type="checkbox" id="fm-in-{{ $m->id }}" value="{{ $m->id }}" wire:model.live="filterMembers" class="peer hidden">
                        <label for="fm-in-{{ $m->id }}"
                               class="flex cursor-pointer items-center gap-2 rounded-lg border border-transparent px-2 py-1.5 pr-7 text-sm text-neutral-600 transition hover:bg-neutral-100 peer-checked:border-indigo-500 peer-checked:bg-indigo-50/60 peer-checked:text-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:peer-checked:border-indigo-500 dark:peer-checked:bg-indigo-500/10 dark:peer-checked:text-white">
                            <span class="min-w-0 flex-1 truncate">{{ $m->name }}</span>
                        </label>
                        <x-phosphor-check class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-indigo-600 opacity-0 transition-opacity peer-checked:opacity-100 dark:text-indigo-400"/>
                    </div>
                @empty
                    <p class="px-2 py-1.5 text-sm text-neutral-400">{{ __('Aucun membre.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Due filter --}}
        <select wire:model.live="filterDue"
                class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm text-neutral-600 focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
            <option value="">{{ __('Échéance : toutes') }}</option>
            <option value="overdue">{{ __('En retard') }}</option>
            <option value="withdue">{{ __('Avec échéance') }}</option>
            <option value="nodue">{{ __('Sans échéance') }}</option>
        </select>

        @if ($hasFilters)
            <button type="button" wire:click="resetFilters" class="text-sm text-neutral-500 hover:text-indigo-600 dark:text-neutral-400 dark:hover:text-indigo-400">{{ __('Réinitialiser') }}</button>
        @endif
    </div>

    {{-- Content --}}
    <div class="mt-4 flex min-h-0 flex-1 flex-col">
        @if ($boards->isEmpty())
            <p class="py-16 text-center text-sm text-neutral-400">{{ __('Aucun tableau accessible dans ce workspace.') }}</p>
        @elseif ($view === 'calendar')
            @include('livewire.workspaces.partials.ws-calendar')
        @else
            @include('livewire.workspaces.partials.ws-table')
        @endif
    </div>
</div>
