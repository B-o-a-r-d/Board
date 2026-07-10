<?php

use App\Enums\Role;
use App\Livewire\Settings\Profile;
use App\Mcp\Servers\BoardServer;
use App\Mcp\Tools\AddCommentTool;
use App\Mcp\Tools\AssignLabelTool;
use App\Mcp\Tools\AssignMemberTool;
use App\Mcp\Tools\AttachFileFromUrlTool;
use App\Mcp\Tools\AttachFileTool;
use App\Mcp\Tools\CreateBoardTool;
use App\Mcp\Tools\CreateCardTool;
use App\Mcp\Tools\CreateChecklistTool;
use App\Mcp\Tools\CreateLabelTool;
use App\Mcp\Tools\DeleteBoardTool;
use App\Mcp\Tools\DeleteLabelTool;
use App\Mcp\Tools\ListBoardsTool;
use App\Mcp\Tools\MoveCardTool;
use App\Mcp\Tools\ToggleChecklistItemTool;
use App\Mcp\Tools\UpdateBoardTool;
use App\Mcp\Tools\UpdateLabelTool;
use App\Models\Activity;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User, list: BoardList}
 */
function makeMcpBoard(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);
    $list = BoardList::factory()->create(['board_id' => $board->id]);

    return ['board' => $board, 'owner' => $owner, 'list' => $list];
}

test('the mcp master switch defaults off and can be toggled', function () {
    expect(Setting::mcpEnabled())->toBeFalse();

    Setting::set('mcp_enabled', true);
    expect(Setting::mcpEnabled())->toBeTrue();
});

test('only an admin can toggle the mcp master switch from the profile', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Livewire::actingAs($admin)->test(Profile::class)
        ->call('toggleMcp')
        ->assertSet('mcpEnabled', true);
    expect(Setting::mcpEnabled())->toBeTrue();

    $member = User::factory()->create();
    Livewire::actingAs($member)->test(Profile::class)
        ->call('toggleMcp')
        ->assertForbidden();
});

test('list boards returns the accessible boards', function () {
    Setting::set('mcp_enabled', true);
    ['board' => $board, 'owner' => $owner] = makeMcpBoard();

    BoardServer::actingAs($owner)->tool(ListBoardsTool::class, [])
        ->assertOk()
        ->assertSee($board->name);
});

test('create card via mcp creates a card and logs an mcp-sourced activity', function () {
    Setting::set('mcp_enabled', true);
    ['owner' => $owner, 'list' => $list] = makeMcpBoard();

    BoardServer::actingAs($owner)->tool(CreateCardTool::class, [
        'list_id' => $list->public_id,
        'title' => 'Créée par une IA',
    ])->assertOk();

    $card = $list->cards()->firstOrFail();
    expect($card->title)->toBe('Créée par une IA');

    $activity = Activity::where('card_id', $card->id)->where('type', 'card.created')->firstOrFail();
    expect($activity->source)->toBe('mcp:client')
        ->and($activity->user_id)->toBe($owner->id);
});

test('mcp tools deny users without board access', function () {
    Setting::set('mcp_enabled', true);
    ['list' => $list] = makeMcpBoard();
    $outsider = User::factory()->create();

    BoardServer::actingAs($outsider)->tool(CreateCardTool::class, [
        'list_id' => $list->public_id,
        'title' => 'Intrus',
    ])->assertHasErrors();

    expect($list->cards()->count())->toBe(0);
});

test('checklist items can be added and toggled via mcp', function () {
    Setting::set('mcp_enabled', true);
    ['board' => $board, 'owner' => $owner, 'list' => $list] = makeMcpBoard();
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    BoardServer::actingAs($owner)->tool(CreateChecklistTool::class, [
        'card_id' => $card->public_id, 'title' => 'Critères',
    ])->assertOk();

    $checklist = $card->checklists()->firstOrFail();
    $item = $checklist->items()->create(['content' => 'Tests écrits', 'position' => 0]);

    BoardServer::actingAs($owner)->tool(ToggleChecklistItemTool::class, [
        'item_id' => $item->public_id, 'completed' => true,
    ])->assertOk();

    expect($item->fresh()->is_completed)->toBeTrue();
});

test('labels and members can be created and assigned via mcp', function () {
    Setting::set('mcp_enabled', true);
    ['board' => $board, 'owner' => $owner, 'list' => $list] = makeMcpBoard();
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    BoardServer::actingAs($owner)->tool(CreateLabelTool::class, [
        'board_id' => $board->public_id, 'color' => '#ef4444', 'name' => 'Bug',
    ])->assertOk();
    $label = $board->labels()->firstOrFail();

    BoardServer::actingAs($owner)->tool(AssignLabelTool::class, [
        'card_id' => $card->public_id, 'label_id' => $label->public_id,
    ])->assertOk();
    expect($card->labels()->whereKey($label->id)->exists())->toBeTrue();

    BoardServer::actingAs($owner)->tool(AssignMemberTool::class, [
        'card_id' => $card->public_id, 'user_id' => $owner->public_id,
    ])->assertOk();
    expect($card->members()->whereKey($owner->id)->exists())->toBeTrue();
});

test('labels can be edited and deleted via mcp', function () {
    Setting::set('mcp_enabled', true);
    ['board' => $board, 'owner' => $owner] = makeMcpBoard();
    $label = $board->labels()->create(['name' => 'Ancien', 'color' => '#000000']);

    BoardServer::actingAs($owner)->tool(UpdateLabelTool::class, [
        'label_id' => $label->public_id, 'name' => 'Nouveau', 'color' => '#22c55e',
    ])->assertOk();
    expect($label->fresh()->name)->toBe('Nouveau')->and($label->fresh()->color)->toBe('#22c55e');

    BoardServer::actingAs($owner)->tool(DeleteLabelTool::class, ['label_id' => $label->public_id])->assertOk();
    expect($board->labels()->count())->toBe(0);
});

test('a board can be created updated and deleted via mcp', function () {
    Setting::set('mcp_enabled', true);
    ['board' => $board, 'owner' => $owner] = makeMcpBoard();
    $workspace = $board->workspace;

    BoardServer::actingAs($owner)->tool(CreateBoardTool::class, [
        'workspace_id' => $workspace->public_id, 'name' => 'Board IA',
    ])->assertOk();
    $created = Board::where('name', 'Board IA')->firstOrFail();

    BoardServer::actingAs($owner)->tool(UpdateBoardTool::class, [
        'board_id' => $created->public_id, 'name' => 'Board IA renommé', 'background' => 'ocean',
    ])->assertOk();
    expect($created->fresh()->name)->toBe('Board IA renommé')->and($created->fresh()->background)->toBe('ocean');

    BoardServer::actingAs($owner)->tool(DeleteBoardTool::class, ['board_id' => $created->public_id])->assertOk();
    expect(Board::whereKey($created->id)->exists())->toBeFalse();
});

test('attach file from base64 content stores a local file', function () {
    Setting::set('mcp_enabled', true);
    Storage::fake('local');
    ['board' => $board, 'owner' => $owner, 'list' => $list] = makeMcpBoard();
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    // 1×1 transparent PNG — finfo detects image/png.
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    BoardServer::actingAs($owner)->tool(AttachFileTool::class, [
        'card_id' => $card->public_id, 'name' => 'capture.png', 'content' => $png,
    ])->assertOk();

    $attachment = $card->attachments()->firstOrFail();
    expect($attachment->name)->toBe('capture.png')
        ->and($attachment->mime_type)->toBe('image/png');
    Storage::disk('local')->assertExists($attachment->path);
});

test('attach file from url stores an attachment', function () {
    Setting::set('mcp_enabled', true);
    Storage::fake('local');
    Http::fake([
        '*' => Http::response('binarydata', 200, ['Content-Type' => 'image/png']),
    ]);
    ['board' => $board, 'owner' => $owner, 'list' => $list] = makeMcpBoard();
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    BoardServer::actingAs($owner)->tool(AttachFileFromUrlTool::class, [
        'card_id' => $card->public_id, 'url' => 'http://93.184.216.34/image.png', 'name' => 'schema.png',
    ])->assertOk();

    expect($card->attachments()->count())->toBe(1)
        ->and($card->attachments()->first()->name)->toBe('schema.png');
});

test('mcp attach respects the workspace attachment allow-list', function () {
    Setting::set('mcp_enabled', true);
    Storage::fake('local');
    ['board' => $board, 'owner' => $owner, 'list' => $list] = makeMcpBoard();
    $board->workspace->update(['allowed_attachment_extensions' => ['png']]);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    // 1×1 transparent PNG — the binary is a valid image whatever the name says.
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    BoardServer::actingAs($owner)->tool(AttachFileTool::class, [
        'card_id' => $card->public_id, 'name' => 'ok.png', 'content' => $png,
    ])->assertOk();

    BoardServer::actingAs($owner)->tool(AttachFileTool::class, [
        'card_id' => $card->public_id, 'name' => 'blocked.svg', 'content' => $png,
    ])->assertHasErrors();

    expect($card->attachments()->count())->toBe(1);
});

test('move card and add comment via mcp work and log activities', function () {
    Setting::set('mcp_enabled', true);
    ['board' => $board, 'owner' => $owner, 'list' => $list] = makeMcpBoard();
    $target = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Fait']);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    BoardServer::actingAs($owner)->tool(MoveCardTool::class, ['card_id' => $card->public_id, 'list_id' => $target->public_id])->assertOk();
    expect($card->fresh()->board_list_id)->toBe($target->id);

    BoardServer::actingAs($owner)->tool(AddCommentTool::class, ['card_id' => $card->public_id, 'body' => 'Note IA'])->assertOk();
    expect($card->comments()->count())->toBe(1)
        ->and(Activity::where('card_id', $card->id)->where('type', 'comment.created')->where('source', 'mcp:client')->exists())->toBeTrue();
});
