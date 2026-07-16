<?php

namespace App\Livewire\Cards;

use App\Automations\AutomationEngine;
use App\Enums\Permission;
use App\Events\BoardActivity;
use App\Livewire\Cards\Concerns\ManagesAttachments;
use App\Livewire\Cards\Concerns\ManagesCardCustomFields;
use App\Livewire\Cards\Concerns\ManagesCardDates;
use App\Livewire\Cards\Concerns\ManagesCardLabels;
use App\Livewire\Cards\Concerns\ManagesCardLinks;
use App\Livewire\Cards\Concerns\ManagesCardMembers;
use App\Livewire\Cards\Concerns\ManagesCardMirrors;
use App\Livewire\Cards\Concerns\ManagesChecklists;
use App\Livewire\Cards\Concerns\ManagesComments;
use App\Models\Activity;
use App\Models\Board;
use App\Models\Card;
use App\Models\CardLink;
use App\Models\CardTemplate;
use App\Models\LinkPreview;
use App\Models\User;
use App\Services\UrlPreviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

use function Illuminate\Support\defer;

class CardDetail extends Component
{
    use ManagesAttachments,
        ManagesCardCustomFields,
        ManagesCardDates,
        ManagesCardLabels,
        ManagesCardLinks,
        ManagesCardMembers,
        ManagesCardMirrors,
        ManagesChecklists,
        ManagesComments,
        WithFileUploads;

    /** @var array<int, string> Curated emoji set available as comment reactions. */
    public const REACTIONS = ['👍', '❤️', '😄', '🎉', '😮', '🚀'];

    public Board $board;

    public ?int $cardId = null;

    public bool $showModal = false;

    public string $title = '';

    public string $description = '';

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
