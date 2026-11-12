# Recommender v1

nextup ranks the games a user **already owns** by how likely they are to play each one next, and explains every
pick in plain English. This is the spec the code in `app/Recommender/` implements. Weights live in
`config/recommender.php`; the signals and their formulas are design decisions Oscar owns, so changing them means
changing this file first.

## What it is, and what it is not

- **Personalised ranking from one user's own history.** Every signal is computed from that user's library,
  playtime, snapshots and IGDB tags. Nothing is learned from other users.
- **Not collaborative filtering.** There is no "people like you also played", because there is no population of
  users to learn from, and a portfolio demo should not pretend otherwise.
- **Not a store recommender.** It never suggests games the user doesn't own.
- **Explainable by construction.** The score is a weighted sum of a few named signals, each in 0..1, so the top
  contributions are the explanation. No black box to rationalise after the fact.

## Inputs (all already stored)

| Data                                             | Source                               | Notes                                                      |
| ------------------------------------------------ | ------------------------------------ | ---------------------------------------------------------- |
| Playtime (minutes), 2-week playtime, last played | `library_entries` (Steam)            | Lifetime totals only                                       |
| Playtime history                                 | `playtime_snapshots`                 | Change-only; sparse until weeks of nightly imports exist   |
| Tags                                             | `tags` + `game_tag` (IGDB)           | genre, theme, game_mode, perspective, keyword              |
| Time to beat (`normally`)                        | `games.ttb_normally` (IGDB, seconds) | About 75% of a real library has one; missing means unknown |
| IGDB id                                          | `games.igdb_id`                      | Several Steam apps can share one                           |

Steam does **not** expose when a game was bought, sessions, or completion. The signals below work around that and
say so where it matters.

## Candidates

1. Every library entry whose game is `matched` (unmatched games are never ranked).
2. **Collapse by `igdb_id`**: two Steam apps that are the same IGDB game become one candidate, using the entry with
   the most playtime for the user-specific inputs.
3. **Exclude likely-finished games**: `ttb_normally` is known, playtime is at least 100% of it, and the game has not
   been played in the last 30 days. These still feed the taste vector (they are the strongest evidence of taste),
   they are just not suggested. Games without a time to beat are never excluded this way, which keeps endless
   multiplayer games (no "finish") eligible.

## Engagement (used to build the taste vector, not a ranking signal)

How much a played game "counts" as evidence of taste, in 0..1:

- With a time to beat: `min(1, playtime_seconds / ttb_normally)`. 40 hours on a 10-hour game saturates at 1.
- Without one: `min(1, ln(1 + hours) / ln(1 + 50))`. Log-scaled, saturating at about 50 hours.
- Unplayed games have engagement 0.

## Signals (each 0..1, or null when it cannot be computed)

### Taste match

Cosine similarity between the user's **taste vector** and the candidate's **tag vector**.

- **Tag weights by type**: genre 1.0, theme 1.0, game_mode 0.5, perspective 0.5, keyword 0.25. Keywords carry
  real signal ("roguelike", "open world") but there are about 10 per game and many are noise, so each counts for
  a quarter.
- **IDF over the user's own tagged library**: `idf(t) = ln((1 + N) / (1 + df(t))) + 1`, where N is the number of
  the user's games with tags. Tags on almost every game (Adventure, Action) are down-weighted; distinctive ones
  dominate.
- **Taste vector**: for each played game, add `engagement × type_weight × idf` for each of its tags.
- **Leave-one-out**: when scoring a game the user has played, its own contribution is removed from the taste
  vector first, so a game never matches itself.
- **Tiny libraries**: with fewer than 5 played games the taste vector is weak, so taste is scaled by
  `played_count / 5`. With no played games it is null.

### Momentum

"Started recently and not finished". The larger of:

- `min(1, hours_played_in_last_14_days / 5)`, where recent hours come from snapshot growth over the last 14 days
  when snapshots cover that window, otherwise from Steam's `playtime_2weeks`.
- `exp(-days_since_last_played / 14)`, so last night is about 0.93, a month ago about 0.12, a year ago about 0.

Unplayed games have momentum 0.

### Fits tonight

Phase 3 has no "how long have you got?" input yet (that is Phase 4), so v1 measures **how finishable** a game is:

- Remaining time = `ttb_normally − playtime`, in hours.
- `fit = 1 / (1 + remaining_hours / 10)`: 30 minutes left is about 0.95, 10 hours left is 0.5, 40 hours left
  is 0.2.
- Unknown time to beat is **0.5**, never 0.
- **Played to or past the time to beat is also 0.5**, and the explanation never mentions finishing. Finishing
  is no longer the point, and momentum already surfaces games still being played. (Approved 2026-11-11 after the
  first real run ranked Rocket League, at 137 hours on a short time to beat, as "less than an hour from the
  end".)

Phase 4 replaces the 10-hour constant with the time the user says they have.

### Social

**Phase 4:** when the user's friends list is public, social = `min(1, friends who played the game in the last two
weeks / 3)`. Only aggregate counts per game are stored, never the friends' identities. Private friends list:
null, exactly as v1.

Friends who played the game recently. Friends arrive in Phase 4, so in v1 this is **null** for everyone. A null
signal contributes nothing to the score and never appears in an explanation. A signal that is null for every
candidate can't change the ranking, so this is safe.

### Freshness

A gentle nudge towards games the user owns but has never really started. Steam doesn't give purchase dates, so
"owned for a long time" can't be measured. v1 uses what is known:

- Never played: 1.0.
- Under 1 hour and untouched for 180+ days (tried once, never returned): 0.5.
- Otherwise: 0.

### Mismatch penalty

Similarity to games the user **dropped**, subtracted from the score.

- Dropped = played, under 2 hours **or** under 15% of the time to beat, and not played for 60+ days.
- Dropped vector: the sum of the dropped games' weighted tag vectors (same weights and IDF as taste, unscaled by
  engagement, because a quick drop is the signal).
- `mismatch = cosine(dropped vector, candidate vector)`, with the candidate's own contribution removed if it is
  itself dropped. No dropped games means 0.

## Score

```
score = 0.35·taste + 0.20·momentum + 0.15·fit + 0.10·social + 0.10·fresh − 0.10·mismatch
```

Starting weights from `PLAN.md`, read from `config/recommender.php`. Null signals contribute 0. Phase 5 tunes the
weights against the leave-one-out and time-split evaluations.

Phase 4 adds two terms (below): `+ 0.15·mood − 0.20·snooze`.

## Session context and feedback (Phase 4)

Approved by Oscar on 2026-11-12. The user can tell the page three things about tonight, and react to each pick.

### How long have you got?

Choices: 1 hour, 2 hours, 4 hours, all evening (6 hours). Default: 2 hours.

- **Fit** keeps its formula, but the half-point becomes **5 × the available hours** instead of a fixed 10.
  `fit = 1 / (1 + remaining_hours / (5 × available))`. With 2 hours this is exactly the v1 behaviour; with 1 hour,
  short games win more clearly; with 4 hours, long games are penalised less.
- Unknown time to beat, and played past it, stay neutral at 0.5.

### Mood (chill or intense)

A new **mood** signal, weight 0.15. Unset mood means the signal is null for everyone (no effect).

- Two lists of IGDB tags, by `(type, id)`, in `Params`:
    - **Intense**: genres Fighting, Shooter, Racing, Tactical, Hack and slash, MOBA; themes Action, Horror,
      Thriller, Survival, Warfare; game mode Battle Royale.
    - **Chill**: genres Point-and-click, Music, Puzzle, Simulator, Strategy, Turn-based strategy, Quiz, Pinball,
      Visual novel, Card and board game; themes Comedy, Business, Sandbox, Educational, Kids, Party, 4X.
- `mood = matches in the chosen list / (matches in either list)`. A game with no tags in either list scores a
  **neutral 0.5**, so missing data never counts against it.

### Solo or with friends

A **filter** on IGDB game modes, applied to candidates. Games with no mode data are never hidden.

- **With friends**: hide games that have mode data but none of Multiplayer, Co-operative, Split screen, MMO,
  Battle Royale.
- **Solo**: hide games that have mode data but no Single player mode (multiplayer-only games).
- Unset: no filter.

### Feedback on a pick

| Button            | Effect                                                                                                                                                              |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Playing it**    | Opens Steam. For 3 days the game's momentum is 1.0 (the user told us; snapshots will confirm).                                                                      |
| **Not tonight**   | Hidden for 24 hours. After that a **snooze** penalty (weight 0.20, subtracted) of `exp(−(days − 1) / 2)` fades it back: 1.0 on day 1, 0.37 on day 3, 0.05 on day 7. |
| **Never suggest** | Excluded until undone from the library.                                                                                                                             |

Only the latest feedback per game counts. Feedback newer than the cached run makes the next page view rank
afresh, so a click always visibly changes the list.

## Explanations

`Recommender::rank()` returns, for every candidate, the score and each signal's weighted contribution. The
explanation uses the **top two or three positive contributions**, in plain English:

| Signal    | Sentence fragment                                                                                                                                          |
| --------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Taste     | "Because you put 140 hours into Hades and Dead Cells (roguelike, short runs)" (the two played games and two shared tags that contribute most to the match) |
| Momentum  | "you played it 3 days ago" / "you've played 6 hours this fortnight"                                                                                        |
| Fit       | "it's about 4 hours from the end" / "it's less than an hour from the end"                                                                                  |
| Freshness | "you've never started it"                                                                                                                                  |
| Mismatch  | Never stated as a reason; when high, the pick usually isn't shown                                                                                          |

Rules: no fragment for a null signal, numbers always come from real data, and nothing is invented (an unknown
time to beat never produces a "hours left" claim).

## Precise rules

Settled while writing the tests first, so code, tests and this document agree:

- **Vectors:** a game's tag vector uses the same weights as the taste vector (`type weight × idf`), so both
  live in the same space.
- **Leave-one-out:** removes the game's contribution _and_ lowers the played count by one. The only played
  game therefore has no taste evidence left, and its taste is null. Tiny-library scaling uses that lowered count.
- **Idle thresholds are inclusive and counted in whole days:** 30+ days for finished, 60+ for dropped, 180+ for
  "tried once". A played game with no last-played date (Steam reports 0 for very old play) counts as idle.
- **Order of steps:** candidates are collapsed by IGDB id first; the finished rule is applied to the entry kept.
- **Mismatch:** a dropped game is compared with the _other_ dropped games. With none, mismatch is 0.

## Edge cases

| Case                             | Behaviour                                                                     |
| -------------------------------- | ----------------------------------------------------------------------------- |
| No time to beat                  | Engagement uses the log scale, fit is 0.5, never excluded as finished         |
| No played games                  | Taste null, so momentum, fit and freshness rank the list; explanations say so |
| Fewer than 5 played games        | Taste scaled down                                                             |
| Private library or zero games    | No candidates; the page shows the library state instead                       |
| All candidates excluded          | Empty state: "You've finished everything we can see"                          |
| Two Steam apps, one IGDB game    | One candidate                                                                 |
| Friends private or not yet built | Social null                                                                   |

## Code shape

`app/Recommender/` is plain PHP with **no Laravel imports**: it takes DTOs in and returns DTOs out. A thin
Laravel-side builder loads the data and maps it to those DTOs. That keeps the scoring testable in isolation and
readable on its own.

- `Candidate`, `PlayedGame`, `TagWeight` and `RecommendationContext` DTOs in.
- One class per signal implementing `Signal` (`name()`, `score(Candidate, Context): ?float`).
- `TasteVector` (IDF and the weighted vector) and `Cosine`.
- `Recommender::rank(Context): list<Ranked>` with the score and per-signal contributions.
- `Explainer` turning a `Ranked` into a sentence.
