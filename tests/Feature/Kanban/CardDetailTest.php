<?php

use App\Livewire\Cards\CardDetail;
use App\Models\Label;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('opening a card loads its data into the modal', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'Ma carte']);

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertSet('showModal', true)
        ->assertSet('title', 'Ma carte');
});

test('outsiders cannot open a card', function () {
    ['board' => $board, 'outsider' => $outsider, 'card' => $card] = makeCardContext();

    Livewire::actingAs($outsider)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertForbidden();
});

test('details (markdown description, due date) are saved', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('title', 'Titre mis à jour')
        ->set('description', "# Titre\n\n- point **gras**")
        ->set('dueAt', '2026-08-01T10:00')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $card->refresh();

    expect($card->title)->toBe('Titre mis à jour')
        ->and($card->description)->toContain('**gras**')
        ->and($card->due_at->format('Y-m-d H:i'))->toBe('2026-08-01 10:00');
});

test('a card can be marked complete and incomplete', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('toggleComplete');
    expect($card->fresh()->completed_at)->not->toBeNull();

    $component->call('toggleComplete');
    expect($card->fresh()->completed_at)->toBeNull();
});

test('members and labels can be toggled on a card', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $label = Label::factory()->create(['board_id' => $board->id]);

    $component = Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('toggleMember', $member->id);
    expect($card->members()->whereKey($member->id)->exists())->toBeTrue();

    $component->call('toggleLabel', $label->id);
    expect($card->labels()->whereKey($label->id)->exists())->toBeTrue();
});

test('a label can be created and is attached to the card', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newLabelName', 'Urgent')
        ->set('newLabelColor', '#ff0000')
        ->call('createLabel')
        ->assertHasNoErrors();

    $label = $board->labels()->where('name', 'Urgent')->first();

    expect($label)->not->toBeNull()
        ->and($card->labels()->whereKey($label->id)->exists())->toBeTrue();
});

test('the description is saved from the wysiwyg editor as markdown', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveDescription', "## Titre\n\n**Gras** et une liste :\n\n- un\n- deux")
        ->assertSet('description', "## Titre\n\n**Gras** et une liste :\n\n- un\n- deux");

    expect($card->fresh()->description)->toContain('## Titre')->toContain('**Gras**');
});

test('a due date can be saved and cleared from the sidebar', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->set('dueAt', '2026-08-01T09:30')->call('saveDueDate')->assertHasNoErrors();
    expect($card->fresh()->due_at->format('Y-m-d H:i'))->toBe('2026-08-01 09:30');

    $component->call('clearDueDate')->assertSet('dueAt', null);
    expect($card->fresh()->due_at)->toBeNull();
});

test('a solid color cover can be set and cleared on a card', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('setCoverColor', '#22c55e');
    expect($card->fresh()->cover_color)->toBe('#22c55e');

    // Selecting the same color again toggles it off.
    $component->call('setCoverColor', '#22c55e');
    expect($card->fresh()->cover_color)->toBeNull();

    $component->call('setCoverColor', '#3b82f6');
    $component->call('clearCover');
    expect($card->fresh()->cover_color)->toBeNull()
        ->and($card->fresh()->cover_path)->toBeNull();
});

test('a label can be renamed, recolored and deleted from the board', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $label = $board->labels()->create(['name' => 'Ancien', 'color' => '#000000']);
    $card->labels()->attach($label);

    $component = Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('renameLabel', $label->id, 'Nouveau');
    expect($label->fresh()->name)->toBe('Nouveau');

    $component->call('recolorLabel', $label->id, '#22c55e');
    expect($label->fresh()->color)->toBe('#22c55e');

    $component->call('deleteLabel', $label->id);
    expect($board->labels()->whereKey($label->id)->exists())->toBeFalse()
        ->and($card->labels()->whereKey($label->id)->exists())->toBeFalse();
});

test('checklists and items with completion can be managed', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newChecklistTitle', 'Étapes')
        ->call('addChecklist');

    $checklist = $card->checklists()->firstOrFail();
    expect($checklist->title)->toBe('Étapes');

    $component->set("newChecklistItem.{$checklist->id}", 'Première étape')
        ->call('addChecklistItem', $checklist->id);

    $item = $checklist->items()->firstOrFail();
    expect($item->content)->toBe('Première étape')
        ->and($item->is_completed)->toBeFalse();

    $component->call('toggleChecklistItem', $item->id);
    expect($item->fresh()->is_completed)->toBeTrue();

    $component->call('deleteChecklistItem', $item->id);
    expect($checklist->items()->count())->toBe(0);
});

test('an image attachment can be uploaded and set as cover', function () {
    Storage::fake('public');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('upload', UploadedFile::fake()->image('photo.png'))
        ->call('saveAttachment')
        ->assertHasNoErrors();

    $attachment = $card->attachments()->firstOrFail();

    expect($attachment->isImage())->toBeTrue()
        ->and($attachment->uploaded_by)->toBe($owner->id);
    Storage::disk('public')->assertExists($attachment->path);

    $component->call('setCover', $attachment->id);
    expect($card->fresh()->cover_path)->toBe($attachment->path);

    $component->call('deleteAttachment', $attachment->id);
    Storage::disk('public')->assertMissing($attachment->path);
    expect($card->fresh()->cover_path)->toBeNull()
        ->and($card->attachments()->count())->toBe(0);
});

test('uploading a non media file is rejected', function () {
    Storage::fake('public');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('upload', UploadedFile::fake()->create('malware.exe', 100))
        ->call('saveAttachment')
        ->assertHasErrors('upload');

    expect($card->attachments()->count())->toBe(0);
});
