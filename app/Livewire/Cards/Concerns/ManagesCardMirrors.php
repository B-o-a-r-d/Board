<?php

namespace App\Livewire\Cards\Concerns;

use App\Enums\Permission;
use App\Models\BoardList;
use App\Models\CardMirror;
use Illuminate\Support\Facades\Auth;

/**
 * Mirrors of the open card into other lists/boards.
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesCardMirrors
{
    /**
     * Mirror targets scan every workspace board (+ a policy check per board) —
     * they are only loaded once this flag is set by the "⋯ → Miroir" menu item.
     */
    public bool $showMirrorPicker = false;

    /** Mirror picker: the target list this card should be mirrored into. */
    public string $mirrorListId = '';

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
}
