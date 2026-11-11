<?php

namespace App\Actions;

use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Writes a playtime snapshot for each of a user's entries that is new or whose total changed since its latest
 * snapshot. Unchanged entries get no row: a missing day means "unchanged" (see docs/decisions.md).
 */
final class RecordPlaytimeSnapshots
{
    private const int CHUNK = 500;

    /** @return int Number of snapshot rows written (inserted or updated). */
    public function __invoke(User $user, CarbonImmutable $capturedOn): int
    {
        $date = $capturedOn->toDateString();
        $now = now();
        $written = 0;

        // One query per chunk of entries: the latest snapshot's total comes from a correlated subquery.
        LibraryEntry::query()
            ->where('user_id', $user->id)
            ->select(['library_entries.id', 'library_entries.playtime_minutes'])
            ->addSelect([
                'latest_minutes' => PlaytimeSnapshot::query()
                    ->select('minutes_total')
                    ->whereColumn('library_entry_id', 'library_entries.id')
                    ->orderByDesc('captured_on')
                    ->limit(1),
            ])
            ->chunkById(self::CHUNK, function (Collection $entries) use ($date, $now, &$written): void {
                $rows = [];

                foreach ($entries as $entry) {
                    /** @var LibraryEntry $entry */
                    $latest = $entry->getAttribute('latest_minutes');

                    if (is_numeric($latest) && (int) $latest === $entry->playtime_minutes) {
                        continue;
                    }

                    $rows[] = [
                        'library_entry_id' => $entry->id,
                        'captured_on' => $date,
                        'minutes_total' => $entry->playtime_minutes,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    PlaytimeSnapshot::upsert($rows, ['library_entry_id', 'captured_on'], ['minutes_total', 'updated_at']);
                    $written += count($rows);
                }
            }, 'library_entries.id', 'id');

        return $written;
    }
}
