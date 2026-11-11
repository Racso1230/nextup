# Architecture

nextup is a Laravel monolith with an Inertia/Vue front end. Steam supplies the library (owned games, lifetime
playtime, recent play, friends, achievements) and IGDB supplies metadata (genres, themes, modes, time to beat).
Neither API is called while serving a page. All external calls run in queued jobs behind Redis rate limiters,
and the results are stored in Postgres. A nightly job snapshots each game's playtime, because Steam only reports
lifetime totals and play sessions have to be derived from the difference between snapshots. The recommender is
a plain PHP library in `app/Recommender/` with no Laravel imports. It takes plain data in and returns scores and
the contribution of each signal, which the explanation generator turns into a sentence. Controllers only read
from the database and pass props to Vue.

## Data flow

```
 Steam Web API ──┐                         ┌─> playtime_snapshots (nightly)
                 ├─> queued jobs ──> Postgres ─┤
 IGDB (Twitch) ──┘   (Redis queue,             └─> games, library_entries
                      rate limits,                      │
                      cached tokens)                    v
                                              Recommender (plain PHP)
                                                        │ scores + signal contributions
                                                        v
                                     Controller ──> Inertia ──> Vue pages
```

## Where things live

| Concern              | Location                                                                      |
| -------------------- | ----------------------------------------------------------------------------- |
| Scoring engine       | `app/Recommender/` (no framework imports)                                     |
| External API clients | `app/` services, tested with `Http::fake()` and fixtures in `tests/Fixtures/` |
| Background work      | queued jobs on Redis; schedule in `routes/console.php`                        |
| Colour tokens        | `resources/css/app.css` (`@theme`), previewed at `/design/tokens`             |
| Local services       | `compose.yaml` (Postgres 17, Redis 7)                                         |

## Tables

Sign-in is Steam only, so a user is identified by their SteamID64. Phase 2 adds IGDB metadata on `games`,
`tags`, and `playtime_snapshots`.

### users

| Column                     | Type            | Notes                                                                                                                                         |
| -------------------------- | --------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| id                         | bigint PK       |                                                                                                                                               |
| steam_id                   | bigint, unique  | SteamID64. Fits in a signed bigint but not in a JS number, so it is always serialised as a string. Set only from a verified OpenID assertion. |
| persona_name               | string          | From `GetPlayerSummaries`, refreshed on login and import                                                                                      |
| avatar_url                 | string, null    | Steam CDN URL, hotlinked                                                                                                                      |
| profile_url                | string, null    |                                                                                                                                               |
| library_visibility         | string          | `public`, `private` or `unknown` (default). Backed by a PHP enum.                                                                             |
| import_status              | string          | `idle` (default), `queued`, `running`, `done`, `private` or `failed`. Backed by a PHP enum.                                                   |
| imported_at                | timestamp, null | Last successful import                                                                                                                        |
| remember_token, timestamps |                 |                                                                                                                                               |

### games

A shared catalogue keyed by Steam appid. Rows survive account deletion, because they hold no personal data.

| Column      | Type            | Notes                                        |
| ----------- | --------------- | -------------------------------------------- |
| id          | bigint PK       |                                              |
| steam_appid | integer, unique |                                              |
| name        | string          | Untrusted text from Steam, escaped on output |
| icon_hash   | string, null    | `img_icon_url` from `GetOwnedGames`          |
| timestamps  |                 |                                              |

### library_entries

One row per game a user owns. Re-imports upsert on `(user_id, game_id)`.

| Column                  | Type                         | Notes                                                                    |
| ----------------------- | ---------------------------- | ------------------------------------------------------------------------ |
| id                      | bigint PK                    |                                                                          |
| user_id                 | FK users, cascade on delete  |                                                                          |
| game_id                 | FK games, restrict on delete |                                                                          |
| playtime_minutes        | integer                      | `playtime_forever` (Steam reports minutes)                               |
| playtime_2weeks_minutes | integer, null                | `playtime_2weeks`. Steam omits it when there was no recent play.         |
| last_played_at          | timestamp, null              | `rtime_last_played` (Unix time). 0 means never, which is stored as null. |
| timestamps              |                              |                                                                          |

Indexes: unique `(user_id, game_id)`, and `(user_id, last_played_at)` for the library page's default sort.

Status (`unplayed`, `played`, `recent`) is derived from playtime, not stored. A user-set status arrives with
feedback in Phase 4.

Account deletion removes the user, which cascades to their library entries, their snapshots and their sessions.

### games: IGDB columns (Phase 2)

| Column                                    | Type                   | Notes                                                                                |
| ----------------------------------------- | ---------------------- | ------------------------------------------------------------------------------------ |
| igdb_id                                   | integer, null, indexed | From IGDB `external_games` (Steam source, `uid` = appid)                             |
| slug                                      | string, null           |                                                                                      |
| first_release_date                        | date, null             | IGDB gives a Unix timestamp                                                          |
| cover_image_id                            | string, null           | URL built in one helper: `t_cover_big/<image_id>.jpg`                                |
| ttb_hastily, ttb_normally, ttb_completely | integer, null          | Seconds, from `game_time_to_beats`. Null means unknown and is never treated as zero. |
| similar_igdb_ids                          | json, null             | IGDB ids of similar games, used by the Phase 3 recommender                           |
| match_status                              | string                 | `pending` (default), `matched`, `unmatched` or `retrying`. Backed by a PHP enum.     |
| match_attempts                            | integer, default 0     |                                                                                      |
| next_match_at                             | timestamp, null        | When an unmatched game is next retried (backoff)                                     |
| enriched_at                               | timestamp, null        | Last successful metadata fetch                                                       |

Index on `(match_status, next_match_at)` for the retry schedule. Only `matched` games are candidates for
recommendations. Everything else stays visible in the library but is never ranked.

### tags and game_tag

One table for every IGDB tag kind, so the recommender can build one taste vector over all of them.

| Column     | Type      | Notes                                                                            |
| ---------- | --------- | -------------------------------------------------------------------------------- |
| id         | bigint PK |                                                                                  |
| type       | string    | `genre`, `theme`, `game_mode`, `perspective` or `keyword`. Backed by a PHP enum. |
| igdb_id    | integer   | The id within that IGDB endpoint, so it is only unique per type                  |
| name       | string    | Untrusted text, escaped on output                                                |
| timestamps |           |                                                                                  |

Unique `(type, igdb_id)`. The `game_tag` pivot has `game_id` and `tag_id` (both FKs cascading on delete) and
a primary key `(game_id, tag_id)`.

### playtime_snapshots

Steam only reports lifetime totals, so play sessions are recovered from the differences between snapshots.
History cannot be fetched later, so collection starts in Phase 2.

| Column           | Type                                  | Notes                                      |
| ---------------- | ------------------------------------- | ------------------------------------------ |
| id               | bigint PK                             |                                            |
| library_entry_id | FK library_entries, cascade on delete |                                            |
| captured_on      | date                                  | UTC date of the import that saw this total |
| minutes_total    | integer                               | `playtime_forever` at that moment          |
| timestamps       |                                       |                                            |

Unique `(library_entry_id, captured_on)`, so re-running an import on the same day updates the row rather than
adding one. A row is written only on the first import of an entry and when `minutes_total` differs from the
entry's latest snapshot. A missing day means "unchanged". The total as of any date is the latest row on or
before it.

### recommendation_runs and recommendation_picks (Phase 3)

Every ranking is stored so a pick can be audited later ("why did it say that?") and so Phase 5 can compare runs.

**recommendation_runs**: `id`, `user_id` (FK users, cascade on delete), `weights` (json: the weights used),
`candidate_count` (integer), `created_at`.

**recommendation_picks**: `id`, `recommendation_run_id` (FK, cascade on delete), `library_entry_id` (FK
library_entries, cascade on delete), `rank` (integer, 1 = top pick), `score` (double), `contributions` (json:
per signal `{value, weight, weighted}`), `explanation` (text), with unique `(recommendation_run_id, rank)`.

Only the top 20 picks of a run are stored. A run is reused while it is newer than the user's last import and
less than 6 hours old; otherwise viewing the page computes a fresh one. Ranking reads only the database (no
Steam or IGDB calls) and takes milliseconds, so it runs inside the request.
