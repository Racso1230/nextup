<?php

use App\Recommender\Cosine;

it('is 1 for identical vectors', function () {
    expect(Cosine::similarity(['a' => 1.0, 'b' => 2.0], ['a' => 1.0, 'b' => 2.0]))->toEqualWithDelta(1.0, 1e-9);
});

it('ignores magnitude', function () {
    // (2,4) is (1,2) scaled: the angle is the same.
    expect(Cosine::similarity(['a' => 1.0, 'b' => 2.0], ['a' => 2.0, 'b' => 4.0]))->toEqualWithDelta(1.0, 1e-9);
});

it('is 0 for vectors with no shared keys', function () {
    expect(Cosine::similarity(['a' => 1.0], ['b' => 1.0]))->toEqualWithDelta(0.0, 1e-9);
});

it('is 0 when either vector is empty', function () {
    expect(Cosine::similarity([], ['a' => 1.0]))->toBe(0.0)
        ->and(Cosine::similarity(['a' => 1.0], []))->toBe(0.0)
        ->and(Cosine::similarity([], []))->toBe(0.0);
});

it('is 0 when either vector is all zeros', function () {
    expect(Cosine::similarity(['a' => 0.0, 'b' => 0.0], ['a' => 1.0]))->toBe(0.0)
        ->and(Cosine::similarity(['a' => 1.0], ['a' => 0.0]))->toBe(0.0);
});

it('handles partial overlap', function () {
    // a = (x:1, y:1), b = (y:1, z:1): dot = 1, |a| = |b| = sqrt(2), so 1 / 2 = 0.5
    expect(Cosine::similarity(['x' => 1.0, 'y' => 1.0], ['y' => 1.0, 'z' => 1.0]))->toEqualWithDelta(0.5, 1e-9);
});

it('computes a non-trivial value by hand', function () {
    // a = (3, 4), b = (4, 3): dot = 24, |a| = |b| = 5, so 24 / 25 = 0.96
    expect(Cosine::similarity(['x' => 3.0, 'y' => 4.0], ['x' => 4.0, 'y' => 3.0]))->toEqualWithDelta(0.96, 1e-9);
});

it('is symmetric', function () {
    $a = ['x' => 1.0, 'y' => 2.0, 'z' => 0.5];
    $b = ['y' => 3.0, 'z' => 1.0, 'w' => 2.0];

    expect(Cosine::similarity($a, $b))->toEqualWithDelta(Cosine::similarity($b, $a), 1e-12);
});
