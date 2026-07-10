<div>
    @if ($showTrigger)
        <button
            type="button"
            wire:click="open"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
            title="{{ __('Automatisations') }}"
            aria-label="{{ __('Automations du board') }}"
        >
            <x-phosphor-robot class="h-4 w-4" />
        </button>
    @endif

    @if ($showModal)
    @php
        $categoryLabels = [
            'move' => ['label' => __('Déplacement'), 'icon' => 'arrow-right'],
            'changes' => ['label' => __('Modifications'), 'icon' => 'plus-minus'],
            'dates' => ['label' => __('Dates'), 'icon' => 'clock'],
            'checklists' => ['label' => __('Checklists'), 'icon' => 'check-square'],
            'content' => ['label' => __('Contenu'), 'icon' => 'chat-circle-dots'],
            'fields' => ['label' => __('Champs'), 'icon' => 'sliders-horizontal'],
            'addremove' => ['label' => __('Ajouter / Retirer'), 'icon' => 'plus-minus'],
            'output' => ['label' => __('Sortie'), 'icon' => 'broadcast'],
        ];
        $sections = [
            'rules' => ['label' => __('Règles'), 'icon' => 'lightning'],
            'scheduled' => ['label' => __('Programmées'), 'icon' => 'clock'],
            'due' => ['label' => __("Date d'échéance"), 'icon' => 'calendar-blank'],
            'buttons' => ['label' => __('Boutons de carte'), 'icon' => 'cursor-click'],
            'board_buttons' => ['label' => __('Boutons de tableau'), 'icon' => 'squares-four'],
        ];
        $hasTriggerStep = ! in_array($section, ['buttons', 'board_buttons'], true);
        $isButtonSection = in_array($section, ['buttons', 'board_buttons'], true);
        $buttonIcons = ['lightning', 'check-circle', 'archive', 'tag', 'user-plus', 'clock', 'arrow-right', 'star', 'flag', 'bell', 'broom', 'rocket'];
        $panelBox = 'rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900';
        $rowBtn = 'flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition';
    @endphp

    {{-- Full-screen builder --}}
    <div class="fixed inset-0 z-50 flex bg-neutral-50 dark:bg-neutral-950" @keydown.escape.window="$wire.close()">
        {{-- Sidebar --}}
        <aside class="hidden w-60 shrink-0 flex-col border-r border-neutral-200 bg-white p-4 sm:flex dark:border-neutral-800 dark:bg-neutral-900">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold"><x-phosphor-lightning class="h-5 w-5 text-amber-500"/> {{ __('Automatisation') }}</h2>
            <p class="mb-1 px-2 text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ __('Automatisations') }}</p>
            <nav class="space-y-0.5">
                @foreach ($sections as $sectionKey => $meta)
                    <button type="button" wire:click="setSection('{{ $sectionKey }}')"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition {{ $section === $sectionKey ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">
                        <x-dynamic-component :component="'phosphor-'.$meta['icon']" class="h-4 w-4 shrink-0 opacity-70"/>
                        {{ $meta['label'] }}
                    </button>
                @endforeach
            </nav>
            <button type="button" wire:click="close" class="mt-auto flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-neutral-500 transition hover:bg-neutral-100 dark:hover:bg-neutral-800">
                <x-phosphor-arrow-left class="h-4 w-4"/> {{ __('Retour au tableau') }}
            </button>
        </aside>

        {{-- Main area --}}
        <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
            {{-- Mobile header: section switcher + close --}}
            <div class="flex items-center gap-2 border-b border-neutral-200 p-3 sm:hidden dark:border-neutral-800">
                <x-select class="min-w-0 flex-1"
                    :options="collect($sections)->map(fn ($m, $k) => ['value' => $k, 'label' => $m['label']])->values()->all()"
                    :value="$section"
                    @select-change="$wire.setSection($event.detail)"
                />
                <button type="button" wire:click="close" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-neutral-300 text-neutral-500 dark:border-neutral-700"><x-phosphor-x class="h-4 w-4"/></button>
            </div>

            <div class="mx-auto w-full max-w-4xl p-4 sm:p-8">
                @if (! $building)
                    {{-- ============ Listing ============ --}}
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h1 class="text-xl font-semibold">{{ $sections[$section]['label'] }}</h1>
                        <button type="button" wire:click="startCreate"
                                class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                            {{ __('Créer une automatisation') }}
                        </button>
                    </div>

                    <div class="space-y-3">
                        @forelse ($items as $item)
                            @php $automation = $item['model']; @endphp
                            <div wire:key="auto-{{ $automation->id }}" class="{{ $panelBox }} p-3 {{ $automation->is_active ? '' : 'opacity-60' }}">
                                <div class="mb-2 flex items-center gap-1">
                                    @if ($isButtonSection)
                                        <span class="mr-auto flex items-center gap-1.5 text-sm font-medium"><x-dynamic-component :component="'phosphor-'.(($automation->trigger_config['icon'] ?? null) ?: 'lightning')" class="h-4 w-4 text-amber-500"/>{{ $automation->name }}</span>
                                    @else
                                        <span class="mr-auto"></span>
                                    @endif
                                    <button type="button" wire:click="startEdit({{ $automation->id }})" title="{{ __('Modifier') }}"
                                            class="rounded-md p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"><x-phosphor-pencil-simple class="h-4 w-4"/></button>
                                    <button type="button" wire:click="duplicateAutomation({{ $automation->id }})" title="{{ __('Dupliquer') }}"
                                            class="rounded-md p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"><x-phosphor-copy class="h-4 w-4"/></button>
                                    <button type="button" title="{{ __('Supprimer') }}"
                                            x-data @click="$store.confirm.open({ title: '{{ __('Supprimer l’automatisation') }}', message: '{{ __('Supprimer définitivement cette règle ?') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteAutomation({{ $automation->id }}))"
                                            class="rounded-md p-1.5 text-neutral-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"><x-phosphor-trash class="h-4 w-4"/></button>
                                </div>

                                <p class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 font-mono text-[13px] leading-relaxed text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-200">{{ $item['sentence'] }}</p>

                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                    <button type="button" wire:click="toggleActive({{ $automation->id }})" class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                                        <span class="relative inline-flex h-5 w-9 items-center rounded-full transition {{ $automation->is_active ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-700' }}">
                                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $automation->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                        </span>
                                        {{ __('Activer dans ce tableau') }}
                                    </button>
                                    @if ($automation->runs_count > 0)
                                        <span class="text-xs text-neutral-400" title="{{ $automation->last_run_at?->translatedFormat('d M Y H:i') }}">
                                            {{ trans_choice('{1}:count exécution|[2,*]:count exécutions', $automation->runs_count, ['count' => $automation->runs_count]) }}@if ($automation->failures_count > 0) · <span class="text-red-500">{{ trans_choice('{1}:count échec|[2,*]:count échecs', $automation->failures_count, ['count' => $automation->failures_count]) }}</span>@endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="{{ $panelBox }} p-8 text-center text-sm text-neutral-400">
                                {{ __('Aucune automatisation dans cette section. Créez-en une pour laisser le tableau travailler à votre place.') }}
                            </div>
                        @endforelse
                    </div>
                @else
                    {{-- ============ Wizard ============ --}}
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h1 class="text-xl font-semibold">{{ $editingId ? __('Modifier la règle') : __('Créer une règle') }}</h1>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="save"
                                    @if (! ($this->triggerReady() && count($actions) > 0)) disabled @endif
                                    class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">
                                {{ __('Enregistrer') }}
                            </button>
                            <button type="button" wire:click="cancelBuild"
                                    class="rounded-lg border border-neutral-300 px-3.5 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">
                                {{ __('Annuler') }}
                            </button>
                        </div>
                    </div>

                    {{-- Breadcrumb --}}
                    <div class="mb-6 flex items-center justify-center gap-2 border-b border-neutral-200 pb-4 text-sm dark:border-neutral-800">
                        @php
                            $steps = $hasTriggerStep
                                ? [1 => __('Déclencheur'), 2 => __('Actions'), 3 => __('Vérifier et enregistrer')]
                                : [2 => __('Actions'), 3 => __('Vérifier et enregistrer')];
                        @endphp
                        @foreach ($steps as $n => $stepLabel)
                            @if (! $loop->first)<x-phosphor-caret-right class="h-3.5 w-3.5 text-neutral-300 dark:text-neutral-600"/>@endif
                            <button type="button" wire:click="goToStep({{ $n }})" class="flex items-center gap-1.5 {{ $step === $n ? 'font-semibold' : 'text-neutral-400' }}">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs {{ $step > $n ? 'bg-green-500 text-white' : ($step === $n ? 'bg-indigo-600 text-white' : 'bg-neutral-200 text-neutral-500 dark:bg-neutral-800') }}">
                                    @if ($step > $n)<x-phosphor-check class="h-3.5 w-3.5"/>@else{{ $loop->iteration }}@endif
                                </span>
                                <span class="hidden sm:inline">{{ $stepLabel }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- ---- Step 1: trigger ---- --}}
                    @if ($step === 1 && $hasTriggerStep)
                        @if ($section === 'rules')
                            <div x-data="{ cat: 'move' }">
                                <div class="mb-4 flex flex-wrap gap-1.5">
                                    @foreach (\App\Livewire\Boards\Automations::TRIGGER_CATEGORIES as $catKey => $keys)
                                        <button type="button" @click="cat = '{{ $catKey }}'"
                                                class="flex flex-col items-center gap-1 rounded-lg border px-3 py-2 text-xs transition"
                                                :class="cat === '{{ $catKey }}' ? 'border-indigo-400 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-200 text-neutral-500 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800'">
                                            <x-dynamic-component :component="'phosphor-'.$categoryLabels[$catKey]['icon']" class="h-4 w-4"/>
                                            {{ $categoryLabels[$catKey]['label'] }}
                                        </button>
                                    @endforeach
                                </div>

                                @foreach (\App\Livewire\Boards\Automations::TRIGGER_CATEGORIES as $catKey => $keys)
                                    <div x-show="cat === '{{ $catKey }}'" class="space-y-1.5">
                                        @foreach ($keys as $key)
                                            <button type="button" wire:click="pickTrigger('{{ $key }}')"
                                                    class="{{ $rowBtn }} {{ $triggerType === $key ? 'border-indigo-400 bg-indigo-50 dark:border-indigo-500/40 dark:bg-indigo-500/10' : 'border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800' }}">
                                                <span>{{ __($registryTriggers[$key]?->label() ?? $key) }}</span>
                                                @if ($triggerType === $key)<x-phosphor-check-circle class="h-4 w-4 shrink-0 text-indigo-500"/>@else<x-phosphor-plus-circle class="h-4 w-4 shrink-0 text-neutral-300 dark:text-neutral-600"/>@endif
                                            </button>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($section === 'scheduled')
                            <div class="space-y-1.5">
                                @php $freqLabels = [
                                    'daily' => __('Tous les jours'),
                                    'days' => __('Certains jours de la semaine'),
                                    'every_n_weeks' => __('Toutes les N semaines'),
                                    'monthly_first_dow' => __('Le premier jour X du mois'),
                                    'monthly_day' => __('Chaque mois à date fixe'),
                                    'yearly' => __('Chaque année'),
                                ]; @endphp
                                @foreach ($freqLabels as $freq => $freqLabel)
                                    <button type="button" wire:click="pickSchedule('{{ $freq }}')"
                                            class="{{ $rowBtn }} {{ ($triggerConfig['freq'] ?? '') === $freq ? 'border-indigo-400 bg-indigo-50 dark:border-indigo-500/40 dark:bg-indigo-500/10' : 'border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800' }}">
                                        <span>{{ $freqLabel }}</span>
                                        @if (($triggerConfig['freq'] ?? '') === $freq)<x-phosphor-check-circle class="h-4 w-4 shrink-0 text-indigo-500"/>@else<x-phosphor-plus-circle class="h-4 w-4 shrink-0 text-neutral-300 dark:text-neutral-600"/>@endif
                                    </button>
                                @endforeach
                            </div>
                        @elseif ($section === 'due')
                            <div class="{{ $panelBox }} flex flex-wrap items-center gap-3 p-4 text-sm">
                                <input type="number" min="1" wire:model.live="triggerConfig.days" class="w-24 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 focus:border-indigo-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                <x-select class="w-40"
                                    :options="[['value' => 'before', 'label' => __('jours avant')], ['value' => 'after', 'label' => __('jours après')]]"
                                    :value="$triggerConfig['direction'] ?? 'before'"
                                    @select-change="$wire.set('triggerConfig.direction', $event.detail)"
                                />
                                <span class="text-neutral-500">{{ __("l'échéance de la carte") }}</span>
                            </div>
                        @endif

                        {{-- Trigger config zone --}}
                        @if ($triggerType !== '' && $section === 'rules' && ($registryTriggers[$triggerType]?->configFields() ?? []) !== [])
                            <div class="{{ $panelBox }} mt-4 p-4">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Configuration du déclencheur') }}</p>
                                @include('livewire.boards.partials.automation-config-fields', [
                                    'fields' => $registryTriggers[$triggerType]->configFields(),
                                    'path' => 'triggerConfig',
                                    'values' => $triggerConfig,
                                ])
                            </div>
                        @endif

                        @if ($section === 'scheduled' && ($triggerConfig['freq'] ?? '') !== '')
                            <div class="{{ $panelBox }} mt-4 grid gap-3 p-4 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Heure') }}</label>
                                    <input type="time" wire:model.live="triggerConfig.at" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                </div>
                                @if (in_array($triggerConfig['freq'], ['days', 'every_n_weeks'], true))
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Jours') }}</label>
                                        <x-select multiple clearable
                                            :options="[
                                                ['value' => 'monday', 'label' => __('lundi')], ['value' => 'tuesday', 'label' => __('mardi')],
                                                ['value' => 'wednesday', 'label' => __('mercredi')], ['value' => 'thursday', 'label' => __('jeudi')],
                                                ['value' => 'friday', 'label' => __('vendredi')], ['value' => 'saturday', 'label' => __('samedi')],
                                                ['value' => 'sunday', 'label' => __('dimanche')],
                                            ]"
                                            :value="$triggerConfig['days'] ?? []"
                                            @select-change="$wire.set('triggerConfig.days', $event.detail)"
                                        />
                                    </div>
                                @endif
                                @if ($triggerConfig['freq'] === 'every_n_weeks')
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Toutes les N semaines') }}</label>
                                        <input type="number" min="1" wire:model.live="triggerConfig.n" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                    </div>
                                @endif
                                @if ($triggerConfig['freq'] === 'monthly_first_dow')
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Jour de la semaine') }}</label>
                                        <x-select
                                            :options="[
                                                ['value' => 'monday', 'label' => __('lundi')], ['value' => 'tuesday', 'label' => __('mardi')],
                                                ['value' => 'wednesday', 'label' => __('mercredi')], ['value' => 'thursday', 'label' => __('jeudi')],
                                                ['value' => 'friday', 'label' => __('vendredi')], ['value' => 'saturday', 'label' => __('samedi')],
                                                ['value' => 'sunday', 'label' => __('dimanche')],
                                            ]"
                                            :value="$triggerConfig['dow'] ?? 'monday'"
                                            @select-change="$wire.set('triggerConfig.dow', $event.detail)"
                                        />
                                    </div>
                                @endif
                                @if (in_array($triggerConfig['freq'], ['monthly_day', 'yearly'], true))
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Jour du mois') }}</label>
                                        <input type="number" min="1" max="31" wire:model.live="triggerConfig.day" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                    </div>
                                @endif
                                @if ($triggerConfig['freq'] === 'yearly')
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Mois (1-12)') }}</label>
                                        <input type="number" min="1" max="12" wire:model.live="triggerConfig.month" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($section === 'rules' && $triggerType !== '')
                            <label class="mt-4 flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                                <input type="checkbox" @checked($actorScope === 'me')
                                       wire:change="$set('actorScope', $event.target.checked ? 'me' : 'anyone')"
                                       class="h-4 w-4 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500/40 dark:border-neutral-600 dark:bg-neutral-800">
                                {{ __('Uniquement quand c’est moi qui déclenche (« par moi »)') }}
                            </label>
                        @endif

                        @error('triggerType')<p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror

                        <div class="mt-6 flex justify-end">
                            <button type="button" wire:click="goToStep(2)"
                                    @if (! $this->triggerReady()) disabled @endif
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">
                                {{ __('Continuer') }}
                            </button>
                        </div>
                    @endif

                    {{-- ---- Step 2: actions (+ conditions) ---- --}}
                    @if ($step === 2)
                        <div class="grid gap-6 lg:grid-cols-2">
                            {{-- Catalog --}}
                            <div x-data="{ cat: 'move' }">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Ajouter une action') }}</p>
                                @if (in_array($section, ['scheduled', 'board_buttons'], true))
                                    <div class="space-y-1.5">
                                        @foreach (\App\Livewire\Boards\Automations::SCHEDULED_ACTIONS as $key)
                                            <button type="button" wire:click="addAction('{{ $key }}')"
                                                    class="{{ $rowBtn }} border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800">
                                                <span>{{ __($registryActions[$key]?->label() ?? $key) }}</span>
                                                <x-phosphor-plus-circle class="h-4 w-4 shrink-0 text-indigo-400"/>
                                            </button>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="mb-3 flex flex-wrap gap-1.5">
                                        @foreach (\App\Livewire\Boards\Automations::ACTION_CATEGORIES as $catKey => $keys)
                                            <button type="button" @click="cat = '{{ $catKey }}'"
                                                    class="rounded-lg border px-2.5 py-1.5 text-xs transition"
                                                    :class="cat === '{{ $catKey }}' ? 'border-indigo-400 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-200 text-neutral-500 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800'">
                                                {{ $categoryLabels[$catKey]['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                    @foreach (\App\Livewire\Boards\Automations::ACTION_CATEGORIES as $catKey => $keys)
                                        <div x-show="cat === '{{ $catKey }}'" class="space-y-1.5">
                                            @foreach ($keys as $key)
                                                <button type="button" wire:click="addAction('{{ $key }}')"
                                                        class="{{ $rowBtn }} border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800">
                                                    <span>{{ __($registryActions[$key]?->label() ?? $key) }}</span>
                                                    <x-phosphor-plus-circle class="h-4 w-4 shrink-0 text-indigo-400"/>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Pipeline --}}
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Pipeline de la règle (dans l’ordre)') }}</p>
                                <div class="space-y-2">
                                    @forelse ($actions as $i => $action)
                                        <div wire:key="act-{{ $i }}-{{ $action['type'] }}" class="{{ $panelBox }} p-3">
                                            <div class="mb-2 flex items-center gap-1">
                                                <span class="mr-auto flex items-center gap-2 text-sm font-medium">
                                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[11px] font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">{{ $i + 1 }}</span>
                                                    {{ __($registryActions[$action['type']]?->label() ?? $action['type']) }}
                                                </span>
                                                <button type="button" wire:click="moveAction({{ $i }}, -1)" @disabled($i === 0) class="rounded p-1 text-neutral-400 transition hover:text-neutral-700 disabled:opacity-30 dark:hover:text-neutral-200"><x-phosphor-caret-up class="h-4 w-4"/></button>
                                                <button type="button" wire:click="moveAction({{ $i }}, 1)" @disabled($i === count($actions) - 1) class="rounded p-1 text-neutral-400 transition hover:text-neutral-700 disabled:opacity-30 dark:hover:text-neutral-200"><x-phosphor-caret-down class="h-4 w-4"/></button>
                                                <button type="button" wire:click="removeAction({{ $i }})" class="rounded p-1 text-neutral-400 transition hover:text-red-500"><x-phosphor-x class="h-4 w-4"/></button>
                                            </div>
                                            @if (($registryActions[$action['type']]?->configFields() ?? []) !== [])
                                                @include('livewire.boards.partials.automation-config-fields', [
                                                    'fields' => $registryActions[$action['type']]->configFields(),
                                                    'path' => "actions.{$i}.config",
                                                    'values' => $action['config'] ?? [],
                                                    'allowMe' => true,
                                                ])
                                            @endif
                                        </div>
                                    @empty
                                        <div class="{{ $panelBox }} border-dashed p-6 text-center text-sm text-neutral-400">
                                            {{ __('Votre automatisation n’a pas encore d’action. Ajoutez-en depuis le catalogue.') }}
                                        </div>
                                    @endforelse
                                </div>
                                @error('actions')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror

                                {{-- Conditions (event rules and card buttons only) --}}
                                @if (! in_array($section, ['scheduled', 'board_buttons'], true))
                                    <p class="mb-2 mt-6 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Et seulement si… (conditions, toutes requises)') }}</p>
                                    <div class="space-y-2">
                                        @foreach ($conditions as $i => $condition)
                                            <div wire:key="cond-{{ $i }}-{{ $condition['type'] }}" class="{{ $panelBox }} p-3">
                                                <div class="mb-2 flex items-center gap-1">
                                                    <span class="mr-auto text-sm font-medium">{{ __($registryConditions[$condition['type']]?->label() ?? $condition['type']) }}</span>
                                                    <button type="button" wire:click="removeCondition({{ $i }})" class="rounded p-1 text-neutral-400 transition hover:text-red-500"><x-phosphor-x class="h-4 w-4"/></button>
                                                </div>
                                                @if (($registryConditions[$condition['type']]?->configFields() ?? []) !== [])
                                                    @include('livewire.boards.partials.automation-config-fields', [
                                                        'fields' => $registryConditions[$condition['type']]->configFields(),
                                                        'path' => "conditions.{$i}.config",
                                                        'values' => $condition['config'] ?? [],
                                                    ])
                                                @endif
                                            </div>
                                        @endforeach
                                        <x-select
                                            :options="collect($registryConditions)->map(fn ($c, $k) => ['value' => $k, 'label' => __($c->label())])->values()->all()"
                                            :value="null"
                                            :placeholder="__('+ Ajouter une condition')"
                                            @select-change="$wire.addCondition($event.detail)"
                                        />
                                    </div>
                                @endif

                                <div class="mt-6 flex justify-end">
                                    <button type="button" wire:click="goToStep(3)"
                                            @if (count($actions) === 0) disabled @endif
                                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">
                                        {{ __('Continuer') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- ---- Step 3: review ---- --}}
                    @if ($step === 3)
                        <div class="space-y-5">
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Votre règle') }}</p>
                                <p class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 font-mono text-sm leading-relaxed text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-200">{{ $this->previewSentence() }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ $isButtonSection ? __('Nom du bouton') : __('Nom (optionnel — la phrase par défaut)') }}</label>
                                <input type="text" wire:model="name" maxlength="255"
                                       class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>

                            @if ($isButtonSection)
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ __('Icône du bouton') }}</label>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($buttonIcons as $icon)
                                            <button type="button" wire:click="$set('triggerConfig.icon', '{{ $icon }}')" title="{{ $icon }}"
                                                    class="flex h-9 w-9 items-center justify-center rounded-lg border transition {{ ($triggerConfig['icon'] ?? 'lightning') === $icon ? 'border-indigo-400 bg-indigo-50 text-indigo-600 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-200 text-neutral-500 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800' }}">
                                                <x-dynamic-component :component="'phosphor-'.$icon" class="h-4 w-4"/>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="goToStep(2)" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Retour') }}</button>
                                <button type="button" wire:click="save" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">{{ __('Enregistrer') }}</button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
