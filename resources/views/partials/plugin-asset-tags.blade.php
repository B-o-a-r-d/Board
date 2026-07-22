{{-- The <link>/<script> tags for a plugin's declared assets. Emitted directly
     into the page (see <x-plugin-assets>); data-navigate-track keeps them across
     wire:navigate. Split out so it can be rendered (and tested) in isolation. --}}
@php($assets = app(\App\Plugins\PluginAssets::class))

@foreach ($bundle['styles'] as $style)
    <link rel="stylesheet" data-navigate-track="reload" href="{{ route('plugins.asset', ['plugin' => $plugin, 'file' => $style]) }}?v={{ $assets->version($plugin, $style) }}">
@endforeach
@foreach ($bundle['scripts'] as $script)
    <script data-navigate-track="reload" src="{{ route('plugins.asset', ['plugin' => $plugin, 'file' => $script]) }}?v={{ $assets->version($plugin, $script) }}"></script>
@endforeach
