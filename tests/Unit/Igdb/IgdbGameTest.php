<?php

use App\Enums\TagType;
use App\Igdb\IgdbGame;
use App\Igdb\IgdbTag;
use Tests\Support\IgdbFixtures;

it('parses a full game from the fixture', function () {
    $game = IgdbGame::fromArray(IgdbFixtures::load('games')[1]);

    expect($game)->not->toBeNull()
        ->and($game->id)->toBe(113112)
        ->and($game->name)->toBe('Hades')
        ->and($game->slug)->toBe('hades--1')
        ->and($game->coverImageId)->toBe('co39vc')
        ->and($game->firstReleaseDate?->toIso8601String())->toBe('2020-09-24T00:00:00+00:00')
        ->and($game->similarIgdbIds)->toBe([26165, 119133])
        ->and($game->tags)->toHaveCount(8);

    $genres = array_values(array_filter($game->tags, fn (IgdbTag $t) => $t->type === TagType::Genre));

    expect($genres[0])->toEqual(new IgdbTag(TagType::Genre, 12, 'Role-playing (RPG)'));
});

it('maps every tag field to its tag type', function () {
    $game = IgdbGame::fromArray([
        'id' => 1,
        'name' => 'X',
        'genres' => [['id' => 1, 'name' => 'g']],
        'themes' => [['id' => 1, 'name' => 't']],
        'game_modes' => [['id' => 1, 'name' => 'm']],
        'player_perspectives' => [['id' => 1, 'name' => 'p']],
        'keywords' => [['id' => 1, 'name' => 'k']],
    ]);

    expect(array_map(fn (IgdbTag $t) => $t->type, $game->tags))->toBe([
        TagType::Genre, TagType::Theme, TagType::GameMode, TagType::Perspective, TagType::Keyword,
    ]);
});

it('degrades optional fields to null or empty', function () {
    $game = IgdbGame::fromArray(['id' => 5, 'name' => 'Bare']);

    expect($game->slug)->toBeNull()
        ->and($game->firstReleaseDate)->toBeNull()
        ->and($game->coverImageId)->toBeNull()
        ->and($game->similarIgdbIds)->toBe([])
        ->and($game->tags)->toBe([]);
});

it('returns null without a usable id or name', function (array $data) {
    expect(IgdbGame::fromArray($data))->toBeNull();
})->with([
    'empty' => [[]],
    'no id' => [['name' => 'X']],
    'string id' => [['id' => '5', 'name' => 'X']],
    'zero id' => [['id' => 0, 'name' => 'X']],
    'no name' => [['id' => 5]],
    'empty name' => [['id' => 5, 'name' => '']],
    'non-string name' => [['id' => 5, 'name' => ['x']]],
]);

it('skips malformed tags and duplicates', function () {
    $game = IgdbGame::fromArray([
        'id' => 1,
        'name' => 'X',
        'genres' => [
            12,
            'junk',
            ['id' => 5],
            ['name' => 'No id'],
            ['id' => 0, 'name' => 'Zero'],
            ['id' => 7, 'name' => ''],
            ['id' => 9, 'name' => 'Good'],
            ['id' => 9, 'name' => 'Good again'],
        ],
        'themes' => 'not a list',
    ]);

    expect($game->tags)->toEqual([new IgdbTag(TagType::Genre, 9, 'Good')]);
});

it('keeps the same igdb id under different tag types', function () {
    $game = IgdbGame::fromArray([
        'id' => 1,
        'name' => 'X',
        'genres' => [['id' => 1, 'name' => 'Point-and-click']],
        'themes' => [['id' => 1, 'name' => 'Action']],
    ]);

    expect($game->tags)->toHaveCount(2);
});

it('ignores unusable covers', function (mixed $cover) {
    expect(IgdbGame::fromArray(['id' => 1, 'name' => 'X', 'cover' => $cover])->coverImageId)->toBeNull();
})->with([
    'bare id' => [89386],
    'no image id' => [['id' => 1]],
    'empty image id' => [['image_id' => '']],
    'path traversal' => [['image_id' => '../x']],
    'int image id' => [['image_id' => 5]],
]);

it('keeps only positive int similar games, without duplicates', function () {
    $game = IgdbGame::fromArray([
        'id' => 1,
        'name' => 'X',
        'similar_games' => [3, '4', 0, -1, 3, ['id' => 9], 8],
    ]);

    expect($game->similarIgdbIds)->toBe([3, 8]);
});

it('ignores a non-int release date', function () {
    expect(IgdbGame::fromArray(['id' => 1, 'name' => 'X', 'first_release_date' => '2020-01-01'])->firstReleaseDate)
        ->toBeNull();
});
