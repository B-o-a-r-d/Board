<?php

namespace App\Services;

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BoardTemplateService
{
    /**
     * Create a fresh board in $workspace from a template board, copying its
     * labels, lists, cards, covers and checklists (no members, comments or
     * attachments — a template is a blueprint).
     */
    public function instantiate(Board $template, Workspace $workspace, User $user, ?string $name = null): Board
    {
        $template->loadMissing([
            'labels',
            'lists' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards.labels',
            'lists.cards.checklists.items',
        ]);

        return DB::transaction(function () use ($template, $workspace, $user, $name): Board {
            $boardName = $name !== null && trim($name) !== '' ? trim($name) : $template->name;

            $board = Board::create([
                'workspace_id' => $workspace->id,
                'created_by' => $user->id,
                'name' => $boardName,
                'slug' => Str::slug($boardName).'-'.Str::lower(Str::random(6)),
                'background' => $template->background,
                'background_image' => $template->background_image,
                'visibility' => BoardVisibility::Private,
                'is_template' => false,
                'position' => (int) $workspace->boards()->max('position') + 1,
            ]);

            $board->members()->attach($user->id, ['role' => Role::Owner->value]);

            $labelMap = [];

            foreach ($template->labels as $label) {
                $labelMap[$label->id] = $board->labels()->create([
                    'name' => $label->name,
                    'color' => $label->color,
                ])->id;
            }

            foreach ($template->lists as $listIndex => $list) {
                $newList = $board->lists()->create([
                    'name' => $list->name,
                    'cover_color' => $list->cover_color,
                    'position' => $listIndex,
                ]);

                foreach ($list->cards as $cardIndex => $card) {
                    $newCard = $newList->cards()->create([
                        'board_id' => $board->id,
                        'created_by' => $user->id,
                        'title' => $card->title,
                        'description' => $card->description,
                        'cover_path' => $card->cover_path,
                        'cover_color' => $card->cover_color,
                        'position' => $cardIndex,
                    ]);

                    $newCard->labels()->attach(
                        $card->labels->map(fn ($label) => $labelMap[$label->id] ?? null)->filter()->all(),
                    );

                    foreach ($card->checklists as $checklistIndex => $checklist) {
                        $newChecklist = $newCard->checklists()->create([
                            'title' => $checklist->title,
                            'position' => $checklistIndex,
                        ]);

                        foreach ($checklist->items as $itemIndex => $item) {
                            $newChecklist->items()->create([
                                'content' => $item->content,
                                'position' => $itemIndex,
                                'is_completed' => false,
                            ]);
                        }
                    }
                }
            }

            return $board;
        });
    }
}
