<?php

use App\Demo\DemoLibrary;
use App\Demo\DemoPersona;
use App\Demo\HeroPick;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('gives the landing page a hero pick', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('hero', fn (Assert $hero) => $hero
                ->has('name')
                ->has('cover_url')
                ->has('explanation')
                ->has('signals', fn (Assert $signals) => $signals->each(fn (Assert $signal) => $signal
                    ->has('key')->has('label')->has('value')))
                ->has('next', 4, fn (Assert $next) => $next->has('name')->has('cover_url'))));
});

it('picks an unplayed or recent game and explains it in plain English', function () {
    $hero = app(HeroPick::class)();

    $catalogue = collect(DemoLibrary::catalogue())->keyBy('name');
    $appId = $catalogue[$hero['name']]['steam_appid'];

    expect(DemoPersona::kind($appId))->toBeIn(['unplayed', 'recent'])
        ->and($hero['explanation'])->not->toBeEmpty()
        ->and($hero['explanation'])->toEndWith('.')
        ->and($hero['cover_url'])->toStartWith('https://images.igdb.com/igdb/image/upload/t_cover_big/');
});

it('shows the same signal bars as the Play next top pick', function () {
    $hero = app(HeroPick::class)();

    $keys = array_column($hero['signals'], 'key');

    expect($keys)->toContain('taste')
        // Mood is null without a chosen mood, and zero freshness or friends bars are left out.
        ->and($keys)->not->toContain('mood', 'mismatch', 'snooze');

    foreach ($hero['signals'] as $signal) {
        expect($signal['value'])->toBeBetween(0.0, 1.0)
            ->and($signal['label'])->not->toBeEmpty();

        if (in_array($signal['key'], ['freshness', 'social'], true)) {
            expect($signal['value'])->toBeGreaterThan(0.0);
        }
    }
});

it('returns four other picks for the cover stack', function () {
    $hero = app(HeroPick::class)();

    $names = array_merge([$hero['name']], array_column($hero['next'], 'name'));

    expect($hero['next'])->toHaveCount(4)
        ->and(array_unique($names))->toHaveCount(5);
});

it('needs no database queries and no external calls', function () {
    Http::fake();
    DB::enableQueryLog();

    app(HeroPick::class)();

    expect(DB::getQueryLog())->toBe([]);
    Http::assertNothingSent();
});

it('caches the pick for an hour', function () {
    $first = app(HeroPick::class)();

    $this->travel(59)->minutes();
    expect(Cache::has('demo.hero-pick.v1'))->toBeTrue()
        ->and(app(HeroPick::class)())->toBe($first);

    $this->travel(2)->minutes();
    expect(Cache::has('demo.hero-pick.v1'))->toBeFalse();
});

it('renders the landing page for signed-in users too, without leaking account details', function () {
    $user = User::factory()->create(['steam_id' => '76561198000000001', 'persona_name' => 'RealPersona']);

    $response = $this->actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome')->has('hero'));

    expect(json_encode($response->viewData('page')['props']))
        ->not->toContain('76561198000000001');
});

it('keeps the hero payload free of any real Steam ID or persona', function () {
    $json = json_encode(app(HeroPick::class)());

    expect($json)->not->toMatch('/7656119\d{10}/')
        ->and($json)->not->toContain('persona');
});
