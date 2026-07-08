<?php

use App\Enums\Role;
use App\Livewire\Cards\CardDetail;
use App\Models\Board;
use App\Models\Comment;
use App\Models\User;
use Livewire\Livewire;

test('a member can post a comment on a card', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', 'Bien joué !')
        ->call('addComment')
        ->assertHasNoErrors()
        ->assertSet('newComment', '');

    $comment = $card->comments()->firstOrFail();

    expect($comment->body)->toBe('Bien joué !')
        ->and($comment->user_id)->toBe($owner->id);
});

test('an empty comment is rejected', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', '   ')
        ->call('addComment')
        ->assertHasErrors('newComment');
});

test('the author can delete their own comment', function () {
    ['board' => $board, 'member' => $member, 'card' => $card] = makeCardContext();
    $comment = Comment::factory()->create(['card_id' => $card->id, 'user_id' => $member->id]);

    Livewire::actingAs($member)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('deleteComment', $comment->id);

    expect(Comment::whereKey($comment->id)->exists())->toBeFalse();
});

test('a board admin can delete any comment', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $comment = Comment::factory()->create(['card_id' => $card->id, 'user_id' => $member->id]);

    // $owner is the board owner (administrator) and not the comment author.
    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('deleteComment', $comment->id);

    expect(Comment::whereKey($comment->id)->exists())->toBeFalse();
});

test('a non-admin non-author cannot delete a comment', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();

    // A plain board member (not admin) who is not the author.
    $author = User::factory()->create();
    $board->members()->attach($author, ['role' => Role::Member->value]);
    $plainMember = User::factory()->create();
    $board->members()->attach($plainMember, ['role' => Role::Member->value]);

    $comment = Comment::factory()->create(['card_id' => $card->id, 'user_id' => $author->id]);

    Livewire::actingAs($plainMember)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('deleteComment', $comment->id)
        ->assertForbidden();

    expect(Comment::whereKey($comment->id)->exists())->toBeTrue();
});

test('the composer posts a comment via the body argument', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('addComment', '**Bravo** @alice')
        ->assertHasNoErrors();

    expect($card->comments()->firstOrFail()->body)->toBe('**Bravo** @alice');
});

test('comment rendering converts markdown to html', function () {
    ['board' => $board] = makeCardContext();
    $board->load('members');

    $component = new CardDetail;
    $component->board = $board;

    $html = $component->renderCommentBody("**bold** and *italic*\n\n- one\n- two");

    expect($html)->toContain('<strong>bold</strong>')
        ->and($html)->toContain('<em>italic</em>')
        ->and($html)->toContain('<li>one</li>');
});

test('comment rendering highlights member mentions and escapes html', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $owner->update(['name' => 'Alice']);
    $board->load('members');

    $component = new CardDetail;
    $component->board = $board;

    $html = $component->renderCommentBody('Salut @Alice <script>alert(1)</script> @Inconnu');

    expect($html)->toContain('>@Alice</span>')          // mention highlighted
        ->and($html)->toContain('&lt;script&gt;')        // html escaped
        ->and($html)->not->toContain('<script>')         // no raw html
        ->and($html)->toContain('@Inconnu');             // unknown mention left as-is
});
