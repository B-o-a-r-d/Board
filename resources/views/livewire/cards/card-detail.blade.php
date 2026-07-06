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
                                    <div wire:key="comment-{{ $comment->id }}" class="flex gap-2">
                                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                                            {{ Str::of($comment->user?->name ?? '?')->substr(0, 1)->upper() }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium">{{ $comment->user?->name ?? 'Utilisateur supprimé' }}</span>
                                                <span class="text-xs text-neutral-400">{{ $comment->created_at->diffForHumans() }}</span>
                                                @if ($canDelete)
                                                    <button type="button" wire:click="deleteComment({{ $comment->id }})" class="ml-auto text-xs text-neutral-300 hover:text-red-500">Supprimer</button>
                                                @endif
                                            </div>
                                            <div class="mt-0.5 whitespace-pre-wrap break-words text-sm text-neutral-700 dark:text-neutral-300">{!! $this->renderCommentBody($comment->body) !!}</div>
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
