<div
    x-data="{
        handleCardFocus(raw) {
            const detail = raw?.params?.[0] ?? raw ?? {};
            if (detail.comment) {
                this.flashElement('comment-' + detail.comment);
            } else if (detail.section === 'attachments') {
                window.dispatchEvent(new CustomEvent('card-open-attachments'));
            }
        },
        flashElement(id) {
            setTimeout(() => {
                const el = document.getElementById(id);
                if (! el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('cf-flash');
                setTimeout(() => el.classList.remove('cf-flash'), 1800);
            }, 200);
        }
    }"
    @card-focus.window="handleCardFocus($event.detail)"
>
    @if ($showModal && $card)
        <x-modal max-width="3xl" on-close="$wire.close()" wire:key="card-modal-{{ $card->id }}">
                {{-- Cover --}}
                @if ($card->cover_path)
                    <img src="{{ Storage::disk('public')->url($card->cover_path) }}" alt="" class="h-40 w-full rounded-t-2xl object-cover">
                @elseif ($card->cover_color)
                    <div class="h-20 w-full rounded-t-2xl" style="background-color: {{ $card->cover_color }}"></div>
                @endif

                <button type="button" wire:click="close" class="absolute right-3 top-3 rounded-full bg-white/80 p-1.5 text-neutral-600 shadow hover:bg-white dark:bg-neutral-800/80 dark:text-neutral-300"><x-phosphor-x class="h-5 w-5" /></button>

                <div class="mt-6 grid gap-6 p-4 sm:grid-cols-3 sm:p-6">
                    {{-- Main column --}}
                    <div class="space-y-6 sm:col-span-2">
                        <form wire:submit="saveDetails" class="flex items-start gap-2">
                            <div class="flex-1">
                                <input
                                    type="text"
                                    wire:model="title"
                                    wire:keydown.enter.prevent="saveDetails"
                                    class="w-full rounded-lg border border-transparent bg-transparent px-2 py-1 text-lg font-semibold hover:bg-neutral-100 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                                >
                                @error('title') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" title="{{ __('Enregistrer') }}" class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-500"><x-phosphor-floppy-disk-duotone class="h-4 w-4" /></button>
                        </form>

                        {{-- Description : éditeur WYSIWYG (TipTap → markdown) --}}
                        @php
                            $tbBtn = 'flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-700';
                        @endphp
                        <div wire:key="desc-{{ $card->id }}" wire:ignore x-data="markdownEditor(@js((string) $card->description))">
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Description') }}</label>

                            {{-- Read mode --}}
                            <div x-ref="readview" x-show="! editing" @click="edit()" class="markdown min-h-[3rem] cursor-text rounded-lg border border-transparent bg-neutral-50 p-3 text-sm hover:border-neutral-300 dark:bg-neutral-800/50 dark:hover:border-neutral-700">
                                @if (filled($card->description))
                                    {!! Str::markdown($card->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                @else
                                    <span class="text-neutral-400">{{ __("Ajoutez une description… (cliquez pour éditer)") }}</span>
                                @endif
                            </div>

                            {{-- Edit mode --}}
                            <div x-show="editing" x-cloak class="rounded-lg border border-neutral-300 dark:border-neutral-700">
                                <div class="flex flex-wrap items-center gap-0.5 border-b border-neutral-200 p-1 dark:border-neutral-700">
                                    <button type="button" @click="run('toggleBold')" :class="isActive('bold') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-bold" title="{{ __('Gras') }}">B</button>
                                    <button type="button" @click="run('toggleItalic')" :class="isActive('italic') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} italic" title="{{ __('Italique') }}">I</button>
                                    <button type="button" @click="run('toggleStrike')" :class="isActive('strike') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} line-through" title="{{ __('Barré') }}">S</button>
                                    <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                    <button type="button" @click="run('toggleHeading', { level: 2 })" :class="isActive('heading', { level: 2 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('Titre') }}">H2</button>
                                    <button type="button" @click="run('toggleHeading', { level: 3 })" :class="isActive('heading', { level: 3 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="{{ __('Sous-titre') }}">H3</button>
                                    <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                    <button type="button" @click="run('toggleBulletList')" :class="isActive('bulletList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste à puces') }}"><x-phosphor-list-bullets class="h-4 w-4" /></button>
                                    <button type="button" @click="run('toggleOrderedList')" :class="isActive('orderedList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste numérotée') }}"><x-phosphor-list-numbers class="h-4 w-4" /></button>
                                    <button type="button" @click="run('toggleCodeBlock')" :class="isActive('codeBlock') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Bloc de code') }}"><x-phosphor-code class="h-4 w-4" /></button>
                                    <button type="button" @click="toggleLink()" :class="isActive('link') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Lien') }}"><x-phosphor-link class="h-4 w-4" /></button>
                                </div>

                                <div class="js-editor-mount" wire:ignore x-ignore></div>

                                <div class="flex items-center justify-end gap-2 border-t border-neutral-200 p-1.5 dark:border-neutral-700">
                                    <button type="button" @click="cancel()" class="rounded-lg px-3 py-1 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                                    <button type="button" @click="save()" class="rounded-lg bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Enregistrer') }}</button>
                                </div>
                            </div>
                        </div>

                        {{-- Description link previews --}}
                        @foreach ($this->linkPreviews($card->description) as $preview)
                            <x-link-preview
                                :preview="$preview"
                                :hidden="in_array($preview->url, $card->hidden_previews ?? [], true)"
                                wire-toggle="toggleDescriptionPreview('{{ $preview->url }}')"
                                wire:key="desc-lp-{{ $card->id }}-{{ $preview->id }}"
                            />
                        @endforeach

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
                                        <button type="button" wire:click="deleteChecklist({{ $checklist->id }})" class="text-xs text-neutral-400 hover:text-red-500">{{ __('Supprimer') }}</button>
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
                                                <x-checkbox :checked="$item->is_completed" :label="$item->content" wire:click="toggleChecklistItem({{ $item->id }})" wire:key="cbitem-{{ $item->id }}-{{ $item->is_completed }}" />
                                                <button type="button" wire:click="deleteChecklistItem({{ $item->id }})" class="ml-auto text-xs text-neutral-300 opacity-100 hover:text-red-500 group-hover:opacity-100 sm:opacity-0"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <form wire:submit="addChecklistItem({{ $checklist->id }})" class="mt-2">
                                        <input type="text" wire:model="newChecklistItem.{{ $checklist->id }}" placeholder="{{ __('+ Ajouter un élément') }}" class="w-full rounded border border-neutral-200 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    </form>
                                </div>
                            @endforeach

                            <form wire:submit="addChecklist" class="flex gap-2">
                                <input type="text" wire:model="newChecklistTitle" placeholder="{{ __('Nouvelle checklist') }}" class="flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                <button type="submit" class="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">{{ __('Ajouter') }}</button>
                            </form>
                        </div>

                        {{-- Relations (card links) --}}
                        <div>
                            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Relations') }}</h3>

                            @if ($cardLinks['blocks']->isNotEmpty() || $cardLinks['blockedBy']->isNotEmpty() || $cardLinks['relates']->isNotEmpty())
                                <div class="mb-2 space-y-2">
                                    @if ($cardLinks['blocks']->isNotEmpty())
                                        <div>
                                            <p class="mb-0.5 text-xs font-medium text-red-600 dark:text-red-400">{{ __('Bloque') }}</p>
                                            @foreach ($cardLinks['blocks'] as $link)
                                                @include('livewire.cards.partials.card-link', ['link' => $link, 'other' => $link->relatedCard])
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($cardLinks['blockedBy']->isNotEmpty())
                                        <div>
                                            <p class="mb-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Bloquée par') }}</p>
                                            @foreach ($cardLinks['blockedBy'] as $link)
                                                @include('livewire.cards.partials.card-link', ['link' => $link, 'other' => $link->card])
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($cardLinks['relates']->isNotEmpty())
                                        <div>
                                            <p class="mb-0.5 text-xs font-medium text-neutral-500">{{ __('Liée à') }}</p>
                                            @foreach ($cardLinks['relates'] as $link)
                                                @include('livewire.cards.partials.card-link', ['link' => $link, 'other' => $link->card_id === $card->id ? $link->relatedCard : $link->card])
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Add a link --}}
                            <div x-data="{ open: false }" @click.outside="open = false" class="flex items-center gap-2">
                                <select wire:model="linkType" class="shrink-0 rounded-lg border border-neutral-300 bg-white px-2 py-1.5 text-sm focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    <option value="blocks">{{ __('Bloque') }}</option>
                                    <option value="blocked_by">{{ __('Bloquée par') }}</option>
                                    <option value="relates_to">{{ __('Liée à') }}</option>
                                </select>
                                <div class="relative flex-1">
                                    <input type="text" wire:model.live.debounce.300ms="linkSearch" @focus="open = true"
                                           placeholder="{{ __('Lier une carte…') }}"
                                           class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    <div x-show="open && $wire.linkSearch.trim().length >= 1" x-cloak
                                         class="absolute left-0 right-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                        @forelse ($linkCandidates as $candidate)
                                            <button type="button" wire:click="linkCard({{ $candidate->id }})" @click="open = false"
                                                    class="block w-full truncate rounded px-2 py-1.5 text-left text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ $candidate->title }}</button>
                                        @empty
                                            <p class="px-2 py-1.5 text-xs text-neutral-400">{{ __('Aucun résultat.') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Attachments --}}
                        @php
                            $media = $card->attachments
                                ->filter(fn ($a) => $a->isImage() || $a->isVideo())
                                ->map(fn ($a) => ['type' => $a->isImage() ? 'image' : 'video', 'url' => $a->url, 'mime' => $a->mime_type])
                                ->values()->all();
                            $mediaUrls = array_column($media, 'url');
                        @endphp
                        <div id="card-section-attachments"
                             x-data="{
                                open: JSON.parse(localStorage.getItem('card-attachments-open') ?? 'true'),
                                view: localStorage.getItem('card-attachments-view') ?? 'list',
                             }"
                             x-init="$watch('open', v => localStorage.setItem('card-attachments-open', JSON.stringify(v))); $watch('view', v => localStorage.setItem('card-attachments-view', v))"
                             @card-open-attachments.window="open = true; setTimeout(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200)"
                             class="space-y-3">
                            <div class="flex items-center justify-between">
                                <button type="button" @click="open = ! open" class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-neutral-500">
                                    <x-phosphor-caret-right class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" />
                                    {{ __('Pièces jointes') }}
                                    @if ($card->attachments->isNotEmpty())<span class="rounded-full bg-neutral-200 px-1.5 text-[10px] font-semibold text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $card->attachments->count() }}</span>@endif
                                </button>
                                <div x-show="open" class="flex items-center gap-0.5">
                                    <button type="button" @click="view = 'list'" title="{{ __('Liste') }}" class="rounded p-1 transition" :class="view === 'list' ? 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200' : 'text-neutral-400 hover:text-neutral-600'"><x-phosphor-list class="h-4 w-4" /></button>
                                    <button type="button" @click="view = 'grid'" title="{{ __('Grille') }}" class="rounded p-1 transition" :class="view === 'grid' ? 'bg-neutral-200 text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200' : 'text-neutral-400 hover:text-neutral-600'"><x-phosphor-squares-four class="h-4 w-4" /></button>
                                </div>
                            </div>

                            <div x-show="open" x-cloak class="space-y-3">
                                @if ($card->attachments->isNotEmpty())
                                    <div class="max-h-72 overflow-y-auto pr-1">
                                        {{-- Grid layout --}}
                                        <div x-show="view === 'grid'" class="grid grid-cols-2 gap-3">
                                            @foreach ($card->attachments as $attachment)
                                                <div wire:key="attg-{{ $attachment->id }}" class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                                                    @if ($attachment->isImage())
                                                        <img src="{{ $attachment->url }}" alt="{{ $attachment->name }}" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="h-28 w-full cursor-zoom-in object-cover transition hover:opacity-90">
                                                    @elseif ($attachment->isVideo())
                                                        <button type="button" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="group relative block h-28 w-full">
                                                            <video src="{{ $attachment->url }}" preload="metadata" muted class="pointer-events-none h-28 w-full bg-black object-contain"></video>
                                                            <span class="absolute inset-0 flex items-center justify-center bg-black/20 transition group-hover:bg-black/30"><span class="flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-white"><x-phosphor-play class="ml-0.5 h-5 w-5" /></span></span>
                                                        </button>
                                                    @else
                                                        <div class="flex h-28 w-full items-center justify-center bg-neutral-100 dark:bg-neutral-800"><x-phosphor-file class="h-8 w-8 text-neutral-400" /></div>
                                                    @endif
                                                    <div class="flex items-center justify-between gap-1 p-2">
                                                        <span class="truncate text-xs" title="{{ $attachment->name }}">{{ $attachment->name }}</span>
                                                        <div class="flex shrink-0 gap-1">
                                                            @if ($attachment->isImage())
                                                                <button type="button" wire:click="setCover({{ $attachment->id }})" class="text-neutral-400 hover:text-amber-500" title="{{ __('Définir comme couverture') }}"><x-phosphor-star class="h-4 w-4" /></button>
                                                            @endif
                                                            <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" class="text-neutral-400 hover:text-red-500"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        {{-- List layout --}}
                                        <div x-show="view === 'list'" class="flex flex-col gap-1.5">
                                            @foreach ($card->attachments as $attachment)
                                                <div wire:key="attl-{{ $attachment->id }}" class="flex items-center gap-2 rounded-lg border border-neutral-200 p-1.5 dark:border-neutral-700">
                                                    @if ($attachment->isImage())
                                                        <img src="{{ $attachment->url }}" alt="" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="h-10 w-10 shrink-0 cursor-zoom-in rounded object-cover">
                                                    @elseif ($attachment->isVideo())
                                                        <button type="button" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="relative h-10 w-10 shrink-0 overflow-hidden rounded bg-black">
                                                            <video src="{{ $attachment->url }}" preload="metadata" muted class="pointer-events-none h-full w-full object-contain"></video>
                                                            <span class="absolute inset-0 flex items-center justify-center"><x-phosphor-play class="h-4 w-4 text-white" /></span>
                                                        </button>
                                                    @else
                                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-neutral-100 dark:bg-neutral-800"><x-phosphor-file class="h-5 w-5 text-neutral-400" /></span>
                                                    @endif
                                                    <span class="min-w-0 flex-1 truncate text-xs" title="{{ $attachment->name }}">{{ $attachment->name }}</span>
                                                    @if ($attachment->isImage())
                                                        <button type="button" wire:click="setCover({{ $attachment->id }})" class="shrink-0 text-neutral-400 hover:text-amber-500" title="{{ __('Définir comme couverture') }}"><x-phosphor-star class="h-4 w-4" /></button>
                                                    @endif
                                                    <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" class="shrink-0 text-neutral-400 hover:text-red-500"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <x-dropzone model="upload" action="saveAttachment" accept="image/*,video/*" />
                            </div>
                        </div>

                        {{-- Comments (real-time) --}}
                        @php
                            $mentionMembers = $boardMembers->map(fn ($m) => [
                                'id' => $m->id,
                                'name' => $m->name,
                                'slug' => \Illuminate\Support\Str::slug($m->name),
                                'avatar_url' => $m->avatarUrl(),
                            ])->values();
                        @endphp
                        <div
                            wire:key="comment-composer-{{ $card->id }}"
                            class="space-y-3"
                            x-data="commentEditor(@js([
                                'members' => $mentionMembers,
                                'boardId' => $board->id,
                                'cardId' => $card->id,
                                'userId' => auth()->id(),
                                'userName' => auth()->user()->name,
                            ]))"
                            x-init="init()"
                        >
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Commentaires') }}</h3>

                            <div class="relative">
                                <div class="rounded-lg border border-neutral-300 focus-within:border-indigo-500 dark:border-neutral-700">
                                    <div class="flex flex-wrap items-center gap-0.5 border-b border-neutral-200 p-1 dark:border-neutral-700">
                                        <button type="button" @click="run('toggleBold')" :class="isActive('bold') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-bold" title="{{ __('Gras') }}">B</button>
                                        <button type="button" @click="run('toggleItalic')" :class="isActive('italic') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} italic" title="{{ __('Italique') }}">I</button>
                                        <button type="button" @click="run('toggleStrike')" :class="isActive('strike') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} line-through" title="{{ __('Barré') }}">S</button>
                                        <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                        <button type="button" @click="run('toggleBulletList')" :class="isActive('bulletList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste à puces') }}"><x-phosphor-list-bullets class="h-4 w-4" /></button>
                                        <button type="button" @click="run('toggleOrderedList')" :class="isActive('orderedList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Liste numérotée') }}"><x-phosphor-list-numbers class="h-4 w-4" /></button>
                                        <button type="button" @click="toggleLink()" :class="isActive('link') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="{{ __('Lien') }}"><x-phosphor-link class="h-4 w-4" /></button>
                                    </div>

                                    <div class="js-comment-mount" wire:ignore x-ignore></div>

                                    <div class="flex items-center justify-between gap-2 border-t border-neutral-200 p-1.5 dark:border-neutral-700">
                                        <span class="text-xs text-indigo-500" x-text="typingText"></span>
                                        <button type="button" @click="submit()" :disabled="empty" class="rounded-lg bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">{{ __('Commenter') }}</button>
                                    </div>
                                </div>

                                {{-- @mention suggestions (driven by TipTap) --}}
                                <div x-show="mention.open" x-cloak
                                     class="fixed z-[60] max-h-56 w-56 overflow-auto rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
                                     :style="`top: ${mention.top}px; left: ${mention.left}px`">
                                    <template x-for="(m, i) in mention.items" :key="m.id">
                                        <button type="button" @mousedown.prevent="pickMention(i)" @mouseenter="mention.index = i"
                                                class="flex w-full items-center gap-2 px-2 py-1.5 text-left text-sm"
                                                :class="i === mention.index ? 'bg-indigo-50 dark:bg-indigo-500/10' : ''">
                                            <template x-if="m.avatar_url"><img :src="m.avatar_url" alt="" class="h-5 w-5 shrink-0 rounded-full object-cover"></template>
                                            <template x-if="! m.avatar_url"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300" x-text="m.name.charAt(0).toUpperCase()"></span></template>
                                            <span class="truncate" x-text="m.name"></span>
                                        </button>
                                    </template>
                                </div>
                                @error('newComment') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div class="space-y-3">
                                @foreach ($card->comments as $comment)
                                    @php $canDelete = $comment->user_id === auth()->id() || $board->memberRole(auth()->user())?->isAdministrator(); @endphp
                                    <div wire:key="comment-{{ $comment->id }}" id="comment-{{ $comment->id }}" class="group/comment flex gap-2">
                                        <x-user-avatar :user="$comment->user" size="sm" class="mt-0.5" />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium">{{ $comment->user?->name ?? 'Utilisateur supprimé' }}</span>
                                                <span class="text-xs text-neutral-400">{{ $comment->created_at->diffForHumans() }}@if ($comment->updated_at->gt($comment->created_at)) · {{ __('modifié') }}@endif</span>
                                                <div class="ml-auto flex items-center gap-2" x-data="{ copied: false }">
                                                    <button type="button" @click="navigator.clipboard?.writeText('{{ route('boards.show', ['board' => $board, 'card' => $card->public_id]) }}#comment-{{ $comment->id }}'); window.toast('{{ __('Lien copié') }}', { type: 'success' }); copied = true; setTimeout(() => copied = false, 1500)" class="text-xs text-neutral-300 opacity-100 transition hover:text-indigo-500 group-hover/comment:opacity-100 sm:opacity-0" title="{{ __('Copier le lien du commentaire') }}"><span x-text="copied ? 'Copié !' : 'Lien'"></span></button>
                                                    @if ($comment->user_id === auth()->id())
                                                        <button type="button" wire:click="startEditComment({{ $comment->id }})" class="text-xs text-neutral-300 opacity-100 transition hover:text-indigo-500 group-hover/comment:opacity-100 sm:opacity-0">{{ __('Modifier') }}</button>
                                                    @endif
                                                    @if ($canDelete)
                                                        <button type="button" wire:click="deleteComment({{ $comment->id }})" class="text-xs text-neutral-300 opacity-100 transition hover:text-red-500 group-hover/comment:opacity-100 sm:opacity-0">{{ __('Supprimer') }}</button>
                                                    @endif
                                                </div>
                                            </div>
                                            @if ($editingCommentId === $comment->id)
                                                <form wire:submit="saveComment" class="mt-1 space-y-1.5">
                                                    <textarea wire:model="editingCommentBody" rows="3" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"></textarea>
                                                    @error('editingCommentBody') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                                    <div class="flex gap-2">
                                                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-500">{{ __('Enregistrer') }}</button>
                                                        <button type="button" wire:click="cancelEditComment" class="rounded-lg px-3 py-1 text-xs text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">{{ __('Annuler') }}</button>
                                                    </div>
                                                </form>
                                            @else
                                                <div class="markdown mt-0.5 break-words text-sm text-neutral-700 dark:text-neutral-300">{!! $this->renderCommentBody($comment->body) !!}</div>
                                                @foreach ($this->linkPreviews($comment->body) as $preview)
                                                    <x-link-preview
                                                        :preview="$preview"
                                                        :hidden="in_array($preview->url, $comment->hidden_previews ?? [], true)"
                                                        wire-toggle="toggleCommentPreview({{ $comment->id }}, '{{ $preview->url }}')"
                                                        wire:key="comment-{{ $comment->id }}-lp-{{ $preview->id }}"
                                                    />
                                                @endforeach
                                            @endif

                                            {{-- Reactions --}}
                                            @php
                                                $grouped = $comment->reactions->groupBy('emoji');
                                                $myReactions = $comment->reactions->where('user_id', auth()->id())->pluck('emoji')->all();
                                            @endphp
                                            <div class="mt-1.5 flex flex-wrap items-center gap-1" x-data="{ picker: false }">
                                                @foreach ($grouped as $emoji => $group)
                                                    <button type="button" wire:click="toggleReaction({{ $comment->id }}, '{{ $emoji }}')"
                                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition {{ in_array($emoji, $myReactions, true) ? 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-300' : 'border-neutral-200 bg-neutral-50 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' }}">
                                                        <span>{{ $emoji }}</span><span class="font-medium">{{ $group->count() }}</span>
                                                    </button>
                                                @endforeach
                                                <div class="relative">
                                                    <button type="button" @click="picker = ! picker" class="flex h-6 w-6 items-center justify-center rounded-full border border-neutral-200 text-neutral-400 transition hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800" title="{{ __('Ajouter une réaction') }}">
                                                        <x-phosphor-smiley class="h-4 w-4"/>
                                                    </button>
                                                    <div x-show="picker" x-cloak @click.outside="picker = false" class="absolute left-0 z-20 mt-1 flex gap-0.5 rounded-lg border border-neutral-200 bg-white p-1 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                                                        @foreach ($reactionEmojis as $emoji)
                                                            <button type="button" wire:click="toggleReaction({{ $comment->id }}, '{{ $emoji }}')" @click="picker = false" class="flex h-7 w-7 items-center justify-center rounded text-base hover:bg-neutral-100 dark:hover:bg-neutral-800">{{ $emoji }}</button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Activity feed (collapsed by default) --}}
                        <div x-data="{ open: false }" class="space-y-2">
                            <button type="button" @click="open = ! open" class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-neutral-500">
                                <x-phosphor-caret-right class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" />
                                {{ __('Activité') }}
                                @if ($card->activities->isNotEmpty())<span class="rounded-full bg-neutral-200 px-1.5 text-[10px] font-semibold text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">{{ $card->activities->count() }}</span>@endif
                            </button>
                            <div x-show="open" x-cloak class="space-y-2">
                            @forelse ($card->activities->take(12) as $activity)
                                @php
                                    $props = $activity->properties ?? [];
                                    if ($activity->type === 'card.moved' && ! empty($props['to_list'])) {
                                        $label = __('activity.card.moved_to', ['list' => $props['to_list']]);
                                    } elseif ($activity->type === 'member.assigned') {
                                        $label = __('activity.member.assigned', ['name' => $props['user_name'] ?? __('activity.someone')]);
                                    } else {
                                        $key = 'activity.' . $activity->type;
                                        $label = trans($key);
                                        $label = $label === $key ? $activity->type : $label;
                                    }
                                @endphp
                                <div wire:key="activity-{{ $activity->id }}" class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                    <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ $activity->user?->name ?? __('Quelqu\'un') }}</span>
                                    <span>{{ $label }}</span>
                                    @if (Str::startsWith((string) $activity->source, 'mcp:'))
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"><x-phosphor-robot class="h-3 w-3" /> {{ Str::after($activity->source, 'mcp:') }}</span>
                                    @endif
                                    <span class="text-neutral-400">· {{ $activity->created_at->diffForHumans() }}</span>
                                </div>
                            @empty
                                <p class="text-xs text-neutral-400">{{ __('Aucune activité pour le moment.') }}</p>
                            @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Sidebar --}}
                    <div class="space-y-5">
                        @php $isWatching = $card->watchers->contains(fn ($w) => $w->id === auth()->id()); @endphp
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="toggleComplete" title="{{ $card->completed_at ? __('Terminée') : __('Marquer terminée') }}"
                                    class="flex h-9 flex-1 items-center justify-center rounded-lg transition {{ $card->completed_at ? 'bg-green-600 text-white hover:bg-green-500' : 'border border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800' }}">
                                <x-phosphor-check-circle class="h-5 w-5" />
                            </button>
                            <button type="button" wire:click="toggleWatch" title="{{ $isWatching ? __('Suivi') : __('Suivre') }}"
                                    class="relative flex h-9 flex-1 items-center justify-center rounded-lg transition {{ $isWatching ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'border border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800' }}">
                                <x-dynamic-component :component="$isWatching ? 'phosphor-eye' : 'phosphor-eye-slash'" class="h-5 w-5" />
                                @if ($card->watchers->isNotEmpty())
                                    <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-neutral-700 px-1 text-[10px] font-semibold text-white dark:bg-neutral-200 dark:text-neutral-900">{{ $card->watchers->count() }}</span>
                                @endif
                            </button>
                            @can('admin')
                                <button type="button" wire:click="saveAsTemplate" title="{{ __('Enregistrer comme modèle') }}"
                                        class="flex h-9 flex-1 items-center justify-center rounded-lg border border-neutral-300 transition hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                    <x-phosphor-stack class="h-5 w-5" />
                                </button>
                            @endcan
                        </div>

                        {{-- Manual automation buttons --}}
                        @if ($cardButtons->isNotEmpty())
                            <div class="space-y-1.5">
                                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Actions rapides') }}</h3>
                                @foreach ($cardButtons as $button)
                                    <button type="button" wire:click="runAutomation({{ $button->id }})" wire:key="cardbtn-{{ $button->id }}" class="flex w-full items-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                        <x-phosphor-lightning class="h-4 w-4 text-amber-500" /> {{ $button->name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        {{-- Dates: start + due (toggleable, like Members / Labels) --}}
                        @php $dueOverdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast(); @endphp
                        <div x-data="{ enabled: @js((bool) ($card->start_at || $card->due_at)) }">
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Dates') }}</h3>
                                <button
                                    type="button"
                                    role="switch"
                                    aria-label="{{ __('Activer les dates') }}"
                                    :aria-checked="enabled"
                                    @click="enabled = ! enabled; if (! enabled) { $wire.clearDates() }"
                                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition"
                                    :class="enabled ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700'"
                                >
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition" :class="enabled ? 'translate-x-4' : 'translate-x-0.5'"></span>
                                </button>
                            </div>
                            <div x-show="enabled" x-cloak class="space-y-2">
                                <div>
                                    <label class="mb-0.5 block text-xs text-neutral-500">{{ __('Début') }}</label>
                                    <div class="flex gap-2">
                                        <input type="date" wire:model="startDate" wire:change="saveDates" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                        <input type="time" wire:model="startTime" wire:change="saveDates" aria-label="{{ __('Heure de début') }}" class="w-28 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-0.5 block text-xs text-neutral-500">{{ __('Échéance') }}</label>
                                    <div class="flex gap-2">
                                        <input type="date" wire:model="dueDate" wire:change="saveDates" class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                        <input type="time" wire:model="dueTime" wire:change="saveDates" aria-label="{{ __('Heure d’échéance') }}" class="w-28 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                    </div>
                                    @error('dueDate') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    <p class="mt-1 text-[11px] text-neutral-400">{{ __('Heure optionnelle (12:00 par défaut).') }}</p>
                                </div>
                                @if ($card->due_at)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="{{ $dueOverdue ? 'font-medium text-red-600 dark:text-red-400' : 'text-neutral-500' }}">
                                            {{ $card->due_at->translatedFormat('d M Y \à H:i') }}{{ $dueOverdue ? __(' · en retard') : '' }}
                                        </span>
                                        <button type="button" wire:click="clearDates" @click="enabled = false" class="text-neutral-400 hover:text-red-500">{{ __('Retirer') }}</button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Members --}}
                        <div>
                            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Membres') }}</h3>
                            <div class="space-y-1">
                                @foreach ($boardMembers as $member)
                                    @php $assigned = $card->members->contains($member->id); @endphp
                                    <button type="button" wire:click="toggleMember({{ $member->id }})" class="flex w-full items-center gap-2 rounded-lg px-2 py-1 text-left text-sm {{ $assigned ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-neutral-100 dark:hover:bg-neutral-800' }}">
                                        <x-user-avatar :user="$member" size="sm" />
                                        <span class="truncate">{{ $member->name }}</span>
                                        @if ($assigned) <x-phosphor-check class="ml-auto h-4 w-4 text-indigo-600 dark:text-indigo-400" /> @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Labels --}}
                        <div>
                            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Labels') }}</h3>
                            @php $labelPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b']; @endphp
                            <div class="space-y-2">
                                @foreach ($boardLabels as $label)
                                    @php $on = $card->labels->contains($label->id); @endphp
                                    <x-context-menu wire:key="label-{{ $label->id }}" class="group/label flex items-center gap-1">
                                        <x-slot:trigger>
                                            <button type="button" wire:click="toggleLabel({{ $label->id }})" class="flex flex-1 items-center gap-2 rounded-lg px-2 py-1 text-left text-sm {{ $on ? 'ring-2 ring-indigo-400' : 'hover:bg-neutral-100 dark:hover:bg-neutral-800' }}">
                                                <span class="h-3 w-6 shrink-0 rounded-full" style="background-color: {{ $label->color }}"></span>
                                                <span class="truncate">{{ $label->name ?? '—' }}</span>
                                            </button>
                                            <button type="button" @click="openAt($event.clientX, $event.clientY)" class="shrink-0 rounded p-1 text-neutral-400 opacity-100 transition hover:bg-neutral-100 hover:text-neutral-700 group-hover/label:opacity-100 sm:opacity-0 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="{{ __('Options du label (clic droit aussi)') }}"><x-phosphor-dots-three class="h-4 w-4" /></button>
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
                                <input type="text" wire:model="newLabelName" placeholder="{{ __('Nouveau label') }}" class="flex-1 rounded-lg border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                <button type="submit" class="rounded-lg border border-neutral-300 px-2 py-1 text-sm hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">+</button>
                            </form>
                        </div>

                        {{-- Custom fields --}}
                        @if ($customFields->isNotEmpty())
                            @php $cfValues = $card->customFieldValues->keyBy('custom_field_id'); @endphp
                            <div>
                                <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">{{ __('Champs personnalisés') }}</h3>
                                <div class="space-y-2.5">
                                    @foreach ($customFields as $field)
                                        @php $val = optional($cfValues->get($field->id))->value; @endphp
                                        <div wire:key="cf-input-{{ $field->id }}">
                                            @if ($field->type === \App\Enums\CustomFieldType::Checkbox)
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" @checked($val)
                                                           wire:change="saveCustomField({{ $field->id }}, $event.target.checked)"
                                                           class="h-4 w-4 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500/40 dark:border-neutral-600 dark:bg-neutral-800">
                                                    {{ $field->name }}
                                                </label>
                                            @else
                                                <label class="mb-0.5 block text-xs text-neutral-500">{{ $field->name }}</label>
                                                @if ($field->type === \App\Enums\CustomFieldType::Select)
                                                    <select wire:change="saveCustomField({{ $field->id }}, $event.target.value)"
                                                            class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                                        <option value="">{{ __('—') }}</option>
                                                        @foreach ($field->options ?? [] as $opt)
                                                            <option value="{{ $opt }}" @selected($val === $opt)>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                @elseif ($field->type === \App\Enums\CustomFieldType::Number)
                                                    <input type="number" value="{{ $val }}" wire:change="saveCustomField({{ $field->id }}, $event.target.value)"
                                                           class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                                @elseif ($field->type === \App\Enums\CustomFieldType::Date)
                                                    <input type="date" value="{{ $val }}" wire:change="saveCustomField({{ $field->id }}, $event.target.value)"
                                                           class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                                @else
                                                    <input type="text" value="{{ $val }}" wire:change="saveCustomField({{ $field->id }}, $event.target.value)"
                                                           class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Cover: solid color or uploaded image (collapsed by default, at the bottom) --}}
                        @php $coverPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b']; @endphp
                        <div x-data="{ open: false }">
                            <button type="button" @click="open = ! open" class="mb-2 flex w-full items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-neutral-500">
                                <x-phosphor-caret-right class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" />
                                {{ __('Couverture') }}
                                @if ($card->cover_path)
                                    <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                                @elseif ($card->cover_color)
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $card->cover_color }}"></span>
                                @endif
                            </button>
                            <div x-show="open" x-cloak>
                                @if ($card->cover_path)
                                    <div class="relative mb-2 overflow-hidden rounded-lg">
                                        <img src="{{ Storage::disk('public')->url($card->cover_path) }}" alt="" class="h-24 w-full object-cover">
                                        <button type="button" wire:click="clearCover" class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70" title="{{ __('Retirer la couverture') }}"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                    </div>
                                @endif
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($coverPalette as $swatch)
                                        <button type="button" wire:click="setCoverColor('{{ $swatch }}')" class="h-6 w-6 rounded-md ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-900 {{ $card->cover_color === $swatch ? 'ring-2 ring-indigo-500' : '' }}" style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
                                    @endforeach
                                    @if ($card->cover_color && ! $card->cover_path)
                                        <button type="button" wire:click="clearCover" class="flex h-6 items-center gap-1 rounded-md border border-neutral-300 px-2 text-xs text-neutral-500 hover:text-neutral-700 dark:border-neutral-700 dark:hover:text-neutral-200" title="{{ __('Retirer la couverture') }}"><x-phosphor-x class="h-3 w-3" /> {{ __('Retirer') }}</button>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    <x-dropzone model="coverUpload" action="uploadCover" accept="image/*" hint="{{ __('Image de couverture · 10 Mo max') }}" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </x-modal>
    @endif
</div>
