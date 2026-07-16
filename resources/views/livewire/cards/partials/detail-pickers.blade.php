{{-- Shared popovers (members / labels / dates pickers), driven by Alpine `openPicker`.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
                        {{-- Pickers (members / labels / dates) — shared popovers, also reachable from "Ajouter" --}}
                        @if ($canContribute)
                        <div class="relative" x-show="openPicker !== null" x-cloak style="margin-top: 0;">
                            <div x-transition @click.outside="openPicker = null" @keydown.escape.window="openPicker = null"
                                 class="absolute left-0 top-0 z-40 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-neutral-200 bg-white p-3 shadow-[0_12px_32px_rgba(0,0,0,0.25)] dark:border-neutral-700 dark:bg-neutral-800 dark:shadow-[0_12px_32px_rgba(0,0,0,0.55)]">
                                <div class="relative mb-2 flex h-8 items-center justify-center">
                                    <p class="text-sm font-medium text-neutral-600 dark:text-neutral-300"
                                       x-text="openPicker === 'members' ? @js(__('Membres')) : (openPicker === 'labels' ? @js(__('Labels')) : @js(__('Dates')))"></p>
                                    <button type="button" @click="openPicker = null"
                                            class="absolute right-0 top-0 flex h-8 w-8 items-center justify-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-white"
                                            aria-label="{{ __('Fermer') }}">
                                        <x-phosphor-x class="h-4 w-4"/>
                                    </button>
                                </div>

                                {{-- Members picker --}}
                                <div x-show="openPicker === 'members'" id="card-members" class="space-y-1">
                                    @foreach ($boardMembers as $member)
                                        @php $assigned = $card->members->contains($member->id); @endphp
                                        <button type="button" wire:click="toggleMember({{ $member->id }})" class="flex w-full items-center gap-2 rounded-lg px-2 py-1 text-left text-sm {{ $assigned ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-neutral-100 dark:hover:bg-neutral-700/50' }}">
                                            <x-user-avatar :user="$member" size="sm" />
                                            <span class="truncate">{{ $member->name }}</span>
                                            @if ($assigned) <x-phosphor-check class="ml-auto h-4 w-4 text-indigo-600 dark:text-indigo-400" /> @endif
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Labels picker --}}
                                <div x-show="openPicker === 'labels'" id="card-labels">
                                    @php $labelPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b']; @endphp
                                    <div class="space-y-2">
                                        @foreach ($boardLabels as $label)
                                            @php $on = $card->labels->contains($label->id); @endphp
                                            <x-context-menu wire:key="label-{{ $label->id }}" class="group/label flex items-center gap-1">
                                                <x-slot:trigger>
                                                    <button type="button" wire:click="toggleLabel({{ $label->id }})" class="flex flex-1 items-center gap-2 rounded-lg px-2 py-1 text-left text-sm {{ $on ? 'ring-2 ring-indigo-400' : 'hover:bg-neutral-100 dark:hover:bg-neutral-700/50' }}">
                                                        <span class="h-3 w-6 shrink-0 rounded-full" style="background-color: {{ $label->color }}"></span>
                                                        <span class="truncate">{{ $label->name ?? '—' }}</span>
                                                    </button>
                                                    <button type="button" @click="openAt($event.clientX, $event.clientY)" class="shrink-0 rounded p-1 text-neutral-400 opacity-100 transition hover:bg-neutral-100 hover:text-neutral-700 group-hover/label:opacity-100 sm:opacity-0 dark:hover:bg-neutral-700 dark:hover:text-neutral-200" title="{{ __('Options du label (clic droit aussi)') }}"><x-phosphor-dots-three class="h-4 w-4" /></button>
                                                </x-slot:trigger>
                                                <x-slot:menu>
                                                    <div class="p-1" x-data="{ name: @js($label->name) }" @click.stop>
                                                        <input
                                                            type="text"
                                                            x-model="name"
                                                            @keydown.enter="$wire.renameLabel({{ $label->id }}, name); shown = false"
                                                            placeholder="{{ __('Nom du label') }}"
                                                            class="w-full rounded-md border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900"
                                                        >
                                                    </div>
                                                    <div class="flex flex-wrap gap-1.5 px-2 py-1.5">
                                                        @foreach ($labelPalette as $swatch)
                                                            <button type="button" @click="$wire.recolorLabel({{ $label->id }}, '{{ $swatch }}'); shown = false" class="h-5 w-5 rounded-full ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-800" style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
                                                        @endforeach
                                                    </div>
                                                    <x-context-menu.separator />
                                                    <x-context-menu.item icon="hash" @click="navigator.clipboard?.writeText('{{ $label->public_id }}'); window.toast('{{ __('ID copié') }}', { type: 'success' })">{{ __("Copier l'ID") }}</x-context-menu.item>
                                                    <x-context-menu.item icon="trash" variant="danger" @click="$store.confirm.open({ title: '{{ __('Supprimer le label') }}', message: '{{ __('Supprimer ce label du board ?') }}', confirmLabel: '{{ __('Supprimer') }}', danger: true }).then(ok => ok && $wire.deleteLabel({{ $label->id }}))">{{ __('Supprimer') }}</x-context-menu.item>
                                                </x-slot:menu>
                                            </x-context-menu>
                                        @endforeach
                                    </div>
                                    <form wire:submit="createLabel" class="mt-2 flex items-center gap-2">
                                        <input type="color" wire:model="newLabelColor" class="h-8 w-8 rounded border border-neutral-300 dark:border-neutral-700">
                                        <input type="text" wire:model="newLabelName" placeholder="{{ __('Nouveau label') }}" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                        <button type="submit" class="shrink-0 rounded-lg border border-neutral-300 px-2 py-1 text-sm hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-700/50">+</button>
                                    </form>
                                </div>

                                {{-- Dates picker --}}
                                <div x-show="openPicker === 'dates'" id="card-dates" class="space-y-2">
                                    <div>
                                        <label class="mb-0.5 block text-xs text-neutral-500">{{ __('Début') }}</label>
                                        <div class="flex gap-2">
                                            <input type="date" wire:model="startDate" wire:change="saveDates" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                            <input type="time" wire:model="startTime" wire:change="saveDates" aria-label="{{ __('Heure de début') }}" class="w-28 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-0.5 block text-xs text-neutral-500">{{ __('Échéance') }}</label>
                                        <div class="flex gap-2">
                                            <input type="date" wire:model="dueDate" wire:change="saveDates" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                            <input type="time" wire:model="dueTime" wire:change="saveDates" aria-label="{{ __('Heure d’échéance') }}" class="w-28 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-600 dark:bg-neutral-900">
                                        </div>
                                        @error('dueDate') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        <p class="mt-1 text-[11px] text-neutral-400">{{ __('Heure optionnelle (12:00 par défaut).') }}</p>
                                    </div>
                                    @if ($card->start_at || $card->due_at)
                                        <div class="flex items-center justify-end text-xs">
                                            <button type="button" wire:click="clearDates" @click="openPicker = null" class="text-neutral-400 hover:text-red-500">{{ __('Retirer') }}</button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif
