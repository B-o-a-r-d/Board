<?php

/**
 * Enforces that the suite always runs on the in-memory sqlite database, never a
 * real connection. The active guards live in Tests\TestCase::setUp():
 *   - it refuses to boot if bootstrap/cache/config.php exists (a cached config
 *     overrides phpunit.xml and could point tests at the real database);
 *   - it aborts unless the resolved connection is sqlite :memory:.
 */
test('the suite runs against the in-memory sqlite database', function () {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');
});
