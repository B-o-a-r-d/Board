<?php

namespace App\Livewire\Cards\Concerns;

use App\Automations\AutomationEngine;
use App\Enums\Permission;
use App\Models\Card;
use App\Models\User;
use App\Notifications\CardNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Comments of the open card: post (with @mentions), edit, delete, react, render.
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesComments
{
    public string $newComment = '';

    public ?int $editingCommentId = null;

    public string $editingCommentBody = '';

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
     * Toggle the shared "embed hidden" state for a URL in a comment.
     */
    public function toggleCommentPreview(int $commentId, string $url): void
    {
        $card = $this->guardedCard();
        $comment = $card->comments()->findOrFail($commentId);

        $comment->update(['hidden_previews' => $this->toggleInList($comment->hidden_previews ?? [], $url)]);
        $this->touched('comment.preview');
    }
}
