<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PluginLoaderServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    // After AppServiceProvider (which binds the PluginRegistry): boot runtime,
    // marketplace-installed plugin packages from the persistent volume.
    PluginLoaderServiceProvider::class,
];
