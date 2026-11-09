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

Tables are designed in Phase 1 and documented here when they exist.
