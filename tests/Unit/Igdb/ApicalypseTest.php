<?php

use App\Igdb\Apicalypse;

it('builds numeric and quoted id lists', function () {
    expect(Apicalypse::idList([1, 22, 333]))->toBe('(1,22,333)')
        ->and(Apicalypse::quotedIdList([620, 413150]))->toBe('("620","413150")');
});

it('ignores array keys', function () {
    expect(Apicalypse::idList(['a' => 4, 'b' => 5]))->toBe('(4,5)');
});

it('rejects anything that is not a positive int', function (mixed $bad) {
    expect(fn () => Apicalypse::idList([1, $bad]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Apicalypse::quotedIdList([1, $bad]))->toThrow(InvalidArgumentException::class);
})->with([
    'zero' => [0],
    'negative' => [-1],
    'numeric string' => ['5'],
    'injection string' => ['1); fields *; where (1'],
    'float' => [2.0],
    'null' => [null],
    'bool' => [true],
    'array' => [[1]],
]);

it('rejects an empty list', function () {
    expect(fn () => Apicalypse::idList([]))->toThrow(InvalidArgumentException::class);
});
