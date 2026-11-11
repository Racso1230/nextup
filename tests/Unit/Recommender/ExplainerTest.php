<?php

use App\Recommender\Contribution;
use App\Recommender\Explainer;
use App\Recommender\Ranked;
use App\Recommender\SignalName;
use Tests\Unit\Recommender\Support\Facts;

/**
 * @param  array<string, ?float>  $values  signal value by SignalName value
 */
function explainRanked(array $values, array $evidenceGames = [], array $evidenceTags = [], ...$facts): Ranked
{
    $weights = ['taste' => 0.35, 'momentum' => 0.2, 'fit' => 0.15, 'social' => 0.1, 'freshness' => 0.1, 'mismatch' => 0.1];
    $contributions = [];

    foreach (SignalName::cases() as $signal) {
        $contributions[] = Contribution::of($signal, $values[$signal->value] ?? null, $weights[$signal->value]);
    }

    return new Ranked(Facts::game(...$facts), 0.0, $contributions, $evidenceGames, $evidenceTags);
}

it('leads with the strongest reason and joins up to three', function () {
    $ranked = explainRanked(
        ['taste' => 0.9, 'fit' => 0.5, 'freshness' => 1.0],
        [['name' => 'Hades', 'hours' => 100.0], ['name' => 'Dead Cells', 'hours' => 40.0]],
        ['Roguelike', 'Short runs'],
        id: 4, ttbHours: 8, tags: [Facts::genre(1)],
    );

    expect((new Explainer)->explain($ranked, Facts::now()))
        ->toBe('Because you put 140 hours into Hades and Dead Cells (roguelike, short runs), you\'ve never started it, and it\'s about 8 hours from the end.');
});

it('describes momentum from recent hours, then from the last-played date', function () {
    $recent = explainRanked(['momentum' => 1.0], [], [], id: 2, minutes: 600, twoWeeks: 270, lastPlayed: Facts::daysAgo(1));
    $yesterday = explainRanked(['momentum' => 0.93], [], [], id: 2, minutes: 600, lastPlayed: Facts::daysAgo(1));
    $days = explainRanked(['momentum' => 0.6], [], [], id: 2, minutes: 600, lastPlayed: Facts::daysAgo(7));

    expect((new Explainer)->explain($recent, Facts::now()))->toBe('You\'ve played 4.5 hours in the last two weeks.')
        ->and((new Explainer)->explain($yesterday, Facts::now()))->toBe('You played it yesterday.')
        ->and((new Explainer)->explain($days, Facts::now()))->toBe('You played it 7 days ago.');
});

it('never claims hours left when the time to beat is unknown', function () {
    $ranked = explainRanked(['fit' => 0.5, 'freshness' => 1.0], [], [], id: 5);

    expect((new Explainer)->explain($ranked, Facts::now()))->toBe('You\'ve never started it.');
});

it('never mentions social or the mismatch penalty', function () {
    $ranked = explainRanked(['mismatch' => 0.9, 'freshness' => 1.0], [], [], id: 5);

    expect((new Explainer)->explain($ranked, Facts::now()))->toBe('You\'ve never started it.');
});

it('falls back to a plain sentence when no reason is strong enough', function () {
    $ranked = explainRanked(['taste' => 0.0, 'momentum' => 0.0, 'freshness' => 0.0], [], [], id: 5, minutes: 600);

    expect((new Explainer)->explain($ranked, Facts::now()))->toBe('It is one of the better fits in your library right now.');
});

it('describes a game nearly finished and a game tried once', function () {
    $nearly = explainRanked(['fit' => 0.95], [], [], id: 3, minutes: 570, ttbHours: 10);
    $tried = explainRanked(['freshness' => 0.5], [], [], id: 3, minutes: 30, lastPlayed: Facts::daysAgo(400));

    expect((new Explainer)->explain($nearly, Facts::now()))->toBe('It\'s less than an hour from the end.')
        ->and((new Explainer)->explain($tried, Facts::now()))->toBe('You tried it once and never went back.');
});
