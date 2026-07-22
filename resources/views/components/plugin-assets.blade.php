@props(['plugin'])

{{--
    Loads a Power-Up's pre-built CSS/JS (declared via ProvidesAssets) on its own
    pages. Drop it once near the top of a plugin page:

        <x-plugin-assets plugin="shelf" />

    The tags are emitted directly (not via @assets): a plain <link>/<script>
    always lands in the DOM on both a full load and a wire:navigate visit, and
    `data-navigate-track="reload"` re-fetches them across SPA navigations. URLs
    carry a content hash so a plugin update busts the immutable browser cache.
    No-op when the plugin ships no assets.
--}}
@php($bundle = app(\App\Plugins\PluginAssets::class)->for($plugin))

@if ($bundle !== null)
    @include('partials.plugin-asset-tags', ['plugin' => $plugin, 'bundle' => $bundle])
@endif
