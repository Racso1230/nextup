<?php

use App\Enums\TagType;
use App\Recommender\Contribution;
use App\Recommender\GameFacts;
use App\Recommender\Params;
use App\Recommender\Ranked;
use App\Recommender\SignalName;
use App\Recommender\TagRef;
use App\Recommender\Weights;

arch('the recommender has no Laravel dependencies')
    ->expect('App\Recommender')
    ->not->toUse(['Illuminate', 'Laravel']);

it('loads weights for every signal from config', function () {
    $weights = Weights::fromArray((require __DIR__.'/../../../config/recommender.php')['weights']);

    expect($weights->for(SignalName::Taste))->toBe(0.35)
        ->and($weights->for(SignalName::Mismatch))->toBe(0.10);
});

it('rejects missing or negative weights', function (array $config) {
    Weights::fromArray($config);
})->throws(InvalidArgumentException::class)->with([
    'missing' => [['taste' => 0.5]],
    'negative' => [['taste' => -0.1, 'momentum' => 0.2, 'fit' => 0.1, 'social' => 0.1, 'freshness' => 0.1, 'mismatch' => 0.1]],
    'non-numeric' => [['taste' => 'high', 'momentum' => 0.2, 'fit' => 0.1, 'social' => 0.1, 'freshness' => 0.1, 'mismatch' => 0.1]],
]);

it('subtracts the mismatch contribution and adds the rest', function () {
    expect(Contribution::of(SignalName::Taste, 0.8, 0.35)->weighted)->toEqualWithDelta(0.28, 1e-9)
        ->and(Contribution::of(SignalName::Mismatch, 0.5, 0.10)->weighted)->toEqualWithDelta(-0.05, 1e-9)
        ->and(Contribution::of(SignalName::Social, null, 0.10)->weighted)->toBe(0.0);
});

it('keys tags by type and id, because IGDB ids are only unique per type', function () {
    expect((new TagRef(TagType::Genre, 12, 'RPG'))->key())->toBe('genre:12')
        ->and((new TagRef(TagType::Theme, 12, 'Fantasy'))->key())->toBe('theme:12');
});

it('weights tag types as the spec says', function () {
    expect(Params::tagTypeWeight(TagType::Genre))->toBe(1.0)
        ->and(Params::tagTypeWeight(TagType::Perspective))->toBe(0.5)
        ->and(Params::tagTypeWeight(TagType::Keyword))->toBe(0.25);
});

it('finds a contribution by signal', function () {
    $game = new GameFacts(1, 10, 'Hades', 0, null, null, null, null, []);
    $ranked = new Ranked($game, 0.3, [Contribution::of(SignalName::Fit, 0.5, 0.15)]);

    expect($ranked->contribution(SignalName::Fit)?->value)->toBe(0.5)
        ->and($ranked->contribution(SignalName::Taste))->toBeNull();
});
