<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('reports every dependency as ok with exactly the documented keys', function () {
    $response = $this->getJson('/health');

    $response->assertOk()->assertExactJson([
        'status' => 'ok',
        'checks' => ['database' => 'ok', 'cache' => 'ok', 'queue' => 'ok'],
    ]);
});

it('returns 503 and only a fail flag when the database is unreachable', function () {
    config(['database.connections.broken' => [
        'driver' => 'sqlite',
        'database' => storage_path('missing-dir/nope.sqlite'),
        'prefix' => '',
    ]]);

    $original = DB::getDefaultConnection();
    DB::setDefaultConnection('broken');

    try {
        $response = $this->getJson('/health');
    } finally {
        DB::setDefaultConnection($original);
    }

    $response->assertStatus(503)->assertExactJson([
        'status' => 'fail',
        'checks' => ['database' => 'fail', 'cache' => 'ok', 'queue' => 'ok'],
    ]);
    expect($response->getContent())->not->toContain('nope.sqlite');
});

it('does not leave its probe key behind in the cache', function () {
    $this->getJson('/health')->assertOk();

    expect(Cache::get('nextup:health:anything'))->toBeNull();
});

it('keeps the framework liveness endpoint working', function () {
    $this->get('/up')->assertOk();
});

it('throttles the health endpoint', function () {
    foreach (range(1, 30) as $ignored) {
        $this->getJson('/health')->assertOk();
    }

    $this->getJson('/health')->assertStatus(429);
});
