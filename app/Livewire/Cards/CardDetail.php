<?php

namespace App\Livewire\Cards;

use App\Events\BoardActivity;
use App\Models\Board;
use App\Models\Card;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

    public function mount(Board $board): void
    {
        $this->board = $board;
    }

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

    public function toggleComplete(): void
    {
        $card = $this->guardedCard();

        $card->update(['completed_at' => $card->completed_at ? null : now()]);
        $this->touched('card.completed');
    }

    public function toggleMember(int $userId): void
    {
        $card = $this->guardedCard();

        if (! $this->board->hasMember(User::findOrNew($userId))) {
            return;
        }

        $card->members()->toggle($userId);
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
            'upload' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,svg,mp4,webm,mov,ogg'],
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

        $card->update(['cover_path' => $card->cover_path === $attachment->path ? null : $attachment->path]);
        $this->touched('card.cover');
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
                ->with(['members', 'labels', 'checklists.items', 'attachments.uploader'])
                ->find($this->cardId)
            : null;

        return view('livewire.cards.card-detail', [
            'card' => $card,
            'boardMembers' => $this->board->members,
            'boardLabels' => $this->board->labels,
        ]);
    }
}
