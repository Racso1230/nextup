<?php

use App\Jobs\MatchGames;

it('backs off 1, 3, 7 and 14 days, then 30 days for every later attempt', function () {
    expect(array_map(MatchGames::backoffDays(...), [1, 2, 3, 4, 5, 6, 50]))
        ->toBe([1, 3, 7, 14, 30, 30, 30]);
});
