<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthChecker
{
    /**
     * Probe each dependency.
     *
     * @return array<string, 'ok'|'fail'>
     */
    public function check(): array
    {
        return [
            'database' => $this->probe(fn () => DB::select('select 1')),
            'redis' => $this->probe(fn () => Redis::connection()->ping()),
        ];
    }

    /**
     * @return 'ok'|'fail'
     */
    private function probe(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable $e) {
            report($e);

            return 'fail';
        }
    }
}
