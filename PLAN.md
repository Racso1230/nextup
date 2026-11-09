# nextup: build plan

Standing rules (stack, commits, colour system, Steam and IGDB usage) are in `CLAUDE.md`. This file is the order of work.

Legend: **[Opus]** lead does it or reviews it personally. **[Sonnet]** delegate to a builder agent with one small task.
Every bullet is roughly one commit. Run the gates from `CLAUDE.md` section 4 before each one.

---

## Phase 0: Foundation

Goal: an empty app with green CI that anyone can start from a fresh clone.

1. [Sonnet] `laravel new nextup` with the Vue starter kit and Pest. Commit: `chore: scaffold laravel app`
2. [Sonnet] Switch `.env.example` to Postgres and Redis, with empty `STEAM_API_KEY`, `IGDB_CLIENT_ID`, `IGDB_CLIENT_SECRET`. Commit: `chore: configure pgsql and redis`
3. [Sonnet] `compose.yaml` with Postgres 17 and Redis 7 for local services (app itself runs on Herd). Commit: `chore: add local services compose file`
4. [Sonnet] Larastan at strict level and a Pint config. Commit: `chore: add larastan and pint`
5. [Sonnet] GitHub Actions: Pint, Larastan, Pest against a Postgres service container. Commit: `ci: add quality workflow`
6. [Sonnet] Tailwind theme with the 60-30-10 tokens from `CLAUDE.md` section 5 and a tiny page that shows each swatch. Commit: `style: add colour tokens`
7. [Opus] `README.md` skeleton, `docs/decisions.md`, `docs/architecture.md` (one-paragraph overview and a data-flow sketch). Commit: `docs: add readme and decisions log`

Done when: CI is green and `docker compose up -d && php artisan migrate && npm run dev` works from a fresh clone.

---

## Phase 1: Auth and library import

Goal: log in with Steam and see your real library.

1. [Opus] Design the tables (`users`, `games`, `library_entries`) and write them into `docs/architecture.md`.
2. [Sonnet] Migrations, models and factories. Commit: `feat(db): add games and library tables`
3. [Sonnet] Steam OpenID login via `socialiteproviders/steam`, session regeneration, logout. Commit: `feat(auth): add steam login`
4. [Sonnet] `SteamClient` class (owned games, summaries) with key-safe error handling and `Http::fake()` fixtures for public, private and empty libraries. Commit: `feat(steam): add owned games client`
5. [Sonnet] `ImportLibrary` job: upserts entries, retries with backoff, Redis rate limit middleware. Commit: `feat(import): queue library import`
6. [Sonnet] Private-library handling with a clear message and a "How to make this public" help link. Commit: `feat(import): handle private libraries`
7. [Sonnet] Library page: sortable, filterable table (playtime, last played, status). Commit: `feat(ui): add library page`
8. [Opus] Review. Add the account deletion endpoint. Commit: `feat(account): add data deletion`
9. [Opus, with `taste` and `frontend-design`] Design direction pass. Save to `docs/design.md`, tokens only. Commit: `docs: add design direction`

Done when: you can log in and see your real games and hours, and a private profile gets a helpful message.

---

## Phase 2: Snapshots and enrichment

Goal: store playtime history (starting now, because it cannot be recovered later) and tag every game.

1. [Sonnet] `playtime_snapshots` table (library_entry, date, minutes_total). Commit: `feat(db): add playtime snapshots`
2. [Sonnet] Nightly scheduled `SnapshotPlaytime` job, idempotent per day. Commit: `feat(snapshot): add nightly playtime job`
3. [Sonnet] `IgdbClient` with cached Twitch token, 3 req/s limiter, fixtures. Commit: `feat(igdb): add authenticated client`
4. [Sonnet] Steam appid to IGDB mapping through `external_games`, batched. Commit: `feat(igdb): map steam appids`
5. [Sonnet] `EnrichGame` job: genres, themes, modes, perspectives, keywords, cover, time to beat. Commit: `feat(igdb): enrich game metadata`
6. [Sonnet] `match_status` (matched, unmatched, retrying), scheduled retry with backoff. Commit: `feat(igdb): retry unmatched games`
7. [Sonnet] Tag display and cover art on the library page. Commit: `feat(ui): show tags and covers`
8. [Opus] Review, and write the snapshot design decision into `docs/decisions.md`.

Done when: every matchable game has tags, snapshots accumulate on schedule, and unmatched games are visible but harmless.

---

## Phase 3: Recommender v1

Goal: a ranked "play this next" list where every pick explains itself. **This is the first thing worth showing.**
Opus owns this phase. Sonnet only writes tests from the spec and the UI.

### Signals (all normalised to 0..1, defined as small pure classes)
- **Engagement**: hours played relative to time to beat when known (clamped, so 40h on a 10h game saturates);
  otherwise a log-scaled hour count. Used to build the taste vector.
- **Taste match**: cosine similarity between the user's taste vector (tags weighted by engagement, with
  TF-IDF style down-weighting of very common tags such as "Action") and the game's tags.
- **Momentum**: started recently and not finished (recent snapshot growth, recent `rtime_last_played`).
- **Fits tonight**: time to beat or typical session length against the time the user says they have. Unknown time
  to beat is neutral (0.5), never zero.
- **Social**: friends who played it recently (optional, `null` if friends are private).
- **Freshness**: owned for a long time and never touched, as a gentle nudge.
- **Mismatch penalty**: similarity to games the user dropped (under about 2 hours or 15% of time to beat, and
  untouched for 60+ days).

```
score = w_taste*taste + w_momentum*momentum + w_fit*fit + w_social*social + w_fresh*fresh - w_mismatch*mismatch
```

Starting weights in `config/recommender.php` (tune later with the backtest, do not hard-code):
taste 0.35, momentum 0.20, fit 0.15, social 0.10, fresh 0.10, mismatch 0.10.

### Tasks
1. [Opus] Write `docs/recommender.md`: the signals above, edge cases (no time to beat, no friends, tiny library),
   and what the model deliberately does not do (it is personalised ranking, not collaborative filtering).
2. [Opus] `app/Recommender/` interfaces and DTOs. Commit: `feat(scoring): add scorer contracts`
3. [Sonnet] One test file per signal from the spec, written first. Commit per signal: `test(scoring): cover momentum signal`
4. [Opus] Implement signals one at a time. Commit per signal: `feat(scoring): add momentum signal`
5. [Opus] Taste vector builder and cosine similarity. Commit: `feat(scoring): add taste vector`
6. [Opus] `Recommender::rank()` returns scores plus the signal contributions. Commit: `feat(scoring): rank candidates`
7. [Opus] Explanation generator: plain English from the top two or three contributing signals, for example
   "Because you put 140 hours into Hades and Dead Cells: roguelike, short runs, and two friends played it this week."
   Commit: `feat(scoring): add explanations`
8. [Sonnet] `recommendation_runs` table that stores scores and explanations for auditing. Commit: `feat(db): store recommendation runs`
9. [Sonnet] "Play this next" page with the top pick and a ranked list. Commit: `feat(ui): add recommendations page`

Done when: the top five looks sensible to Oscar, every pick shows its reason, and the scorer has full unit tests.
Stop here and take screenshots for the portfolio.

---

## Phase 4: Context and feedback

1. [Sonnet] "How long have you got?" and mood (chill or intense) and solo or with friends controls. Commit: `feat(ui): add session context inputs`
2. [Opus] Apply context as filters and reweighting inside the scorer. Commit: `feat(scoring): apply session context`
3. [Sonnet] Feedback table and buttons: Playing it, Not tonight, Never suggest. Commit: `feat(feedback): record pick feedback`
4. [Opus] Feed feedback back into ranking (suppress "never", decay "not tonight" over a few days). Commit: `feat(scoring): use feedback signals`
5. [Sonnet] Friends signal, only if the friends list is public. Commit: `feat(steam): add friends signal`

Done when: clicking feedback visibly changes the next recommendation.

---

## Phase 5: Evaluation

Goal: evidence the ranking works, which becomes the case study's headline numbers.

Steam only exposes lifetime totals, so two evaluations are needed:

1. **Leave-one-out (works from day one).** For each heavily played game, remove it from the taste vector and check
   where it ranks among the owned games. Report precision@k and NDCG. [Opus] Commit: `feat(eval): add leave-one-out harness`
2. **Time-split backtest (needs about 90 days of snapshots, which Phase 2 starts collecting).** Hide the last 90
   days, rank using older data, and check whether the games actually played land near the top. [Opus] Commit: `feat(eval): add time-split backtest`
3. [Sonnet] Baselines: random, most owned-hours, most recently played, same tag-count. Commit: `feat(eval): add baselines`
4. [Sonnet] `php artisan eval:run` prints a results table and writes it to `docs/eval/`. Commit: `feat(eval): add results command`
5. [Opus] Tune weights against the results and record the before and after in `docs/decisions.md`. Optionally fit
   weights with logistic regression from feedback once there is enough of it.

Done when: there is a results table comparing the recommender against the baselines, with the method written down.

---

## Phase 6: Polish, deploy and write-up

1. [Sonnet] Hero: GSAP sequence per `CLAUDE.md` section 6, with reduced-motion handling and SSR-visible content. Commit: `feat(ui): add hero animation`
2. [Opus] `impeccable` critique pass on library, recommendations and hero. Fix findings in small `style:` and `fix:` commits.
3. [Sonnet] Demo mode: seeded fixture library, no Steam login and no external calls, so any visitor can try it. Commit: `feat(demo): add seeded demo mode`
4. [Sonnet] `/up` health endpoint, structured JSON logs, failed-job alerting. Commit: `feat(ops): add health and logging`
5. [Sonnet] Production `compose.prod.yaml` with Caddy, and a deployment doc. Commit: `chore: add production compose`
6. [Opus, with `claude-seo`] README with screenshots, an architecture diagram, setup steps, eval results; meta tags and
   structured data for the demo page. Commit: `docs: finish readme`
7. [Opus] Portfolio case study (sheet A-106): lead with the problem and trade-offs (why an explainable heuristic over a
   black box, why snapshots, what broke), then the numbers (eval results, p95 response time, test coverage), then the
   stack. Status "Live". Commit in the portfolio repo: `feat(work): add nextup case study`

Done when: a stranger can open the demo, understand it in two minutes, and find the code and the numbers.

---

## Cross-cutting checklist
- [ ] No secrets in git history
- [ ] Account deletion works
- [ ] IGDB credited in the footer, terms checked
- [ ] Reduced-motion tested
- [ ] Keyboard-only walkthrough done
- [ ] Accent covers roughly a tenth of each view or less
- [ ] `docs/decisions.md` has an entry for every real trade-off
