<?php

namespace App\Console\Commands;

use App\Evaluation\Baselines\MostHoursRanker;
use App\Evaluation\Baselines\MostRecentRanker;
use App\Evaluation\Baselines\RandomRanker;
use App\Evaluation\Baselines\TagOverlapRanker;
use App\Evaluation\LeaveOneOut;
use App\Evaluation\LeaveOneOutScore;
use App\Evaluation\Ranker;
use App\Evaluation\RecommenderRanker;
use App\Evaluation\TimeSplit;
use App\Models\User;
use App\Recommendations\AsOfCutoffContext;
use App\Recommendations\LibraryContextFactory;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('eval:run {--user= : user id, defaults to the first user} {--write : write the results to a Markdown file} {--path= : where --write puts the file, defaults to docs/eval/results.md}')]
#[Description('Score the recommender against baselines (leave-one-out and time-split); read-only')]
class RunEvaluation extends Command
{
    private const array LOO_HEADERS = ['ranker', 'trials', 'mean pool', 'HR@5', 'HR@10', 'P@5', 'P@10', 'NDCG@10', 'MRR', 'mean rank'];

    private const array SPLIT_HEADERS = ['ranker', 'relevant', 'ranked', 'P@5', 'P@10', 'R@5', 'R@10', 'NDCG@10'];

    public function handle(LibraryContextFactory $contexts, AsOfCutoffContext $asOf): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('No such user.');

            return self::FAILURE;
        }

        $context = $contexts->build($user, CarbonImmutable::now('UTC'));
        $heldOut = count(LeaveOneOut::heldOut($context));

        $this->info('Leave-one-out');
        $loo = new LeaveOneOut;
        $scores = array_map(
            fn (Ranker $ranker): LeaveOneOutScore => $loo->run($context, $ranker),
            [new RecommenderRanker, new RandomRanker, new TagOverlapRanker],
        );
        $looRows = array_map(self::looRow(...), $scores);
        $this->table(self::LOO_HEADERS, $looRows);

        $this->newLine();
        $split = $this->timeSplit($user, $asOf);

        if ($split['rows'] !== []) {
            $this->info('Time-split backtest (cut-off '.$split['cutoff'].')');
            $this->table(self::SPLIT_HEADERS, $split['rows']);
        } else {
            $this->line($split['note']);
        }

        if ($this->option('write')) {
            $path = $this->option('path');
            $path = is_string($path) && $path !== '' ? $path : base_path('docs/eval/results.md');
            $this->writeReport($path, count($context->library), $heldOut, $scores[0], $looRows, $split);
            $this->info("Wrote {$path}");
        }

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $id = $this->option('user');

        if ($id === null) {
            return User::orderBy('id')->first();
        }

        return is_numeric($id) ? User::find((int) $id) : null;
    }

    /**
     * @return array{rows: list<list<string>>, cutoff: ?string, note: string}
     */
    private function timeSplit(User $user, AsOfCutoffContext $asOf): array
    {
        $cutoff = $asOf->daysUntilAvailable($user) === 0 ? $asOf->cutoffFor($user) : null;
        $built = $cutoff === null ? null : $asOf->build($user, $cutoff);

        if ($cutoff === null || $built === null) {
            $started = $asOf->firstSnapshotOn($user);
            $needs = $asOf->daysUntilAvailable($user);
            $when = $started === null ? 'no snapshots collected yet' : 'collection started '.$started->toDateString();

            return ['rows' => [], 'cutoff' => null, 'note' => "Time-split backtest: needs {$needs} more days of snapshots ({$when})."];
        }

        $timeSplit = new TimeSplit;
        $rows = [];

        foreach ([new RecommenderRanker, new RandomRanker, new MostHoursRanker, new MostRecentRanker, new TagOverlapRanker] as $ranker) {
            $score = $timeSplit->score($built['context'], $built['relevant'], $ranker);
            $rows[] = [
                $score['ranker'],
                (string) $score['relevant'],
                (string) $score['ranked'],
                self::f($score['precision'][5]),
                self::f($score['precision'][10]),
                self::f($score['recall'][5]),
                self::f($score['recall'][10]),
                self::f($score['ndcg10']),
            ];
        }

        return ['rows' => $rows, 'cutoff' => $cutoff->toDateString(), 'note' => ''];
    }

    /**
     * @return list<string>
     */
    private static function looRow(LeaveOneOutScore $s): array
    {
        return [
            $s->ranker,
            (string) $s->count(),
            self::f($s->meanPool()),
            self::f($s->hitRate(5)),
            self::f($s->hitRate(10)),
            self::f($s->precision(5)),
            self::f($s->precision(10)),
            self::f($s->ndcg(10)),
            self::f($s->mrr()),
            self::f($s->meanRank()),
        ];
    }

    private static function f(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    /**
     * @param  list<list<string>>  $looRows
     * @param  array{rows: list<list<string>>, cutoff: ?string, note: string}  $split
     */
    private function writeReport(string $path, int $librarySize, int $heldOut, LeaveOneOutScore $recommender, array $looRows, array $split): void
    {
        $md = "# Evaluation results\n\n";
        $md .= 'Run date: '.CarbonImmutable::now('UTC')->toDateString()." (UTC)\n\n";
        $md .= "- Library size: {$librarySize} games\n";
        $md .= "- Held-out games (10+ hours, matched, tagged): {$heldOut}\n\n";
        $md .= "How to read these tables: a ranker only means something relative to the baselines on the same data. See [method.md](method.md).\n\n";
        $md .= "## Leave-one-out\n\n".self::markdownTable(self::LOO_HEADERS, $looRows)."\n";
        $md .= "## Time-split backtest\n\n";
        $md .= $split['rows'] === []
            ? $split['note']."\n\n"
            : "Cut-off {$split['cutoff']}.\n\n".self::markdownTable(self::SPLIT_HEADERS, $split['rows'])."\n";
        $md .= "## Recommender rank per held-out game\n\n";

        $perGame = [];

        foreach ($recommender->trials as $trial) {
            $perGame[] = [$trial['name'], (string) $trial['rank'], (string) $trial['pool']];
        }

        $md .= $perGame === [] ? "No trials.\n" : self::markdownTable(['game', 'rank', 'pool'], $perGame);

        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $md);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private static function markdownTable(array $headers, array $rows): string
    {
        $line = fn (array $cells): string => '| '.implode(' | ', array_map(fn (string $c): string => str_replace('|', '\|', $c), $cells))." |\n";

        $md = $line($headers).'|'.str_repeat(' --- |', count($headers))."\n";

        foreach ($rows as $row) {
            $md .= $line($row);
        }

        return $md;
    }
}
