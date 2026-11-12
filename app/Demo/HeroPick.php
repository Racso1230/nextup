<?php

namespace App\Demo;

use App\Igdb\IgdbImages;
use App\Recommendations\PickSignals;
use App\Recommender\Explainer;
use App\Recommender\Ranked;
use App\Recommender\Recommender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * The sample pick on the landing page: the demo persona's library, ranked and explained by the real
 * recommender. No database and no external calls, and cached for an hour because it barely changes.
 *
 * @phpstan-type HeroCover array{name: string, cover_url: string|null}
 * @phpstan-type Hero array{
 *     name: string,
 *     cover_url: string|null,
 *     explanation: string,
 *     signals: list<array{key: string, label: string, value: float}>,
 *     next: list<HeroCover>
 * }
 */
final class HeroPick
{
    /** Picks after the top one, for the shuffling cover stack. */
    public const int NEXT_PICKS = 4;

    private const string CACHE_KEY = 'demo.hero-pick.v1';

    private const int CACHE_SECONDS = 3600;

    public function __construct(
        private readonly Recommender $recommender,
        private readonly Explainer $explainer,
    ) {}

    /**
     * @return Hero
     */
    public function __invoke(): array
    {
        /** @var Hero */
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->build());
    }

    /**
     * @return Hero
     */
    private function build(): array
    {
        $context = DemoLibrary::context(CarbonImmutable::now('UTC'));
        $ranked = $this->recommender->rank($context);
        $catalogue = DemoLibrary::catalogue();

        $cover = fn (Ranked $pick): ?string => IgdbImages::coverUrl($catalogue[$pick->game->entryId - 1]['cover_image_id'], 'cover_big');

        // The demo library always has candidates; this only guards a broken catalogue.
        $top = $ranked[0] ?? throw new RuntimeException('The demo library produced no recommendations.');

        $values = [];

        foreach ($top->contributions as $contribution) {
            $values[$contribution->signal->value] = $contribution->value;
        }

        return [
            'name' => $top->game->name,
            'cover_url' => $cover($top),
            'explanation' => $this->explainer->explain($top, $context->now),
            'signals' => PickSignals::from($values),
            'next' => array_map(
                fn (Ranked $pick): array => ['name' => $pick->game->name, 'cover_url' => $cover($pick)],
                array_slice($ranked, 1, self::NEXT_PICKS),
            ),
        ];
    }
}
