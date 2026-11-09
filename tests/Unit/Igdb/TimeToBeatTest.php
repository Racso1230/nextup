<?php

use App\Igdb\TimeToBeat;

it('reads all three values in seconds', function () {
    $time = TimeToBeat::fromArray(['hastily' => 100, 'normally' => 200, 'completely' => 300]);

    expect($time->hastily)->toBe(100)
        ->and($time->normally)->toBe(200)
        ->and($time->completely)->toBe(300);
});

it('keeps a missing value null, never zero', function () {
    $time = TimeToBeat::fromArray(['normally' => 79200]);

    expect($time->hastily)->toBeNull()
        ->and($time->normally)->toBe(79200)
        ->and($time->completely)->toBeNull();
});

it('treats zero, negative and non-int values as unknown', function () {
    $time = TimeToBeat::fromArray(['hastily' => 0, 'normally' => 50, 'completely' => -5]);

    expect($time->hastily)->toBeNull()
        ->and($time->normally)->toBe(50)
        ->and($time->completely)->toBeNull()
        ->and(TimeToBeat::fromArray(['normally' => '50']))->toBeNull()
        ->and(TimeToBeat::fromArray(['normally' => 1.5]))->toBeNull();
});

it('returns null when nothing is usable', function () {
    expect(TimeToBeat::fromArray([]))->toBeNull()
        ->and(TimeToBeat::fromArray(['hastily' => 0, 'normally' => 0, 'completely' => 0]))->toBeNull();
});
