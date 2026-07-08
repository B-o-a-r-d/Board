<?php

namespace App\Livewire\Cards;

use App\Automations\AutomationEngine;
use App\Enums\CustomFieldType;
use App\Events\BoardActivity;
use App\Models\Activity;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardLink;
use App\Models\CardTemplate;
use App\Models\ChecklistItem;
use App\Models\LinkPreview;
use App\Models\User;
use App\Notifications\CardNotification;
use App\Services\UrlPreviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class CardDetail extends Component
{
    use WithFileUploads;

    /** @var array<int, string> Curated emoji set available as comment reactions. */
    public const REACTIONS = ['👍', '❤️', '😄', '🎉', '😮', '🚀'];

    public Board $board;

    public ?int $cardId = null;

    public bool $showModal = false;

    public string $title = '';

    public string $description = '';

    public ?string $startDate = null;

    public ?string $startTime = null;

    public ?string $dueDate = null;

    public ?string $dueTime = null;

    public string $newChecklistTitle = '';

    /** @var array<int, string> */
    public array $newChecklistItem = [];

    public string $newLabelName = '';

    public string $newLabelColor = '#3b82f6';

    public mixed $upload = null;

    public mixed $coverUpload = null;

    public string $newComment = '';

    public ?int $editingCommentId = null;

    public string $editingCommentBody = '';

    public function mount(Board $board): void
    {
        $this->board = $board;

        // Deep-link: open a card directly via ?card=<public_id> (notifications, copied links).
        $cardKey = (string) request()->query('card');

        if ($cardKey !== '' && $card = $this->board->cards()->where('public_id', $cardKey)->first()) {
            $this->openCard($card->id);
        }
    }

    /**
     * Re-render the open modal when a remote board activity is broadcast,
     * so comments and edits from other users appear live.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            "echo-private:board.{$this->board->id},.board.activity" => 'onRemoteActivity',
        ];
    }

    public function onRemoteActivity(): void {}

    #[On('open-card')]
    public function openCard(int $cardId, ?string $section = null, ?int $comment = null): void
    {
        $this->authorize('view', $this->board);

        $card = $this->board->cards()->findOrFail($cardId);

        $this->cardId = $card->id;
        $this->title = $card->title;
        $this->description = (string) $card->description;
        $this->startDate = $card->start_at?->format('Y-m-d');
        $this->startTime = $card->start_at?->format('H:i');
        $this->dueDate = $card->due_at?->format('Y-m-d');
        $this->dueTime = $card->due_at?->format('H:i');
        $this->resetValidation();
        $this->showModal = true;

        // Let the freshly-rendered modal scroll to / expand the relevant element
        // (a specific comment or a named section) once its DOM exists.
        if ($section !== null || $comment !== null) {
            $this->dispatch('card-focus', section: $section, comment: $comment);
        }
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->cardId = null;
        $this->reset('title', 'description', 'startDate', 'startTime', 'dueDate', 'dueTime', 'newChecklistTitle', 'newChecklistItem', 'upload', 'editingCommentId', 'editingCommentBody');
    }

    public function saveDetails(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $card->update([
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
        ]);

        $this->touched('card.updated');
    }

    public function saveDates(): void
    {
        $card = $this->guardedCard();

        $this->validate([
            'startDate' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $start = $this->combineDateTime($this->startDate, $this->startTime);
        $newDue = $this->combineDateTime($this->dueDate, $this->dueTime);

        if ($start !== null && $newDue !== null && $newDue->lt($start)) {
            $this->addError('dueDate', __('L’échéance doit être postérieure au début.'));

            return;
        }

        $hadDue = $card->due_at !== null;

        $card->update([
            'start_at' => $start,
            'due_at' => $newDue,
        ]);

        if ($newDue === null && $hadDue) {
            $this->logActivity($card, 'card.due_removed');
        } elseif ($newDue !== null) {
            $this->logActivity($card, $hadDue ? 'card.due_changed' : 'card.due_set', ['value' => $newDue->translatedFormat('d M Y \à H:i')]);
        }

        $this->touched('card.updated');
    }

    /**
     * Combine a date (required) with an optional time into a Carbon instant.
     * The time is optional — a due date with no time defaults to noon so the
     * schedule saves from the date alone.
     */
    private function combineDateTime(?string $date, ?string $time): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        return Carbon::parse($date.' '.(empty($time) ? '12:00' : $time));
    }

    public function clearDates(): void
    {
        $card = $this->guardedCard();

        $hadDue = $card->due_at !== null;

        $card->update(['start_at' => null, 'due_at' => null]);
        $this->startDate = null;
        $this->startTime = null;
        $this->dueDate = null;
        $this->dueTime = null;

        if ($hadDue) {
            $this->logActivity($card, 'card.due_removed');
        }

        $this->touched('card.updated');
    }

    /**
     * Set (or clear) a custom field value on the current card. A null/empty
     * value removes the stored row so the field reads as unset.
     */
    public function saveCustomField(int $fieldId, mixed $value): void
    {
        $card = $this->guardedCard();

        $field = $this->board->customFields()->findOrFail($fieldId);

        $stored = match ($field->type) {
            CustomFieldType::Checkbox => $value ? '1' : null,
            CustomFieldType::Select => in_array($value, $field->options ?? [], true) ? $value : null,
            default => ($value === '' || $value === null) ? null : (string) $value,
        };

        if ($stored === null) {
            $card->customFieldValues()->where('custom_field_id', $field->id)->delete();
        } else {
            $card->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $stored],
            );
        }

        $this->touched('card.updated');
    }

    public function saveDescription(string $markdown): void
    {
        $card = $this->guardedCard();

        $markdown = trim($markdown);
        $this->description = $markdown;
        $card->update(['description' => $markdown ?: null]);
        $this->touched('card.updated');
    }

    public function toggleComplete(): void
    {
        $card = $this->guardedCard();

        $card->update(['completed_at' => $card->completed_at ? null : now()]);

        $this->logActivity($card, $card->completed_at ? 'card.completed' : 'card.uncompleted');

        $this->touched('card.completed');
    }

    /**
     * Toggle whether the current user watches this card (personal subscription:
     * receive comment notifications without being assigned).
     */
    public function toggleWatch(): void
    {
        $card = $this->guardedCard();

        $card->watchers()->toggle(Auth::id());
    }

    public string $linkType = 'blocks';

    public string $linkSearch = '';

    /**
     * Link another card on the same board. Types are normalised so only
     * 'blocks' and (symmetric) 'relates_to' rows are stored.
     */
    public function linkCard(int $relatedCardId, ?string $type = null): void
    {
        $card = $this->guardedCard();

        $type ??= $this->linkType;

        if (! in_array($type, ['blocks', 'blocked_by', 'relates_to'], true)) {
            return;
        }

        $related = $this->board->cards()->whereKey($relatedCardId)->first();

        if (! $related || $related->id === $card->id) {
            return;
        }

        if ($type === 'relates_to') {
            CardLink::firstOrCreate([
                'card_id' => min($card->id, $related->id),
                'related_card_id' => max($card->id, $related->id),
                'type' => 'relates_to',
            ]);
        } else {
            [$from, $to] = $type === 'blocks' ? [$card->id, $related->id] : [$related->id, $card->id];
            CardLink::firstOrCreate(['card_id' => $from, 'related_card_id' => $to, 'type' => 'blocks']);
        }

        $this->linkSearch = '';
        $this->touched('card.linked');
    }

    public function unlinkCard(int $linkId): void
    {
        $card = $this->guardedCard();

        CardLink::whereKey($linkId)
            ->where(fn ($q) => $q->where('card_id', $card->id)->orWhere('related_card_id', $card->id))
            ->delete();

        $this->touched('card.unlinked');
    }

    public function toggleMember(int $userId): void
    {
        $card = $this->guardedCard();

        if (! $this->board->hasMember(User::findOrNew($userId))) {
            return;
        }

        $result = $card->members()->toggle($userId);

        if (in_array($userId, $result['attached'], true) && $userId !== Auth::id()) {
            $assignee = User::find($userId);

            $this->logActivity($card, 'member.assigned', ['user_id' => $userId, 'user_name' => $assignee?->name]);

            if ($assignee) {
                $assignee->notify(new CardNotification($card, 'assigned', Auth::user()));
            }
        }

        $this->touched('card.members');
    }

    public function toggleLabel(int $labelId): void
    {
        $card = $this->guardedCard();
        $label = $this->board->labels()->findOrFail($labelId);

        $card->labels()->toggle($label->id);
        $this->touched('card.labels');
    }

    public function createLabel(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate([
            'newLabelName' => ['nullable', 'string', 'max:255'],
            'newLabelColor' => ['required', 'string', 'max:9'],
        ]);

        $label = $this->board->labels()->create([
            'name' => $data['newLabelName'] ?: null,
            'color' => $data['newLabelColor'],
        ]);

        $card->labels()->attach($label->id);
        $this->reset('newLabelName');
        $this->touched('label.created');
    }

    public function renameLabel(int $labelId, string $name): void
    {
        $this->authorize('view', $this->board);

        $this->board->labels()->findOrFail($labelId)->update(['name' => trim($name) ?: null]);
        $this->touched('label.renamed');
    }

    public function recolorLabel(int $labelId, string $color): void
    {
        $this->authorize('view', $this->board);

        $this->board->labels()->whereKey($labelId)->update(['color' => $color]);
        $this->touched('label.recolored');
    }

    public function deleteLabel(int $labelId): void
    {
        $this->authorize('view', $this->board);

        $this->board->labels()->whereKey($labelId)->delete();
        $this->touched('label.deleted');
    }

    public function addChecklist(): void
    {
        $card = $this->guardedCard();

        $this->validate(['newChecklistTitle' => ['required', 'string', 'max:255']]);

        $card->checklists()->create([
            'title' => $this->newChecklistTitle,
            'position' => (int) $card->checklists()->max('position') + 1,
        ]);

        $this->reset('newChecklistTitle');
        $this->logActivity($card, 'checklist.created');
        $this->touched('checklist.created');
    }

    public function deleteChecklist(int $checklistId): void
    {
        $card = $this->guardedCard();
        $card->checklists()->findOrFail($checklistId)->delete();
        $this->logActivity($card, 'checklist.deleted');
        $this->touched('checklist.deleted');
    }

    public function addChecklistItem(int $checklistId): void
    {
        $card = $this->guardedCard();
        $checklist = $card->checklists()->findOrFail($checklistId);

        $content = trim($this->newChecklistItem[$checklistId] ?? '');

        if ($content === '') {
            return;
        }

        $checklist->items()->create([
            'content' => $content,
            'position' => (int) $checklist->items()->max('position') + 1,
        ]);

        $this->newChecklistItem[$checklistId] = '';
        $this->touched('checklist.item.added');
    }

    public function toggleChecklistItem(int $itemId): void
    {
        $card = $this->guardedCard();

        $item = ChecklistItem::query()
            ->whereHas('checklist', fn ($query) => $query->where('card_id', $card->id))
            ->findOrFail($itemId);

        $item->update(['is_completed' => ! $item->is_completed]);
        $this->touched('checklist.item.toggled');
    }

    public function deleteChecklistItem(int $itemId): void
    {
        $card = $this->guardedCard();

        ChecklistItem::query()
            ->whereHas('checklist', fn ($query) => $query->where('card_id', $card->id))
            ->findOrFail($itemId)
            ->delete();

        $this->touched('checklist.item.deleted');
    }

    /**
     * Assign (or clear, with null) a board member to a checklist item — turning
     * items into real sub-tasks.
     */
    public function assignChecklistItem(int $itemId, ?int $userId): void
    {
        $card = $this->guardedCard();
        $item = $this->guardedChecklistItem($card, $itemId);

        if ($userId !== null && ! $this->board->hasMember(User::findOrNew($userId))) {
            return;
        }

        $item->update(['assigned_to' => $userId]);
        $this->touched('checklist.item.assigned');
    }

    /**
     * Set (or clear, with an empty value) a checklist item due date (noon).
     */
    public function setChecklistItemDue(int $itemId, ?string $date): void
    {
        $card = $this->guardedCard();
        $item = $this->guardedChecklistItem($card, $itemId);

        $item->update([
            'due_at' => ($date !== null && $date !== '') ? Carbon::parse($date)->setTime(12, 0) : null,
        ]);
        $this->touched('checklist.item.due');
    }

    private function guardedChecklistItem(Card $card, int $itemId): ChecklistItem
    {
        return ChecklistItem::query()
            ->whereHas('checklist', fn ($query) => $query->where('card_id', $card->id))
            ->findOrFail($itemId);
    }

    public function saveAttachment(): void
    {
        $card = $this->guardedCard();

        $this->validate([
            'upload' => ['required', 'file', 'max:204800', 'mimes:jpg,jpeg,png,gif,webp,svg,mp4,webm,mov,ogg'],
        ]);

        $path = $this->upload->store("attachments/{$this->board->id}", 'public');

        $card->attachments()->create([
            'uploaded_by' => Auth::id(),
            'disk' => 'public',
            'path' => $path,
            'name' => $this->upload->getClientOriginalName(),
            'mime_type' => $this->upload->getMimeType(),
            'size' => $this->upload->getSize(),
        ]);

        $this->logActivity($card, 'attachment.added', ['value' => $this->upload->getClientOriginalName()]);
        $this->reset('upload');
        $this->touched('attachment.added');
        $this->dispatch('toast', message: 'Pièce jointe ajoutée', type: 'success');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $card = $this->guardedCard();
        $attachment = $card->attachments()->findOrFail($attachmentId);

        Storage::disk($attachment->disk)->delete($attachment->path);

        if ($card->cover_path === $attachment->path) {
            $card->update(['cover_path' => null]);
        }

        $attachment->delete();
        $this->touched('attachment.deleted');
    }

    public function setCover(int $attachmentId): void
    {
        $card = $this->guardedCard();
        $attachment = $card->attachments()->findOrFail($attachmentId);

        if (! $attachment->isImage()) {
            return;
        }

        $useAsCover = $card->cover_path !== $attachment->path;

        $card->update([
            'cover_path' => $useAsCover ? $attachment->path : null,
            'cover_color' => $useAsCover ? null : $card->cover_color,
        ]);
        $this->touched('card.cover');
    }

    public function setCoverColor(string $color): void
    {
        $card = $this->guardedCard();

        $card->update([
            'cover_color' => $card->cover_color === $color ? null : $color,
            'cover_path' => null,
        ]);
        $this->touched('card.cover');
    }

    public function clearCover(): void
    {
        $card = $this->guardedCard();

        $card->update(['cover_path' => null, 'cover_color' => null]);
        $this->touched('card.cover');
    }

    public function uploadCover(): void
    {
        $card = $this->guardedCard();

        $this->validate(['coverUpload' => ['required', 'image', 'max:10240']]);

        $path = $this->coverUpload->store("covers/{$this->board->id}", 'public');

        $card->update(['cover_path' => $path, 'cover_color' => null]);
        $this->reset('coverUpload');
        $this->touched('card.cover');
        $this->dispatch('toast', message: 'Couverture mise à jour', type: 'success');
    }

    public function saveAsTemplate(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $card = $this->guardedCard()->load('checklists.items');

        CardTemplate::create([
            'created_by' => Auth::id(),
            'name' => $card->title,
            'title' => $card->title,
            'description' => $card->description,
            'cover_color' => $card->cover_color,
            'checklists' => $card->checklists->map(fn ($checklist) => [
                'title' => $checklist->title,
                'items' => $checklist->items->pluck('content')->all(),
            ])->all(),
        ]);

        $this->dispatch('toast', message: 'Carte enregistrée comme modèle global', type: 'success');
    }

    public function addComment(string $body = ''): void
    {
        $card = $this->guardedCard();

        // The TipTap composer passes the markdown body directly; fall back to the
        // bound property so other callers/tests keep working.
        if (trim($body) !== '') {
            $this->newComment = trim($body);
        }

        $data = $this->validate(['newComment' => ['required', 'string', 'max:5000']]);

        $comment = $card->comments()->create([
            'user_id' => Auth::id(),
            'body' => $data['newComment'],
        ]);

        // Commenting subscribes you to the card so you follow the thread.
        $card->watchers()->syncWithoutDetaching([Auth::id()]);

        $this->logActivity($card, 'comment.created', [
            'excerpt' => Str::limit(trim(strip_tags($data['newComment'])), 140),
            'comment_id' => $comment->id,
        ]);
        $this->notifyForComment($card, $data['newComment']);

        $this->reset('newComment');
        $this->touched('comment.created');
    }

    /**
     * Notify mentioned users (as mentions) and the card's members + watchers
     * (as a comment), excluding the comment author and the mentioned users.
     */
    private function notifyForComment(Card $card, string $body): void
    {
        $actor = Auth::user();
        $excerpt = Str::limit(trim($body), 120);

        $mentioned = $this->mentionedUsers($body)->reject(fn (User $user) => $user->is($actor));

        $mentioned->each(fn (User $user) => $user->notify(new CardNotification($card, 'mention', $actor, $excerpt)));

        $mentionedIds = $mentioned->pluck('id')->push($actor->getKey());

        $recipientIds = $card->members()->pluck('users.id')
            ->merge($card->watchers()->pluck('users.id'))
            ->unique()
            ->diff($mentionedIds);

        User::whereKey($recipientIds)
            ->get()
            ->each(fn (User $user) => $user->notify(new CardNotification($card, 'comment', $actor, $excerpt)));
    }

    /**
     * @return Collection<int, User>
     */
    private function mentionedUsers(string $body): Collection
    {
        preg_match_all('/@([\p{L}0-9_-]+)/u', $body, $matches);

        if (empty($matches[1])) {
            return collect();
        }

        $slugs = collect($matches[1])->map(fn (string $token) => Str::slug($token))->unique();

        return $this->board->members->filter(fn (User $user) => $slugs->contains(Str::slug($user->name)))->values();
    }

    public function deleteComment(int $commentId): void
    {
        $card = $this->guardedCard();
        $comment = $card->comments()->findOrFail($commentId);

        abort_unless(
            $comment->user_id === Auth::id() || $this->board->memberRole(Auth::user())?->isAdministrator(),
            403,
        );

        $comment->delete();
        $this->touched('comment.deleted');
    }

    public function startEditComment(int $commentId): void
    {
        $card = $this->guardedCard();
        $comment = $card->comments()->findOrFail($commentId);

        abort_unless(
            $comment->user_id === Auth::id() || $this->board->memberRole(Auth::user())?->isAdministrator(),
            403,
        );

        $this->editingCommentId = $comment->id;
        $this->editingCommentBody = $comment->body;
    }

    public function saveComment(): void
    {
        $card = $this->guardedCard();
        $comment = $card->comments()->findOrFail($this->editingCommentId);

        abort_unless(
            $comment->user_id === Auth::id() || $this->board->memberRole(Auth::user())?->isAdministrator(),
            403,
        );

        $data = $this->validate(['editingCommentBody' => ['required', 'string', 'max:5000']]);

        $comment->update(['body' => $data['editingCommentBody']]);
        $this->reset('editingCommentId', 'editingCommentBody');
        $this->touched('comment.updated');
    }

    public function cancelEditComment(): void
    {
        $this->reset('editingCommentId', 'editingCommentBody');
    }

    /**
     * Toggle the current user's emoji reaction on a comment. Adding a reaction
     * notifies the comment author (unless they reacted to their own comment).
     */
    public function toggleReaction(int $commentId, string $emoji): void
    {
        $card = $this->guardedCard();
        $comment = $card->comments()->findOrFail($commentId);

        abort_unless(in_array($emoji, self::REACTIONS, true), 422);

        $existing = $comment->reactions()
            ->where('user_id', Auth::id())
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            $comment->reactions()->create(['user_id' => Auth::id(), 'emoji' => $emoji]);

            if ($comment->user_id !== Auth::id() && $comment->user) {
                $comment->user->notify(new CardNotification($card, 'reaction', Auth::user(), $emoji));
            }
        }

        $this->touched('comment.reaction');
    }

    /**
     * Escape a comment body, highlight @mentions of board members, and linkify URLs.
     */
    public function renderCommentBody(string $body): string
    {
        $members = $this->board->members;

        // Render the stored markdown; raw HTML is escaped (never rendered).
        $html = Str::markdown($body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);

        // Highlight @slug mentions that resolve to a board member.
        return (string) preg_replace_callback('/@([\p{L}0-9_-]+)/u', function (array $match) use ($members) {
            $token = $match[1];

            $member = $members->first(fn ($user) => Str::slug($user->name) === Str::slug($token)
                || Str::lower(Str::before($user->name, ' ')) === Str::lower($token));

            return $member
                ? '<span class="rounded bg-indigo-100 px-1 font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">@'.e($member->name).'</span>'
                : $match[0];
        }, $html);
    }

    /**
     * Resolve Open Graph link previews for the URLs found in a text block.
     *
     * @return array<int, LinkPreview>
     */
    public function linkPreviews(?string $text): array
    {
        if (blank($text)) {
            return [];
        }

        $service = app(UrlPreviewService::class);

        return collect($service->extractUrls($text))
            ->map(fn (string $url) => $service->preview($url))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Toggle the shared "embed hidden" state for a URL in the card description.
     */
    public function toggleDescriptionPreview(string $url): void
    {
        $card = $this->guardedCard();

        $card->update(['hidden_previews' => $this->toggleInList($card->hidden_previews ?? [], $url)]);
        $this->touched('card.preview');
    }

    /**
     * Toggle the shared "embed hidden" state for a URL in a comment.
     */
    public function toggleCommentPreview(int $commentId, string $url): void
    {
        $card = $this->guardedCard();
        $comment = $card->comments()->findOrFail($commentId);

        $comment->update(['hidden_previews' => $this->toggleInList($comment->hidden_previews ?? [], $url)]);
        $this->touched('comment.preview');
    }

    /**
     * @param  array<int, string>  $list
     * @return array<int, string>
     */
    private function toggleInList(array $list, string $value): array
    {
        return in_array($value, $list, true)
            ? array_values(array_diff($list, [$value]))
            : [...$list, $value];
    }

    /**
     * Run a manual automation (a card button) against the open card.
     */
    public function runAutomation(int $automationId): void
    {
        $card = $this->guardedCard();

        $automation = $this->board->automations()
            ->where('trigger_type', 'manual')
            ->where('is_active', true)
            ->findOrFail($automationId);

        app(AutomationEngine::class)->runManual($automation, $card);

        $this->touched('automation.run');
        $this->dispatch('toast', message: 'Action « '.$automation->name.' » exécutée', type: 'success');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logActivity(Card $card, string $type, array $properties = []): void
    {
        Activity::create([
            'board_id' => $card->board_id,
            'card_id' => $card->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'properties' => array_merge(['card_title' => $card->title], $properties),
        ]);
    }

    private function guardedCard(): Card
    {
        $this->authorize('view', $this->board);

        return $this->board->cards()->findOrFail($this->cardId);
    }

    private function touched(string $action): void
    {
        broadcast(new BoardActivity($this->board->id, $action, Auth::id()))->toOthers();
        $this->dispatch('board-refresh');
    }

    public function render(): View
    {
        $card = $this->cardId
            ? $this->board->cards()
                ->with(['members', 'watchers', 'labels', 'checklists.items.assignee', 'attachments.uploader', 'comments.user', 'comments.reactions', 'activities.user', 'customFieldValues'])
                ->find($this->cardId)
            : null;

        $cardLinks = ['blocks' => collect(), 'blockedBy' => collect(), 'relates' => collect()];
        $linkCandidates = collect();

        if ($card) {
            $cardLinks['blocks'] = CardLink::where('card_id', $card->id)->where('type', 'blocks')->with('relatedCard')->get();
            $cardLinks['blockedBy'] = CardLink::where('related_card_id', $card->id)->where('type', 'blocks')->with('card')->get();
            $cardLinks['relates'] = CardLink::where('type', 'relates_to')
                ->where(fn ($q) => $q->where('card_id', $card->id)->orWhere('related_card_id', $card->id))
                ->with(['card', 'relatedCard'])->get();

            if (trim($this->linkSearch) !== '') {
                $linkedIds = collect([$card->id])
                    ->merge($cardLinks['blocks']->pluck('related_card_id'))
                    ->merge($cardLinks['blockedBy']->pluck('card_id'))
                    ->merge($cardLinks['relates']->flatMap(fn ($l) => [$l->card_id, $l->related_card_id]))
                    ->unique()->all();

                $linkCandidates = $this->board->cards()
                    ->whereNull('archived_at')
                    ->whereKeyNot($linkedIds)
                    ->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower(trim($this->linkSearch)).'%'])
                    ->orderBy('title')->limit(8)->get();
            }
        }

        return view('livewire.cards.card-detail', [
            'card' => $card,
            'boardMembers' => $this->board->members,
            'boardLabels' => $this->board->labels,
            'cardButtons' => $this->board->automations()->where('trigger_type', 'manual')->where('is_active', true)->get(),
            'reactionEmojis' => self::REACTIONS,
            'cardLinks' => $cardLinks,
            'linkCandidates' => $linkCandidates,
            'customFields' => $this->board->customFields,
        ]);
    }
}
