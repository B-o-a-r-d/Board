@props(['plugin'])

{{--
    Loads a Power-Up's pre-built CSS/JS (declared via ProvidesAssets) on its own
    pages. Drop it once near the top of a plugin page:

        <x-plugin-assets plugin="shelf" />

    @assets makes Livewire inject each file into <head> exactly once and never
    re-run it across component updates. URLs carry a content hash so a plugin
    update busts the immutable browser cache. No-op when the plugin ships none.
--}}
@php($bundle = app(\App\Plugins\PluginAssets::class)->for($plugin))

@if ($bundle !== null)
    @assets
        @include('partials.plugin-asset-tags', ['plugin' => $plugin, 'bundle' => $bundle])
    @endassets
@endif
