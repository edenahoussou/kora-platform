<?php

namespace App\Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __construct(private CacheRepository $cache) {}

    /**
     * Full application health: database, cache (Redis), and API version.
     *
     * Returns 200 when every dependency is reachable, 503 otherwise so load
     * balancers and monitoring can drain traffic from an unhealthy instance.
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseStatus(),
            'cache' => $this->cacheStatus(),
        ];

        $healthy = collect($checks)->every(fn (array $c) => $c['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => config('kora.service'),
            'scope' => config('kora.scope'),
            'version' => config('kora.api_version'),
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ], $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Lightweight liveness probe for load balancers: always 200 when the
     * process can answer, with no dependency checks.
     */
    public function ping(): Response
    {
        return response()->noContent();
    }

    /** @return array{status: string, latency_ms: int|null} */
    private function databaseStatus(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = (int) round((microtime(true) - $start) * 1000);

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (\Throwable) {
            return ['status' => 'error', 'latency_ms' => null];
        }
    }

    /** @return array{status: string, latency_ms: int|null} */
    private function cacheStatus(): array
    {
        try {
            $start = microtime(true);
            $this->cache->getStore()->get('kora:health:probe');
            $latency = (int) round((microtime(true) - $start) * 1000);

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (\Throwable) {
            return ['status' => 'error', 'latency_ms' => null];
        }
    }
}
