<div>
    @if ($showModal && $card)
        <x-modal max-width="3xl" on-close="$wire.close()" wire:key="card-modal-{{ $card->id }}">
                {{-- Cover --}}
                @if ($card->cover_path)
                    <img src="{{ Storage::disk('public')->url($card->cover_path) }}" alt="" class="h-40 w-full rounded-t-2xl object-cover">
                @elseif ($card->cover_color)
                    <div class="h-20 w-full rounded-t-2xl" style="background-color: {{ $card->cover_color }}"></div>
                @endif

                <button type="button" wire:click="close" class="absolute right-3 top-3 rounded-full bg-white/80 p-1.5 text-neutral-600 shadow hover:bg-white dark:bg-neutral-800/80 dark:text-neutral-300"><x-phosphor-x class="h-5 w-5" /></button>

                <div class="grid gap-6 p-6 sm:grid-cols-3 mt-6">
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
                            <button type="submit" class="mt-1 shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Enregistrer</button>
                        </form>

                        {{-- Description : éditeur WYSIWYG (TipTap → markdown) --}}
                        @php
                            $tbBtn = 'flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-700';
                        @endphp
                        <div wire:key="desc-{{ $card->id }}" wire:ignore x-data="markdownEditor(@js((string) $card->description))">
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-neutral-500">Description</label>

                            {{-- Read mode --}}
                            <div x-ref="readview" x-show="! editing" @click="edit()" class="markdown min-h-[3rem] cursor-text rounded-lg border border-transparent bg-neutral-50 p-3 text-sm hover:border-neutral-300 dark:bg-neutral-800/50 dark:hover:border-neutral-700">
                                @if (filled($card->description))
                                    {!! Str::markdown($card->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                @else
                                    <span class="text-neutral-400">Ajoutez une description… (cliquez pour éditer)</span>
                                @endif
                            </div>

                            {{-- Edit mode --}}
                            <div x-show="editing" x-cloak class="rounded-lg border border-neutral-300 dark:border-neutral-700">
                                <div class="flex flex-wrap items-center gap-0.5 border-b border-neutral-200 p-1 dark:border-neutral-700">
                                    <button type="button" @click="run('toggleBold')" :class="isActive('bold') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-bold" title="Gras">B</button>
                                    <button type="button" @click="run('toggleItalic')" :class="isActive('italic') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} italic" title="Italique">I</button>
                                    <button type="button" @click="run('toggleStrike')" :class="isActive('strike') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} line-through" title="Barré">S</button>
                                    <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                    <button type="button" @click="run('toggleHeading', { level: 2 })" :class="isActive('heading', { level: 2 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="Titre">H2</button>
                                    <button type="button" @click="run('toggleHeading', { level: 3 })" :class="isActive('heading', { level: 3 }) && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }} font-semibold" title="Sous-titre">H3</button>
                                    <span class="mx-1 h-5 w-px bg-neutral-200 dark:bg-neutral-700"></span>
                                    <button type="button" @click="run('toggleBulletList')" :class="isActive('bulletList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="Liste à puces"><x-phosphor-list-bullets class="h-4 w-4" /></button>
                                    <button type="button" @click="run('toggleOrderedList')" :class="isActive('orderedList') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="Liste numérotée"><x-phosphor-list-numbers class="h-4 w-4" /></button>
                                    <button type="button" @click="run('toggleCodeBlock')" :class="isActive('codeBlock') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="Bloc de code"><x-phosphor-code class="h-4 w-4" /></button>
                                    <button type="button" @click="toggleLink()" :class="isActive('link') && 'bg-neutral-200 dark:bg-neutral-700'" class="{{ $tbBtn }}" title="Lien"><x-phosphor-link class="h-4 w-4" /></button>
                                </div>

                                <div class="js-editor-mount" wire:ignore x-ignore></div>

                                <div class="flex items-center justify-end gap-2 border-t border-neutral-200 p-1.5 dark:border-neutral-700">
                                    <button type="button" @click="cancel()" class="rounded-lg px-3 py-1 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">Annuler</button>
                                    <button type="button" @click="save()" class="rounded-lg bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-500">Enregistrer</button>
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
                                                <x-checkbox :checked="$item->is_completed" :label="$item->content" wire:click="toggleChecklistItem({{ $item->id }})" wire:key="cbitem-{{ $item->id }}-{{ $item->is_completed }}" />
                                                <button type="button" wire:click="deleteChecklistItem({{ $item->id }})" class="ml-auto text-xs text-neutral-300 opacity-0 group-hover:opacity-100 hover:text-red-500"><x-phosphor-x class="h-3.5 w-3.5" /></button>
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
                        @php
                            $media = $card->attachments
                                ->filter(fn ($a) => $a->isImage() || $a->isVideo())
                                ->map(fn ($a) => ['type' => $a->isImage() ? 'image' : 'video', 'url' => $a->url, 'mime' => $a->mime_type])
                                ->values()->all();
                            $mediaUrls = array_column($media, 'url');
                        @endphp
                        <div class="space-y-3">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Pièces jointes</h3>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ($card->attachments as $attachment)
                                    <div wire:key="att-{{ $attachment->id }}" class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                                        @if ($attachment->isImage())
                                            <img src="{{ $attachment->url }}" alt="{{ $attachment->name }}" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="h-28 w-full cursor-zoom-in object-cover transition hover:opacity-90">
                                        @elseif ($attachment->isVideo())
                                            <button type="button" @click="$store.lightbox.open(@js($media), {{ array_search($attachment->url, $mediaUrls, true) }})" class="group relative block h-28 w-full">
                                                <video src="{{ $attachment->url }}" preload="metadata" muted class="pointer-events-none h-28 w-full bg-black object-contain"></video>
                                                <span class="absolute inset-0 flex items-center justify-center bg-black/20 transition group-hover:bg-black/30">
                                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-white"><x-phosphor-play class="ml-0.5 h-5 w-5" /></span>
                                                </span>
                                            </button>
                                        @endif
                                        <div class="flex items-center justify-between gap-1 p-2">
                                            <span class="truncate text-xs" title="{{ $attachment->name }}">{{ $attachment->name }}</span>
                                            <div class="flex shrink-0 gap-1">
                                                @if ($attachment->isImage())
                                                    <button type="button" wire:click="setCover({{ $attachment->id }})" class="text-neutral-400 hover:text-amber-500" title="Définir comme couverture"><x-phosphor-star class="h-4 w-4" /></button>
                                                @endif
                                                <button type="button" wire:click="deleteAttachment({{ $attachment->id }})" class="text-xs text-neutral-400 hover:text-red-500"><x-phosphor-x class="h-3.5 w-3.5" /></button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <x-dropzone model="upload" action="saveAttachment" accept="image/*,video/*" />
                        </div>

                        {{-- Comments (real-time) --}}
                        <div
                            class="space-y-3"
                            data-uname="{{ auth()->user()->name }}"
                            data-members="{{ json_encode($boardMembers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'slug' => \Illuminate\Support\Str::slug($m->name)])->values()) }}"
                            x-data='{
                                typers: {},
                                channel: null,
                                timers: {},
                                lastPing: 0,
                                members: [],
                                showList: false,
                                items: [],
                                index: 0,
                                caretTop: 0,
                                caretLeft: 0,
                                init() {
                                    this.members = JSON.parse(this.$root.dataset.members || "[]");
                                    if (! window.Echo) return;
                                    this.channel = window.Echo.private("board.{{ $board->id }}");
                                    this.channel.listenForWhisper("typing", (e) => {
                                        if (e.cardId !== {{ $card->id }} || e.id === {{ auth()->id() }}) return;
                                        this.typers = { ...this.typers, [e.id]: e.name };
                                        clearTimeout(this.timers[e.id]);
                                        this.timers[e.id] = setTimeout(() => {
                                            let t = { ...this.typers }; delete t[e.id]; this.typers = t;
                                        }, 2500);
                                    });
                                },
                                ping() {
                                    const now = Date.now();
                                    if (now - this.lastPing < 800) return;
                                    this.lastPing = now;
                                    if (this.channel) this.channel.whisper("typing", { id: {{ auth()->id() }}, name: this.$root.dataset.uname, cardId: {{ $card->id }} });
                                },
                                onInput() {
                                    this.ping();
                                    this.detect();
                                },
                                detect() {
                                    const el = this.$refs.input;
                                    const before = el.value.substring(0, el.selectionStart);
                                    const m = before.match(/(?:^|\s)@([\p{L}0-9_-]*)$/u);
                                    if (! m) { this.showList = false; return; }
                                    const q = m[1].toLowerCase();
                                    this.items = this.members.filter(u => u.name.toLowerCase().includes(q) || u.slug.includes(q)).slice(0, 6);
                                    this.index = 0;
                                    this.showList = this.items.length > 0;
                                    if (this.showList) this.position();
                                },
                                position() {
                                    const el = this.$refs.input;
                                    const div = document.createElement("div");
                                    const s = getComputedStyle(el);
                                    ["fontFamily","fontSize","fontWeight","lineHeight","letterSpacing","paddingTop","paddingRight","paddingBottom","paddingLeft","borderTopWidth","borderLeftWidth","boxSizing","textTransform"].forEach(p => div.style[p] = s[p]);
                                    div.style.position = "absolute"; div.style.visibility = "hidden"; div.style.whiteSpace = "pre-wrap"; div.style.wordWrap = "break-word"; div.style.width = el.offsetWidth + "px";
                                    div.textContent = el.value.substring(0, el.selectionStart);
                                    const marker = document.createElement("span"); marker.textContent = "​"; div.appendChild(marker);
                                    document.body.appendChild(div);
                                    this.caretTop = marker.offsetTop - el.scrollTop;
                                    this.caretLeft = Math.min(marker.offsetLeft, el.offsetWidth - 180);
                                    document.body.removeChild(div);
                                },
                                onKeydown(e) {
                                    if (! this.showList) return;
                                    if (e.key === "ArrowDown") { e.preventDefault(); this.index = (this.index + 1) % this.items.length; }
                                    else if (e.key === "ArrowUp") { e.preventDefault(); this.index = (this.index - 1 + this.items.length) % this.items.length; }
                                    else if (e.key === "Enter" || e.key === "Tab") { e.preventDefault(); this.pick(this.items[this.index]); }
                                    else if (e.key === "Escape") { this.showList = false; }
                                },
                                pick(member) {
                                    const el = this.$refs.input;
                                    const caret = el.selectionStart;
                                    const before = el.value.substring(0, caret);
                                    const after = el.value.substring(caret);
                                    const replaced = before.replace(/@([\p{L}0-9_-]*)$/u, "@" + member.slug + " ");
                                    el.value = replaced + after;
                                    const pos = replaced.length;
                                    el.dispatchEvent(new Event("input"));
                                    this.$nextTick(() => { el.focus(); el.setSelectionRange(pos, pos); });
                                    this.showList = false;
                                },
                                get typingText() {
                                    const n = Object.values(this.typers);
                                    if (! n.length) return "";
                                    return n.length === 1 ? n[0] + " écrit…" : n.length + " personnes écrivent…";
                                }
                            }'
                        >
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Commentaires</h3>

                            <form wire:submit="addComment" class="space-y-2">
                                <div class="relative">
                                    <textarea
                                        x-ref="input"
                                        wire:model="newComment"
                                        @input="onInput()"
                                        @keydown="onKeydown($event)"
                                        @blur="setTimeout(() => showList = false, 150)"
                                        rows="2"
                                        placeholder="Écrire un commentaire… (mentionnez avec @nom)"
                                        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"
                                    ></textarea>

                                    {{-- Mention autocomplete popup (anchored above the caret) --}}
                                    <div
                                        x-show="showList"
                                        x-cloak
                                        class="absolute z-50 w-48 -translate-y-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
                                        :style="`top: ${caretTop - 4}px; left: ${caretLeft}px;`"
                                    >
                                        <template x-for="(u, i) in items" :key="u.id">
                                            <button
                                                type="button"
                                                @mousedown.prevent="pick(u)"
                                                @mouseenter="index = i"
                                                class="flex w-full items-center gap-2 px-2 py-1.5 text-left text-sm"
                                                :class="i === index ? 'bg-indigo-50 dark:bg-indigo-500/10' : ''"
                                            >
                                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300" x-text="u.name.charAt(0).toUpperCase()"></span>
                                                <span class="truncate" x-text="u.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-indigo-500" x-text="typingText"></span>
                                    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Commenter</button>
                                </div>
                                @error('newComment') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </form>

                            <div class="space-y-3">
                                @foreach ($card->comments as $comment)
                                    @php $canDelete = $comment->user_id === auth()->id() || $board->memberRole(auth()->user())?->isAdministrator(); @endphp
                                    <div wire:key="comment-{{ $comment->id }}" id="comment-{{ $comment->id }}" class="group/comment flex gap-2">
                                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                                            {{ Str::of($comment->user?->name ?? '?')->substr(0, 1)->upper() }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium">{{ $comment->user?->name ?? 'Utilisateur supprimé' }}</span>
                                                <span class="text-xs text-neutral-400">{{ $comment->created_at->diffForHumans() }}</span>
                                                <div class="ml-auto flex items-center gap-2" x-data="{ copied: false }">
                                                    <button type="button" @click="navigator.clipboard?.writeText('{{ route('boards.show', ['board' => $board->id, 'card' => $card->id]) }}#comment-{{ $comment->id }}'); copied = true; setTimeout(() => copied = false, 1500)" class="text-xs text-neutral-300 opacity-0 transition hover:text-indigo-500 group-hover/comment:opacity-100" title="Copier le lien du commentaire"><span x-text="copied ? 'Copié !' : 'Lien'"></span></button>
                                                    @if ($canDelete)
                                                        <button type="button" wire:click="deleteComment({{ $comment->id }})" class="text-xs text-neutral-300 opacity-0 transition hover:text-red-500 group-hover/comment:opacity-100">Supprimer</button>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="mt-0.5 whitespace-pre-wrap break-words text-sm text-neutral-700 dark:text-neutral-300">{!! $this->renderCommentBody($comment->body) !!}</div>
                                            @foreach ($this->linkPreviews($comment->body) as $preview)
                                                <x-link-preview
                                                    :preview="$preview"
                                                    :hidden="in_array($preview->url, $comment->hidden_previews ?? [], true)"
                                                    wire-toggle="toggleCommentPreview({{ $comment->id }}, '{{ $preview->url }}')"
                                                    wire:key="comment-{{ $comment->id }}-lp-{{ $preview->id }}"
                                                />
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Activity feed --}}
                        <div class="space-y-2">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Activité</h3>
                            @forelse ($card->activities->take(12) as $activity)
                                @php
                                    $label = match ($activity->type) {
                                        'card.created' => 'a créé la carte',
                                        'card.moved' => 'a déplacé la carte' . (! empty($activity->properties['to_list']) ? ' vers « ' . $activity->properties['to_list'] . ' »' : ''),
                                        'card.completed' => 'a marqué la carte terminée',
                                        'comment.created' => 'a commenté',
                                        'member.assigned' => 'a assigné ' . ($activity->properties['user_name'] ?? 'un membre'),
                                        default => $activity->type,
                                    };
                                @endphp
                                <div wire:key="activity-{{ $activity->id }}" class="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                                    <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ $activity->user?->name ?? 'Quelqu\'un' }}</span>
                                    <span>{{ $label }}</span>
                                    <span class="text-neutral-400">· {{ $activity->created_at->diffForHumans() }}</span>
                                </div>
                            @empty
                                <p class="text-xs text-neutral-400">Aucune activité pour le moment.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Sidebar --}}
                    <div class="space-y-5">
                        <button type="button" wire:click="toggleComplete" class="w-full rounded-lg px-3 py-2 text-sm font-medium {{ $card->completed_at ? 'bg-green-600 text-white hover:bg-green-500' : 'border border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800' }}">
                            {{ $card->completed_at ? 'Terminée' : 'Marquer terminée' }}
                        </button>

                        @can('admin')
                            <button type="button" wire:click="saveAsTemplate" class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                <x-phosphor-stack class="h-4 w-4" /> Enregistrer comme modèle
                            </button>
                        @endcan

                        {{-- Manual automation buttons --}}
                        @if ($cardButtons->isNotEmpty())
                            <div class="space-y-1.5">
                                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Actions rapides</h3>
                                @foreach ($cardButtons as $button)
                                    <button type="button" wire:click="runAutomation({{ $button->id }})" wire:key="cardbtn-{{ $button->id }}" class="flex w-full items-center gap-2 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                                        <x-phosphor-lightning class="h-4 w-4 text-amber-500" /> {{ $button->name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        {{-- Cover (solid color — an image cover is set from the attachments list) --}}
                        @php $coverPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b']; @endphp
                        <div>
                            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Couverture</h3>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @foreach ($coverPalette as $swatch)
                                    <button type="button" wire:click="setCoverColor('{{ $swatch }}')" class="h-6 w-6 rounded-md ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-900 {{ $card->cover_color === $swatch ? 'ring-2 ring-indigo-500' : '' }}" style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
                                @endforeach
                                @if ($card->cover_path || $card->cover_color)
                                    <button type="button" wire:click="clearCover" class="flex h-6 items-center gap-1 rounded-md border border-neutral-300 px-2 text-xs text-neutral-500 hover:text-neutral-700 dark:border-neutral-700 dark:hover:text-neutral-200" title="Retirer la couverture"><x-phosphor-x class="h-3 w-3" /> Retirer</button>
                                @endif
                            </div>
                            @if ($card->cover_path)
                                <p class="mt-1.5 text-xs text-neutral-400">Une image est utilisée comme couverture (voir Pièces jointes).</p>
                            @endif
                        </div>

                        {{-- Due date (toggleable, like Members / Labels) --}}
                        @php $dueOverdue = $card->due_at && ! $card->completed_at && $card->due_at->isPast(); @endphp
                        <div x-data="{ enabled: @js((bool) $card->due_at) }">
                            <div class="mb-2 flex items-center justify-between">
                                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Échéance</h3>
                                <button
                                    type="button"
                                    role="switch"
                                    aria-label="Activer l'échéance"
                                    :aria-checked="enabled"
                                    @click="enabled = ! enabled; if (! enabled) { $wire.clearDueDate() }"
                                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition"
                                    :class="enabled ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700'"
                                >
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition" :class="enabled ? 'translate-x-4' : 'translate-x-0.5'"></span>
                                </button>
                            </div>
                            <div x-show="enabled" x-cloak class="space-y-1.5">
                                <input type="datetime-local" wire:model="dueAt" wire:change="saveDueDate" class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                                @if ($card->due_at)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="{{ $dueOverdue ? 'font-medium text-red-600 dark:text-red-400' : 'text-neutral-500' }}">
                                            {{ $card->due_at->translatedFormat('d M Y \à H:i') }}{{ $dueOverdue ? ' · en retard' : '' }}
                                        </span>
                                        <button type="button" wire:click="clearDueDate" @click="enabled = false" class="text-neutral-400 hover:text-red-500">Retirer</button>
                                    </div>
                                @endif
                            </div>
                        </div>

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
                                        @if ($assigned) <x-phosphor-check class="ml-auto h-4 w-4 text-indigo-600 dark:text-indigo-400" /> @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Labels --}}
                        <div>
                            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Labels</h3>
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
                                            <button type="button" @click="openAt($event.clientX, $event.clientY)" class="shrink-0 rounded p-1 text-neutral-400 opacity-0 transition hover:bg-neutral-100 hover:text-neutral-700 group-hover/label:opacity-100 dark:hover:bg-neutral-800 dark:hover:text-neutral-200" title="Options du label (clic droit aussi)"><x-phosphor-dots-three class="h-4 w-4" /></button>
                                        </x-slot:trigger>
                                        <x-slot:menu>
                                            <div class="p-1" x-data="{ name: @js($label->name) }" @click.stop>
                                                <input
                                                    type="text"
                                                    x-model="name"
                                                    @keydown.enter="$wire.renameLabel({{ $label->id }}, name); shown = false"
                                                    placeholder="Nom du label"
                                                    class="w-full rounded-md border border-neutral-300 bg-white px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900"
                                                >
                                            </div>
                                            <div class="flex flex-wrap gap-1.5 px-2 py-1.5">
                                                @foreach ($labelPalette as $swatch)
                                                    <button type="button" @click="$wire.recolorLabel({{ $label->id }}, '{{ $swatch }}'); shown = false" class="h-5 w-5 rounded-full ring-offset-1 hover:ring-2 hover:ring-neutral-400 dark:ring-offset-neutral-800" style="background-color: {{ $swatch }}" title="{{ $swatch }}"></button>
                                                @endforeach
                                            </div>
                                            <x-context-menu.separator />
                                            <x-context-menu.item icon="trash" variant="danger" @click="$store.confirm.open({ title: 'Supprimer le label', message: 'Supprimer ce label du board ?', confirmLabel: 'Supprimer', danger: true }).then(ok => ok && $wire.deleteLabel({{ $label->id }}))">Supprimer</x-context-menu.item>
                                        </x-slot:menu>
                                    </x-context-menu>
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
        </x-modal>
    @endif
</div>
