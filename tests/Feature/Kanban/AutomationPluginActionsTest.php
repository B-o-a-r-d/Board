<?php

use App\Automations\AutomationEngine;
use App\Automations\AutomationRegistry;
use App\Automations\PluginAutomationAction;
use App\Events\UserToast;
use App\Livewire\Boards\Automations;
use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** Phase 9: Power-Up-contributed automation actions (ProvidesAutomationActions). */
function acmeActionRule(int $boardId, int $userId, array $extraActions = []): Automation
{
    return Automation::create([
        'board_id' => $boardId,
        'created_by' => $userId,
        'name' => 'Règle Acme',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'is_active' => true,
        'actions' => array_merge([
            ['type' => 'plugin:acme:create_item', 'config' => ['resource' => 'team/project']],
        ], $extraActions),
    ]);
}

test('the registry exposes the plugin action under its qualified key', function () {
    $action = app(AutomationRegistry::class)->action('plugin:acme:create_item');

    expect($action)->toBeInstanceOf(PluginAutomationAction::class)
        ->and($action->label())->toBe('Créer un item Acme')
        ->and($action->configFields())->toBe([
            ['key' => 'resource', 'label' => 'Resource', 'type' => 'text'],
        ]);
});

test('the engine runs a plugin action with the instance config and a normalized card payload', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'Payer la facture']);
    Http::fake();

    $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'is_active' => true,
        'config' => ['token' => 'secret-token'],
    ]);
    acmeActionRule($board->id, $owner->id);

    app(AutomationEngine::class)->fire('card.completed', $card);

    Http::assertSent(function ($request) use ($card) {
        return str_contains($request->url(), 'api.acme.test/items')
            && $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request['title'] === 'Payer la facture'
            && $request['resource'] === 'team/project'
            && $request['card_id'] === $card->public_id;
    });

    expect(AutomationRun::where('board_id', $board->id)->first()->status)->toBe('success');
});

test('a returned toast is broadcast to the acting user, with unsafe action links dropped', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    Http::fake(['api.acme.test/*' => Http::response(['id' => 1, 'url' => 'https://acme.test/items/1'])]);
    Event::fake([UserToast::class]);

    $board->plugins()->create(['plugin_key' => 'acme', 'name' => 'Acme', 'is_active' => true, 'config' => []]);
    acmeActionRule($board->id, $owner->id);

    $this->actingAs($owner);
    app(AutomationEngine::class)->fire('card.completed', $card);

    Event::assertDispatched(UserToast::class, function (UserToast $event) use ($owner) {
        $payload = $event->broadcastWith();

        return $event->userId === $owner->id
            && $event->broadcastOn()[0]->name === 'private-App.Models.User.'.$owner->id
            && $payload['message'] === 'Item Acme créé'
            && $payload['type'] === 'success'
            && $payload['duration'] === 6000
            // The fixture also returned a javascript: action — filtered out.
            && $payload['actions'] === [['label' => 'Ouvrir', 'url' => 'https://acme.test/items/1']];
    });
});

test('no toast is broadcast when the run has no acting user', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    Http::fake(['api.acme.test/*' => Http::response(['id' => 1, 'url' => 'https://acme.test/items/1'])]);
    Event::fake([UserToast::class]);

    $board->plugins()->create(['plugin_key' => 'acme', 'name' => 'Acme', 'is_active' => true, 'config' => []]);
    acmeActionRule($board->id, $owner->id);

    // System-fired event, nobody authenticated.
    app(AutomationEngine::class)->fire('card.completed', $card);

    Event::assertNotDispatched(UserToast::class);
});

test('a plugin action fails safely when the Power-Up is not installed, without blocking the pipeline', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    Http::fake();

    // No acme BoardPlugin instance → the adapter throws, sandboxed by the pipeline.
    $rule = acmeActionRule($board->id, $owner->id, [
        ['type' => 'archive_card', 'config' => []],
    ]);

    app(AutomationEngine::class)->fire('card.completed', $card);

    Http::assertNothingSent();

    $run = AutomationRun::where('automation_id', $rule->id)->first();
    expect($run->status)->toBe('partial')
        ->and($run->actions_failed)->toBe(1)
        ->and($run->error)->toContain('acme')
        // The next action still ran.
        ->and($card->fresh()->archived_at)->not->toBeNull();
});

test('an inactive instance also refuses the plugin action', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    Http::fake();

    $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'is_active' => false, 'config' => [],
    ]);
    $rule = acmeActionRule($board->id, $owner->id);

    app(AutomationEngine::class)->fire('card.completed', $card);

    Http::assertNothingSent();
    expect($rule->fresh()->failures_count)->toBe(1);
});

test('the builder only offers plugin actions when the board has an active instance', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    // Without an instance: the action is refused and the tab is absent.
    Livewire::actingAs($owner)->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('startCreate')
        ->call('pickTrigger', 'card.completed')
        ->call('goToStep', 2)
        ->assertDontSee('Créer un item Acme')
        ->call('addAction', 'plugin:acme:create_item')
        ->assertSet('actions', []);

    // With an active instance: tab visible, action accepted, sentence uses the label.
    $board->plugins()->create(['plugin_key' => 'acme', 'name' => 'Acme', 'is_active' => true, 'config' => []]);

    $component = Livewire::actingAs($owner)->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('startCreate')
        ->call('pickTrigger', 'card.completed')
        ->call('goToStep', 2)
        ->assertSee('Créer un item Acme')
        ->call('addAction', 'plugin:acme:create_item')
        ->set('actions.0.config.resource', 'team/project')
        ->call('save')
        ->assertHasNoErrors();

    $automation = $board->automations()->first();
    expect(array_column($automation->actionList(), 'type'))->toBe(['plugin:acme:create_item'])
        ->and($automation->name)->toContain('Créer un item Acme');
});
