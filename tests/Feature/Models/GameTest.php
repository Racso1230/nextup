<?php

use App\Enums\MatchStatus;
use App\Enums\TagType;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Tag;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

test('steam_appid is unique', function () {
    Game::factory()->create(['steam_appid' => 730]);

    expect(fn () => Game::factory()->create(['steam_appid' => 730]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('igdb_id is unique', function () {
    Game::factory()->create(['igdb_id' => 1942]);

    expect(fn () => Game::factory()->create(['igdb_id' => 1942]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('match_status defaults to pending', function () {
    $game = Game::query()->create(['steam_appid' => 10, 'name' => 'Counter-Strike'])->fresh();

    expect($game->match_status)->toBe(MatchStatus::Pending)
        ->and($game->match_attempts)->toBe(0)
        ->and($game->igdb_id)->toBeNull();
});

test('similar_igdb_ids round-trips as an array', function () {
    $game = Game::factory()->create(['similar_igdb_ids' => [12, 34, 56]]);

    expect($game->fresh()->similar_igdb_ids)->toBe([12, 34, 56]);
});

test('an unknown time to beat stays null', function () {
    $game = Game::factory()->matched()->create([
        'ttb_hastily' => 7200,
        'ttb_normally' => null,
        'ttb_completely' => null,
    ])->fresh();

    expect($game->ttb_hastily)->toBe(7200)
        ->and($game->ttb_normally)->toBeNull()
        ->and($game->ttb_completely)->toBeNull();
});

test('the matched scope returns only matched games', function () {
    $matched = Game::factory()->matched()->create();
    Game::factory()->unmatched()->create();
    Game::factory()->create();
    Game::factory()->create(['match_status' => MatchStatus::Retrying]);

    expect(Game::query()->matched()->pluck('id')->all())->toBe([$matched->id]);
});

test('the matched state fills the igdb columns', function () {
    $game = Game::factory()->matched()->create()->fresh();

    expect($game->match_status)->toBe(MatchStatus::Matched)
        ->and($game->igdb_id)->toBeInt()
        ->and($game->slug)->toBeString()
        ->and($game->first_release_date)->not->toBeNull()
        ->and($game->coverUrl())->toStartWith('https://images.igdb.com/igdb/image/upload/t_cover_big/');
});

test('the unmatched state schedules a retry', function () {
    $game = Game::factory()->unmatched()->create()->fresh();

    expect($game->match_status)->toBe(MatchStatus::Unmatched)
        ->and($game->igdb_id)->toBeNull()
        ->and($game->next_match_at)->not->toBeNull();
});

test('coverUrl is null without a cover and built from the image id with one', function () {
    expect(Game::factory()->make(['cover_image_id' => null])->coverUrl())->toBeNull()
        ->and(Game::factory()->make(['cover_image_id' => 'co1abc'])->coverUrl())
        ->toBe('https://images.igdb.com/igdb/image/upload/t_cover_big/co1abc.jpg')
        ->and(Game::factory()->make(['cover_image_id' => 'co1abc'])->coverUrl('thumb'))
        ->toBe('https://images.igdb.com/igdb/image/upload/t_thumb/co1abc.jpg');
});

test('tags attach to games and back', function () {
    $game = Game::factory()->create();
    $tag = Tag::factory()->genre()->create();

    $game->tags()->attach($tag);

    expect($game->tags)->toHaveCount(1)
        ->and($tag->games->first()?->is($game))->toBeTrue();
});

test('the same tag cannot be attached to a game twice', function () {
    $game = Game::factory()->create();
    $tag = Tag::factory()->create();
    $game->tags()->attach($tag);

    expect(fn () => $game->tags()->attach($tag))->toThrow(UniqueConstraintViolationException::class);
});

test('deleting a game removes its pivot rows and keeps the tags', function () {
    $game = Game::factory()->create();
    $tag = Tag::factory()->create();
    $game->tags()->attach($tag);

    $game->delete();

    expect(DB::table('game_tag')->count())->toBe(0)
        ->and(Tag::query()->whereKey($tag->id)->exists())->toBeTrue();
});

test('deleting a tag removes its pivot rows and keeps the games', function () {
    $game = Game::factory()->create();
    $tag = Tag::factory()->create();
    $game->tags()->attach($tag);

    $tag->delete();

    expect(DB::table('game_tag')->count())->toBe(0)
        ->and(Game::query()->whereKey($game->id)->exists())->toBeTrue();
});

test('tag type is cast to the enum', function () {
    expect(Tag::factory()->gameMode()->create()->fresh()->type)->toBe(TagType::GameMode)
        ->and(Tag::factory()->keyword()->make()->type)->toBe(TagType::Keyword);
});

test('a tag igdb_id is unique within a type', function () {
    Tag::factory()->genre()->create(['igdb_id' => 5]);

    expect(fn () => Tag::factory()->genre()->create(['igdb_id' => 5]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the same igdb_id is allowed under a different type', function () {
    Tag::factory()->genre()->create(['igdb_id' => 5]);
    Tag::factory()->theme()->create(['igdb_id' => 5]);

    expect(Tag::query()->where('igdb_id', 5)->count())->toBe(2);
});

test('a game referenced by a library entry still cannot be deleted with tags attached', function () {
    $game = Game::factory()->create();
    $game->tags()->attach(Tag::factory()->create());
    LibraryEntry::factory()->create(['game_id' => $game->id]);

    expect(fn () => $game->delete())->toThrow(QueryException::class);
});
