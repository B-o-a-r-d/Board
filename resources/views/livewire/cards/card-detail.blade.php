<div>
    @if ($showModal && $card)
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8" wire:key="card-modal-{{ $card->id }}">
            <div class="relative w-full max-w-3xl rounded-2xl bg-white shadow-xl dark:bg-neutral-900" @click.outside="$wire.close()">
                {{-- Cover --}}
                @if ($card->cover_path)
                    <img src="{{ Storage::disk('public')->url($card->cover_path) }}" alt="" class="h-40 w-full rounded-t-2xl object-cover">
                @endif

                <button type="button" wire:click="close" class="absolute right-3 top-3 rounded-full bg-white/80 p-1.5 text-neutral-600 shadow hover:bg-white dark:bg-neutral-800/80 dark:text-neutral-300"><x-phosphor-x class="h-5 w-5" /></button>

                <div class="grid gap-6 p-6 sm:grid-cols-3 mt-6">
                    {{-- Main column --}}
                    <div class="space-y-6 sm:col-span-2">
                        <form wire:submit="saveDetails" class="space-y-3">
                            <input
                                type="text"
                                wire:model="title"
                                class="w-full rounded-lg border border-transparent bg-transparent px-2 py-1 text-lg font-semibold hover:bg-neutral-100 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                            >
                            @error('title') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                            <div>
                                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-neutral-500">Description (markdown)</label>
                                <textarea
                                    wire:model="description"
                                    rows="5"
                                    placeholder="Ajoutez une description en markdown…"
                                    class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                                ></textarea>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-neutral-500">Échéance</label>
                                <input type="datetime-local" wire:model="dueAt" class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                            </div>

                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Enregistrer</button>
                        </form>

                        @if (filled($card->description))
                            <div>
                                <h3 class="mb-1 text-xs font-medium uppercase tracking-wide text-neutral-500">Aperçu</h3>
                                <div class="markdown rounded-lg bg-neutral-50 p-3 text-sm dark:bg-neutral-800/50">
                                    {!! Str::markdown($card->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                </div>
                            </div>
                        @endif

                        {{-- Checklists --}}
                        <div class="space-y-4">
                            @foreach ($card->checklists as $checklist)
                                @php
                                    $total = $checklist->items->count();
                                    $done = $checklist->items->where('is_completed', true)->count();
                                    $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
                                @endphp
                                <div wire:key="checklist-{{ $checklist->id }}" class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="text-sm font-medium">{{ $checklist->title }}</span>
                                        <button type="button" wire:click="deleteChecklist({{ $checklist->id }})" class="text-xs text-neutral-400 hover:text-red-500">Supprimer</button>
                                    </div>
                                    <div class="mb-2 flex items-center gap-2">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                                            <div class="h-full rounded-full bg-green-500" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="text-xs text-neutral-500">{{ $pct }}%</span>
                                    </div>
                                    <ul class="space-y-1">
                                        @foreach ($checklist->items as $item)
                                            <li wire:key="item-{{ $item->id }}" class="group flex items-center gap-2 text-sm">
                                                <input type="checkbox" @checked($item->is_completed) wire:click="toggleChecklistItem({{ $item->id }})" class="rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500 dark:border-neutral-600 dark:bg-neutral-800">
                                                <span class="{{ $item->is_completed ? 'text-neutral-400 line-through' : '' }}">{{ $item->content }}</span>
                                                <button type="button" wire:click="deleteChecklistItem({{ $item->id }})" class="ml-auto text-xs text-neutral-300 opacity-0 group-hover:opacity-100 hover:text-red-500">✕</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <form wire:submit="addChecklistItem({{ $checklist->id }})" class="mt-2">
                                        <input type="text" wire:model="newChecklistItem.{{ $checklist->id }}" placeholder="+ Ajouter un élément" class="w-full rounded border border-neutral-200 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    </form>
                                </div>
                            @endforeach

                            <form wire:submit="addChecklist" class="flex gap-2">
                                <input type="text" wire:model="newChecklistTitle" placeholder="Nouvelle checklist" class="flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                <button type="submit" class="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">Ajouter</button>
                            </form>
                        </div>

                        {{-- Attachments --}}
                        <div class="space-y-3">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Pièces jointes</h3>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ($card->attachments as $attachment)
                                    <div wire:key="att-{{ $attachment->id }}" class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                                        @if ($attachment->isImage())
                                            <img src="{{ $attachment->url }}" alt="{{ $attachment->name }}" class="h-28 w-full object-cover">
                                        @elseif ($attachment->isVideo())
                                            <video src="{{ $attachment->url }}" controls class="h-28 w-full bg-black object-contain"></video>
                                        @endif
                                        <div class="flex items-center justify-between gap-1 p-2">
                                            <span class="truncate text-xs" title="{{ $attachment->name }}">{{ $attachment->name }}</span>
                                            <div class="flex shrink-0 gap-1">
                                                @if ($attachment->isImage())
                                                    <button type="button" wire:click="setCover({{ $attachment->id }})" class="text-xs text-neutral-400 hover:text-indigo-500" title="Définir comme couverture">★</button>
                                                @endif
                                                <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" class="text-xs text-neutral-400 hover:text-red-500">✕</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <form wire:submit="saveAttachment" class="flex items-center gap-2">
                                <input type="file" wire:model="upload" class="text-sm">
                                <button type="submit" class="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">Téléverser</button>
                            </form>
                            <div wire:loading wire:target="upload" class="text-xs text-neutral-500">Chargement du fichier…</div>
                            @error('upload') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Sidebar --}}
                    <div class="space-y-5">
                        <button type="button" wire:click="toggleComplete" class="w-full rounded-lg px-3 py-2 text-sm font-medium {{ $card->completed_at ? 'bg-green-600 text-white hover:bg-green-500' : 'border border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800' }}">
                            {{ $card->completed_at ? 'Terminée' : 'Marquer terminée' }}
                        </button>

                        {{-- Members --}}
                        <div>
                            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Membres</h3>
                            <div class="space-y-1">
                                @foreach ($boardMembers as $member)
                                    @php $assigned = $card->members->contains($member->id); @endphp
                                    <button type="button" wire:click="toggleMember({{ $member->id }})" class="flex w-full items-center gap-2 rounded-lg px-2 py-1 text-left text-sm {{ $assigned ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-neutral-100 dark:hover:bg-neutral-800' }}">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                                            {{ Str::of($member->name)->substr(0, 1)->upper() }}
                                        </span>
                                        <span class="truncate">{{ $member->name }}</span>
                                        @if ($assigned) <span class="ml-auto text-indigo-600 dark:text-indigo-400">✓</span> @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Labels --}}
                        <div>
                            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Labels</h3>
                            <div class="space-y-2">
                                @foreach ($boardLabels as $label)
                                    @php $on = $card->labels->contains($label->id); @endphp
                                    <button type="button" wire:click="toggleLabel({{ $label->id }})" class="flex w-full items-center gap-2 rounded-lg px-2 py-1 text-left text-sm {{ $on ? 'ring-2 ring-indigo-400' : 'hover:bg-neutral-100 dark:hover:bg-neutral-800' }}">
                                        <span class="h-3 w-6 rounded-full" style="background-color: {{ $label->color }}"></span>
                                        <span class="truncate">{{ $label->name ?? '—' }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <form wire:submit="createLabel" class="mt-2 flex items-center gap-2">
                                <input type="color" wire:model="newLabelColor" class="h-8 w-8 rounded border border-neutral-300 dark:border-neutral-700">
                                <input type="text" wire:model="newLabelName" placeholder="Nouveau label" class="flex-1 rounded-lg border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                <button type="submit" class="rounded-lg border border-neutral-300 px-2 py-1 text-sm hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">+</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
