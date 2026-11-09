<?php

use App\Models\Game;
use Illuminate\Database\UniqueConstraintViolationException;

test('steam_appid is unique', function () {
    Game::factory()->create(['steam_appid' => 730]);

    expect(fn () => Game::factory()->create(['steam_appid' => 730]))
        ->toThrow(UniqueConstraintViolationException::class);
});
