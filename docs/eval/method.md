# Evaluation method

Two offline evaluations answer one question: **does the recommender put the games a user actually plays near
the top?** Both compare the recommender with simple baselines on the same data, so a number means something
only relative to them. Results are written to `docs/eval/` by `php artisan eval:run`.

## 1. Leave-one-out (works from day one)

Steam gives no history before nextup started collecting it, but it does tell us which games a user loved. So we
pretend each loved game was still unplayed and ask where the recommender would have ranked it.

**Held-out games:** every matched, tagged game with at least **10 hours** played. One at a time, a held-out game is
reset to "never played" (no playtime, no last-played date, no recent hours). It drops out of the taste vector
(it can't teach taste about itself) and becomes a candidate.

**Candidate pool:** the held-out game plus every game the user has **never played**. The question is "among the
games you hadn't started, would we have picked the one you went on to love?". Ranking it against games already
in progress would mostly measure momentum.

**Metrics** (one relevant game per trial, averaged over all trials):

| Metric            | Meaning                                                                          |
| ----------------- | -------------------------------------------------------------------------------- |
| Hit rate @5, @10  | Share of trials where the held-out game was in the top 5 / 10                    |
| Precision @5, @10 | Hit rate divided by k (one relevant game per trial)                              |
| NDCG @10          | `1 / log2(rank + 1)` when in the top 10, else 0. Rewards being near the very top |
| MRR               | Mean of `1 / rank`. Uses the whole list                                          |
| Mean rank         | Average position, out of the pool size                                           |

**Baselines on the same pool:**

- **Random:** a fixed-seed shuffle, so runs are repeatable. Its expected hit rate @k is `k / pool size`.
- **Tag overlap:** rank by how many tags a game shares with the user's played games, unweighted. It is the
  "obvious" content-based answer, without IDF, engagement or the other signals.

"Most hours" and "most recently played" are meaningless here (every pool game has 0 hours) and are used only in
the time-split test.

**What it does not show:** momentum, social and feedback can't be tested this way (the hidden game has no recent
play by construction). It mainly measures taste, fit, freshness and the mismatch penalty, which is the part of
the model that picks unstarted games.

## 2. Time-split backtest (needs about 90 days of snapshots)

The honest test: rank the library as it was on a past date, then check whether the games the user actually
played afterwards were near the top.

- **Cut-off T** = 90 days before the latest snapshot. Requires snapshots from on or before T; until then
  `eval:run` reports how many days of history are still needed.
- **Library as of T:** entries first seen on or before T, with playtime as of T (latest snapshot on or before T).
  Last-played as of T is the date of the last snapshot growth on or before T.
- **Relevant games:** those whose playtime grew after T.
- **Metrics:** precision @k, recall @k and NDCG @10 against the relevant set.
- **Baselines:** random, most hours (as of T), most recently played (as of T) and tag overlap.

Snapshot collection started on 2026-11-09, so the first backtest is possible around 2027-02-07, provided the
nightly import runs every night.

## Reading the results

- **Beat the baselines on the same data.** A hit rate of 0.3 is good if random gets 0.05, and weak if tag
  overlap gets 0.35.
- **Small libraries mean noisy numbers.** A 130-game library has a few dozen held-out games, so differences of a
  few points are not meaningful. Results report the number of trials alongside every metric.
- **Weight changes must not be tuned to one library.** Phase 5's tuning records the before and after in
  `docs/decisions.md`, and a change is kept only if it helps without breaking the hand-checked top five.
