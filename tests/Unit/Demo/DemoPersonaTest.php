<?php

use App\Demo\DemoLibrary;
use App\Demo\DemoPersona;
use App\Demo\DemoPlay;
use Carbon\CarbonImmutable;

/**
 * Every catalogue game with its persona kind and play, keyed by appid.
 *
 * @return array<int, array{game: array<string, mixed>, kind: string, play: ?DemoPlay}>
 */
function personaLibrary(CarbonImmutable $now): array
{
    $library = [];

    foreach (DemoLibrary::catalogue() as $game) {
        $library[$game['steam_appid']] = [
            'game' => $game,
            'kind' => DemoPersona::kind($game['steam_appid']),
            'play' => DemoPersona::play($game['steam_appid'], $now),
        ];
    }

    return $library;
}

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC');
});

it('is deterministic', function () {
    expect(personaLibrary($this->now))->toEqual(personaLibrary($this->now));
});

it('only names games that are in the catalogue', function () {
    $appIds = array_column(DemoLibrary::catalogue(), 'steam_appid');
    $library = personaLibrary($this->now);

    $named = array_filter($library, fn (array $e): bool => $e['kind'] !== 'unplayed');
    $withPlay = array_filter($library, fn (array $e): bool => $e['play'] !== null);

    // Every loved, dropped and recent appid in the persona matched a catalogue game.
    expect(array_keys($withPlay))->toBe(array_keys($named))
        ->and($withPlay)->toHaveCount(14 + 4 + 3)
        ->and(array_diff(array_keys(DemoPersona::friendActivity()), $appIds))->toBe([]);
});

it('loves about fourteen games with 20 to 250 hours', function () {
    $loved = array_filter(personaLibrary($this->now), fn (array $e): bool => $e['kind'] === 'loved');

    expect($loved)->toHaveCount(14);

    foreach ($loved as $entry) {
        expect($entry['play']->minutes)->toBeBetween(20 * 60, 250 * 60)
            ->and($entry['play']->minutes2Weeks)->toBeNull();
    }
});

it('loves story-rich RPGs and co-op shooters', function () {
    $loved = array_filter(personaLibrary($this->now), fn (array $e): bool => $e['kind'] === 'loved');

    foreach ($loved as $entry) {
        $tags = array_column($entry['game']['tags'], 'name');

        $fits = in_array('Role-playing (RPG)', $tags, true)
            || (in_array('Co-operative', $tags, true) && in_array('Shooter', $tags, true));

        expect($fits)->toBeTrue("{$entry['game']['name']} does not fit the persona");
    }
});

it('drops a few games after under two hours, long ago', function () {
    $dropped = array_filter(personaLibrary($this->now), fn (array $e): bool => $e['kind'] === 'dropped');

    expect(count($dropped))->toBeBetween(3, 5);

    foreach ($dropped as $entry) {
        expect($entry['play']->minutes)->toBeBetween(1, 119)
            ->and($entry['play']->lastPlayedAt->diffInDays($this->now, true))->toBeGreaterThan(180);
    }
});

it('plays a few games right now', function () {
    $recent = array_filter(personaLibrary($this->now), fn (array $e): bool => $e['kind'] === 'recent');

    expect(count($recent))->toBeBetween(2, 4);

    foreach ($recent as $entry) {
        expect($entry['play']->minutes2Weeks)->toBeGreaterThan(0)
            ->and($entry['play']->minutes)->toBeGreaterThanOrEqual($entry['play']->minutes2Weeks)
            ->and($entry['play']->lastPlayedAt->diffInDays($this->now, true))->toBeLessThanOrEqual(7)
            ->and($entry['play']->lastPlayedAt->lte($this->now))->toBeTrue();
    }
});

it('leaves most of the library unplayed', function () {
    $library = personaLibrary($this->now);
    $unplayed = array_filter($library, fn (array $e): bool => $e['kind'] === 'unplayed');

    expect(count($unplayed))->toBeGreaterThan(count($library) * 0.7);

    foreach ($unplayed as $entry) {
        expect($entry['play'])->toBeNull();
    }
});

it('keeps every date relative to now', function () {
    $later = $this->now->addDays(10);

    foreach (personaLibrary($this->now) as $appId => $entry) {
        $shifted = DemoPersona::play($appId, $later);

        if ($entry['play'] === null) {
            expect($shifted)->toBeNull();

            continue;
        }

        expect((int) $entry['play']->lastPlayedAt->diffInDays($shifted->lastPlayedAt, true))->toBe(10)
            ->and($shifted->minutes)->toBe($entry['play']->minutes);
    }
});

it('invents friend counts for two or three games', function () {
    $friends = DemoPersona::friendActivity();

    expect(count($friends))->toBeBetween(2, 3);

    foreach ($friends as $count) {
        expect($count)->toBeGreaterThan(0);
    }
});
