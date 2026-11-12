<?php

namespace App\Evaluation;

/**
 * Leave-one-out results for one ranker, averaged over trials (one held-out game per trial).
 */
final readonly class LeaveOneOutScore
{
    /**
     * @param  list<array{entry_id: int, name: string, rank: int, pool: int}>  $trials
     */
    public function __construct(
        public string $ranker,
        public array $trials,
    ) {}

    public function count(): int
    {
        return count($this->trials);
    }

    public function hitRate(int $k): float
    {
        return $this->mean(fn (array $t): float => $t['rank'] <= $k ? 1.0 : 0.0);
    }

    /** One relevant game per trial, so precision@k is hit rate / k. */
    public function precision(int $k): float
    {
        return $this->hitRate($k) / $k;
    }

    public function ndcg(int $k): float
    {
        return $this->mean(fn (array $t): float => Metrics::ndcgSingle($t['rank'], $k));
    }

    public function mrr(): float
    {
        return $this->mean(fn (array $t): float => 1 / $t['rank']);
    }

    public function meanRank(): float
    {
        return $this->mean(fn (array $t): float => (float) $t['rank']);
    }

    public function meanPool(): float
    {
        return $this->mean(fn (array $t): float => (float) $t['pool']);
    }

    /** @param callable(array{entry_id: int, name: string, rank: int, pool: int}): float $metric */
    private function mean(callable $metric): float
    {
        if ($this->trials === []) {
            return 0.0;
        }

        return array_sum(array_map($metric, $this->trials)) / count($this->trials);
    }
}
