<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DetectPromotionConflictsAction
{
    /**
     * Signale les promotions vivantes (non archivées, hors promotion elle-
     * même) qui partagent le même périmètre, la même priorité et dont la
     * période d'application chevauche celle proposée. Un conflit n'est pas
     * une erreur : il est signalé avant enregistrement pour confirmation.
     *
     * @param  array{scope_type: string, channel: string|null, line_id: int|null, origin_stop_id: int|null, destination_stop_id: int|null, priority: int, starts_at: string, ends_at: string|null, ...}  $attributes
     * @return Collection<int, Promotion>
     */
    public function execute(array $attributes, ?Promotion $except = null): Collection
    {
        $signature = implode(':', [
            $attributes['scope_type'],
            $attributes['channel'] ?? '-',
            $attributes['line_id'] ?? '-',
            $attributes['origin_stop_id'] ?? '-',
            $attributes['destination_stop_id'] ?? '-',
        ]);

        $startsAt = Carbon::parse($attributes['starts_at']);
        $endsAt = isset($attributes['ends_at']) ? Carbon::parse($attributes['ends_at']) : null;

        return Promotion::query()
            ->where('status', '!=', 'archived')
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->where('priority', (int) $attributes['priority'])
            ->get()
            ->filter(fn (Promotion $candidate): bool => $candidate->scopeSignature() === $signature
                && $this->periodsOverlap($startsAt, $endsAt, $candidate))
            ->values();
    }

    private function periodsOverlap(Carbon $startsAt, ?Carbon $endsAt, Promotion $candidate): bool
    {
        $candidateStarts = $candidate->starts_at;
        $candidateEnds = $candidate->ends_at;

        $startOverlap = $candidateEnds === null || $startsAt->lte($candidateEnds);
        $endOverlap = $endsAt === null || $candidateStarts->lte($endsAt);

        return $startOverlap && $endOverlap;
    }
}
