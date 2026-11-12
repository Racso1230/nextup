<?php

use App\Enums\FeedbackKind;
use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use App\Enums\MatchStatus;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\PickFeedback;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 */
function addEntry(User $user, string $name, array $attributes = []): LibraryEntry
{
    return LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => Game::factory()->create(['name' => $name])->id,
        ...$attributes,
    ]);
}

/**
 * The game names on the page, in order.
 *
 * @param  array<string, mixed>  $params
 * @return list<string>
 */
function libraryNames(User $user, array $params = []): array
{
    /** @var list<array{name: string}> $rows */
    $rows = test()->actingAs($user)->get(route('library', $params))->inertiaProps('entries.data');

    return array_column($rows, 'name');
}

test('guests are redirected to the login page', function () {
    $this->get(route('library'))->assertRedirect(route('login'));
});

test('it renders the library with its props', function () {
    $user = User::factory()->create([
        'import_status' => ImportStatus::Done,
        'imported_at' => '2026-01-02 03:04:05',
    ]);
    $game = Game::factory()->create(['name' => 'Hades', 'steam_appid' => 1145360, 'icon_hash' => 'abc']);
    $entry = LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'playtime_minutes' => 600,
        'playtime_2weeks_minutes' => 60,
        'last_played_at' => '2026-01-01 10:00:00',
    ]);

    $this->actingAs($user)->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library')
            ->where('entries.data.0', [
                'id' => $entry->id,
                'name' => 'Hades',
                'app_id' => 1145360,
                'icon_url' => 'https://media.steampowered.com/steamcommunity/public/images/apps/1145360/abc.jpg',
                'cover_url' => null,
                'tags' => [],
                'match_status' => 'pending',
                'playtime_minutes' => 600,
                'last_played_at' => '2026-01-01T10:00:00+00:00',
                'status' => 'recent',
                'hidden' => false,
            ])
            ->where('entries.current_page', 1)
            ->where('entries.last_page', 1)
            ->where('filters', ['sort' => 'last_played', 'direction' => 'desc', 'status' => null, 'q' => null])
            ->where('status_counts', ['all' => 1, 'unplayed' => 0, 'recent' => 1, 'played' => 0])
            ->where('total', 1)
            ->where('import.status', 'done')
            ->where('import.visibility', 'public')
            ->where('import.imported_at', '2026-01-02T03:04:05+00:00')
            ->where('privacy', null));
});

test('a game without an icon hash has no icon url', function () {
    $user = User::factory()->create();
    LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => Game::factory()->create(['icon_hash' => null])->id,
    ]);

    $this->actingAs($user)->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.icon_url', null));
});

test('only your own entries appear', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    addEntry($me, 'Mine');
    addEntry($other, 'Theirs');
    addEntry($other, 'Theirs too');

    $this->actingAs($me)->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 1)
            ->has('entries.data', 1)
            ->where('entries.data.0.name', 'Mine'));
});

test('total ignores the filters', function () {
    $user = User::factory()->create();
    addEntry($user, 'Alpha');
    addEntry($user, 'Beta');

    $this->actingAs($user)->get(route('library', ['q' => 'alpha']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 2)->has('entries.data', 1));
});

test('it sorts by name', function () {
    $user = User::factory()->create();
    addEntry($user, 'banana');
    addEntry($user, 'Cherry');
    addEntry($user, 'Apple');

    expect(libraryNames($user, ['sort' => 'name']))->toBe(['Apple', 'banana', 'Cherry'])
        ->and(libraryNames($user, ['sort' => 'name', 'direction' => 'desc']))->toBe(['Cherry', 'banana', 'Apple']);
});

test('it sorts by playtime', function () {
    $user = User::factory()->create();
    addEntry($user, 'Mid', ['playtime_minutes' => 100]);
    addEntry($user, 'High', ['playtime_minutes' => 900]);
    addEntry($user, 'Low', ['playtime_minutes' => 5]);

    expect(libraryNames($user, ['sort' => 'playtime']))->toBe(['High', 'Mid', 'Low'])
        ->and(libraryNames($user, ['sort' => 'playtime', 'direction' => 'asc']))->toBe(['Low', 'Mid', 'High']);
});

test('last played sorts newest first by default and puts never played last both ways', function () {
    $user = User::factory()->create();
    addEntry($user, 'Old', ['last_played_at' => '2024-01-01 00:00:00']);
    addEntry($user, 'Never', ['playtime_minutes' => 0, 'last_played_at' => null]);
    addEntry($user, 'New', ['last_played_at' => '2026-01-01 00:00:00']);

    expect(libraryNames($user))->toBe(['New', 'Old', 'Never'])
        ->and(libraryNames($user, ['sort' => 'last_played', 'direction' => 'asc']))->toBe(['Old', 'New', 'Never']);
});

test('ties break on game name', function () {
    $user = User::factory()->create();
    addEntry($user, 'Zed', ['playtime_minutes' => 10]);
    addEntry($user, 'Abe', ['playtime_minutes' => 10]);

    expect(libraryNames($user, ['sort' => 'playtime']))->toBe(['Abe', 'Zed']);
});

test('the status filter returns only matching entries', function (string $status, string $expected) {
    $user = User::factory()->create();
    addEntry($user, 'U', ['playtime_minutes' => 0, 'playtime_2weeks_minutes' => null, 'last_played_at' => null]);
    addEntry($user, 'P', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => null]);
    addEntry($user, 'R', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => 20]);

    expect(libraryNames($user, ['status' => $status]))->toBe([$expected]);

    $this->actingAs($user)->get(route('library', ['status' => $status]))
        ->assertInertia(fn (Assert $page) => $page->where('filters.status', $status)->where('total', 3));
})->with([
    'unplayed' => ['unplayed', 'U'],
    'played' => ['played', 'P'],
    'recent' => ['recent', 'R'],
]);

test('status counts cover the whole library and ignore the status filter', function () {
    $user = User::factory()->create();
    addEntry($user, 'U1', ['playtime_minutes' => 0, 'playtime_2weeks_minutes' => null, 'last_played_at' => null]);
    addEntry($user, 'U2', ['playtime_minutes' => 0, 'playtime_2weeks_minutes' => null, 'last_played_at' => null]);
    addEntry($user, 'P', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => null]);
    addEntry($user, 'R', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => 20]);
    addEntry(User::factory()->create(), 'Someone else');

    $expected = ['all' => 4, 'unplayed' => 2, 'recent' => 1, 'played' => 1];

    $this->actingAs($user)->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page->where('status_counts', $expected));
    $this->actingAs($user)->get(route('library', ['status' => 'recent']))
        ->assertInertia(fn (Assert $page) => $page->where('status_counts', $expected)->has('entries.data', 1));
});

test('status counts respect the search', function () {
    $user = User::factory()->create();
    addEntry($user, 'Hades', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => 20]);
    addEntry($user, 'Hades II', ['playtime_minutes' => 0, 'playtime_2weeks_minutes' => null, 'last_played_at' => null]);
    addEntry($user, 'Stardew', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => null]);

    $this->actingAs($user)->get(route('library', ['q' => 'hades']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status_counts', ['all' => 2, 'unplayed' => 1, 'recent' => 1, 'played' => 0])
            ->where('total', 3));
});

test('it sorts by status: unplayed, recent, played, and reversed', function () {
    $user = User::factory()->create();
    addEntry($user, 'B played', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => null]);
    addEntry($user, 'A played', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => null]);
    addEntry($user, 'Recent', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => 20]);
    addEntry($user, 'Unplayed', ['playtime_minutes' => 0, 'playtime_2weeks_minutes' => null, 'last_played_at' => null]);

    expect(libraryNames($user, ['sort' => 'status']))->toBe(['Unplayed', 'Recent', 'A played', 'B played'])
        ->and(libraryNames($user, ['sort' => 'status', 'direction' => 'desc']))->toBe(['A played', 'B played', 'Recent', 'Unplayed']);

    $this->actingAs($user)->get(route('library', ['sort' => 'status']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'status')->where('filters.direction', 'asc'));
});

test('the status SQL expression matches the derived status accessor', function () {
    $user = User::factory()->create();
    $entries = [
        addEntry($user, 'U', ['playtime_minutes' => 0, 'playtime_2weeks_minutes' => null, 'last_played_at' => null]),
        addEntry($user, 'R', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => 20]),
        addEntry($user, 'P', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => null]),
        addEntry($user, 'P0', ['playtime_minutes' => 50, 'playtime_2weeks_minutes' => 0]),
    ];

    foreach ($entries as $entry) {
        $sql = LibraryEntry::query()->whereKey($entry->id)->selectRaw(LibraryEntry::STATUS_SQL.' as s')->value('s');
        expect($sql)->toBe($entry->status->value);
    }
});

test('search is case-insensitive', function () {
    $user = User::factory()->create();
    addEntry($user, 'Stardew Valley');
    addEntry($user, 'Hades');

    expect(libraryNames($user, ['q' => 'STARDEW']))->toBe(['Stardew Valley']);
    $this->actingAs($user)->get(route('library', ['q' => 'stardew']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', 'stardew'));
});

test('search treats percent and underscore literally', function () {
    $user = User::factory()->create();
    addEntry($user, '100% Orange Juice');
    addEntry($user, 'Hades');
    addEntry($user, 'A_B');
    addEntry($user, 'AXB');

    expect(libraryNames($user, ['q' => '%']))->toBe(['100% Orange Juice'])
        ->and(libraryNames($user, ['q' => 'a_b']))->toBe(['A_B']);
});

test('invalid params fall back to the defaults', function () {
    $user = User::factory()->create();
    addEntry($user, 'Hades');

    $this->actingAs($user)->get(route('library', [
        'sort' => 'drop table',
        'direction' => 'sideways',
        'status' => 'nonsense',
        'q' => str_repeat('a', 101),
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['sort' => 'last_played', 'direction' => 'desc', 'status' => null, 'q' => null])
        ->has('entries.data', 1));

    $this->actingAs($user)->get('/library?sort[]=name&q[]=x&status[]=played')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'last_played')->where('filters.q', null));
});

test('name sort defaults to ascending', function () {
    $this->actingAs(User::factory()->create())->get(route('library', ['sort' => 'name']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.direction', 'asc'));
});

test('it paginates 50 per page and keeps the query string', function () {
    $user = User::factory()->create();
    LibraryEntry::factory()->count(60)->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('library', ['sort' => 'name']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 50)
            ->where('entries.last_page', 2)
            ->where('total', 60)
            ->where('entries.next_page_url', fn (string $url) => str_contains($url, 'sort=name')));

    $this->actingAs($user)->get(route('library', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 10)->where('entries.current_page', 2));
});

test('privacy steps appear only for a private library', function () {
    $private = User::factory()->privateLibrary()->create(['import_status' => ImportStatus::Private]);

    $this->actingAs($private)->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('import.visibility', LibraryVisibility::Private->value)
            ->where('import.status', 'private')
            ->where('privacy.settings_url', 'https://steamcommunity.com/my/edit/settings')
            ->has('privacy.steps', 4));

    $this->actingAs(User::factory()->create())->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page->where('privacy', null));
});

test('cover_url uses the IGDB small cover pattern and is null without an image id', function () {
    $user = User::factory()->create();
    addEntry($user, 'Covered')->game->update(['cover_image_id' => 'co1abc']);
    addEntry($user, 'Bare');

    $this->actingAs($user)->get(route('library', ['sort' => 'name']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.cover_url', null)
            ->where('entries.data.1.cover_url', 'https://images.igdb.com/igdb/image/upload/t_cover_small/co1abc.jpg'));
});

test('tags are capped at two, genres first, in name order', function () {
    $user = User::factory()->create();
    $game = addEntry($user, 'Tagged')->game;
    $game->tags()->attach([
        Tag::factory()->perspective()->create(['name' => 'Third person'])->id,
        Tag::factory()->keyword()->create(['name' => 'Aardvark'])->id,
        Tag::factory()->gameMode()->create(['name' => 'Single player'])->id,
        Tag::factory()->theme()->create(['name' => 'Fantasy'])->id,
        Tag::factory()->genre()->create(['name' => 'Role-playing (RPG)'])->id,
        Tag::factory()->genre()->create(['name' => 'Adventure'])->id,
    ]);

    $this->actingAs($user)->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.tags', [
                ['name' => 'Adventure', 'type' => 'genre'],
                ['name' => 'Role-playing (RPG)', 'type' => 'genre'],
            ])
            ->missing('entries.data.0.tag_overflow'));
});

test('themes fill the tag slots that genres leave free', function () {
    $user = User::factory()->create();
    $game = addEntry($user, 'One genre')->game;
    $game->tags()->attach([
        Tag::factory()->theme()->create(['name' => 'Fantasy'])->id,
        Tag::factory()->theme()->create(['name' => 'Horror'])->id,
        Tag::factory()->genre()->create(['name' => 'Adventure'])->id,
        Tag::factory()->gameMode()->create(['name' => 'Co-op'])->id,
    ]);

    $this->actingAs($user)->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.tags', [
            ['name' => 'Adventure', 'type' => 'genre'],
            ['name' => 'Fantasy', 'type' => 'theme'],
        ]));
});

test('keywords and game modes alone produce no tags', function () {
    $user = User::factory()->create();
    $game = addEntry($user, 'Keywords')->game;
    $game->tags()->attach(Tag::factory()->keyword()->count(6)->create()->modelKeys());
    $game->tags()->attach(Tag::factory()->gameMode()->create()->id);
    $game->tags()->attach(Tag::factory()->perspective()->create()->id);

    $this->actingAs($user)->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page->where('entries.data.0.tags', [])->missing('entries.data.0.tag_overflow'));
});

test('match_status is present and an unmatched game still appears', function () {
    $user = User::factory()->create();
    LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => Game::factory()->unmatched()->create()->id,
    ]);

    $this->actingAs($user)->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 1)
            ->where('entries.data.0.match_status', MatchStatus::Unmatched->value));
});

test('igdb internals are not exposed', function () {
    $user = User::factory()->create();
    LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => Game::factory()->matched()->create()->id,
    ]);

    $props = $this->actingAs($user)->get(route('library'))->inertiaProps('entries.data.0');

    expect(array_keys($props))->not->toContain('igdb_id')
        ->and(json_encode($props))->not->toContain('igdb_id');
});

test('fifty rows with tags use a bounded number of queries', function () {
    $user = User::factory()->create();
    $tags = Tag::factory()->genre()->count(6)->create();

    foreach (Game::factory()->matched()->count(50)->create() as $game) {
        LibraryEntry::factory()->create(['user_id' => $user->id, 'game_id' => $game->id]);
        $game->tags()->attach($tags->modelKeys());
    }

    DB::enableQueryLog();
    $this->actingAs($user)->get(route('library'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThan(15);
});

describe('never-suggested games', function () {
    function neverFor(User $user, LibraryEntry $entry, ?FeedbackKind $then = null): void
    {
        PickFeedback::factory()->ofKind(FeedbackKind::Never)->create([
            'user_id' => $user->id, 'library_entry_id' => $entry->id, 'created_at' => now()->subDay(),
        ]);

        if ($then !== null) {
            PickFeedback::factory()->ofKind($then)->create(['user_id' => $user->id, 'library_entry_id' => $entry->id]);
        }
    }

    it('flags rows whose latest feedback is never', function () {
        $user = User::factory()->create();
        $never = addEntry($user, 'Never');
        $undone = addEntry($user, 'Later changed its mind');
        addEntry($user, 'Untouched');
        neverFor($user, $never);
        neverFor($user, $undone, FeedbackKind::Playing);

        $rows = $this->actingAs($user)->get(route('library', ['sort' => 'name']))->inertiaProps('entries.data');

        expect(array_column($rows, 'hidden', 'name'))->toBe(['Later changed its mind' => false, 'Never' => true, 'Untouched' => false]);
    });

    it('filters to never-suggested games with status=hidden', function () {
        $user = User::factory()->create();
        neverFor($user, addEntry($user, 'Hidden one'));
        addEntry($user, 'Shown');
        neverFor(User::factory()->create(), addEntry(User::factory()->create(), 'Someone else\'s'));

        expect(libraryNames($user, ['status' => 'hidden']))->toBe(['Hidden one']);

        $this->actingAs($user)->get(route('library', ['status' => 'hidden']))
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'hidden')->where('total', 2));
    });

    it('counts hidden games separately and leaves the status counts alone', function () {
        $user = User::factory()->create();
        neverFor($user, addEntry($user, 'Hidden played', ['playtime_minutes' => 60, 'playtime_2weeks_minutes' => null]));
        addEntry($user, 'Plain played', ['playtime_minutes' => 60, 'playtime_2weeks_minutes' => null]);

        $this->actingAs($user)->get(route('library'))->assertInertia(fn (Assert $page) => $page
            ->where('hidden_count', 1)
            ->where('status_counts', ['all' => 2, 'unplayed' => 0, 'recent' => 0, 'played' => 2]));
        $this->actingAs($user)->get(route('library', ['q' => 'plain']))
            ->assertInertia(fn (Assert $page) => $page->where('hidden_count', 0));
    });

    it('is zero for a user with no feedback', function () {
        $user = User::factory()->create();
        addEntry($user, 'Plain');

        $this->actingAs($user)->get(route('library'))->assertInertia(fn (Assert $page) => $page->where('hidden_count', 0));
    });
});
