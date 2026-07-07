<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fixtures\Connector\AcmeServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Register the neutral Acme connector fixture so plugin-system tests have a
     * concrete, capability-complete Power-Up in the registry (the app itself no
     * longer bundles any plugin package).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(AcmeServiceProvider::class);
    }
}
