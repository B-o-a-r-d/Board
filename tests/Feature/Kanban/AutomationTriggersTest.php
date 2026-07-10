<?php

use App\Automations\AutomationEngine;
use App\Automations\AutomationRegistry;
use App\Automations\Contracts\AutomationAction;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\Automation;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CustomField;
use App\Models\Label;
use Livewire\Livewire;

/**
 * Phase 2: the trigger catalog. Each trigger is exercised at engine level
 * (event + payload → does the rule fire?), plus integration checks that the
 * Livewire components actually emit the new events.
 */
class TriggerProbeAction implements AutomationAction
{
    public static int $runs = 0;

    public static function key(): string
    {
        return 'trigger_probe';
    }

    public function label(): string
    {
        return 'Probe';
    }

    public function configFields(): array
    {
        return [];
    }

    public function run(Card $card, array $config): void
    {
        self::$runs++;
    }
}

function probeRule(int $boardId, int $userId, string $triggerType, array $triggerConfig = []): Automation
{
    TriggerProbeAction::$runs = 0;
    app(AutomationRegistry::class)->registerAction(new TriggerProbeAction);

    return Automation::create([
        'board_id' => $boardId,
        'created_by' => $userId,
        'name' => 'Probe '.$triggerType,
        'trigger_type' => $triggerType,
        'action_type' => 'noop',
        'is_active' => true,
        'actions' => [['type' => 'trigger_probe', 'config' => []]],
    ]);
}

// --- Engine-level trigger matching -------------------------------------------------

test('card.archived trigger fires on the archive event', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    probeRule($board->id, $owner->id, 'card.archived');

    app(AutomationEngine::class)->fire('card.archived', $card);

    expect(TriggerProbeAction::$runs)->toBe(1);
});

test('card.moved_from_list trigger matches the source list only', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $other = BoardList::factory()->create(['board_id' => $board->id]);
    $rule = probeRule($board->id, $owner->id, 'card.moved_from_list');
    $rule->update(['trigger_config' => ['list_id' => $card->board_list_id]]);

    $engine = app(AutomationEngine::class);

    $engine->fire('card.moved', $card, ['from_list_id' => $other->id, 'to_list_id' => $card->board_list_id]);
    expect(TriggerProbeAction::$runs)->toBe(0);

    $engine->fire('card.moved', $card, ['from_list_id' => $card->board_list_id, 'to_list_id' => $other->id]);
    expect(TriggerProbeAction::$runs)->toBe(1);
});

test('list.has_n_cards trigger compares the live list count', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    Card::factory()->count(2)->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id]);

    $rule = probeRule($board->id, $owner->id, 'list.has_n_cards');
    $rule->update(['trigger_config' => ['list_id' => $card->board_list_id, 'op' => 'exactly', 'count' => 3]]);

    $engine = app(AutomationEngine::class);

    $engine->fire('card.created', $card, ['list_id' => $card->board_list_id]);
    expect(TriggerProbeAction::$runs)->toBe(1);

    $rule->update(['trigger_config' => ['list_id' => $card->board_list_id, 'op' => 'exactly', 'count' => 5]]);
    $engine->fire('card.created', $card, ['list_id' => $card->board_list_id]);
    expect(TriggerProbeAction::$runs)->toBe(1);

    $rule->update(['trigger_config' => ['list_id' => $card->board_list_id, 'op' => 'at_least', 'count' => 2]]);
    $engine->fire('card.created', $card, ['list_id' => $card->board_list_id]);
    expect(TriggerProbeAction::$runs)->toBe(2);
});

test('label added/removed triggers filter by label when configured', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $label = Label::factory()->create(['board_id' => $board->id]);
    $otherLabel = Label::factory()->create(['board_id' => $board->id]);

    $rule = probeRule($board->id, $owner->id, 'card.label_added');
    $rule->update(['trigger_config' => ['label_id' => $label->id]]);

    $engine = app(AutomationEngine::class);

    $engine->fire('card.label_added', $card, ['label_id' => $otherLabel->id]);
    expect(TriggerProbeAction::$runs)->toBe(0);

    $engine->fire('card.label_added', $card, ['label_id' => $label->id]);
    expect(TriggerProbeAction::$runs)->toBe(1);

    // Removal trigger, unfiltered → any label matches.
    $rule->update(['trigger_type' => 'card.label_removed', 'trigger_config' => []]);
    $engine->fire('card.label_removed', $card, ['label_id' => $otherLabel->id]);
    expect(TriggerProbeAction::$runs)->toBe(2);
});

test('member assigned, due set and checklist triggers match their events', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $engine = app(AutomationEngine::class);

    $rule = probeRule($board->id, $owner->id, 'card.member_assigned');
    $rule->update(['trigger_config' => ['user_id' => $member->id]]);
    $engine->fire('card.member_assigned', $card, ['user_id' => $owner->id]);
    expect(TriggerProbeAction::$runs)->toBe(0);
    $engine->fire('card.member_assigned', $card, ['user_id' => $member->id]);
    expect(TriggerProbeAction::$runs)->toBe(1);

    foreach (['card.due_set', 'checklist.added', 'checklist.item_checked', 'checklist.completed'] as $eventKey) {
        probeRule($board->id, $owner->id, $eventKey);
        $engine->fire($eventKey, $card);
        expect(TriggerProbeAction::$runs)->toBe(1, "trigger {$eventKey} did not fire");
    }
});

test('comment.added trigger can filter on the comment text', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $rule = probeRule($board->id, $owner->id, 'comment.added');
    $rule->update(['trigger_config' => ['text' => 'urgent']]);

    $engine = app(AutomationEngine::class);

    $engine->fire('comment.added', $card, ['body' => 'Tout va bien']);
    expect(TriggerProbeAction::$runs)->toBe(0);

    $engine->fire('comment.added', $card, ['body' => 'Cas URGENT à traiter']);
    expect(TriggerProbeAction::$runs)->toBe(1);
});

test('card.title_contains trigger matches on create and rename', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $rule = probeRule($board->id, $owner->id, 'card.title_contains');
    $rule->update(['trigger_config' => ['text' => 'bug']]);

    $engine = app(AutomationEngine::class);

    $card->update(['title' => 'Feature paiement']);
    $engine->fire('card.renamed', $card);
    expect(TriggerProbeAction::$runs)->toBe(0);

    $card->update(['title' => '[BUG] paiement KO']);
    $engine->fire('card.renamed', $card);
    expect(TriggerProbeAction::$runs)->toBe(1);
});

test('custom_field.changed trigger filters by field and optional value', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = CustomField::create(['board_id' => $board->id, 'name' => 'Statut', 'type' => 'select', 'options' => ['Ouvert', 'Fermé'], 'position' => 0]);
    $other = CustomField::create(['board_id' => $board->id, 'name' => 'Autre', 'type' => 'text', 'position' => 1]);

    $rule = probeRule($board->id, $owner->id, 'custom_field.changed');
    $rule->update(['trigger_config' => ['field_id' => $field->id, 'value' => 'Fermé']]);

    $engine = app(AutomationEngine::class);

    $engine->fire('custom_field.changed', $card, ['field_id' => $other->id, 'value' => 'Fermé']);
    $engine->fire('custom_field.changed', $card, ['field_id' => $field->id, 'value' => 'Ouvert']);
    expect(TriggerProbeAction::$runs)->toBe(0);

    $engine->fire('custom_field.changed', $card, ['field_id' => $field->id, 'value' => 'Fermé']);
    expect(TriggerProbeAction::$runs)->toBe(1);
});

// --- Instrumentation: the components actually emit the events ----------------------

test('modal interactions emit the new automation events', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $label = Label::factory()->create(['board_id' => $board->id]);
    $field = CustomField::create(['board_id' => $board->id, 'name' => 'Statut', 'type' => 'text', 'position' => 0]);

    // One always-matching probe per event family.
    probeRule($board->id, $owner->id, 'card.label_added');
    probeRule($board->id, $owner->id, 'card.member_assigned');
    probeRule($board->id, $owner->id, 'comment.added');
    // The field trigger requires its target field in config.
    probeRule($board->id, $owner->id, 'custom_field.changed')
        ->update(['trigger_config' => ['field_id' => $field->id]]);
    $lastRule = probeRule($board->id, $owner->id, 'card.title_contains');
    $lastRule->update(['trigger_config' => ['text' => 'renommée']]);
    TriggerProbeAction::$runs = 0;

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('toggleLabel', $label->id);
    expect(TriggerProbeAction::$runs)->toBe(1, 'label_added');
    $component->call('toggleMember', $owner->id);
    expect(TriggerProbeAction::$runs)->toBe(2, 'member_assigned');
    $component->call('addComment', 'Un commentaire');
    expect(TriggerProbeAction::$runs)->toBe(3, 'comment.added');
    $component->call('saveCustomField', $field->id, 'x');
    expect(TriggerProbeAction::$runs)->toBe(4, 'custom_field.changed');
    $component->set('title', 'Carte renommée');
    expect(TriggerProbeAction::$runs)->toBe(5, 'card.renamed');
});

test('completing the last checklist item emits item_checked and checklist.completed', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $checklist = $card->checklists()->create(['title' => 'QA', 'position' => 0]);
    $item = $checklist->items()->create(['content' => 'Vérifier', 'position' => 0]);

    probeRule($board->id, $owner->id, 'checklist.item_checked');
    probeRule($board->id, $owner->id, 'checklist.completed');
    TriggerProbeAction::$runs = 0;

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleChecklistItem', $item->id);

    expect(TriggerProbeAction::$runs)->toBe(2);

    // Unchecking fires nothing.
    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleChecklistItem', $item->id);

    expect(TriggerProbeAction::$runs)->toBe(2);
});

test('archiving from the modal and bulk labels emit their events', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $label = Label::factory()->create(['board_id' => $board->id]);

    probeRule($board->id, $owner->id, 'card.label_added');
    probeRule($board->id, $owner->id, 'card.archived');
    TriggerProbeAction::$runs = 0;

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('bulkAddLabel', [$card->id], $label->id);

    expect(TriggerProbeAction::$runs)->toBe(1);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('archive');

    expect(TriggerProbeAction::$runs)->toBe(2);
});
