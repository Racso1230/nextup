<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Dependency health for monitoring. `/up` stays the cheap liveness probe; this one checks the
 * database, cache and queue. Each check says only "ok" or "fail": no messages, hosts or versions.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1') !== []),
            'cache' => $this->check(function (): bool {
                $key = 'nextup:health:'.bin2hex(random_bytes(4));
                Cache::put($key, 'ok', 10);
                $read = Cache::get($key);
                Cache::forget($key);

                return $read === 'ok';
            }),
            // size() talks to the queue backend without dispatching anything.
            'queue' => $this->check(fn () => Queue::size() >= 0),
        ];

        $healthy = ! in_array('fail', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'fail',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /** @param  callable(): bool  $probe */
    private function check(callable $probe): string
    {
        try {
            return $probe() ? 'ok' : 'fail';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
