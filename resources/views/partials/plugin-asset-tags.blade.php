{{-- The raw <link>/<script> tags for a plugin's declared assets, wrapped by
     <x-plugin-assets> in @assets. Split out so it can be rendered (and tested)
     without the Livewire @assets capture. --}}
@php($assets = app(\App\Plugins\PluginAssets::class))

@foreach ($bundle['styles'] as $style)
    <link rel="stylesheet" href="{{ route('plugins.asset', ['plugin' => $plugin, 'file' => $style]) }}?v={{ $assets->version($plugin, $style) }}">
@endforeach
@foreach ($bundle['scripts'] as $script)
    <script src="{{ route('plugins.asset', ['plugin' => $plugin, 'file' => $script]) }}?v={{ $assets->version($plugin, $script) }}"></script>
@endforeach
