<div>
    @if ($showTrigger)
        <button
            type="button"
            wire:click="open"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-300 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
            title="{{ __('Automations') }}"
            aria-label="{{ __('Automations du board') }}"
        >
            <x-phosphor-robot class="h-4 w-4" />
        </button>
    @endif

    @if ($showModal)
        <x-modal max-width="2xl" on-close="$wire.close()">
            <x-slot:header>
                <span class="flex items-center gap-2"><x-phosphor-robot class="h-5 w-5" /> {{ __('Automations') }}</span>
            </x-slot:header>

            <div class="space-y-4 p-5">
                {{-- Existing automations --}}
                <div class="space-y-2">
                    @forelse ($automations as $automation)
                        <div wire:key="auto-{{ $automation->id }}" class="flex items-start justify-between gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700 {{ $automation->is_active ? '' : 'opacity-60' }}">
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ $automation->name }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                    <span class="font-medium">{{ $triggers[$automation->trigger_type]?->label() ?? $automation->trigger_type }}</span>
                                    <x-phosphor-arrow-right class="inline h-3 w-3" />
                                    {{ $actions[$automation->action_type]?->label() ?? $automation->action_type }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" wire:click="startEdit({{ $automation->id }})" title="{{ __('Modifier') }}" class="rounded p-1 text-neutral-400 hover:text-indigo-600 dark:hover:text-indigo-400"><x-phosphor-pencil-simple class="h-4 w-4" /></button>
                                <button type="button" wire:click="toggleActive({{ $automation->id }})" title="{{ $automation->is_active ? __('Désactiver') : __('Activer') }}" class="rounded p-1 text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200">
                                    <x-dynamic-component :component="$automation->is_active ? 'phosphor-toggle-right' : 'phosphor-toggle-left'" class="h-5 w-5 {{ $automation->is_active ? 'text-indigo-600 dark:text-indigo-400' : '' }}" />
                                </button>
                                <button type="button" wire:click="deleteAutomation({{ $automation->id }})" title="{{ __('Supprimer') }}" class="rounded p-1 text-neutral-400 hover:text-red-500"><x-phosphor-x class="h-4 w-4" /></button>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg bg-neutral-50 px-3 py-4 text-center text-sm text-neutral-500 dark:bg-neutral-800/50 dark:text-neutral-400">{{ __('Aucune automation. Créez-en une pour automatiser ce board.') }}</p>
                    @endforelse
                </div>

                {{-- Builder --}}
                @if ($building)
                    @php
                        $selTrigger = $triggers[$triggerType] ?? null;
                        $selAction = $actions[$actionType] ?? null;
                    @endphp
                    <form wire:submit="save" class="space-y-3 rounded-lg border border-indigo-200 bg-indigo-50/40 p-3 dark:border-indigo-500/30 dark:bg-indigo-500/5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">{{ $editingId ? __("Modifier l'automation") : __('Nouvelle automation') }}</p>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Nom') }}</label>
                            <input type="text" wire:model="name" placeholder="{{ __('Ex : Terminer en entrant dans « Fait »') }}" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                            @error('name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            {{-- Trigger --}}
                            <div class="space-y-2 rounded-lg border border-neutral-200 p-2 dark:border-neutral-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Déclencheur') }}</p>
                                <select wire:model.live="triggerType" class="w-full rounded-lg border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    <option value="">{{ __('Choisir…') }}</option>
                                    @foreach ($triggers as $key => $trigger)
                                        <option value="{{ $key }}">{{ $trigger->label() }}</option>
                                    @endforeach
                                </select>
                                @error('triggerType') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                @foreach ($selTrigger?->configFields() ?? [] as $field)
                                    <div>
                                        <label class="mb-0.5 block text-xs text-neutral-500">{{ $field['label'] }}</label>
                                        @include('livewire.boards.partials.automation-field', ['field' => $field, 'model' => 'triggerConfig', 'lists' => $lists, 'labels' => $labels, 'members' => $members])
                                    </div>
                                @endforeach
                            </div>

                            {{-- Action --}}
                            <div class="space-y-2 rounded-lg border border-neutral-200 p-2 dark:border-neutral-700">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Action') }}</p>
                                <select wire:model.live="actionType" class="w-full rounded-lg border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                                    <option value="">{{ __('Choisir…') }}</option>
                                    @foreach ($actions as $key => $action)
                                        <option value="{{ $key }}">{{ $action->label() }}</option>
                                    @endforeach
                                </select>
                                @error('actionType') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                @foreach ($selAction?->configFields() ?? [] as $field)
                                    <div>
                                        <label class="mb-0.5 block text-xs text-neutral-500">{{ $field['label'] }}</label>
                                        @include('livewire.boards.partials.automation-field', ['field' => $field, 'model' => 'actionConfig', 'lists' => $lists, 'labels' => $labels, 'members' => $members])
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="$set('building', false)" class="rounded-lg px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">{{ $editingId ? __('Enregistrer') : __("Créer l'automation") }}</button>
                        </div>
                    </form>
                @else
                    <button type="button" wire:click="startCreate" class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-600 hover:border-indigo-400 hover:text-indigo-600 dark:border-neutral-700 dark:text-neutral-300 dark:hover:border-indigo-500 dark:hover:text-indigo-400">
                        <x-phosphor-plus class="h-4 w-4" /> {{ __('Nouvelle automation') }}
                    </button>
                @endif
            </div>
        </x-modal>
    @endif
</div>
