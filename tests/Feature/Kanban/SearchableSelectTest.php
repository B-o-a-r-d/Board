<?php

use Illuminate\Support\Facades\Blade;

test('the searchable-select renders options with phosphor icons and no emoji', function () {
    $options = [
        ['value' => 'group/app', 'label' => 'group/app', 'icon' => 'lock-simple'],
        ['value' => 'octo/public', 'label' => 'octo/public', 'icon' => null],
    ];

    $html = Blade::render(
        '<x-searchable-select :model="$m" :options="$options" placeholder="Choisir…" />',
        ['m' => 'newPluginListConfig.project', 'options' => $options],
    );

    expect($html)->toContain('group/app')       // option labels rendered
        ->and($html)->toContain('octo/public')
        ->and($html)->toContain('Rechercher')    // the search box
        ->and($html)->toContain('<svg')          // a phosphor icon rendered (lock-simple)
        ->and($html)->not->toContain('🔒')
        ->and($html)->not->toContain('↗')
        ->and($html)->not->toContain('💬');
});
