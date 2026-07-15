<?php

use Board\PluginSdk\PluginListItem;

/**
 * Plugin list items are built from external API responses (GitHub PRs, GitLab
 * issues…). Their `url` must only ever produce an href when it is http(s), so a
 * compromised or malicious upstream can't inject a javascript:/data: scheme.
 */
function renderPluginList(PluginListItem $item): string
{
    return view('livewire.boards.plugin-list', [
        'items' => collect([$item]),
        'list' => (object) ['sourcePlugin' => null, 'id' => 1],
        'plugin' => null,
        'hasMore' => false,
    ])->render();
}

test('an item with an unsafe url is rendered without an href', function () {
    $html = renderPluginList(new PluginListItem(
        externalRef: '1',
        title: 'Evil item',
        url: 'javascript:alert(document.cookie)',
    ));

    expect($html)->toContain('Evil item')
        ->and($html)->not->toContain('javascript:');
});

test('an item with an https url keeps its href', function () {
    $html = renderPluginList(new PluginListItem(
        externalRef: '2',
        title: 'Good item',
        url: 'https://example.com/pr/2',
    ));

    expect($html)->toContain('href="https://example.com/pr/2"');
});
