<?php

use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CustomField;
use Livewire\Livewire;

function makeField(array $attributes): CustomField
{
    return CustomField::create(array_merge(['position' => 0], $attributes));
}

// --- Creation (admin modal) ---------------------------------------------------

test('an admin can create the new field types', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    foreach (['url', 'email', 'member', 'rating', 'progress'] as $type) {
        $component->set('newFieldName', 'Champ '.$type)
            ->set('newFieldType', $type)
            ->call('addCustomField')
            ->assertHasNoErrors();
    }

    expect($board->customFields()->pluck('type')->map(fn ($t) => $t->value)->all())
        ->toContain('url', 'email', 'member', 'rating', 'progress');
});

test('a multiselect field requires options and stores them', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('newFieldName', 'Tags')
        ->set('newFieldType', 'multiselect')
        ->set('newFieldOptions', '')
        ->call('addCustomField')
        ->assertHasErrors('newFieldOptions');

    $component->set('newFieldOptions', 'Front, Back, Infra')
        ->call('addCustomField')
        ->assertHasNoErrors();

    $field = $board->customFields()->where('type', 'multiselect')->first();
    expect($field->optionList())->toBe(['Front', 'Back', 'Infra']);
});

test('a money field stores its currency and a placement can be chosen', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('newFieldName', 'Budget')
        ->set('newFieldType', 'money')
        ->set('newFieldCurrency', '$')
        ->set('newFieldPlacement', 'content')
        ->call('addCustomField')
        ->assertHasNoErrors();

    $field = $board->customFields()->where('type', 'money')->first();
    expect($field->currency())->toBe('$')
        ->and($field->placement)->toBe('content');
});

// --- Value validation per type --------------------------------------------------

test('a url field accepts http(s) and rejects other schemes', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = makeField(['board_id' => $board->id, 'name' => 'Doc', 'type' => 'url']);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveCustomField', $field->id, 'https://exemple.com/doc')
        ->assertHasNoErrors();

    expect($card->customFieldValues()->first()->value)->toBe('https://exemple.com/doc');

    // A dangerous scheme is refused AND the stored value is kept.
    $component->call('saveCustomField', $field->id, 'javascript:alert(1)')
        ->assertHasErrors('cf-'.$field->id);

    expect($card->customFieldValues()->first()->value)->toBe('https://exemple.com/doc');
});

test('an email field rejects invalid addresses and keeps the previous value', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = makeField(['board_id' => $board->id, 'name' => 'Contact', 'type' => 'email']);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveCustomField', $field->id, 'jean@exemple.com')
        ->assertHasNoErrors();

    $component->call('saveCustomField', $field->id, 'pas-un-email')
        ->assertHasErrors('cf-'.$field->id);

    expect($card->customFieldValues()->first()->value)->toBe('jean@exemple.com');
});

test('a multiselect value only keeps declared options, stored as JSON', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = makeField(['board_id' => $board->id, 'name' => 'Tags', 'type' => 'multiselect', 'options' => ['Front', 'Back', 'Infra']]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveCustomField', $field->id, ['Front', 'Hacked', 'Infra']);

    $raw = $card->customFieldValues()->first()->value;
    expect($field->decode($raw))->toBe(['Front', 'Infra']);

    // Emptying the selection clears the row.
    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveCustomField', $field->id, []);

    expect($card->customFieldValues()->count())->toBe(0);
});

test('a member field only accepts an actual board member', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'outsider' => $outsider, 'card' => $card] = makeCardContext();
    $field = makeField(['board_id' => $board->id, 'name' => 'Référent', 'type' => 'member']);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveCustomField', $field->id, $member->id);

    expect($card->customFieldValues()->first()->value)->toBe((string) $member->id);

    // An outsider id clears the value instead of storing it.
    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveCustomField', $field->id, $outsider->id);

    expect($card->customFieldValues()->count())->toBe(0);
});

test('rating, progress and money values are normalized', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $rating = makeField(['board_id' => $board->id, 'name' => 'Note', 'type' => 'rating']);
    $progress = makeField(['board_id' => $board->id, 'name' => 'Avancement', 'type' => 'progress']);
    $money = makeField(['board_id' => $board->id, 'name' => 'Budget', 'type' => 'money', 'options' => ['currency' => '€']]);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveCustomField', $rating->id, 7)
        ->call('saveCustomField', $progress->id, 150)
        ->call('saveCustomField', $money->id, '12,50');

    $values = $card->customFieldValues()->get()->keyBy('custom_field_id');
    expect($values->get($rating->id)->value)->toBe('5')
        ->and($values->get($progress->id)->value)->toBe('100')
        ->and($values->get($money->id)->value)->toBe('12.5');

    // Rating 0 clears the stored value.
    $component->call('saveCustomField', $rating->id, 0);
    expect($card->customFieldValues()->where('custom_field_id', $rating->id)->exists())->toBeFalse();
});

// --- Board badges ----------------------------------------------------------------

test('custom field values render as badges on the board card face', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = makeField(['board_id' => $board->id, 'name' => 'Priorité', 'type' => 'select', 'options' => ['Basse', 'Haute']]);
    $card->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'Haute']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('loadCards')
        ->assertSee('Priorité')
        ->assertSee('Haute');
});

// --- Field scopes (board / list / card) --------------------------------------------

test('a card-scoped field only shows on its card', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $other = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id]);
    makeField(['board_id' => $board->id, 'card_id' => $card->id, 'name' => 'Champ privé', 'type' => 'text']);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertSee('Champ privé');

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $other->id)
        ->assertDontSee('Champ privé');
});

test('a list-scoped field is inherited by the cards of that list only', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $otherList = BoardList::factory()->create(['board_id' => $board->id]);
    $otherCard = Card::factory()->create(['board_list_id' => $otherList->id, 'board_id' => $board->id]);
    $field = makeField(['board_id' => $board->id, 'board_list_id' => $card->board_list_id, 'name' => 'Champ de liste', 'type' => 'text']);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertSee('Champ de liste');

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $otherCard->id)
        ->assertDontSee('Champ de liste');

    // Saving a value from a card outside the scope is refused.
    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $otherCard->id)
        ->call('saveCustomField', $field->id, 'nope')
        ->assertStatus(404);
});

test('a contributor can create card and list scoped fields from the card, but not board-wide', function () {
    ['board' => $board, 'member' => $member, 'card' => $card] = makeCardContext();

    Livewire::actingAs($member)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newCfName', 'Estimation')
        ->set('newCfType', 'number')
        ->set('newCfScope', 'card')
        ->call('addCardCustomField')
        ->assertHasNoErrors();

    $field = $board->customFields()->where('name', 'Estimation')->first();
    expect($field->card_id)->toBe($card->id)
        ->and($field->board_list_id)->toBeNull();

    Livewire::actingAs($member)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newCfName', 'Sprint')
        ->set('newCfType', 'text')
        ->set('newCfScope', 'list')
        ->call('addCardCustomField')
        ->assertHasNoErrors();

    expect($board->customFields()->where('name', 'Sprint')->first()->board_list_id)->toBe($card->board_list_id);

    // Board-wide creation stays an admin action.
    Livewire::actingAs($member)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newCfName', 'Global')
        ->set('newCfType', 'text')
        ->set('newCfScope', 'board')
        ->call('addCardCustomField')
        ->assertForbidden();

    expect($board->customFields()->where('name', 'Global')->exists())->toBeFalse();
});

test('the card modal shows the "Add to card" action bar for contributors only', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertSee(__('Ajouter à la carte'))
        ->assertSee(__('Créer vos propres champs'));
});

test('a card-scoped field value does not leak onto other cards on the board', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $other = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id, 'title' => 'Autre carte']);
    $field = makeField(['board_id' => $board->id, 'card_id' => $card->id, 'name' => 'Champ privé', 'type' => 'text']);
    $card->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'Secret']);
    // A stray value on the other card must not render since the field doesn't apply.
    $other->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'Leaked']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('loadCards')
        ->assertSee('Secret')
        ->assertDontSee('Leaked');
});

// --- Plugin-injected fields (ProvidesCardFields) -----------------------------------

test('installing a plugin materializes its declared card fields', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('installPlugin', 'acme');

    $fields = $board->customFields()->where('plugin_key', 'acme')->get()->keyBy('field_key');

    expect($fields)->toHaveCount(2)
        ->and($fields->get('acme_status')->type->value)->toBe('select')
        ->and($fields->get('acme_status')->placement)->toBe('content')
        ->and($fields->get('acme_status')->optionList())->toBe(['Open', 'In progress', 'Done'])
        ->and($fields->get('acme_ref')->type->value)->toBe('url')
        ->and($fields->get('acme_ref')->placement)->toBe('sidebar');
});

test('uninstalling the plugin removes its fields and their values', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('installPlugin', 'acme');

    $field = $board->customFields()->where('field_key', 'acme_ref')->first();
    $card->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'https://acme.test/x']);

    $instance = $board->plugins()->where('plugin_key', 'acme')->first();
    $component->call('uninstallPlugin', $instance->id);

    expect($board->customFields()->where('plugin_key', 'acme')->count())->toBe(0)
        ->and($card->customFieldValues()->count())->toBe(0);
});

test('deactivating the plugin hides its fields from the card modal but keeps the rows', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('installPlugin', 'acme');

    $instance = $board->plugins()->where('plugin_key', 'acme')->first();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertSee('Acme reference');

    $component->call('togglePluginActive', $instance->id);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertDontSee('Acme reference');

    expect($board->customFields()->where('plugin_key', 'acme')->count())->toBe(2);
});

test('plugin-managed fields cannot be deleted from the custom fields modal', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('installPlugin', 'acme');

    $field = $board->customFields()->where('plugin_key', 'acme')->first();
    $component->call('deleteCustomField', $field->id);

    expect($board->customFields()->whereKey($field->id)->exists())->toBeTrue();
});
