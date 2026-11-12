# Decisions

Each entry gives what was decided, why, and what was rejected. Newest first.

## 2026-11-12: Demo mode is a private throwaway account per visitor

**What:** "Try the demo" creates a demo user (`users.is_demo`) with a library built from
`database/data/demo-catalogue.json`. That file holds 127 games of public catalogue metadata (names, IGDB tags,
covers, time to beat) exported from the dev database. The library is shaped by `App\Demo\DemoPersona`:

- the playtime is invented and deterministic, for a player who loves story RPGs and immersive sims and plays co-op
  shooters with friends;
- all dates are relative to "now", so the demo never goes stale.

Demo users:

- **Never call Steam or IGDB.** Import, the friends refresh, the nightly schedule and "refresh library" all skip
  them, and each path is tested.
- **Are deleted** after 24 hours (hourly `demo:prune`) or on logout.
- **Have a fake steam_id** starting `76561190`, below the real SteamID64 range, so it can never collide with a
  real account. Steam login refuses it.

The landing page's sample pick (`App\Demo\HeroPick`) ranks the same persona in memory with no database, and a test
proves it matches the seeded account's ranking.

**Why:** a hiring manager can try every feature, including the feedback buttons, without a Steam account, and one
visitor's clicks never affect another's.

**Rejected:**

- One shared demo account (visitors would see each other's "never suggest" clicks).
- A read-only demo (the most interesting interaction couldn't be tried).

**Privacy:** the fixture holds no user data. Which games are in it came from Oscar's catalogue, but the playtime is
invented.

## 2026-11-12: "Fits tonight" only counts when the user picks a time (Phase 5 tuning)

**What:** fit is null (no effect) unless the user chooses a time on the Play next page. The default is now "Any".
When a time is chosen, fit applies at its full weight (0.15) as before. No other weight changed.

**Evidence** (leave-one-out on the real 130-game library: 43 held-out games, each ranked against about 45
never-played games; method in `docs/eval/method.md`):

| Variant                       | HR@5      | HR@10     | NDCG@10   | MRR       | Mean rank |
| ----------------------------- | --------- | --------- | --------- | --------- | --------- |
| Before (fit always 0.15)      | 0.116     | 0.209     | 0.126     | 0.137     | 23.1      |
| Fit 0.10                      | 0.279     | 0.442     | 0.223     | 0.179     | 18.7      |
| Fit 0.05                      | 0.372     | 0.535     | 0.319     | 0.275     | 14.9      |
| **After (no fit by default)** | **0.395** | **0.581** | **0.392** | **0.357** | **12.3**  |
| Fit only, nothing else        | 0.023     | 0.023     | 0.015     | 0.046     | 33.8      |
| Random baseline               | 0.116     | 0.163     | 0.067     | 0.070     | 26.1      |
| Tag-overlap baseline          | 0.140     | 0.256     | 0.109     | 0.099     | 19.9      |

Removing the mismatch penalty as well dropped HR@5 from 0.395 to 0.256, so mismatch stays.

**Why:** finishability favours short games, and the games people go on to love are mostly long. On its own fit
ranked _worse than random_. With it always on, the recommender lost to plain tag overlap. It was also why short
bundled extras (Day of Defeat, Counter-Strike: Condition Zero Deleted Scenes, Half-Life: Blue Shift) crowded the
top ten; without fit they leave the top eight, while taste picks (Dishonored: Death of the Outsider, BioShock, Prey)
stay at the top.

**Kept the control:** leave-one-out asks "which game would you come to love?", not "what fits one evening?", so
it cannot evaluate an explicit "I have an hour". When the user picks a time they are asking for exactly that
trade-off, so fit applies then.

**Rejected:**

- Fit at 0.05 always (close on the numbers, but it keeps nudging every view towards short games).
- Removing the time control (it throws away a feature the evaluation can't judge).

**Caveat:** 43 trials from one library. The gap here (5 vs 17 hits in the top five) is large enough to trust;
small differences would not be. The time-split backtest (about 2027-02-07) is the next check.

## 2026-11-12: Session context and feedback (Phase 4)

**What:** four approved choices, specified in `docs/recommender.md` under "Session context and feedback":

- **Time available:** fit's half-point becomes 5 times the hours the user has. Two hours reproduces v1 exactly.
- **Mood:** a tag-based boost signal (weight 0.15), with a neutral 0.5 for games with no mood tags.
- **Solo or with friends:** a filter on IGDB game modes. Games with no mode data are never hidden.
- **Feedback:**
    - "Never suggest" hides a game until undone.
    - "Not tonight" hides it for 24 hours, then a snooze penalty fades over about a week.
    - "Playing it" counts as full momentum for 3 days.

**Why:**

- **Each rule degrades to neutral when data is missing.** No mood tags, no mode data and no time to beat all
  leave the ranking as it would otherwise be.
- **The 2-hour default keeps Phase 3's ranking as the baseline,** so the change is measurable in Phase 5.
- **Feedback must visibly change the next list** (PLAN's done criterion), so any feedback newer than the cached
  run forces a fresh ranking.

**Rejected:**

- Half-point equal to the available hours (it buries every long game).
- A hard time filter.
- A mood filter (it misclassifies games with mixed tags).
- A boost-only friends toggle (a single-player RPG could still top a "with friends" list).
- "Never" as a penalty rather than an exclusion (the user said never).

**Privacy:** the friends signal stores only how many friends played each game, never who.

## 2026-11-11: Recommender v1 works around what Steam can't tell us

**What:** four approved choices in `docs/recommender.md`:

- **Likely-finished games are hidden.** A game is hidden when playtime is at least the IGDB time to beat and it
  hasn't been played for 30 days. It still feeds taste.
- **Freshness uses only known facts.** Never played scores 1.0, tried once and abandoned scores 0.5, and
  everything else 0.
- **Keywords count at quarter weight** in the taste vector.
- **"Fits tonight" means finishability** until Phase 4 adds the "how long have you got?" input.

**Why:** Steam exposes no completion flag, no purchase date and no sessions. Each rule uses the closest honest
proxy and degrades to neutral when data is missing (an unknown time to beat is 0.5, never 0, and is never used
to hide a game). On the real library, 21 of 130 games are past their time to beat, so without the finished rule
completed story games would top the list on taste alone.

**Rejected:**

- Hiding anything past its time to beat regardless of recency (it would hide a game being replayed).
- Using nextup's first-seen date as an ownership date (every game is "seen" on the same day at signup).
- Equal-weight or zero-weight keywords. Both stay open to change in Phase 5 if the evaluation says so.

## 2026-11-09: `games.igdb_id` is indexed, not unique

**What:** the Phase 2 design made `igdb_id` unique. A follow-up migration replaces the unique key with a plain
index.
**Why:** IGDB's `external_games` can map several Steam appids to one IGDB game (re-releases, editions, separately
listed test builds). With a unique key, the second match would throw and fail the whole batch. Two Steam rows
sharing IGDB metadata is correct: they are different things the user owns, described by the same game.
**Rejected:** keeping only the first appid matched (the other copy would never get tags), and merging the Steam
rows (playtime is per appid on Steam, and merging would corrupt snapshots).
**Consequence:** enrichment updates every `games` row with a given `igdb_id`, and Phase 3 should collapse
candidates that share an `igdb_id`, so one IGDB game isn't recommended twice.

## 2026-11-09: Snapshots only when playtime changes

**What:** an import writes a `playtime_snapshots` row for an entry the first time it sees it, and then only when
`minutes_total` differs from that entry's latest snapshot. The nightly schedule re-imports every public
library, so a changed total is captured within a day.
**Why:** most of a backlog never moves. Writing every game every night is roughly 180k identical rows a year
for a 500-game library. Storing only changes loses nothing: no row for a day means the total didn't change, and
the total on any date is the latest row on or before it. That is the query both session derivation and the
Phase 5 time-split backtest need.
**Rejected:** a row per game per night (simplest to query, but almost all duplicates), and storing sessions
directly (Steam has no sessions; they are always derived).
**Consequence:** a gap is ambiguous between "unchanged" and "the import didn't run". `users.imported_at` tells
the two apart, and the backtest must treat days with no successful import as unknown.

## 2026-11-09: One tags table for every IGDB tag kind

**What:** genres, themes, game modes, perspectives and keywords all live in `tags` with a `type` column, and
games link to them through one `game_tag` pivot.
**Why:** the recommender's taste vector and TF-IDF weighting work over "tags" as one vocabulary. One table keeps
that a single join, and adding a tag kind later needs no migration beyond a new enum case.
**Rejected:** a table and pivot per kind (10 tables, and every recommender query would have to union them).

## 2026-11-09: Subclass the Steam Socialite provider to force https

**What:** `socialiteproviders/steam` (4.6) handles the OpenID assertion correctly: `check_authentication`,
signed fields, return_to, and a nonce replay check. After that, though, it fetches the profile from
`http://api.steampowered.com/...?key=...`, over plain http with our API key in the query string.
`App\Auth\SteamProvider` overrides only that lookup to use https, and our listener registers it as the
`steam` driver. A test asserts the scheme. Login failures log the exception class only, never the message,
because Guzzle messages include the request URL.
**Why:** CLAUDE.md requires that the key never leak. A plain-http request leaks it to every hop on the network.
**Rejected:** hand-rolling OpenID verification (CLAUDE.md forbids it, and the package's verification is good),
and patching `vendor/` (lost on the next install).
**Revisit:** drop the subclass if upstream switches to https.

## 2026-11-09: Steam is the only way to sign in

**What:** the starter kit's Fortify stack (registration, password login and reset, email verification, 2FA,
passkeys) is removed. Users sign in with Steam OpenID only, and `users` is keyed by `steam_id`. The appearance
settings are removed at the same time, which closes the follow-up from the dark-only entry.
**Why:** every feature needs a Steam account anyway, because the library comes from Steam. A second login
method would double the auth surface to secure and test, and add pages to restyle, without letting anyone do
anything new. No data exists yet, so the base users migration is edited in place rather than altered.
**Rejected:** keeping email/password as a fallback (more attack surface, and an account without Steam has
nothing to show), and linking Steam to email accounts (the same, plus account-merging edge cases).

## 2026-11-09: Library status derived, not stored

**What:** `library_entries` has no status column in Phase 1. The library page derives `unplayed` (0 minutes),
`recent` (played in the last two weeks) and `played` from playtime.
**Why:** these come straight from Steam data, so storing them would only let them go stale. A user-set status
(playing, not tonight, never) has a real source, feedback, and arrives with it in Phase 4.
**Rejected:** a user-editable status column now (building a feature before the recommender can use it).

## 2026-11-09: Dark-only theme, Appearance page deferred

**What:** `<html class="dark">` is permanent, and the shadcn variables have a single mapping onto our tokens.
The starter kit's Appearance settings page, `useAppearance` composable and `HandleAppearance` middleware are
still there, but they no longer change any colours.
**Why:** v1 ships one theme. Removing the appearance feature touches settings routes, tests and navigation,
which is more than a colour-token commit should carry.
**Rejected:** keeping a light theme in step with the tokens (doubles the design work for no v1 benefit).
**Follow-up:** remove the appearance feature in Phase 1, and rebuild `Welcome.vue`, which still has the
starter kit's arbitrary hex classes, as the hero in Phase 6.

## 2026-11-09: shadcn "accent" hover moved off the brand accent

**What:** in the shadcn starter, `bg-accent` is the hover and selected background for menus, dropdowns and
selects. Our `--color-accent` is the lime brand accent. The shadcn `accent` mapping was removed, those classes
now use `bg-surface-2` and `text-text`, and the sidebar's own accent variable maps to `surface-2`.
**Why:** otherwise every menu hover would turn lime, breaking the rule that the accent covers about a tenth of
the view and only marks actions and meaning.
**Rejected:** renaming our token (CLAUDE.md fixes the token names), and keeping two "accent" meanings (confusing).
**Consequence:** shadcn `primary` maps to the accent, so a default `<Button>` is the primary action. Views must
keep to one of them and use `variant="secondary"` or `outline` for the rest.

## 2026-11-09: Tests on in-memory SQLite locally, Postgres in CI

**What:** `phpunit.xml` keeps `DB_CONNECTION=sqlite` with `:memory:`. CI sets `DB_CONNECTION=pgsql` and the
other `DB_*` variables as process environment, which PHPUnit's non-forced `<env>` entries do not override, so
Pest runs against a real Postgres 17 service there. CI also runs `php artisan migrate` on Postgres.
**Why:** the local suite runs in seconds with no services, while CI catches Postgres-specific behaviour
(`bigint` SteamIDs, JSON columns, case-sensitive `LIKE`) before merge.
**Rejected:** Postgres for every local test run (slower, and needs Docker up for every gate), and SQLite
everywhere (would hide dialect bugs until production).
**Revisit:** if a test passes on SQLite but fails on Postgres more than once, switch the local suite too.

## 2026-11-09: Larastan level 9, not max

**What:** PHPStan runs at level 9 over `app/`, `config/`, `database/`, `routes/` and `bootstrap/app.php`, with no
baseline. The starter kit's 13 level-9 errors (nullable `$request->user()`, mixed request input) were fixed
in code. Analysis needs `--memory-limit=1G`.
**Why:** level 9 bans `mixed` leaking through, which is the strictness that matters for API payloads from Steam
and IGDB. Level 10 also flags implicit `mixed` from vendor code, which mostly adds noise around framework internals.
**Rejected:** a baseline (hides debt from day one), and analysing `tests/` (63 false positives from Pest's
dynamic API).

## 2026-11-09: Keep vite-plus from the starter kit

**What:** the Laravel Vue starter kit ships `vite-plus` (`vp build`, `vp check`) in place of plain Vite plus
ESLint and Prettier. It is kept. `npm run check` (lint and format, including Markdown and YAML) is a CI gate.
**Why:** one tool covers build, lint and format, it is the starter kit's default, and swapping it out adds
dependencies and config with no benefit to the product.
**Rejected:** replacing it with ESLint and Prettier for familiarity.
