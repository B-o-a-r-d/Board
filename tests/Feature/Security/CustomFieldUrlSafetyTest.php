<?php

use App\Automations\Actions\SetCustomFieldAction;
use App\Enums\CustomFieldType;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\CustomField;
use Livewire\Livewire;

/**
 * A Url custom field must never store or render a non-http(s) scheme, otherwise
 * a `javascript:` value becomes one-click stored XSS in the card view.
 */
function urlField(int $boardId): CustomField
{
    return CustomField::create([
        'board_id' => $boardId,
        'name' => 'Lien',
        'type' => CustomFieldType::Url->value,
        'position' => 0,
    ]);
}

test('isSafeUrl accepts http(s) and rejects everything else', function (string $value, bool $safe) {
    expect(CustomFieldType::isSafeUrl($value))->toBe($safe);
})->with([
    ['https://example.com', true],
    ['http://example.com/path?q=1', true],
    ['javascript:alert(1)', false],
    ['JavaScript:alert(1)', false],
    ['data:text/html,<script>alert(1)</script>', false],
    ['vbscript:msgbox(1)', false],
    ['ftp://example.com', false],
    ['not-a-url', false],
    ['', false],
]);

test('the table-view inline editor refuses a javascript: URL', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = urlField($board->id);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setCardCustomField', $card->id, $field->id, 'javascript:alert(document.cookie)');

    expect($card->customFieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse();

    // A legitimate https URL still goes through.
    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setCardCustomField', $card->id, $field->id, 'https://example.com');

    expect($card->customFieldValues()->where('custom_field_id', $field->id)->value('value'))
        ->toBe('https://example.com');
});

test('the set_custom_field automation action drops a javascript: URL', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $field = urlField($board->id);

    (new SetCustomFieldAction)->run($card, ['field_id' => $field->id, 'value' => 'javascript:alert(1)']);

    expect($card->customFieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse();

    (new SetCustomFieldAction)->run($card, ['field_id' => $field->id, 'value' => 'https://example.com']);

    expect($card->customFieldValues()->where('custom_field_id', $field->id)->value('value'))
        ->toBe('https://example.com');
});

test('the card modal rejects a javascript: URL and keeps the field unset', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = urlField($board->id);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveCustomField', $field->id, 'javascript:alert(1)')
        ->assertHasErrors('cf-'.$field->id);

    expect($card->customFieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse();
});

test('a stored unsafe URL is rendered as inert text, never a javascript href', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = urlField($board->id);

    // Simulate a value that slipped in before the guard existed.
    $card->customFieldValues()->create([
        'custom_field_id' => $field->id,
        'value' => 'javascript:alert(1)',
    ]);

    $html = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->html();

    expect($html)->not->toContain('href="javascript:')
        ->and($html)->not->toContain("href='javascript:");
});
