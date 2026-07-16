{{-- Checklists with items (assignee, due date, progress).
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
                        {{-- Checklists — the section only exists when the card has some --}}
                        @if ($card->checklists->isNotEmpty())
                        <div class="space-y-4">
                            @foreach ($card->checklists as $checklist)
                                @php
                                    $total = $checklist->items->count();
                                    $done = $checklist->items->where('is_completed', true)->count();
                                    $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
                                @endphp
                                <div
                                    wire:key="checklist-{{ $checklist->id }}"
                                    x-data="{ collapsed: JSON.parse(localStorage.getItem('checklist-collapsed-{{ $checklist->id }}') ?? 'false') }"
                                    class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <button type="button" @click="collapsed = ! collapsed; localStorage.setItem('checklist-collapsed-{{ $checklist->id }}', collapsed)" class="flex min-w-0 flex-1 items-center gap-1.5 text-left" title="{{ __('Replier / déplier') }}">
                                            <x-phosphor-check-square class="h-4 w-4 shrink-0 text-neutral-400"/>
                                            <span class="truncate text-sm font-medium">{{ $checklist->title }}</span>
                                            <span class="shrink-0 rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">{{ $done }}/{{ $total }}</span>
                                        </button>
                                        @if ($canContribute)<button type="button" wire:click="deleteChecklist({{ $checklist->id }})" class="shrink-0 rounded-md border border-neutral-200 px-2 py-0.5 text-xs text-neutral-400 transition hover:border-red-200 hover:text-red-500 dark:border-neutral-700">{{ __('Supprimer') }}</button>@endif
                                    </div>

                                    <div x-show="! collapsed" x-cloak class="mt-2">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="w-8 shrink-0 text-right text-[11px] tabular-nums text-neutral-400">{{ $pct }}%</span>
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                            <div class="h-full rounded-full bg-green-500" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>

                                    <ul class="space-y-1">
                                        @foreach ($checklist->items as $item)
                                            @php $itemOverdue = $item->due_at && ! $item->is_completed && $item->due_at->isPast(); @endphp
                                            {{-- Optimistic toggle: `done` flips instantly client-side, the server
                                                 confirms after. Server classes are the no-FOUC truth; the completion
                                                 state is part of wire:key so a server change re-seeds `done`. --}}
                                            <li wire:key="chk-item-{{ $item->id }}-{{ (int) $item->is_completed }}"
                                                x-data="{ done: {{ $item->is_completed ? 'true' : 'false' }} }"
                                                class="group flex items-center gap-2 rounded px-1 py-0.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800/60">
                                                @if ($canContribute)
                                                <button type="button" @click="done = ! done; $wire.toggleChecklistItem({{ $item->id }})"
                                                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition {{ $item->is_completed ? 'border-green-500 bg-green-500 text-white' : 'border-neutral-300 hover:border-green-400 dark:border-neutral-600' }}"
                                                        :class="{
                                                            'border-green-500 bg-green-500 text-white': done,
                                                            'border-neutral-300 hover:border-green-400 dark:border-neutral-600': ! done,
                                                        }">
                                                    <x-phosphor-check class="h-3 w-3" x-show="done" @style(['display: none' => ! $item->is_completed])/>
                                                </button>
                                                <span class="min-w-0 flex-1 break-words {{ $item->is_completed ? 'text-neutral-400 line-through' : '' }}"
                                                      :class="{ 'text-neutral-400 line-through': done }">{{ $item->content }}</span>
                                                <div class="ml-auto flex shrink-0 items-center gap-1.5">
                                                    @if ($item->due_at)
                                                        <span class="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs {{ $itemOverdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300' }}"><x-phosphor-calendar-blank class="h-3.5 w-3.5"/>{{ $item->due_at->translatedFormat('d M') }}</span>
                                                    @endif
                                                    @if ($item->assignee)
                                                        <x-user-avatar :user="$item->assignee" size="xs" :hover-card="false" />
                                                    @endif
                                                    <div x-data="{ open: false }" class="relative opacity-100 sm:opacity-0 sm:group-hover:opacity-100">
                                                        <button type="button" @click="open = ! open" class="rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"><x-phosphor-dots-three class="h-4 w-4"/></button>
                                                        <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-52 rounded-lg border border-neutral-200 bg-white p-1.5 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                                            <p class="px-1 pb-1 text-[10px] font-medium uppercase tracking-wide text-neutral-400">{{ __('Assigner à') }}</p>
                                                            <div class="max-h-36 overflow-y-auto">
                                                                @foreach ($boardMembers as $checklistMember)
                                                                    <button type="button" wire:click="assignChecklistItem({{ $item->id }}, {{ $checklistMember->id }})" @click="open = false" class="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left text-xs hover:bg-neutral-100 dark:hover:bg-neutral-800 {{ $item->assigned_to === $checklistMember->id ? 'font-semibold text-indigo-600 dark:text-indigo-400' : '' }}">
                                                                        <x-user-avatar :user="$checklistMember" size="xs" :hover-card="false" />
                                                                        <span class="truncate">{{ $checklistMember->name }}</span>
                                                                    </button>
                                                                @endforeach
                                                            </div>
                                                            @if ($item->assigned_to)
                                                                <button type="button" wire:click="assignChecklistItem({{ $item->id }}, null)" @click="open = false" class="mt-0.5 w-full rounded px-1.5 py-1 text-left text-xs text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ __('Retirer l’assignation') }}</button>
                                                            @endif
                                                            <p class="border-t border-neutral-100 px-1 pb-1 pt-1.5 text-[10px] font-medium uppercase tracking-wide text-neutral-400 dark:border-neutral-800">{{ __('Échéance') }}</p>
                                                            <input type="date" value="{{ $item->due_at?->format('Y-m-d') }}" @change="$wire.setChecklistItemDue({{ $item->id }}, $event.target.value); open = false" class="w-full rounded border border-neutral-200 bg-white px-1.5 py-1 text-xs dark:border-neutral-700 dark:bg-neutral-800">
                                                            @if ($item->due_at)
                                                                <button type="button" wire:click="setChecklistItemDue({{ $item->id }}, null)" @click="open = false" class="mt-0.5 w-full rounded px-1.5 py-1 text-left text-xs text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ __('Retirer l’échéance') }}</button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <button type="button" wire:click="deleteChecklistItem({{ $item->id }})" class="text-neutral-300 opacity-100 hover:text-red-500 group-hover:opacity-100 dark:text-neutral-600 sm:opacity-0"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                                </div>
                                                @else
                                                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border {{ $item->is_completed ? 'border-green-500 bg-green-500 text-white' : 'border-neutral-300 dark:border-neutral-600' }}">@if ($item->is_completed)<x-phosphor-check class="h-3 w-3"/>@endif</span>
                                                <span class="min-w-0 flex-1 {{ $item->is_completed ? 'text-neutral-400 line-through' : '' }}">{{ $item->content }}</span>
                                                <div class="ml-auto flex shrink-0 items-center gap-1.5">
                                                    @if ($item->due_at)
                                                        <span class="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs {{ $itemOverdue ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300' }}"><x-phosphor-calendar-blank class="h-3.5 w-3.5"/>{{ $item->due_at->translatedFormat('d M') }}</span>
                                                    @endif
                                                    @if ($item->assignee)<x-user-avatar :user="$item->assignee" size="xs" :hover-card="false" />@endif
                                                </div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if ($canContribute)
                                    <form wire:submit="addChecklistItem({{ $checklist->id }})" class="mt-2">
                                        <input type="text" wire:model="newChecklistItem.{{ $checklist->id }}" placeholder="{{ __('+ Ajouter un élément') }}" class="w-full rounded border border-neutral-200 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    </form>
                                    @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @endif
