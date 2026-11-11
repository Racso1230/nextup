<?php

/*
|--------------------------------------------------------------------------
| Recommender weights
|--------------------------------------------------------------------------
|
| How much each signal counts in the score (docs/recommender.md). Mismatch is a penalty: its weight is
| subtracted. Starting values from PLAN.md; Phase 5 tunes them against the evaluation, and every change is
| recorded in docs/decisions.md.
|
*/

return [
    'weights' => [
        'taste' => 0.35,
        'momentum' => 0.20,
        'fit' => 0.15,
        'social' => 0.10,
        'freshness' => 0.10,
        'mismatch' => 0.10,
    ],
];
