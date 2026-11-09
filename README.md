# nextup

Ranks your game backlog by what you will actually play next, and explains every pick.

> Status: in development. Phase 0 (foundation) of [PLAN.md](PLAN.md).

<!-- Screenshots go here once the recommendations page exists (Phase 3). -->

## How it works

Sign in with Steam. nextup imports your library and playtime, tags each game from IGDB, and ranks what you
own with an explainable scoring model: taste match, momentum, how well a game fits the time you have tonight,
and what your friends are playing. Every pick comes with a plain-English reason.

The scoring engine is a plain PHP class in `app/Recommender/` with no framework imports, so it can be read and
tested on its own. See [docs/architecture.md](docs/architecture.md).

## Stack

- Laravel 13, PHP 8.4+, PostgreSQL 17, Redis (queues, cache, rate limiting)
- Inertia 3, Vue 3, TypeScript, Tailwind CSS 4
- Pest, Larastan (level 9), Pint, GitHub Actions

## Local setup

Requirements: PHP 8.4+ with `pdo_pgsql` and `redis`, Composer, Node 22+, Docker. The app is served by
[Laravel Herd](https://herd.laravel.com) at `https://nextup.test`. Postgres and Redis run in Docker.

```bash
docker compose up -d            # Postgres 17 on port 5433, Redis 7 on 6379
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev
```

In two more terminals:

```bash
php artisan queue:work
php artisan schedule:work
```

Steam and IGDB keys go in `.env` only (`STEAM_API_KEY`, `IGDB_CLIENT_ID`, `IGDB_CLIENT_SECRET`). Tests never
need them.

The colour tokens can be previewed at `/design/tokens` outside production.

## Quality gates

These run before every commit and in CI (against a Postgres service container):

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/pest
npm run build
npm run check          # lint and format (vite-plus)
npm run types:check    # vue-tsc
```

## Evaluation

<!-- Results table comparing the recommender with baselines goes here (Phase 5). -->

## Docs

- [PLAN.md](PLAN.md): build order, phase by phase
- [docs/architecture.md](docs/architecture.md): overview and data flow
- [docs/decisions.md](docs/decisions.md): trade-offs and why
