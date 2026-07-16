{{-- Comments (TipTap composer, @mentions, reactions) + the lazy activity feed.
     Included from card-detail.blade.php — shares its full Blade + Alpine scope. --}}
                        {{-- Comments & activity --}}
                        <div x-show="panel === 'comments'" class="space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="flex min-w-0 items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                                    <x-phosphor-chat-circle-dots class="h-5 w-5 shrink-0 text-neutral-400" />
                                    <span class="truncate">{{ __('Commentaires et activité') }}</span>
                                </h3>
                                <button type="button" wire:click="toggleActivity"
                                        class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-neutral-300 px-2.5 text-sm text-neutral-600 transition hover:bg-neutral-100 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-700/50">
                                    {{ $showActivity ? __('Masquer les détails') : __('Afficher les détails') }}
                                    <span wire:loading wire:target="toggleActivity"><x-phosphor-circle-notch class="h-3.5 w-3.5 animate-spin text-neutral-400" /></span>
                                </button>
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
                                @if ($canComment)
                                <div class="relative">
                                    <div class="rounded-lg border border-neutral-300 bg-white focus-within:border-indigo-500 dark:border-neutral-700 dark:bg-neutral-900/60">
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
                                @else
                                    <p class="rounded-lg bg-neutral-100 px-3 py-2 text-sm text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">{{ __('Lecture seule : vous ne pouvez pas commenter.') }}</p>
                                @endif

                                <div class="space-y-3">
                                    @foreach ($card->comments as $comment)
                                        @php $canDelete = $comment->user_id === auth()->id() || $board->memberRole(auth()->user())?->isAdministrator(); @endphp
                                        <div wire:key="comment-{{ $comment->id }}" id="comment-{{ $comment->id }}" class="group/comment flex gap-2">
                                            <x-user-avatar :user="$comment->user" size="sm" class="mt-0.5" />
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium">{{ $comment->user?->name ?? __('Utilisateur supprimé') }}</span>
                                                    <span class="text-xs text-neutral-400">{{ $comment->created_at->diffForHumans() }}@if ($comment->updated_at->gt($comment->created_at)) · {{ __('modifié') }}@endif</span>
                                                    <div class="ml-auto flex items-center gap-2" x-data="{ copied: false }">
                                                        <button type="button" @click="navigator.clipboard?.writeText('{{ route('boards.show', ['board' => $board, 'card' => $card->public_id]) }}#comment-{{ $comment->id }}'); window.toast('{{ __('Lien copié') }}', { type: 'success' }); copied = true; setTimeout(() => copied = false, 1500)" class="text-xs text-neutral-300 opacity-100 transition hover:text-indigo-500 group-hover/comment:opacity-100 sm:opacity-0" title="{{ __('Copier le lien du commentaire') }}"><span x-text="copied ? '{{ __('Copié !') }}' : '{{ __('Lien') }}'"></span></button>
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
                                                @if ($canComment)
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
                                                @else
                                                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                                        @foreach ($grouped as $emoji => $group)
                                                            <span class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"><span>{{ $emoji }}</span><span class="font-medium">{{ $group->count() }}</span></span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Activity feed (lazy-loaded via the "details" toggle) --}}
                            @if ($showActivity)
                            <div class="space-y-2.5 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                                @forelse ($activities as $activity)
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
                                        <x-user-avatar :user="$activity->user" size="xs" />
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
                            @endif
                        </div>
