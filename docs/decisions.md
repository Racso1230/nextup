# Decisions

Each entry gives what was decided, why, and what was rejected. Newest first.

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
