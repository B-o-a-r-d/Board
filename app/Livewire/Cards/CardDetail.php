<?php

namespace App\Livewire\Cards;

use App\Automations\AutomationEngine;
use App\Enums\CustomFieldType;
use App\Enums\Permission;
use App\Events\BoardActivity;
use App\Models\Activity;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardLink;
use App\Models\CardMirror;
use App\Models\CardTemplate;
use App\Models\ChecklistItem;
use App\Models\Label;
use App\Models\LinkPreview;
use App\Models\User;
use App\Notifications\CardNotification;
use App\Services\UrlPreviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

use function Illuminate\Support\defer;

class CardDetail extends Component
{
    use WithFileUploads;

    /** @var array<int, string> Curated emoji set available as comment reactions. */
    public const REACTIONS = ['👍', '❤️', '😄', '🎉', '😮', '🚀'];

    public Board $board;

    public ?int $cardId = null;

    public bool $showModal = false;

    /** Mirror picker: the target list this card should be mirrored into. */
    /**
     * Mirror targets scan every workspace board (+ a policy check per board) —
     * they are only loaded once this flag is set by the "⋯ → Miroir" menu item.
     */
    public bool $showMirrorPicker = false;

    public string $mirrorListId = '';

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

    /**
     * A remote broadcast only matters while the modal is open (comments, labels…
     * may have changed). Closed, re-rendering would run the whole heavy render()
     * on every client for nothing.
     */
    public function onRemoteActivity(): void
    {
        if (! $this->showModal) {
            $this->skipRender();
        }
    }

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
        $this->reset('title', 'description', 'startDate', 'startTime', 'dueDate', 'dueTime', 'newChecklistTitle', 'newChecklistItem', 'upload', 'editingCommentId', 'editingCommentBody', 'mirrorListId', 'showMirrorPicker');

        // Let the instant-open skeleton (Alpine) stand down once the modal is gone.
        $this->dispatch('card-modal-closed');
    }

    public function saveDetails(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $renamed = $card->title !== $data['title'];

        $card->update([
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
        ]);

        if ($renamed) {
            app(AutomationEngine::class)->fire('card.renamed', $card);
        }

        $this->touched('card.updated');
    }

    /**
     * Implicit save: the title persists on blur (no save button). A transient
     * empty/too-long value while typing is ignored rather than flashing an error.
     */
    public function updatedTitle(string $value): void
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 255) {
            return;
        }

        $card = $this->guardedCard();

        if ($card->title !== $value) {
            $card->update(['title' => $value]);
            app(AutomationEngine::class)->fire('card.renamed', $card);
        }

        $this->touched('card.updated');
    }

    /** The activity feed is lazy-loaded on first open (it can be long). */
    public bool $showActivity = false;

    public function toggleActivity(): void
    {
        $this->showActivity = ! $this->showActivity;
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
            app(AutomationEngine::class)->fire('card.due_set', $card);
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

        $field = $this->board->customFields()->visibleOn($this->board)->findOrFail($fieldId);

        // Scoped fields (list / single card) only accept values where they apply.
        abort_unless($field->appliesToCard($card), 404);

        $this->resetErrorBag('cf-'.$field->id);

        // Invalid input keeps the previous value instead of silently clearing it.
        if (in_array($field->type, [CustomFieldType::Url, CustomFieldType::Email], true)
            && is_string($value) && trim($value) !== '') {
            $trimmed = trim($value);
            $invalid = $field->type === CustomFieldType::Url
                ? ! CustomFieldType::isSafeUrl($trimmed)
                : filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false;

            if ($invalid) {
                $this->addError('cf-'.$field->id, $field->type === CustomFieldType::Url
                    ? __('URL invalide (http/https requis).')
                    : __('Adresse email invalide.'));

                return;
            }
        }

        $stored = match ($field->type) {
            CustomFieldType::Checkbox => $value ? '1' : null,
            CustomFieldType::Select => in_array($value, $field->optionList(), true) ? $value : null,
            CustomFieldType::MultiSelect => $this->encodeMultiSelect($field->optionList(), $value),
            CustomFieldType::Member => $this->normalizeMemberValue($value),
            CustomFieldType::Money => is_numeric(str_replace(',', '.', trim((string) $value)))
                ? (string) (float) str_replace(',', '.', trim((string) $value))
                : null,
            CustomFieldType::Rating => ((int) $value > 0) ? (string) min(5, (int) $value) : null,
            CustomFieldType::Progress => ($value === '' || $value === null) ? null : (string) max(0, min(100, (int) $value)),
            default => ($value === '' || $value === null) ? null : (string) trim((string) $value),
        };

        if ($stored === null) {
            $card->customFieldValues()->where('custom_field_id', $field->id)->delete();
        } else {
            $card->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $stored],
            );
        }

        app(AutomationEngine::class)->fire('custom_field.changed', $card, [
            'field_id' => $field->id,
            'value' => $stored,
        ]);

        $this->touched('card.updated');
    }

    public string $newCfName = '';

    public string $newCfType = 'text';

    /** Comma-separated options for the select/multiselect types. */
    public string $newCfOptions = '';

    /** Field scope: card (this card only), list (inherited by its cards), board. */
    public string $newCfScope = 'card';

    /**
     * Create a custom field from the card's "Add to card" menu. Card and list
     * scopes are contributor actions; a board-wide field stays admin-only.
     */
    public function addCardCustomField(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate([
            'newCfName' => ['required', 'string', 'max:60'],
            'newCfType' => ['required', 'string', Rule::enum(CustomFieldType::class)],
            'newCfScope' => ['required', 'string', 'in:card,list,board'],
        ]);

        if ($data['newCfScope'] === 'board') {
            $this->authorize('update', $this->board);
        }

        $type = CustomFieldType::from($data['newCfType']);
        $options = null;

        if ($type->hasOptions()) {
            $options = collect(explode(',', $this->newCfOptions))
                ->map(fn (string $option): string => trim($option))
                ->filter()
                ->values()
                ->all();

            if (empty($options)) {
                $this->addError('newCfOptions', __('Ajoutez au moins une option.'));

                return;
            }
        }

        $this->board->customFields()->create([
            'board_list_id' => $data['newCfScope'] === 'list' ? $card->board_list_id : null,
            'card_id' => $data['newCfScope'] === 'card' ? $card->id : null,
            'name' => $data['newCfName'],
            'type' => $type,
            'options' => $options,
            'position' => (int) $this->board->customFields()->max('position') + 1,
        ]);

        $this->reset('newCfName', 'newCfOptions');
        $this->newCfType = 'text';
        $this->newCfScope = 'card';
        $this->dispatch('card-field-added');
        $this->touched('card.updated');
    }

    /**
     * Keep only declared options, JSON-encoded; null when nothing remains.
     *
     * @param  array<int, string>  $options
     */
    private function encodeMultiSelect(array $options, mixed $value): ?string
    {
        $picked = array_values(array_intersect(
            array_map(strval(...), is_array($value) ? $value : (array) $value),
            $options,
        ));

        return $picked === [] ? null : (string) json_encode($picked);
    }

    /**
     * A Member field only accepts an actual member of this board.
     */
    private function normalizeMemberValue(mixed $value): ?string
    {
        $id = (int) $value;

        return ($id > 0 && $this->board->members()->whereKey($id)->exists()) ? (string) $id : null;
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

        // Let automations react (e.g. "when a card is completed → move to Done").
        if ($card->completed_at) {
            app(AutomationEngine::class)->fire('card.completed', $card);
        }

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

    /**
     * Move the card to the end of another list (the modal's list selector).
     */
    public function moveToList(int $listId): void
    {
        $card = $this->guardedCard();

        $targetList = $this->board->lists()
            ->whereNull('archived_at')
            ->whereNull('source_plugin_id')
            ->findOrFail($listId);

        if ($targetList->id === $card->board_list_id) {
            return;
        }

        $fromListId = $card->board_list_id;
        $fromList = $card->list?->name;
        $card->update([
            'board_list_id' => $targetList->id,
            'position' => (int) $targetList->cards()->max('position') + 1,
        ]);

        $this->logActivity($card, 'card.moved', ['from_list' => $fromList, 'to_list' => $targetList->name]);

        app(AutomationEngine::class)->fire('card.moved', $card->fresh(), [
            'to_list_id' => $targetList->id,
            'from_list_id' => $fromListId,
        ]);

        $this->touched('card.moved');
    }

    /**
     * Duplicate the card into its own list (labels and members included).
     */
    public function duplicate(): void
    {
        $card = $this->guardedCard();

        $copy = $card->list->cards()->create([
            'board_id' => $this->board->id,
            'created_by' => Auth::id(),
            'title' => $card->title.' '.__('(copie)'),
            'description' => $card->description,
            'cover_path' => $card->cover_path,
            'cover_color' => $card->cover_color,
            'due_at' => $card->due_at,
            'position' => (int) $card->list->cards()->max('position') + 1,
        ]);

        $copy->labels()->attach($card->labels->pluck('id'));
        $copy->members()->attach($card->members->pluck('id'));

        $this->logActivity($copy, 'card.duplicated', ['from' => $card->id]);
        // "Added to board/list" also means copied (Butler semantics).
        app(AutomationEngine::class)->fire('card.created', $copy, ['list_id' => $copy->board_list_id]);
        $this->dispatch('toast', message: __('Carte dupliquée'), type: 'success');
        $this->touched('card.duplicated');
    }

    /**
     * Archive the card and close the modal (restore lives in the board trash).
     */
    public function archive(): void
    {
        $card = $this->guardedCard();

        $card->update(['archived_at' => now()]);
        $this->logActivity($card, 'card.archived', ['list' => $card->list?->name]);
        app(AutomationEngine::class)->fire('card.archived', $card);
        $this->touched('card.archived');
        $this->close();
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

        if (in_array($userId, $result['attached'], true)) {
            // Fires for self-assignment too ("Rejoindre") — rules like
            // "when I'm assigned" must see it.
            app(AutomationEngine::class)->fire('card.member_assigned', $card, ['user_id' => $userId]);
        }

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

        $result = $card->labels()->toggle($label->id);

        app(AutomationEngine::class)->fire(
            in_array($label->id, $result['attached'], true) ? 'card.label_added' : 'card.label_removed',
            $card,
            ['label_id' => $label->id],
        );

        $this->touched('card.labels');
    }

    public function createLabel(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate([
            'newLabelName' => ['nullable', 'string', 'max:255'],
            'newLabelColor' => ['required', 'string', Label::COLOR_RULE],
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
        $this->authorize('contribute', $this->board);

        $this->board->labels()->findOrFail($labelId)->update(['name' => trim($name) ?: null]);
        $this->touched('label.renamed');
    }

    public function recolorLabel(int $labelId, string $color): void
    {
        $this->authorize('contribute', $this->board);

        Validator::make(['color' => $color], ['color' => ['required', 'string', Label::COLOR_RULE]])->validate();

        $this->board->labels()->whereKey($labelId)->update(['color' => $color]);
        $this->touched('label.recolored');
    }

    public function deleteLabel(int $labelId): void
    {
        $this->authorize('contribute', $this->board);

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
        app(AutomationEngine::class)->fire('checklist.added', $card);
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

        if ($item->is_completed) {
            $engine = app(AutomationEngine::class);
            $engine->fire('checklist.item_checked', $card, ['item_id' => $item->id]);

            // Last unchecked item just got ticked → the whole checklist is done.
            if ($item->checklist->items()->where('is_completed', false)->doesntExist()) {
                $engine->fire('checklist.completed', $card, ['checklist_id' => $item->checklist_id]);
            }
        }

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

        if (! $this->board->workspace->attachmentExtensionAllowed($this->upload->getClientOriginalExtension())) {
            $this->addError('upload', __("Ce type de fichier n'est pas autorisé pour ce workspace."));

            return;
        }

        // Read metadata BEFORE store(): the attachments disk is now the same as
        // Livewire's temporary-upload disk, so store() moves (not copies) the temp
        // file — reading getSize()/getMimeType() afterwards would fail.
        $name = $this->upload->getClientOriginalName();
        $mimeType = $this->upload->getMimeType();
        $size = $this->upload->getSize();

        $path = $this->upload->store("attachments/{$this->board->id}", 'local');

        $card->attachments()->create([
            'uploaded_by' => Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => $size,
        ]);

        $this->logActivity($card, 'attachment.added', ['value' => $name]);
        $this->reset('upload');
        $this->touched('attachment.added');
        $this->dispatch('toast', message: __('Pièce jointe ajoutée'), type: 'success');
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

        $path = $this->coverUpload->store("covers/{$this->board->id}", 'local');

        $card->update(['cover_path' => $path, 'cover_color' => null]);
        $this->reset('coverUpload');
        $this->touched('card.cover');
        $this->dispatch('toast', message: __('Couverture mise à jour'), type: 'success');
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

        $this->dispatch('toast', message: __('Carte enregistrée comme modèle global'), type: 'success');
    }

    public function addComment(string $body = ''): void
    {
        $card = $this->guardedCard(Permission::CommentPost);

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
        app(AutomationEngine::class)->fire('comment.added', $card, ['body' => $data['newComment']]);
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
        $card = $this->guardedCard(Permission::CommentPost);
        $comment = $card->comments()->findOrFail($commentId);

        abort_unless(
            $comment->user_id === Auth::id() || $this->board->userCan(Auth::user(), Permission::MemberManage),
            403,
        );

        $comment->delete();
        $this->touched('comment.deleted');
    }

    public function startEditComment(int $commentId): void
    {
        $card = $this->guardedCard(Permission::CommentPost);
        $comment = $card->comments()->findOrFail($commentId);

        abort_unless(
            $comment->user_id === Auth::id() || $this->board->userCan(Auth::user(), Permission::MemberManage),
            403,
        );

        $this->editingCommentId = $comment->id;
        $this->editingCommentBody = $comment->body;
    }

    public function saveComment(): void
    {
        $card = $this->guardedCard(Permission::CommentPost);
        $comment = $card->comments()->findOrFail($this->editingCommentId);

        abort_unless(
            $comment->user_id === Auth::id() || $this->board->userCan(Auth::user(), Permission::MemberManage),
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
        $card = $this->guardedCard(Permission::CommentPost);
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
    /**
     * Mirror this card into another list/board — the same underlying card shown in
     * several places, not a copy. Requires contribute on the target board.
     */
    public function mirrorCard(): void
    {
        // Write authorization on the SOURCE board — a read-only viewer must not
        // be able to surface this card into another board it can contribute to.
        $card = $this->guardedCard();

        $targetList = BoardList::with('board')
            ->whereNull('archived_at')
            ->whereNull('source_plugin_id')
            ->find((int) $this->mirrorListId);

        if (! $targetList) {
            $this->addError('mirrorListId', __('Choisissez une liste.'));

            return;
        }

        abort_unless(Auth::user()->can('contribute', $targetList->board), 403);

        if ($targetList->id === $card->board_list_id) {
            $this->addError('mirrorListId', __('La carte est déjà dans cette liste.'));

            return;
        }

        $card->mirrors()->firstOrCreate(
            ['board_list_id' => $targetList->id],
            [
                'board_id' => $targetList->board_id,
                'created_by' => Auth::id(),
                'position' => (int) $targetList->mirrors()->max('position') + 1,
            ],
        );

        $this->reset('mirrorListId', 'showMirrorPicker');
        $this->dispatch('toast', message: __('Carte reflétée'), type: 'success');
    }

    public function removeMirror(int $mirrorId): void
    {
        // Write authorization on the SOURCE board (the authoritative card), not
        // only the target board the mirror was placed on.
        abort_unless($this->board->userCan(Auth::user(), Permission::CardManage), 403);

        $mirror = CardMirror::where('card_id', $this->cardId)->with('board')->findOrFail($mirrorId);

        abort_unless(Auth::user()->can('contribute', $mirror->board), 403);

        $mirror->delete();
        $this->dispatch('toast', message: __('Miroir retiré'), type: 'info');
    }

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
        $this->dispatch('toast', message: __('Action « :name » exécutée', ['name' => $automation->name]), type: 'success');
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

    /**
     * Fetch the open card, guarding by permission — every write funnels through
     * here. Card mutations require CardManage; comment/reaction writes pass
     * CommentPost. Read-only roles (Observer) hold neither, so they get a 403.
     */
    private function guardedCard(Permission $permission = Permission::CardManage): Card
    {
        abort_unless($this->board->userCan(Auth::user(), $permission), 403);

        return $this->board->cards()->findOrFail($this->cardId);
    }

    private function touched(string $action): void
    {
        $boardId = $this->board->id;
        $actorId = Auth::id();

        // Defer the Reverb broadcast so the modal action returns without waiting on
        // the realtime round-trip (see InteractsWithBoardCards::broadcastActivity).
        defer(fn () => broadcast(new BoardActivity($boardId, $action, $actorId))->toOthers());
        $this->dispatch('board-refresh');
    }

    public function render(): View
    {
        $card = $this->cardId
            ? $this->board->cards()
                ->with(['members', 'watchers', 'labels', 'checklists.items.assignee', 'attachments.uploader', 'comments.user', 'comments.reactions', 'customFieldValues'])
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

        $cardMirrors = $card ? $card->mirrors()->with(['list', 'board'])->get() : collect();

        // Scanning the workspace for mirror targets costs a query + a policy check
        // PER BOARD on every render — only pay for it once the picker is opened
        // ("⋯ → Miroir"), or when mirrors already exist (their section shows the
        // picker right away).
        $mirrorTargets = ($card && ($this->showMirrorPicker || $cardMirrors->isNotEmpty()))
            ? $this->board->workspace->boards()
                ->whereNull('archived_at')
                ->with(['lists' => fn ($q) => $q->whereNull('archived_at')->whereNull('source_plugin_id')->orderBy('position')])
                ->orderBy('name')->get()
                ->filter(fn ($b) => Auth::user()->can('contribute', $b))
                ->values()
            : collect();

        return view('livewire.cards.card-detail', [
            'card' => $card,
            // The modal chrome data is only needed while a card is open — closed,
            // this render must stay free (it runs on every open-card listener).
            'boardMembers' => $card ? $this->board->members : collect(),
            'boardLabels' => $card ? $this->board->labels : collect(),
            'boardLists' => $card ? $this->board->lists()->whereNull('archived_at')->whereNull('source_plugin_id')->orderBy('position')->get() : collect(),
            'boardPlugins' => $card ? $this->board->plugins()->where('is_active', true)->get() : collect(),
            'cardButtons' => $card ? $this->board->automations()->where('trigger_type', 'manual')->where('is_active', true)->get() : collect(),
            'reactionEmojis' => self::REACTIONS,
            'cardLinks' => $cardLinks,
            'linkCandidates' => $linkCandidates,
            'customFields' => $card
                ? $this->board->customFields()->visibleOn($this->board)->forCard($card)->orderBy('position')->get()
                : collect(),
            'canContribute' => Auth::user()->can('contribute', $this->board),
            'canComment' => Auth::user()->can('comment', $this->board),
            // Cheap gate for the "Miroir" menu item (the real targets load later).
            'canMirror' => $card !== null && ($cardMirrors->isNotEmpty()
                || $this->board->workspace->boards()->whereKeyNot($this->board->id)->whereNull('archived_at')->exists()),
            'mirrorTargets' => $mirrorTargets,
            'cardMirrors' => $cardMirrors,
            // Lazy: only fetch the activity feed once the user opens it.
            'activities' => ($card && $this->showActivity)
                ? $card->activities()->with('user')->latest()->limit(30)->get()
                : collect(),
        ]);
    }
}
