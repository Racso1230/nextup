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

Sign-in is Steam only, so a user is identified by their SteamID64. Phase 2 adds IGDB metadata, `match_status`
and `playtime_snapshots`.

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

Account deletion removes the user, which cascades to their library entries and ends their sessions.
