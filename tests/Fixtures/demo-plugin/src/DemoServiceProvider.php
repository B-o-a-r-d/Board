<?php

namespace Acme\DemoPlugin;

use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\PluginServiceProvider;

class DemoServiceProvider extends PluginServiceProvider
{
    protected function plugin(): Plugin
    {
        return new DemoPlugin;
    }
}
