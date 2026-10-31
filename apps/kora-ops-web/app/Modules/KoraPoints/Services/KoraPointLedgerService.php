<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointSale;
use Illuminate\Support\Facades\DB;

class KoraPointLedgerService
{
    public function balance(KoraPoint $point): int
    {
        return (int) KoraPointEntry::query()->where('point_id', $point->getKey())->sum('amount');
    }

    public function post(KoraPointSale $sale, User $actor, string $key, string $type, int $amount, int $commissionDelta = 0): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Le journal doit être écrit dans la transaction de l’opération.');
        }
        $entry = KoraPointEntry::query()->firstOrCreate(['event_key' => $key], [
            'point_id' => $sale->point_id, 'sale_id' => $sale->getKey(), 'actor_id' => $actor->getKey(),
            'type' => $type, 'amount' => $amount, 'commission_delta' => $commissionDelta,
        ]);
        if ($entry->sale_id !== $sale->getKey() || $entry->point_id !== $sale->point_id || $entry->amount !== $amount || $entry->commission_delta !== $commissionDelta || $entry->type !== $type) {
            throw new \LogicException('Cette clé de journal correspond à une autre écriture.');
        }
        if ($entry->wasRecentlyCreated) {
            $sale->update(['outstanding_amount' => (int) $sale->entries()->sum('amount')]);
        }
    }
}
