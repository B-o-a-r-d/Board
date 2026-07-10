<?php

namespace App\Services;

use App\Models\Board;
use Illuminate\Support\Collection;

class BoardExportService
{
    /**
     * Eager-load everything an export needs.
     */
    private function load(Board $board): Board
    {
        return $board->loadMissing([
            'lists' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
            'lists.cards.labels',
            'lists.cards.members',
            'lists.cards.checklists.items',
            'lists.cards.comments.user',
            'lists.cards.attachments',
        ]);
    }

    /**
     * Flatten a board's cards into export rows — one card per row, with nested
     * data (checklists + items, comments, attachments) serialised into cells so
     * a spreadsheet still carries ALL the information.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Board $board): Collection
    {
        $this->load($board);

        $rows = collect();

        foreach ($board->lists as $list) {
            foreach ($list->cards as $card) {
                $checklists = $card->checklists->map(function ($cl) {
                    $items = $cl->items->map(fn ($i) => ($i->is_completed ? '[x] ' : '[ ] ').$i->content)->implode(' ; ');

                    return $cl->title.' : '.$items;
                })->implode(' || ');

                $comments = $card->comments->map(
                    fn ($c) => ($c->user?->name ?? '?').' ('.optional($c->created_at)->format('Y-m-d H:i').') : '.$c->body
                )->implode(' || ');

                $attachments = $card->attachments->map(fn ($a) => $a->name.' ('.$a->url.')')->implode(' ; ');

                $rows->push([
                    'Liste' => $list->name,
                    'Titre' => (string) $card->title,
                    'Description' => (string) $card->description,
                    'Labels' => $card->labels->map(fn ($l) => $l->name ?? $l->color)->implode(', '),
                    'Membres' => $card->members->pluck('name')->implode(', '),
                    'Échéance' => optional($card->due_at)->format('Y-m-d H:i'),
                    'Terminée' => $card->completed_at ? 'Oui' : 'Non',
                    'Couverture' => $card->cover_color ?: ($card->coverUrl() ?? ''),
                    'Checklists' => $checklists,
                    'Commentaires' => $comments,
                    'Pièces jointes' => $attachments,
                    'Position' => $card->position,
                    'Créée le' => optional($card->created_at)->format('Y-m-d H:i'),
                    'Modifiée le' => optional($card->updated_at)->format('Y-m-d H:i'),
                ]);
            }
        }

        return $rows;
    }

    /**
     * Full structured representation of a board for JSON export — every card
     * with its labels, members, checklists (+ items), comments and attachments.
     *
     * @return array<string, mixed>
     */
    public function structured(Board $board): array
    {
        $this->load($board);

        return [
            'board' => [
                'id' => $board->public_id,
                'name' => $board->name,
                'description' => $board->description,
                'background' => $board->background,
                'visibility' => $board->visibility->value,
                'created_at' => optional($board->created_at)->toIso8601String(),
            ],
            'lists' => $board->lists->map(fn ($list) => [
                'id' => $list->public_id,
                'name' => $list->name,
                'cover_color' => $list->cover_color,
                'position' => $list->position,
                'cards' => $list->cards->map(fn ($card) => [
                    'id' => $card->public_id,
                    'title' => $card->title,
                    'description' => $card->description,
                    'position' => $card->position,
                    'cover_color' => $card->cover_color,
                    'cover_url' => $card->coverUrl(),
                    'due_at' => optional($card->due_at)->toIso8601String(),
                    'completed_at' => optional($card->completed_at)->toIso8601String(),
                    'created_at' => optional($card->created_at)->toIso8601String(),
                    'labels' => $card->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values(),
                    'members' => $card->members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email])->values(),
                    'checklists' => $card->checklists->map(fn ($cl) => [
                        'title' => $cl->title,
                        'items' => $cl->items->map(fn ($i) => ['content' => $i->content, 'completed' => (bool) $i->is_completed])->values(),
                    ])->values(),
                    'comments' => $card->comments->map(fn ($c) => [
                        'author' => $c->user?->name,
                        'body' => $c->body,
                        'created_at' => optional($c->created_at)->toIso8601String(),
                    ])->values(),
                    'attachments' => $card->attachments->map(fn ($a) => [
                        'name' => $a->name,
                        'url' => $a->url,
                        'mime_type' => $a->mime_type,
                        'size' => $a->size,
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }
}
