<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
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
        // A cached config (bootstrap/cache/config.php) is loaded verbatim and
        // OVERRIDES phpunit.xml's <env> values, so the suite could silently run
        // against the real database — and RefreshDatabase would wipe it. Refuse
        // before the application (and any DB access) ever boots.
        if (file_exists(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new RuntimeException(
                'A cached config is present (bootstrap/cache/config.php). It overrides '
                .'phpunit.xml and could point the tests at your real database. '
                .'Run `php artisan config:clear` before running the test suite.'
            );
        }

        parent::setUp();

        // Second tripwire: the suite must run on the in-memory sqlite database,
        // never a real (e.g. pgsql) connection.
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException(
                'Refusing to run tests against the "'.config('database.default').'" connection. '
                .'Tests must use the in-memory sqlite connection defined in phpunit.xml.'
            );
        }

        $this->app->register(AcmeServiceProvider::class);
    }
}
