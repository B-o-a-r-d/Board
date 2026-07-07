<?php

use App\Enums\CustomFieldType;
use App\Enums\Role;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User, card: Card}
 */
function makeCustomFieldContext(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id]);

    return compact('board', 'owner', 'card');
}

test('an admin can add a text custom field to a board', function () {
    ['board' => $board, 'owner' => $owner] = makeCustomFieldContext();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('newFieldName', 'Estimation')
        ->set('newFieldType', 'number')
        ->call('addCustomField')
        ->assertHasNoErrors();

    $field = $board->customFields()->firstOrFail();

    expect($field->name)->toBe('Estimation')
        ->and($field->type)->toBe(CustomFieldType::Number)
        ->and($field->public_id)->not->toBeEmpty();
});

test('a select field requires at least one option', function () {
    ['board' => $board, 'owner' => $owner] = makeCustomFieldContext();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('newFieldName', 'Priorité')
        ->set('newFieldType', 'select')
        ->set('newFieldOptions', '   ,  ')
        ->call('addCustomField')
        ->assertHasErrors('newFieldOptions');

    expect($board->customFields()->count())->toBe(0);
});

test('a select field stores trimmed options', function () {
    ['board' => $board, 'owner' => $owner] = makeCustomFieldContext();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('newFieldName', 'Priorité')
        ->set('newFieldType', 'select')
        ->set('newFieldOptions', 'Basse, Moyenne , Haute')
        ->call('addCustomField')
        ->assertHasNoErrors();

    expect($board->customFields()->firstOrFail()->options)->toBe(['Basse', 'Moyenne', 'Haute']);
});

test('deleting a field removes its values', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCustomFieldContext();
    $field = $board->customFields()->create(['name' => 'Note', 'type' => CustomFieldType::Text, 'position' => 1]);
    $card->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'hello']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('deleteCustomField', $field->id);

    expect(CustomField::whereKey($field->id)->exists())->toBeFalse()
        ->and(CustomFieldValue::where('custom_field_id', $field->id)->exists())->toBeFalse();
});

test('a card value can be set, updated and cleared', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCustomFieldContext();
    $field = $board->customFields()->create(['name' => 'Note', 'type' => CustomFieldType::Text, 'position' => 1]);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('saveCustomField', $field->id, 'first');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->value('value'))->toBe('first');

    $component->call('saveCustomField', $field->id, 'second');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->count())->toBe(1)
        ->and($card->customFieldValues()->where('custom_field_id', $field->id)->value('value'))->toBe('second');

    $component->call('saveCustomField', $field->id, '');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse();
});

test('a select value only accepts a defined option', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCustomFieldContext();
    $field = $board->customFields()->create(['name' => 'Priorité', 'type' => CustomFieldType::Select, 'options' => ['Basse', 'Haute'], 'position' => 1]);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('saveCustomField', $field->id, 'Inconnue');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse();

    $component->call('saveCustomField', $field->id, 'Haute');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->value('value'))->toBe('Haute');
});

test('a checkbox value stores 1 when checked and clears when unchecked', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCustomFieldContext();
    $field = $board->customFields()->create(['name' => 'Urgent', 'type' => CustomFieldType::Checkbox, 'position' => 1]);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('saveCustomField', $field->id, true);
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->value('value'))->toBe('1');

    $component->call('saveCustomField', $field->id, false);
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse();
});

test('a non-admin member cannot add custom fields', function () {
    ['board' => $board] = makeCustomFieldContext();
    $member = User::factory()->create();
    $board->workspace->members()->attach($member, ['role' => Role::Member->value]);
    $board->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($member)->test(Show::class, ['board' => $board])
        ->set('newFieldName', 'Secret')
        ->set('newFieldType', 'text')
        ->call('addCustomField')
        ->assertForbidden();
});
