<?php

namespace App\Livewire\Cards;

use App\Automations\AutomationEngine;
use App\Events\BoardActivity;
use App\Models\Activity;
use App\Models\Board;
use App\Models\Card;
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

    public Board $board;

    public ?int $cardId = null;

    public bool $showModal = false;

    public string $title = '';

    public string $description = '';

    public ?string $dueAt = null;

    public string $newChecklistTitle = '';

    /** @var array<int, string> */
    public array $newChecklistItem = [];

    public string $newLabelName = '';

    public string $newLabelColor = '#3b82f6';

    public mixed $upload = null;

    public mixed $coverUpload = null;

    public string $newComment = '';

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
    public function openCard(int $cardId): void
    {
        $this->authorize('view', $this->board);

        $card = $this->board->cards()->findOrFail($cardId);

        $this->cardId = $card->id;
        $this->title = $card->title;
        $this->description = (string) $card->description;
        $this->dueAt = $card->due_at?->format('Y-m-d\TH:i');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->cardId = null;
        $this->reset('title', 'description', 'dueAt', 'newChecklistTitle', 'newChecklistItem', 'upload');
    }

    public function saveDetails(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'dueAt' => ['nullable', 'date'],
        ]);

        $card->update([
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'due_at' => $data['dueAt'] ? Carbon::parse($data['dueAt']) : null,
        ]);

        $this->touched('card.updated');
    }

    public function saveDueDate(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate(['dueAt' => ['nullable', 'date']]);

        $card->update(['due_at' => $data['dueAt'] ? Carbon::parse($data['dueAt']) : null]);
        $this->touched('card.updated');
    }

    public function clearDueDate(): void
    {
        $card = $this->guardedCard();

        $card->update(['due_at' => null]);
        $this->dueAt = null;
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

        if ($card->completed_at) {
            $this->logActivity($card, 'card.completed');
        }

        $this->touched('card.completed');
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
        $this->touched('checklist.created');
    }

    public function deleteChecklist(int $checklistId): void
    {
        $card = $this->guardedCard();
        $card->checklists()->findOrFail($checklistId)->delete();
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

    public function addComment(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate(['newComment' => ['required', 'string', 'max:5000']]);

        $card->comments()->create([
            'user_id' => Auth::id(),
            'body' => $data['newComment'],
        ]);

        $this->logActivity($card, 'comment.created');
        $this->notifyForComment($card, $data['newComment']);

        $this->reset('newComment');
        $this->touched('comment.created');
    }

    /**
     * Notify mentioned members (as mentions) and other card members (as a comment),
     * excluding the comment author.
     */
    private function notifyForComment(Card $card, string $body): void
    {
        $actor = Auth::user();
        $excerpt = Str::limit(trim($body), 120);

        $mentioned = $this->mentionedUsers($body)->reject(fn (User $user) => $user->is($actor));

        $mentioned->each(fn (User $user) => $user->notify(new CardNotification($card, 'mention', $actor, $excerpt)));

        $mentionedIds = $mentioned->pluck('id')->push($actor->getKey())->all();

        $card->members()
            ->whereKeyNot($mentionedIds)
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

    /**
     * Escape a comment body, highlight @mentions of board members, and linkify URLs.
     */
    public function renderCommentBody(string $body): string
    {
        $members = $this->board->members;

        $html = (string) preg_replace_callback('/@([\p{L}0-9_-]+)/u', function (array $match) use ($members) {
            $token = $match[1];

            $member = $members->first(fn ($user) => Str::slug($user->name) === Str::slug($token)
                || Str::lower(Str::before($user->name, ' ')) === Str::lower($token));

            if ($member) {
                return '<span class="rounded bg-indigo-100 px-1 font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">@'.e($member->name).'</span>';
            }

            return $match[0];
        }, e($body));

        // Linkify plain URLs (the text is already HTML-escaped; the regex stops at "<").
        return (string) preg_replace_callback('#https?://[^\s<]+#', function (array $match) {
            $url = $match[0];

            return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">'.$url.'</a>';
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
            'properties' => $properties,
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
                ->with(['members', 'labels', 'checklists.items', 'attachments.uploader', 'comments.user', 'activities.user'])
                ->find($this->cardId)
            : null;

        return view('livewire.cards.card-detail', [
            'card' => $card,
            'boardMembers' => $this->board->members,
            'boardLabels' => $this->board->labels,
            'cardButtons' => $this->board->automations()->where('trigger_type', 'manual')->where('is_active', true)->get(),
        ]);
    }
}
